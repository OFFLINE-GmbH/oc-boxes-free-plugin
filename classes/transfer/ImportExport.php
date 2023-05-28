<?php

namespace OFFLINE\Boxes\Classes\Transfer;

use Illuminate\Support\Collection;
use October\Rain\Database\Model;
use October\Rain\Parse\Yaml;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Page;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use System\Helpers\Cache;
use System\Models\PluginVersion;

class ImportExport
{
    protected Yaml $yaml;

    protected PageMapper $pageMapper;

    protected BoxMapper $boxMapper;

    protected string $pluginVersion = '';

    public function __construct(
        protected string $targetDir,
        protected string $structureFileName = 'structure.yaml',
        protected string $pagesDir = 'pages',
        protected string $attachmentsDir = 'attachments',
    ) {
        $this->yaml = new Yaml();
        $this->pageMapper = new PageMapper();
        $this->boxMapper = new BoxMapper();

        $this->pluginVersion = PluginVersion::where('code', 'OFFLINE.Boxes')->first()->version;
    }

    /**
     * Export the given pages to the file system.
     *
     * @var Collection<Page> $pagesCollection
     */
    public function export(
        Collection $pagesCollection,
        bool $withAttachments = true,
        bool $isPartial = false
    ) {
        // Make sure no cached Boxes data is used.
        Cache::clear();

        $this->emptyTargetDir($withAttachments);

        // Build pages array.
        $pages = $pagesCollection->map(
            fn (Page $page) => $this->pageMapper->toArray($page)
        )->values()->toArray();

        // Write the pages array to the filesystem.
        $this->writeToFile($this->targetPath($this->structureFileName), $this->yaml->render([
            'boxes_version' => $this->pluginVersion,
            'type' => $isPartial ? 'partial' : 'full',
            'pages' => $pages,
        ]));

        // Write all attachments to the filesystem.
        if ($withAttachments) {
            $this->exportAttachments($pages);
        }

        // Export all Boxes on this page.
        $pagesCollection->each(
            fn (Page $page) => $this->exportBoxes($page, $withAttachments)
        );
    }

    /**
     * Import data from a directory.
     *
     * @return Collection<Page>
     */
    public function import(bool $withAttachments = true): Collection
    {
        // Make sure no cached Boxes data is used.
        Cache::clear();

        // Look for the structure file.
        $files = Finder::create()
            ->depth(0)
            ->in($this->targetDir)
            ->files()
            ->name($this->structureFileName);

        if (!$files->hasResults()) {
            throw new RuntimeException(
                sprintf(
                    "[OFFLINE.Boxes] Could not find structure file named '%s' in '%s'",
                    $this->structureFileName,
                    $this->targetDir
                )
            );
        }

        $structure = $this->yaml->parse(array_first($files)->getContents());

        $pages = collect([]);

        Site::withGlobalContext(function () use (&$pages, $structure, $withAttachments) {
            $pages = $this->importPages($structure['pages'], $withAttachments);

            // When doing a full import, all existing Pages that were not
            // part of the import must be deleted.
            if (array_get($structure, 'type') === 'full') {
                $this->removePagesMissingFromImport($pages);
            }
        });

        return $pages;
    }

    /**
     * Recursively export the Boxes of a given Page.
     */
    protected function exportBoxes(
        Page $page,
        bool $withAttachments = true,
        array $breadcrumbs = []
    ) {
        $breadcrumbs[] = $page->slug;

        $filename = $this->targetPath($this->pagesDir . '/' . implode('--', $breadcrumbs) . '.yaml');

        $boxes = $page->boxes()->getNested()->map(
            fn (Box $box) => $this->boxMapper->toArray($box)
        )->values()->toArray();

        $this->writeToFile($filename, $this->yaml->render([
            'page' => $page->slug,
            'boxes' => $boxes,
        ]));

        if ($withAttachments) {
            $this->exportAttachments($boxes);
        }

        if ($page->children->count()) {
            $page->children->each(
                fn (Page $child) => $this->exportBoxes($child, $withAttachments, $breadcrumbs)
            );
        }
    }

    /**
     * Write all attachments to the filesystem.
     */
    protected function exportAttachments(array $data)
    {
        foreach ($data as $item) {
            if (isset($item['attachments']) && is_array($item['attachments'])) {
                foreach ($item['attachments'] as $attachments) {
                    foreach ($attachments as $attachment) {
                        if (File::exists($attachment['path'])) {
                            File::copy(
                                $attachment['path'],
                                $this->targetPath($this->attachmentsDir, $attachment['disk_name'])
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Import all Pages.
     */
    protected function importPages(array $structure, bool $withAttachments): Collection
    {
        $pages = new Collection();

        $previous = null;

        foreach ($structure as $pageData) {
            $importedPages = $this->importPage(
                $pageData,
                null,
                $previous,
                $withAttachments
            );

            $previous = Page::published()->where('slug', array_get($pageData, 'slug'))->first();

            $pages->push(... $importedPages);
        }

        // Publish the imported pages.
        Page::whereIn('id', $pages)->get()->each->publishDraft();

        return $pages;
    }

    /**
     * Recursively import page data.
     * @return Collection<int>
     */
    protected function importPage(
        array $pageData,
        ?Page $parent,
        ?Page $previous,
        bool $withAttachments = true,
        array $breadcrumbs = []
    ): Collection {
        $pages = new Collection();

        $page = $this->pageMapper->fromArray($pageData);
        $page->save();

        if ($previous) {
            $page->moveAfter($previous);
        } elseif ($parent) {
            $page->makeChildOf($parent);
        }

        if ($withAttachments) {
            $this->importAttachments($page, $pageData);
        }

        $pages->push($page->id);

        $breadcrumbs[] = $page->slug;

        $files = Finder::create()
            ->depth(0)
            ->in($this->targetPath($this->pagesDir))
            ->files()
            ->name(implode('--', $breadcrumbs) . '.yaml');

        if ($files->hasResults()) {
            // Load all existing Boxes, remove the ones that we have in the import.
            // The remaining ones will be deleted.
            $missingBoxesInImport = Box::query()
                ->where('holder_id', $page->id)
                ->where('holder_type', Page::class)
                ->get()
                ->pluck('unique_id', 'unique_id');

            $boxesData = $this->yaml->parse(array_first($files)->getContents());

            $previousBox = null;

            foreach ($boxesData['boxes'] as $boxData) {
                $uniqueId = array_get($boxData, 'unique_id');

                if (!$uniqueId) {
                    continue;
                }

                $this->importBox(
                    $page,
                    null,
                    $previousBox,
                    $boxData,
                    $withAttachments
                );

                $missingBoxesInImport = $missingBoxesInImport->forget($uniqueId);

                $previousBox = Box::where('unique_id', $uniqueId)->first();
            }

            $missingBoxesInImport->each(
                fn ($uniqueId) => Box::where('holder_type', Page::class)->where('holder_id', $page->id)->where('unique_id', $uniqueId)?->delete()
            );
        }

        if (isset($pageData['children']) && is_array($pageData['children'])) {
            $previousChild = null;

            foreach ($pageData['children'] as $childData) {
                $childPages = $this->importPage(
                    $childData,
                    $page,
                    $previousChild,
                    $withAttachments,
                    $breadcrumbs
                );

                $previousChild = Page::published()->where('slug', array_get($childData, 'slug'))->first();

                $pages->push(...$childPages);
            }
        }

        return $pages;
    }

    /**
     * Recursively import the Boxes of a given Page.
     */
    protected function importBox(
        Page $page,
        ?Box $parent,
        ?Box $previous,
        array $boxData = [],
        bool $withAttachments = true
    ): void {
        $box = $this->boxMapper->fromArray(array_except($boxData, 'children'), $page);
        $box->holder_type = Page::class;
        $box->holder_id = $page->id;
        $box->save();

        if ($previous) {
            $box->moveAfter($previous);
        } elseif ($parent) {
            $box->makeChildOf($parent);
        }

        if ($withAttachments) {
            $this->importAttachments($box, $boxData);
        }

        if (isset($boxData['children']) && is_array($boxData['children'])) {
            $previousChild = null;

            foreach ($boxData['children'] as $children) {
                $this->importBox(
                    $page,
                    $box,
                    $previousChild,
                    $children,
                    $withAttachments,
                );

                $previousChild = Box::where('unique_id', array_get($children, 'unique_id'))->first();
            }
        }
    }

    /**
     * Import all attachments to the filesystem.
     */
    protected function importAttachments(Model $model, array $data)
    {
        if (!isset($data['attachments']) || !is_array($data['attachments'])) {
            return;
        }

        // Keep track of all attached and processed files.
        // Any attached file that was not processed here, should be deleted.
        $attachedFiles = [];
        $processedFiles = [];

        // Get all currently attached files from the model.
        foreach (array_keys($model->attachOne) as $relation) {
            if ($model->$relation) {
                $attachedFiles[] = $model->$relation->disk_name;
            }
        }

        foreach (array_keys($model->attachMany) as $relation) {
            $attachedFiles += $model->$relation->pluck('disk_name')->toArray();
        }

        // Process all attachments.
        foreach ($data['attachments'] as $relation => $attachments) {
            foreach (array_wrap($attachments) as $attachment) {
                $source = $this->targetPath($this->attachmentsDir, $attachment['disk_name']);

                if (!File::exists($source)) {
                    continue;
                }

                $existing = null;

                switch ($attachment['type']) {
                    case 'attachMany':
                        $existing = $model
                            ->$relation
                            ->where('disk_name', $attachment['disk_name'])
                            ->first();
                        break;
                    case 'attachOne':
                        $existing = $model->$relation;
                        // If an attachment exists but the disk_name does not match, delete it.
                        if ($existing && $existing->disk_name !== $attachment['disk_name']) {
                            $existing->delete();
                            $existing = null;
                        }
                        break;
                }

                if ($existing) {
                    $dest = $existing->getLocalPath();
                    File::copy($source, $dest);
                } else {
                    $file = new \System\Models\File();
                    $file->title = array_get($attachment, 'title');
                    $file->description = array_get($attachment, 'description');
                    $file->disk_name = array_get($attachment, 'disk_name');

                    $file->fromFile($source, $attachment['file_name']);

                    $model->$relation()->add($file);
                }

                $processedFiles[] = $attachment['disk_name'];
            }
        }

        // Remove any attached files that were not processed from the model.
        $missing = array_diff($attachedFiles, $processedFiles);

        if (count($missing)) {
            \System\Models\File::query()
                ->whereIn('disk_name', $missing)
                ->where('attachment_type', $model->getMorphClass())
                ->where('attachment_id', $model->getKey())
                ->get()->each->delete();
        }
    }

    protected function removePagesMissingFromImport(Collection $pages): void
    {
        $allPages = Page::published()->get('id')->pluck('id');
        $notImported = $allPages->diff($pages);

        if ($notImported->count()) {
            Page::published()->whereIn('id', $notImported)->get()->each->update(['published_state' => PublishedState::OUTDATED]);
        }
    }

    /**
     * Write data to a file.
     */
    protected function writeToFile(string $file, string $content)
    {
        if (file_exists($file)) {
            unlink($file);
        }

        file_put_contents($file, $content);
    }

    /**
     * Create and/or empty the target directory.
     */
    protected function emptyTargetDir(bool $withAttachments): void
    {
        if (!$this->targetDir) {
            throw new RuntimeException(
                '[OFFLINE.Boxes] Missing target directory for YAML export.'
            );
        }

        \October\Rain\Support\Facades\File::deleteDirectory($this->targetDir . '/' . $this->attachmentsDir);
        \October\Rain\Support\Facades\File::deleteDirectory($this->targetDir . '/' . $this->pagesDir);
        \October\Rain\Support\Facades\File::delete($this->targetDir . '/' . $this->structureFileName);

        $this->createDirectory($this->targetDir);
        $this->createDirectory($this->targetDir . '/' . $this->pagesDir);

        if ($withAttachments) {
            $this->createDirectory($this->targetDir . '/' . $this->attachmentsDir);
        }
    }

    /**
     * Create a directory.
     */
    protected function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException(
                sprintf('[OFFLINE.Boxes] Failed to create directory "%s"', $path)
            );
        }
    }

    /**
     * Returns the path to a filename inside the target directory.
     */
    protected function targetPath(string ...$filename): string
    {
        return $this->targetDir . '/' . implode('/', $filename);
    }
}

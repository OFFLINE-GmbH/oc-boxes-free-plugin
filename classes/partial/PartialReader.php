<?php

namespace OFFLINE\Boxes\Classes\Partial;

use App;
use Cms\Classes\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use LogicException;
use October\Rain\Parse\Yaml;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Traits\Singleton;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Classes\Events;
use OFFLINE\Boxes\Classes\Exceptions\PartialNotFoundException;
use OFFLINE\Boxes\Classes\Features;
use RuntimeException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * PartialReader reads partial config from the Theme.
 */
class PartialReader
{
    use Singleton;

    /**
     * YAML files that start with this prefix are considered mixins.
     */
    public const MIXIN_PREFIX = '_mixin';

    /**
     * Additional partial paths registered by 3rd-party plugins.
     * @var array<string>
     */
    protected array $additionalPartialPaths = [];

    /**
     * The theme partials directory.
     */
    protected string $themePartialsDir = '';

    /**
     * Yaml Parser
     */
    protected Yaml $yaml;

    /**
     * Lookup for partial handles.
     */
    protected Collection $byHandle;

    /**
     * The boxes.yaml definition from `/themes/<theme>/boxes.yaml`
     */
    protected Collection $boxesConfig;

    public function __construct()
    {
        $theme = self::getSiteThemeFromContext();

        $this->themePartialsDir = sprintf('%s/partials', $theme?->getPath());

        // Update the currently active theme when the site or theme is changed.
        Event::listen('system.site.setEditSite', function () {
            if ($theme = self::getSiteThemeFromContext()) {
                $this->themePartialsDir = sprintf('%s/partials', $theme->getPath());
                $this->init();
            }
        });

        Event::listen('cms.theme.setEditTheme', function ($code) {
            if ($theme = self::getSiteThemeFromContext()) {
                $this->themePartialsDir = sprintf('%s/partials', $theme->getPath());
                $this->init();
            }
        });

        $this->yaml = new Yaml();
        $this->boxesConfig = $this->readBoxesConfig();
        $this->byHandle = new Collection();

        $this->init();
    }

    /**
     * Parse all available Partials.
     *
     * @return void
     */
    public function init()
    {
        $partialPaths = $this->getPartials();

        $this->byHandle = new Collection();

        foreach ($partialPaths as $partialPath) {
            $config = $this->configForPartial($partialPath->getPathname());

            if ($this->byHandle->has($config->handle)) {
                throw new LogicException(
                    sprintf(
                        '[OFFLINE.Boxes] Duplicate partial handle "%s" detected. Make sure to use each handle only once.',
                        $config->handle
                    )
                );
            }

            $this->byHandle->put($config->handle, new Partial($config));
        }

        $this->byHandle->each(fn (Partial $partial) => $partial->config->processMixins($this->byHandle));

        $this
            ->getSystemPartials()
            ->mapInto(Partial::class)
            ->each(fn (Partial $partial) => $this->byHandle->put($partial->config->handle, $partial));
    }

    /**
     * Return the parsed YAML config for a given yaml file.
     */
    public function configForPartial(string $yamlPath): PartialConfig
    {
        $yamlPath = str_replace_last('.htm', '.yaml', $yamlPath);

        if (!$yamlPath || !file_exists($yamlPath)) {
            return new PartialConfig();
        }

        return PartialConfig::fromPath($yamlPath);
    }

    /**
     * Find a specific partial by its handle.
     */
    public function findByHandle(string $handle): Partial
    {
        if (!$this->byHandle->has($handle)) {
            throw new PartialNotFoundException($handle);
        }

        return $this->byHandle->get($handle);
    }

    /**
     * List all partials from the current theme that have a YAML config grouped by their section.
     */
    public function listPartials(array $context = []): Collection
    {
        $toolsSection = Lang::get('offline.boxes::lang.tools_section');

        $partialList = $this
            ->getPartials()
            ->filter(fn (SplFileInfo $info) => !starts_with($info->getFilename(), self::MIXIN_PREFIX))
            ->mapInto(PartialConfig::class)
            ->concat($this->getSystemPartials())
            ->filter(fn (PartialConfig $config) => $config->isAvailableInContext($context))
            ->sortBy(fn ($partial) => $partial->section === $toolsSection ? 'ZZZZZZ_LAST_SECTION' : $partial->section)
            ->keyBy('handle');

        // Use the section order from the boxes.yaml file, if available.
        $sectionOrder = array_flip($this->boxesConfig->get('sections', []));

        if (count($sectionOrder) === 0) {
            return $partialList;
        }

        return $partialList->sort(function ($a, $b) use ($sectionOrder) {
            $aPos = $sectionOrder[$a->section] ?? 99999;
            $bPos = $sectionOrder[$b->section] ?? 99999;

            return $aPos <=> $bPos;
        });
    }

    /**
     * List all partials from the current theme that have a YAML config grouped by their section.
     */
    public function listPartialsGrouped(): Collection
    {
        return $this
            ->listPartials()
            ->groupBy('section')
            ->sortKeys();
    }

    /**
     * Return the parsed boxes.yaml config from the current theme.
     */
    public function getBoxesConfig()
    {
        return $this->boxesConfig;
    }

    /**
     * Process the template configuration.
     */
    public function processTemplateConfig(?array $templates)
    {
        if (!$templates) {
            return collect([]);
        }

        return collect($templates)->map(function (array $template) {
            $template = collect($template);

            if (!$template->has('handle')) {
                throw new LogicException('[OFFLINE.Boxes] A page template must have a "handle" property.');
            }

            if (!$template->has('name')) {
                $template->put('name', $template->get('handle'));
            }

            if (!$template->has('contexts')) {
                $template->put('contexts', ['default']);
            } elseif (!is_array($template->get('contexts'))) {
                throw new RuntimeException('[OFFLINE.Boxes] The "contexts" key of a page template definition must be an array.');
            }

            if (!$template->has('boxes')) {
                $template->put('boxes', []);
            } elseif (!is_array($template->get('boxes'))) {
                throw new RuntimeException('[OFFLINE.Boxes] The "boxes" key of a page template definition must be an array.');
            }

            // Normalize the boxes array.
            $boxes = collect($template->get('boxes'))->map(function (array $box) {
                if (!isset($box['partial'])) {
                    throw new RuntimeException('[OFFLINE.Boxes] Every item of a page template boxes must have a "partial" key.');
                }

                // Normalize the locked attribute.
                $locked = array_get($box, 'locked', false);

                if ($locked === false) {
                    $locked = [];
                } elseif ($locked === true) {
                    $locked = PartialOptions::LOCKABLE;
                }

                $locked = array_wrap($locked);

                if (array_diff($locked, PartialOptions::LOCKABLE)) {
                    throw new RuntimeException(
                        sprintf(
                            '[OFFLINE.Boxes] The "locked" key of a page template definition must only contain any of these values: %s',
                            implode(', ', PartialOptions::LOCKABLE)
                        )
                    );
                }

                $box['locked'] = $locked;

                return $box;
            });

            $template->put('boxes', $boxes);

            return $template;
        })->keyBy('handle');
    }

    /**
     * Returns all partials that have a YAML config.
     *
     * @return Collection<SplFileInfo>
     */
    protected function getPartials(): Collection
    {
        $eventPaths = array_filter(array_flatten(Event::fire(Events::REGISTER_PARTIAL_PATH) ?? []));

        $additionalPaths = array_map(fn ($path) => $this->makeAbsolute($path), $eventPaths);

        $paths = [$this->themePartialsDir, ...$additionalPaths];

        try {
            $files = Finder::create()->files()->name(['*.yml', '*.yaml'])->in($paths);

            if (!$files->hasResults()) {
                return collect([]);
            }
        } catch (DirectoryNotFoundException $e) {
            return collect([]);
        }

        // Only include partials/YAML pairs. Single partials and single YAMLs must be ignored,
        // except YAML starting with _mixin.
        $partialsWithConfig = [];

        foreach ($files as $file) {
            if (starts_with($file->getFilename(), self::MIXIN_PREFIX)) {
                $partialsWithConfig[] = $file;

                continue;
            }

            $htm = str_replace(['.yml', '.yaml'], '', $file->getRealPath()) . '.htm';

            if (file_exists($htm)) {
                $partialsWithConfig[] = $file;
            }
        }

        return collect($partialsWithConfig);
    }

    /**
     * Turns a $/relative/path into an /project/absolute/path
     */
    protected function makeAbsolute(string $path): string
    {
        return str_replace_first('$', App::basePath(), $path);
    }

    /**
     * Find and parse the `boxes.yaml` file.
     */
    protected function readBoxesConfig()
    {
        $baseDir = self::getSiteThemeFromContext()?->getPath();

        if (!$baseDir) {
            return collect(['sections' => [], 'templates' => collect([])]);
        }

        $file = Finder::create()
            ->files()
            ->name('boxes.yaml')
            ->depth('== 0')
            ->in($baseDir);

        if (!$file->hasResults()) {
            return collect(['sections' => [], 'templates' => collect([])]);
        }

        $config = collect($this->yaml->parseFile(sprintf('%s/%s', $baseDir, 'boxes.yaml')));

        $config->put('templates', $this->processTemplateConfig($config->get('templates', [])));

        return $config;
    }

    /**
     * Return the currently active theme based on the active site.
     */
    protected static function getSiteThemeFromContext(): ?Theme
    {
        return Theme::load(ThemeResolver::instance()?->getThemeCode());
    }

    private function getSystemPartials()
    {
        if (config('offline.boxes.disable_references')) {
            return collect([]);
        }

        $partials = collect([]);

        if (Features::instance()->references) {
            $partials->push(PartialConfig::fromPath('plugins/offline/boxes/views/partials/reference.yaml'));
        }

        return $partials;
    }
}

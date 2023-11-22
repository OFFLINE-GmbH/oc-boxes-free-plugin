<?php

namespace OFFLINE\Boxes\Classes\Partial;

use App;
use Cms\Classes\Theme;
use Exception;
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
     * We look for the following token in a single partial file to determine if it is a single file partial.
     */
    public const SINGLE_FILE_TOKEN = 'handle:';

    /**
     * Additional partial paths registered by 3rd-party plugins.
     * @var array<string>
     */
    protected array $additionalPartialPaths = [];

    /**
     * The theme partials directory.
     * @var array<string>
     */
    protected array $themePartialsDir = [];

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

    private ?Collection $cachedPartials = null;

    public function __construct()
    {
        $theme = self::getSiteThemeFromContext();

        if ($theme) {
            $this->handleThemePartialsDir($theme);
        }

        // Update the currently active theme when the site or theme is changed.
        Event::listen('system.site.setEditSite', function () {
            if ($theme = self::getSiteThemeFromContext()) {
                $this->handleThemePartialsDir($theme);
                $this->init();
            }
        });

        Event::listen('cms.theme.setEditTheme', function ($code) {
            if ($theme = self::getSiteThemeFromContext()) {
                $this->handleThemePartialsDir($theme);
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
            $config = $this->configForPartial($partialPath);

            if ($this->byHandle->has($config->handle)) {
                $currentTheme = self::getSiteThemeFromContext();

                // If this is a child theme, it is possible that the parent theme has the same partial.
                // In this case, simply ignore it.
                if ($currentTheme && $currentTheme->hasParentTheme()) {
                    // Only consider partials from themes here.
                    if (!str_starts_with($partialPath->getPath(), themes_path())) {
                        continue;
                    }

                    // The partial is not from the current theme, so ignore it.
                    if (!str_starts_with($partialPath->getPath(), $currentTheme->getPath())) {
                        continue;
                    }
                }

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

        // Notify consumers about all available partials.
        Event::fire(Events::BEFORE_FILTER_PARTIALS, [clone $this->byHandle]);

        // Allow consumers to filter the available partials.
        Event::fire(Events::FILTER_PARTIALS, [$this->byHandle]);
    }

    /**
     * Return the parsed YAML config for a given yaml file.
     */
    public function configForPartial(SplFileInfo $file): PartialConfig
    {
        if (property_exists($file, '_boxes_single_file_partial')) {
            return PartialConfig::fromSingleFileHtm($file);
        }

        $yamlPath = str_replace_last('.htm', '.yaml', $file->getPathname());

        if (!$yamlPath || !file_exists($yamlPath)) {
            return new PartialConfig();
        }

        return PartialConfig::fromYaml($yamlPath);
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
     * Gather and store the partial dirs from all themes (child/parent).
     */
    protected function handleThemePartialsDir(Theme $theme)
    {
        $this->themePartialsDir = [
            sprintf('%s/partials', $theme->getPath()),
        ];

        if ($parentTheme = $theme->getParentTheme()) {
            $this->themePartialsDir[] = sprintf('%s/partials', $parentTheme->getPath());
        }
    }

    /**
     * Returns all partials that have a YAML config.
     *
     * @return Collection<SplFileInfo>
     */
    protected function getPartials(): Collection
    {
        if ($this->cachedPartials) {
            return $this->cachedPartials;
        }

        $eventPaths = array_filter(array_flatten(Event::fire(Events::REGISTER_PARTIAL_PATH) ?? []));

        $additionalPaths = array_map(fn ($path) => $this->makeAbsolute($path), $eventPaths);

        $paths = [...$this->themePartialsDir, ...$additionalPaths];

        $files = $this->findFiles($paths, ['*.yml', '*.yaml']);

        // Only include partials/YAML pairs. Single partials and single YAMLs must be ignored,
        // except YAML starting with _mixin.
        $partialsWithConfig = [];
        $knownPartialPaths = [];

        foreach ($files as $file) {
            // This is a mixin.
            if (starts_with($file->getFilename(), self::MIXIN_PREFIX)) {
                $partialsWithConfig[] = $file;

                continue;
            }

            $htm = str_replace(['.yml', '.yaml'], '', $file->getRealPath()) . '.htm';

            // This YAML config has a matching htm file.
            if (file_exists($htm)) {
                $partialsWithConfig[] = $file;
                $knownPartialPaths[$htm] = true;
            }
        }

        // Search for single file partials.
        $themePaths = array_map(fn ($path) => $path . '/boxes', $this->themePartialsDir);
        $paths = [...$themePaths, ...$this->additionalPartialPaths];

        $files = $this->findFiles($paths, ['*.htm']);

        if (count($files) > 0) {
            foreach ($files as $file) {
                // We have already processed this partial in the previous loop.
                if (array_key_exists($file->getRealPath(), $knownPartialPaths)) {
                    continue;
                }

                if ($this->includesSingleFileToken($file)) {
                    $file->_boxes_single_file_partial = true;

                    $partialsWithConfig[] = $file;
                }
            }
        }

        return $this->cachedPartials = collect($partialsWithConfig);
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

        return collect($this->yaml->parseFile(sprintf('%s/%s', $baseDir, 'boxes.yaml')));
    }

    /**
     * Return the currently active theme based on the active site.
     */
    protected static function getSiteThemeFromContext(): ?Theme
    {
        return Theme::load(ThemeResolver::instance()?->getThemeCode());
    }

    /**
     * Find files with a given name in a set of paths.
     */
    protected function findFiles(array $paths, array $patterns)
    {
        $files = [];

        foreach ($paths as $path) {
            try {
                $finder = Finder::create()
                    ->files()
                    ->ignoreUnreadableDirs()
                    ->name($patterns)
                    ->in($path);

                if (!$finder->hasResults()) {
                    continue;
                }

                foreach ($finder as $file) {
                    $files[] = $file;
                }
            } catch (DirectoryNotFoundException $e) {
                // Ignore missing dirs.
                continue;
            }
        }

        return $files;
    }

    private function getSystemPartials()
    {
        $partials = collect([]);

        if (Features::instance()->references) {
            $partial = PartialConfig::fromYaml('plugins/offline/boxes/views/partials/reference.yaml');
            $partial->specialCategory = PartialConfig::SYSTEM_PARTIAL;
            $partials->push($partial);
        }

        return $partials;
    }

    /**
     * Checks if the file starts with a single file token.
     */
    private function includesSingleFileToken(SplFileInfo $file): bool
    {
        // We only check the first 3 lines.
        $searchLineCount = 3;

        try {
            $handle = fopen($file->getRealPath(), 'rb');

            if (!$handle) {
                return false;
            }

            $lineNum = 0;
            while (++$lineNum <= $searchLineCount) {
                $line = fgets($handle, 20);

                if (starts_with(trim($line), self::SINGLE_FILE_TOKEN)) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        } finally {
            fclose($handle);
        }
    }
}

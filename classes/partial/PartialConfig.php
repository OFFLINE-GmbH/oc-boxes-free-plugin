<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Backend\Facades\Backend;
use Exception;
use October\Rain\Parse\Yaml;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use RuntimeException;
use SplFileInfo;
use System\Helpers\System;

/**
 * The PartialConfig that is defined in a YAML file.
 */
class PartialConfig
{
    public const MIXIN_TYPE = 'mixin';

    public const PARTIAL_CONTEXT_SEPARATOR = '||';

    public const PARTIAL_CONFIG_SEPARATOR = '==';

    /**
     * System partials are special partials provided by the plugin itself.
     */
    public const SYSTEM_PARTIAL = 'system';

    /**
     * External partials are not stored in the theme directory (i.e. provided by a plugin).
     */
    public const EXTERNAL_PARTIAL = 'external';

    public string $handle = '';

    public array $children = [];

    public array $form = [];

    public array $spacing = [];

    public array $validation = [];

    public array $translatable = [];

    public ?string $labelFrom = '';

    public ?string $name = '';

    public ?string $description = '';

    public string $path = '';

    public string $icon = '';

    public string $iconRealPath = '';

    public string $preview = '';

    public string $previewRealPath = '';

    public ?int $order = null;

    public bool $placeholderPreview = true;

    public string $section = '';

    public array $components = [];

    public array $assets = [];

    public array $eagerLoad = [];

    public array $mixin = [];

    public array $contexts = [];

    public bool $isSingleFile = false;

    public string $specialCategory = '';

    public string $themePath = '';

    public function __construct(?SplFileInfo $file = null)
    {
        if (!$file) {
            return;
        }

        if (property_exists($file, '_boxes_single_file_partial')) {
            $this->processSingleFileHtmFile($file);

            return;
        }

        $path = $file->getRealPath();

        // Under some circumstances (symlinks, etc.) the pathname is not available.
        // We try a fallback version here.
        if (!$path) {
            $path = base_path($file);
        }

        // Normalize Windows paths.
        $path = str_replace('\\', '/', $path);

        try {
            $yaml = (new Yaml())->parseFileCached($path);
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf(
                    '[OFFLINE.Boxes] The partial "%s" contains invalid YAML. Please check the syntax: "%s"',
                    $path,
                    $e->getMessage(),
                )
            );
        }

        $themePaths = array_reverse(ThemeResolver::instance()->getThemePaths());

        foreach ($themePaths as $themePath) {
            if (str_starts_with($path, $themePath)) {
                $this->themePath = $themePath;
            }
        }

        $this->applyYaml($path, $yaml);
    }

    public static function fromYaml(string $yamlPath): self
    {
        return new self(new SplFileInfo($yamlPath));
    }

    public static function fromArray(array $config): self
    {
        $partial = new self();

        foreach ($config as $key => $value) {
            $partial->{$key} = $value;
        }

        $partial->setDefaults();

        return $partial;
    }

    public static function fromSingleFileHtm(\Symfony\Component\Finder\SplFileInfo $file): self
    {
        return (new self())->processSingleFileHtmFile($file);
    }

    /**
     * Replace the mixins in the form config.
     */
    public function processMixins(?\Illuminate\Support\Collection $partials)
    {
        $form = $this->form;

        $process = function (array $fields) use ($partials, &$process) {
            foreach ($fields as $key => $config) {
                if (array_get($config, 'type') !== self::MIXIN_TYPE) {
                    continue;
                }

                if (!$partials->has($key)) {
                    throw new RuntimeException(
                        sprintf(
                            '[OFFLINE.Boxes] The referenced mixin handle "%s" in "%s" could not be found.',
                            $key,
                            $this->path
                        )
                    );
                }

                $mixin = $partials->get($key)->config->mixin ?? [];

                // Merge in the tab value on all fields if the mixin definition has a mixin.
                if ($tab = array_get($config, 'tab', '')) {
                    foreach ($mixin as $field => $values) {
                        $mixin[$field]['tab'] = $tab;
                    }
                }

                $keys = array_keys($fields);
                $values = array_values($fields);
                $oldKeyIndex = array_search($key, $keys, true);

                // Splice in the keys...
                unset($keys[$oldKeyIndex]);
                array_splice($keys, $oldKeyIndex, 0, array_keys($mixin));

                // Splice in the values...
                unset($values[$oldKeyIndex]);
                array_splice($values, $oldKeyIndex, 0, array_values($mixin));

                // Build the new array from the new keys and values.
                $fields = array_combine($keys, $values);
            }

            // Process the fields recursively.
            $hasMixinChildren = false;

            foreach ($fields as $key => $field) {
                // If any field is a mixin, process the fields.
                if (array_get($field, 'type') === self::MIXIN_TYPE) {
                    $hasMixinChildren = true;
                }

                // Process repeater fields directly.
                if (array_get($field, 'type') === 'repeater' && is_array(array_get($field, 'form.fields'))) {
                    $fields[$key]['form']['fields'] = $process($field['form']['fields']);
                }

                // Process repeater groups.
                if (array_get($field, 'type') === 'repeater' && is_array(array_get($field, 'groups'))) {
                    $fields[$key]['groups'] = array_map(function ($group) use ($process) {
                        $group['fields'] = $process($group['fields']);

                        return $group;
                    }, $field['groups']);
                }

                // Process nested forms.
                if (array_get($field, 'type') === 'nestedform' && is_array(array_get($field, 'form.fields'))) {
                    $fields[$key]['form']['fields'] = $process($field['form']['fields']);
                }
            }

            if ($hasMixinChildren) {
                $fields = $process($fields);
            }

            return $fields;
        };

        if (isset($form['fields'])) {
            $form['fields'] = $process($form['fields']);
        }

        if (isset($form['tabs']['fields'])) {
            $form['tabs']['fields'] = $process($form['tabs']['fields']);
        }

        if (isset($form['secondaryTabs']['fields'])) {
            $form['secondaryTabs']['fields'] = $process($form['secondaryTabs']['fields']);
        }

        $this->form = $form;
    }

    /**
     * Check if the partial is available in any of the given contexts.
     */
    public function isAvailableInContext(array $contexts)
    {
        if (count($contexts) === 0) {
            return true;
        }

        return count(array_intersect($this->contexts, $contexts)) > 0;
    }

    protected function processSingleFileHtmFile(SplFileInfo $file): self
    {
        $this->isSingleFile = true;

        $parts = explode(self::PARTIAL_CONFIG_SEPARATOR, file_get_contents($file->getPathname()));

        if (count($parts) < 2) {
            throw new RuntimeException(
                sprintf(
                    '[OFFLINE.Boxes] The partial "%s" does not contain a valid yaml configuration section. Please separate the file into sections using "%s" as a separator.',
                    $file->getRelativePathname(),
                    self::PARTIAL_CONFIG_SEPARATOR
                )
            );
        }

        // Remove all INI parts.
        $yamlPart = preg_replace('/\s?^\[.*\]\n(?:.+\s+=\s+.+\n?)*/m', '', $parts[0]);

        return $this->applyYaml($file->getRealPath(), (new Yaml())->parse($yamlPart));
    }

    protected function setDefaults()
    {
        if (!$this->section) {
            $this->section = trans('offline.boxes::lang.section_common');
        }

        if (!$this->specialCategory && !str_starts_with($this->path, themes_path())) {
            $this->specialCategory = self::EXTERNAL_PARTIAL;
        }

        // Convert paths.
        $this->iconRealPath = $this->processPath($this->icon);

        if ($this->iconRealPath) {
            $this->icon = $this->imagePreviewPath('icon');
        }

        $this->previewRealPath = $this->processPath($this->preview);

        if ($this->previewRealPath) {
            $this->preview = $this->imagePreviewPath('preview');
        }

        if (!$this->icon) {
            // Use the preview if available. Otherwise, use a fallback icon.
            if ($this->preview) {
                $this->icon = $this->preview;
                $this->iconRealPath = $this->previewRealPath;
            } else {
                $this->icon = '/plugins/offline/boxes/assets/img/boxes/generic.svg';
                $this->iconRealPath = '/plugins/offline/boxes/assets/img/boxes/generic.svg';
            }
        }
    }

    /**
     * Convert relative paths to absolute paths. It follows these rules:
     * 1. Absolute paths are left unchanged.
     * 2. Relative paths are first resolved against the partials' directory, then against the theme directory.
     */
    private function processPath(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (str_starts_with($path, '/')) {
            return base_path($path);
        }

        $theme = ThemeResolver::instance()->getThemeCode();
        $paths = [
            dirname($this->path),
            themes_path($theme),
        ];

        foreach ($paths as $dir) {
            $absolute = $dir . '/' . $path;

            if (file_exists($absolute) && \System\Facades\System::checkBaseDir($absolute)) {
                return $absolute;
            }
        }

        return '';
    }

    private function imagePreviewPath(string $context)
    {
        return Backend::url(sprintf('offline/boxes/editorcontroller/preview?handle=%s&context=%s', urlencode($this->handle), $context));
    }

    private function applyYaml($path, array $yaml): self
    {
        if (!isset($yaml['handle']) || !$yaml['handle']) {
            throw new RuntimeException(
                sprintf(
                    '[OFFLINE.Boxes] YAML config for partial "%s" has no "handle" property defined. Add a unique custom handle to the YAML configuration file.',
                    $path,
                )
            );
        }

        $yaml['path'] = $path;
        $yaml['icon'] ??= '';
        $yaml['preview'] ??= '';
        $yaml['placeholderPreview'] ??= true;
        $yaml['section'] = isset($yaml['section']) ? trans($yaml['section']) : '';
        $yaml['name'] = trans($yaml['name'] ?? $yaml['handle']);
        $yaml['description'] = trans($yaml['description'] ?? '');
        $yaml['contexts'] ??= ['default'];
        $yaml['order'] ??= null;

        if (array_get($yaml, 'children') === true) {
            $yaml['children'] = ['default'];
        } else {
            $yaml['children'] ??= [];
        }

        foreach ($yaml as $key => $value) {
            $this->{$key} = $value;
        }

        $this->setDefaults();

        return $this;
    }
}

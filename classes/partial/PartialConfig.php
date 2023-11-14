<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Exception;
use Illuminate\Support\Facades\URL;
use October\Rain\Parse\Yaml;
use RuntimeException;
use SplFileInfo;

/**
 * The PartialConfig that is defined in a YAML file.
 */
class PartialConfig
{
    public const MIXIN_TYPE = 'mixin';

    public const PARTIAL_CONTEXT_SEPARATOR = '||';

    public const PARTIAL_CONFIG_SEPARATOR = '==';

    public const SYSTEM_PARTIAL = 'system';

    public string $handle = '';

    public array $children = [];

    public array $form = [];

    public array $spacing = [];

    public array $validation = [];

    public array $translatable = [];

    public ?string $name = '';

    public string $path = '';

    public string $icon = '';

    public bool $placeholderPreview = true;

    public string $section = '';

    public array $components = [];

    public array $assets = [];

    public array $eagerLoad = [];

    public array $mixin = [];

    public array $contexts = [];

    public bool $isSingleFile = false;

    public string $specialCategory = '';

    public function __construct(SplFileInfo $file = null)
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
                        if (!isset($values['tab'])) {
                            $mixin[$field]['tab'] = $tab;
                        }
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
        if (!$this->icon) {
            $this->icon = URL::to('/plugins/offline/boxes/assets/img/boxes/generic.svg');
        }

        if (!$this->section) {
            $this->section = trans('offline.boxes::lang.section_common');
        }
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
        $yaml['icon'] = $yaml['icon'] ?? '';
        $yaml['placeholderPreview'] = $yaml['placeholderPreview'] ?? true;
        $yaml['section'] = isset($yaml['section']) ? trans($yaml['section']) : '';
        $yaml['name'] = trans($yaml['name'] ?? $yaml['handle']);
        $yaml['contexts'] = $yaml['contexts'] ?? ['default'];

        if (array_get($yaml, 'children') === true) {
            $yaml['children'] = ['default'];
        } else {
            $yaml['children'] = $yaml['children'] ?? [];
        }

        foreach ($yaml as $key => $value) {
            $this->{$key} = $value;
        }

        $this->setDefaults();

        return $this;
    }
}

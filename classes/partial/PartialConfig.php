<?php

namespace OFFLINE\Boxes\Classes\Partial;

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

    public string $handle = '';

    public array $children = [];

    public array $form = [];

    public array $spacing = [];

    public array $validation = [];

    public array $translatable = [];

    public ?string $name = '';

    public string $path = '';

    public string $icon = '';

    public string $section = '';

    public array $components = [];

    public array $assets = [];

    public array $eagerLoad = [];

    public array $mixin = [];

    public array $contexts = [];

    public function __construct(SplFileInfo $file = null)
    {
        if (!$file) {
            return;
        }

        $path = $file->getRealPath();

        // Under some circumstances (symlinks, etc.) the real path is not available.
        // We try a fallback version here.
        if (!$path) {
            $path = base_path($file);
        }

        $yaml = (new Yaml())->parseFileCached($path);

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
    }

    public static function fromPath(string $yamlPath)
    {
        return new self(new SplFileInfo($yamlPath));
    }

    public static function fromArray(array $config)
    {
        $partial = new self();

        foreach ($config as $key => $value) {
            $partial->{$key} = $value;
        }

        $partial->setDefaults();

        return $partial;
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

    protected function setDefaults()
    {
        if (!$this->icon) {
            $this->icon = URL::to('/plugins/offline/boxes/assets/img/boxes/generic.svg');
        }

        if (!$this->section) {
            $this->section = trans('offline.boxes::lang.section_common');
        }
    }
}

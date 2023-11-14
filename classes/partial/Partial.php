<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Exception;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Plugin;
use System\Models\File;
use System\Traits\ViewMaker;
use SystemException;

/**
 * A Partial in the Theme directory.
 */
class Partial
{
    use ViewMaker;

    /**
     * Marks this partial as being external to the Theme.
     * This prefix will be replaced by the effective path once
     * the partial is being processed by the CMS.
     *
     * @see Plugin::boot()
     */
    public const EXTERNAL_PREFIX = 'boxes_external|';

    /**
     * Absolute of the partial.
     */
    public string $path = '';

    /**
     * The Partial config defined in the YAML file.
     */
    public PartialConfig $config;

    public function __construct(?PartialConfig $config = null)
    {
        $this->addViewPath('$/offline/boxes/views');
        $this->config = new PartialConfig();

        if ($config) {
            $this->config = $config;

            $path = preg_replace('/.ya?ml/i', '.htm', $config->path);

            // Normalize Windows paths.
            $this->path = str_replace('\\', '/', $path);
        }
    }

    /**
     * Render a Partial with the given RenderContext.
     * @throws \Cms\Classes\CmsException
     * @throws SystemException
     */
    public function render(Box $box, RenderContext $context): string
    {
        if (!$this->path) {
            return '';
        }

        $controller = Controller::getController() ?? new Controller();

        $this->path = $this->normalizePath();

        $context->partial = $this;
        $context->box = $box;

        $output = $controller->renderPartial(
            $this->path,
            [
                'context' => clone $context, // Make sure the context is not modified by the partial.
                'box' => $box,
            ]
        );

        // Do not render anything for empty Boxes.
        if (trim($output) === '') {
            return '';
        }

        return $this->makePartial('box', [
            'output' => $output,
            'context' => clone $context, // Make sure the context is not modified by the partial.
        ]);
    }

    /**
     * Build an example data array for this Partial.
     * @return array
     */
    public function getExampleData()
    {
        $exampleImage = $this->getExampleImage();

        $processFields = function ($fields, $carry = []) use (&$processFields, $exampleImage) {
            foreach ($fields as $name => $field) {
                $label = array_get($field, 'label', '');
                $example = array_get($field, 'example', '');
                $type = array_get($field, 'type', '');

                if (in_array($type, ['section', 'tab', 'checkbox'])) {
                    continue;
                }

                if ($type === 'repeater') {
                    $carry[$name][] = $processFields(data_get($field, 'form.fields', []));

                    continue;
                }

                if (!$example && $type === 'fileupload') {
                    $carry[$name] = $exampleImage;

                    continue;
                }

                if ($example === false) {
                    continue;
                }

                if (is_array($example)) {
                    $example = $this->processExample($example);

                    if (is_array($example)) {
                        $example = implode("\n", $example);
                    }
                }

                $carry[$name] = trans($example ?: $label ?: $field ?: '');
            }

            return $carry;
        };

        $data = $processFields(data_get($this->config->form, 'fields', []));

        return $processFields(data_get($this->config->form, 'tabs.fields', []), $data);
    }

    /**
     * Return the preview image file.
     */
    protected function getExampleImage(): ?File
    {
        $name = 'boxes-placeholder-preview.jpg';
        $previewImage = File::where('file_name', $name)->first();

        if (!$previewImage) {
            $previewImage = (new File())->fromFile(public_path('plugins/offline/boxes/assets/img/preview-image.jpg'));
            $previewImage->file_name = $name;
            $previewImage->save();
        }

        return $previewImage;
    }

    /**
     * Expand the example placeholder to a full string.
     */
    private function processExample(mixed $example)
    {
        $fn = array_get($example, 'fake', '');

        if (!$fn) {
            return '';
        }

        $args = array_wrap(array_get($example, 'args', []));

        try {
            return fake()->$fn(...$args);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Make this partial's path relative to the Theme directory.
     */
    private function normalizePath(): string
    {
        foreach (ThemeResolver::instance()?->getThemePaths() as $themePath) {
            if (starts_with($this->path, $themePath)) {
                return str_replace($themePath . '/partials/', '', $this->path);
            }

            if ($this->config->specialCategory === PartialConfig::SYSTEM_PARTIAL || starts_with($this->path, plugins_path())) {
                return sprintf('%s%s', self::EXTERNAL_PREFIX, $this->path);
            }
        }

        return $this->path;
    }
}

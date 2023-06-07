<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Cms\Classes\Controller;
use Cms\Classes\Theme;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Plugin;
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
            $this->path = preg_replace('/.ya?ml/i', '.htm', $config->path);
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

        $themePath = Theme::load(ThemeResolver::instance()?->getThemeCode())?->getPath();

        $context->partial = $this;
        $context->box = $box;

        $controller = Controller::getController() ?? new Controller();

        if (starts_with($this->path, $themePath)) {
            $this->path = str_replace($themePath . '/partials/', '', $this->path);
        } elseif (starts_with($this->path, plugins_path())) {
            $this->path = sprintf('%s%s', self::EXTERNAL_PREFIX, $this->path);
        }

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
}

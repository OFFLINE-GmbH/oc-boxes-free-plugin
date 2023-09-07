<?php

namespace OFFLINE\Boxes\Components;

use Backend\Facades\BackendAuth;
use Exception;
use October\Rain\Support\Facades\Event;
use OFFLINE\Boxes\Classes\Events;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\Classes\Partial\RenderContext;
use OFFLINE\Boxes\Models\Box;

/**
 * Render a Page in the editor mode.
 */
class BoxesPageEditor extends BoxesPage
{
    public function componentDetails()
    {
        return [
            'name' => 'Boxes Page Editor',
            'description' => 'Displays the Boxes Page Editor.',
        ];
    }

    public function onRun()
    {
        if (!BackendAuth::getUser()) {
            return $this->controller->run('404');
        }

        $this->addCss('assets/css/offline.boxes.editor.css?v=6');
        $this->addJs('assets/js/offline.boxes.editor.js?v=6');

        Event::fire(Events::EDITOR_RENDER, [$this]);

        return parent::onRun();
    }

    public function onRefreshBoxesPreview()
    {
        $this->setData();

        return [
            '.oc-boxes-editor__render' => $this->renderPartial($this->alias . '::render'),
        ];
    }

    public function onRenderPlaceholder()
    {
        if (!Features::instance()->placeholderPreviews) {
            return [
                '.oc-boxes-box-placeholder__preview' => '',
            ];
        }

        try {
            $partial = PartialReader::instance()?->findByHandle(post('partial'));
        } catch (Exception $e) {
            return [];
        }

        $box = new Box();
        $box->forceFill($partial->getExampleData());

        return [
            '.oc-boxes-box-placeholder__preview' => $partial->render(
                $box,
                RenderContext::fromArray(['renderScaffolding' => false])
            ),
        ];
    }
}

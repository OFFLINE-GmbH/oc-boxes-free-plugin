<?php

namespace OFFLINE\Boxes\Components;

use Backend\Facades\BackendAuth;
use October\Rain\Support\Facades\Event;
use OFFLINE\Boxes\Classes\Events;

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

        $this->addCss('assets/css/offline.boxes.editor.css?v=3');
        $this->addJs('assets/js/offline.boxes.editor.js?v=3');

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
}

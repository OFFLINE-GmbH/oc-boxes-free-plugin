<?php

namespace OFFLINE\Boxes\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Backend\Widgets\Form;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Page;

/**
 * BoxFinder Form Widget
 */
class BoxFinder extends FormWidgetBase
{
    protected const PREFIX = 'BoxFinder';

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'offline_boxes_box_finder';

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('form');
    }

    /**
     * @inerhitDoc
     */
    public function init()
    {
        $widget = new Form($this->controller, (object)[
            'arrayName' => self::PREFIX,
            'model' => $this->model,
            'fields' => [
                'reference_page' => [
                    'type' => 'dropdown',
                    'label' => 'Seite',
                    'emptyOption' => '-- Bitte wählen',
                    'options' => 'OFFLINE\Boxes\FormWidgets\BoxFinder::getPageOptions',
                    'value' => post(self::PREFIX . '.reference_page'),
                    // TODO: This is somehow registered for each Box on the page and results in one request per box.
                    'changeHandler' => 'onReferencePageChange',
                ],
                'reference_box' => [
                    'type' => 'dropdown',
                    'label' => 'Box',
                    'emptyOption' => '-- Bitte wählen',
                    'options' => 'OFFLINE\Boxes\FormWidgets\BoxFinder::getBoxOptions',
                    'trigger' => [
                        'action' => 'hide',
                        'condition' => 'value[]',
                        'field' => 'page',
                    ],
                ],
            ],
        ]);

        $widget->bindToController();

        $this->vars['widget'] = $widget;
    }

    public static function getPageOptions()
    {
        return Page::getPageOptionsKeyBy('origin_page_id');
    }

    public function onReferencePageChange()
    {
        return [sprintf('#Form-field-%s-reference_box-group', self::PREFIX) => $this->vars['widget']->renderField('reference_box')];
    }

    public static function getBoxOptions()
    {
        $id = post(self::PREFIX . '.reference_page');

        $page = Page::currentDrafts()
            ->with('boxes')
            ->where(fn ($q) => $q->where('origin_page_id', $id)->orWhere('id', $id))
            ->first();

        if (!$page) {
            return [];
        }

        return Box::getBoxOptions($page);
    }

    /**
     * prepareVars for view data
     */
    public function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['model'] = $this->model;
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}

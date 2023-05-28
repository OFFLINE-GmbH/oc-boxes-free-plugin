<?php

namespace OFFLINE\Boxes\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * HiddenInput Form Widget
 */
class HiddenInput extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'offline_boxes_hidden_input';

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('hiddeninput');
    }

    /**
     * @inerhitDoc
     */
    public function init()
    {
        $this->formField->config['cssClass'] = 'hidden';
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

<?php

namespace OFFLINE\Boxes\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Backend\Widgets\Form;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\Models\Box;
use stdClass;

/**
 * BoxDataForm widget renders a form based on the yaml config for a given partial.
 * @property Box $model
 */
class BoxesDataForm extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'offline_boxes_box_data_form';

    /**
     * @var Form
     */
    protected $widget;

    /**
     * The YAML Configuration.
     */
    protected \OFFLINE\Boxes\Classes\Partial\Partial $partial;

    public function init()
    {
        if (!$this->model->partial) {
            return;
        }

        $reader = PartialReader::instance();

        $this->partial = $reader->findByHandle($this->model->partial);

        if (!$this->partial->path) {
            return;
        }

        $this->widget = $this->buildWidget(
            $this->buildConfig()
        );
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        if (!$this->model->partial) {
            return '';
        }

        $this->prepareVars();

        return $this->makePartial('form');
    }

    /**
     * prepare view vars
     */
    public function prepareVars()
    {
        $this->vars['widget'] = $this->widget;
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['model'] = $this->model;
    }

    public function getSaveValue($value)
    {
        return $this->widget->getSaveData();
    }

    /**
     * Build the form widget containing the fields defined in the YAML config.
     */
    protected function buildWidget(object $config)
    {
        $widget = new Form($this->controller, $config);

        // Set the nested "data" property of the model as well as all relations as the widget's data.
        $widget->data = $this->model;
        $widget->model = $this->model;
        $widget->bindToController();

        return $widget;
    }

    /**
     * Build the config object for the Form widget.
     */
    protected function buildConfig()
    {
        $config = (object)($this->partial->config->form ?? new stdClass());
        $config->arrayName = $this->formField->getName();
        $config->model = $this->model;

        if (property_exists($this->partial->config, 'spacing') && is_array($this->partial->config->spacing)) {
            $config = $this->addSpacingTab($config);
        }

        return $config;
    }

    protected function addSpacingTab(object $config): object
    {
        $spacing = config('offline.boxes::config.spacing', ['before' => [], 'after' => []]);

        $allowedSpacing = $this->partial->config->spacing;

        $spacingBefore = collect(array_get($spacing, 'before', []))->filter(fn ($item) => in_array($item['group'], $allowedSpacing, true));

        $spacingAfter = collect(array_get($spacing, 'after', []))->filter(fn ($item) => in_array($item['group'], $allowedSpacing, true));

        if ($spacingBefore->count() === 0 && $spacingAfter->count() === 0) {
            return $config;
        }

        if (!isset($config->tabs)) {
            $config->tabs = [];
        }

        if (!isset($config->tabs['fields'])) {
            $config->tabs['fields'] = [];
        }

        if ($spacingBefore->count() > 0) {
            $config->tabs['fields'][Box::SPACING_KEY_BEFORE] = [
                'label' => trans('offline.boxes::lang.spacing_before'),
                'type' => 'dropdown',
                'tab' => trans('offline.boxes::lang.spacing'),
                'options' => $spacingBefore->mapWithKeys(fn ($options, $name) => [$name => $options['label']]),
            ];
        }

        if ($spacingAfter->count() > 0) {
            $config->tabs['fields'][Box::SPACING_KEY_AFTER] = [
                'label' => trans('offline.boxes::lang.spacing_after'),
                'type' => 'dropdown',
                'tab' => trans('offline.boxes::lang.spacing'),
                'options' => $spacingAfter->mapWithKeys(fn ($options, $name) => [$name => $options['label']]),
            ];
        }

        return $config;
    }
}

<?php

namespace OFFLINE\Boxes\Models;

use Model;
use October\Rain\Element\ElementHolder;
use OFFLINE\Boxes\Classes\Features;
use ValidationException;

class BoxesSetting extends Model
{
    public $implement = [
        \System\Behaviors\SettingsModel::class,
    ];

    public $settingsCode = 'offline_boxes_settings';

    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->revisions_enabled = false;
        $this->revisions_cleanup_enabled = false;
        $this->revisions_keep_number = 10;
        $this->revisions_keep_days = 7;
        $this->partial_selector_default_cols = 4;

        $this->limit_page_levels = false;
        $this->max_page_levels = 2;
    }

    public function filterFields(ElementHolder $fields, $context = null)
    {
        if (!Features::instance()->isProVersion) {
            $fields->get('revisions_enabled')->hidden = true;
            $fields->get('section_revisions')->hidden = true;
            $fields->get('revisions_keep_number')->hidden = true;
            $fields->get('revisions_keep_days')->hidden = true;
        }
    }

    /**
     * Provides the state for the frontend editor.
     * @return array
     */
    public static function editorState()
    {
        $settings = new self();

        return [
            'partial_selector_default_cols' => (int)$settings->get('partial_selector_default_cols', 4),
            'limit_page_levels' => (int)$settings->get('limit_page_levels', false),
            'max_page_levels' => (int)$settings->get('max_page_levels', 2),
        ];
    }

    public function beforeSave()
    {
        $cols = $this->getSettingsValue('partial_selector_default_cols');

        if ($cols !== null && ($cols > 5 || $cols < 1)) {
            throw new ValidationException([
                'partial_selector_default_cols' => trans('offline.boxes::lang.settings.validation.partial_selector_default_cols_min_max'),
            ]);
        }
    }
}

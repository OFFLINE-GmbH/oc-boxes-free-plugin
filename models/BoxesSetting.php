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

        $partialCols = $settings->get('partial_selector_default_cols', 4);

        return [
            'partial_selector_default_cols' => (int)$partialCols,
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

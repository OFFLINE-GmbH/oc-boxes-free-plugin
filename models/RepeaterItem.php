<?php

namespace OFFLINE\Boxes\Models;

use October\Rain\Database\ExpandoModel;

class RepeaterItem extends ExpandoModel
{
    public $table = 'offline_boxes_repeater_items';

    public $attachMany = [
        'images' => \System\Models\File::class,
        'files' => \System\Models\File::class,
    ];

    public $attachOne = [
        'image' => \System\Models\File::class,
        'file' => \System\Models\File::class,
    ];

    protected $expandoPassthru = ['parent_id'];
}

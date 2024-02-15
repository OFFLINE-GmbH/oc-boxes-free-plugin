<?php

namespace OFFLINE\Boxes\VueComponents;

use Backend\Classes\VueComponentBase;

/**
 * EditorExtension Vue component
 */
class EditorExtension extends VueComponentBase
{
    /**
     * @var array require
     */
    protected $require = [
        \Backend\VueComponents\MonacoEditor::class,
    ];
}

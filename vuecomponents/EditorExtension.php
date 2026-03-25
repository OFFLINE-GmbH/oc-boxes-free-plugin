<?php

namespace OFFLINE\Boxes\VueComponents;

use Backend\Classes\VueComponentBase;

/**
 * EditorExtension Vue component
 */
class EditorExtension extends VueComponentBase
{
    /**
     * @var string componentName is the Vue component tag name.
     */
    protected $componentName = 'offline-boxes-editor-extension';

    /**
     * @var array require
     */
    protected $require = [
        \Backend\VueComponents\MonacoEditor::class,
    ];
}

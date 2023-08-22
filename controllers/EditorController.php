<?php

namespace OFFLINE\Boxes\Controllers;

use Backend\Behaviors\FormController\HasMultisite;
use Backend\Classes\Controller;
use Backend\Traits\WidgetMaker;
use BackendMenu;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\FormWidgets\BoxesEditor;
use System\Traits\ConfigMaker;
use System\Traits\ViewMaker;

class EditorController extends Controller
{
    use WidgetMaker;
    use ConfigMaker;
    use ViewMaker;
    use HasMultisite;

    /**
     * @var bool turboVisitControl
     */
    public $turboVisitControl = 'disable';

    public $requiredPermissions = ['offline.boxes.access_editor'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('OFFLINE.Boxes', 'boxes', 'editorcontroller');
    }

    public function index()
    {
        if (Features::instance()->multisite && $redirect = $this->handleMultiSite()) {
            return $redirect;
        }

        $this->bodyClass = 'compact-container';
        $this->pageTitle = trans('offline.boxes::lang.editor');

        // Build a BoxesEditor widget and render it in full mode.
        $widget = $this->makeFormWidget(BoxesEditor::class, [
            'name' => 'boxes',
        ], $this->makeConfigFromArray([
            'mode' => 'full',
        ]));

        $widget->bindToController();

        $this->vars['widget'] = $widget;
    }
}

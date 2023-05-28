<?php

namespace OFFLINE\Boxes\Controllers;

use Backend\Behaviors\FormController\HasMultisite;
use Backend\Classes\Controller;
use Backend\Traits\WidgetMaker;
use BackendMenu;
use October\Rain\Database\Scopes\MultisiteScope;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\FormWidgets\BoxesEditor;
use OFFLINE\Boxes\Models\Page;
use Site;
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
    public $turboVisitControl = 'reload';

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


    /// PRO
    /**
     * Handle switching between Multisite sites.
     */
    public function onSwitchSite(): array
    {
        $result = [];

        $siteId = post('site_id');
        $model = Page::find(get('page'));

        if (!$siteId || !$model) {
            return $result;
        }

        $otherModel = $model->findForSite($siteId);

        // Model missing or trashed
        $showConfirm = !$otherModel || (
                $otherModel->isClassInstanceOf(\October\Contracts\Database\SoftDeleteInterface::class)
                && $otherModel->trashed()
            );

        if ($showConfirm) {
            $result['confirm'] = __('A record does not exist for the selected site. Create one?');
        }

        return $result;
    }
    /// PRO

    /// PRO
    /**
     * Initialize the site switcher logic.
     */
    private function handleMultiSite()
    {
        $this->addHandlerToSiteSwitcher();

        return $this->redirectToMultisiteModel();
    }
    /// PRO

    /// PRO
    /**
     * Creates a copy of the original page model for this site and returns a redirect.
     */
    private function redirectToMultisiteModel(): ?\Illuminate\Http\RedirectResponse
    {
        if (!get('_site_id') || !get('site_switch')) {
            return null;
        }

        $model = Page::withoutGlobalScope(MultisiteScope::class)->find(get('page'));

        if (!$model) {
            return null;
        }

        $site = Site::getSiteFromId(get('_site_id'));

        if (!$site) {
            return null;
        }

        $newModel = $model->findOrCreateForSite($site->id);

        if (!$newModel) {
            return null;
        }

        $newModel->origin_page_id = $newModel->id;
        $newModel->save();

        return redirect(\Backend\Facades\Backend::url('offline/boxes/editorcontroller?page=' . $newModel->id));
    }
    /// PRO
}

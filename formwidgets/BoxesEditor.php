<?php

namespace OFFLINE\Boxes\FormWidgets;

use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use Backend\Widgets\Form;
use Backend\Widgets\Lists;
use Illuminate\Support\Facades\Session;
use October\Rain\Exception\SystemException;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Facades\Flash;
use OFFLINE\Boxes\Classes\CMS\Controller;
use OFFLINE\Boxes\Classes\Events;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\BoxesSetting;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;
use RuntimeException;
use System\Models\File;

/**
 * BoxesEditor Form Widget.
 */
class BoxesEditor extends FormWidgetBase
{
    protected const CURRENT_PARTIAL_KEY = 'offline.boxes.current_partial';

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'offline_boxes_editor';

    /**
     * Editor Mode. Either 'full' or 'single'.
     * - Full mode is used to display the full content editor.
     * - Single mode is used if the editor acts as form widget.
     *   You cannot create new pages in single mode.
     */
    protected string $mode = 'single';

    /**
     * The layout to use to preview the page.
     */
    protected string $previewLayout = 'default';

    /**
     * The configured partial contexts.
     */
    protected array $partialContexts = ['default'];
    
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->processConfig();

        if (!property_exists($this, 'allowSingleMode') && $this->isSingleMode()) {
            throw new RuntimeException('[OFFLINE.Boxes] Using the Boxes Editor as a form widget is a Boxes Pro feature. Please upgrade to use it.');
        }

        // Make sure the widget is registered when AJAX calls happen by other widgets (like file uploads).
        if ($this->getController()->getAjaxHandler()) {
            if (post('Page') && $this->isFullMode()) {
                $this->buildPageForm($this->resolvePageModel());
            } else {
                $this->buildBoxForm($this->resolveBoxModel(allowPartialFromSession: true));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('boxeseditor');
    }

    /**
     * prepareVars for view data
     */
    public function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['model'] = $this->model;
        $this->vars['state'] = $this->buildState();
    }

    public function withState(array $data = [], array $state = [])
    {
        return array_merge(
            $data,
            ['state' => array_merge($this->buildState(), $state)],
        );
    }

    public function onRenderPageForm(?Page $model = null)
    {
        $model = $model ?? $this->resolvePageModel();

        $widget = $this->buildPageForm($model);

        $this->vars['widget'] = $widget;

        return [
            '#boxes-page-form-container' => $this->makePartial('widget_render', [
                'handler' => 'onSavePageForm',
            ]),
            '#boxes-box-form-container' => $this->makePartial('box_tab_empty'),
            'boxes' => $model->boxes->toNested()->values(),
            'id' => $model->id,
        ];
    }

    public function onSavePageForm()
    {
        /** @var array{id?: int} $data */
        $data = post('Page');

        $page = new Page();

        if ($data['id']) {
            /** @var Page $page */
            $page = Page::findOrFail($data['id']);
        }

        $widget = $this->buildPageForm($page);

        $page->fill($widget->getSaveData());

        $page->withoutMultisiteScope(function () use ($page) {
            $page->save([], post('_session_key'));
        });

        return $this->withState([
            'id' => $page->id,
            'publish' => $this->handlePagePublishing($page, 'onSavePageForm', $page->id),
            'origin' => 'onSavePageForm',
        ]);
    }

    public function onSortPages()
    {
        $page = Page::findOrFail(post('page'));
        $relativeTo = Page::findOrFail(post('relativeTo'));
        $position = post('position');

        if ($position === 'before') {
            $page->moveBefore($relativeTo);
        } else {
            $page->moveAfter($relativeTo);
        }
    }

    public function onDuplicatePage()
    {
        $model = $this->resolvePageModel();

        $clone = $model->duplicate();

        Flash::success(trans('offline.boxes::lang.copy_created'));

        return $this->withState(
            $this->onRenderPageForm($clone)
        );
    }

    public function onSortBoxes()
    {
        $box = Box::findOrFail(post('Box.id'));
        $relativeTo = Box::findOrFail(post('relativeTo'));
        $position = post('position');

        if ($position === 'before') {
            $box->moveBefore($relativeTo);
        } else {
            $box->moveAfter($relativeTo);
        }

        $page = $this->resolvePageModel();

        return $this->withState([
            'id' => $box->id,
        ]);
    }

    public function onDuplicateBox()
    {
        $model = $this->resolveBoxModel();

        $clone = $model->duplicate();

        Flash::success(trans('offline.boxes::lang.copy_created'));

        return $this->withState(
            $this->onRenderBoxForm($clone),
            [
                'boxes' => $clone->holder->boxes->toNested()->values(),
            ]
        );
    }

    public function onMoveBox()
    {
        $box = Box::findOrFail(post('Box.id'));

        if (post('direction') === 'up') {
            $box->moveLeft();
        } else {
            $box->moveRight();
        }

        return $this->withState([
            'id' => $box->id,
        ]);
    }

    public function onRenderBoxForm(?Box $model = null)
    {
        $model = $model ?? $this->resolveBoxModel();

        $widget = $this->buildBoxForm($model);

        $this->vars['widget'] = $widget;

        return [
            '#boxes-box-form-container' => $this->makePartial('widget_render', [
                'handler' => 'onSaveBoxForm',
            ]),
            'holder_id' => $model->holder_id,
            'holder_type' => $model->holder_type,
            'id' => $model->id,
        ];
    }

    public function onDeleteBox()
    {
        $box = Box::with('children')->findOrFail(post('Box.id'));
        $box->delete();

        Flash::success(trans('offline.boxes::lang.flashes.deleted_successfully'));

        return $this->withState([
            '#boxes-box-form-container' => $this->makePartial('box_tab_empty'),
        ]);
    }

    public function onDeletePage()
    {
        /** @var Page $page */
        $page = Page::with('children')->findOrFail(post('Page.id'));

        if (method_exists($page, 'deleteEverything')) {
            $page->deleteEverything();
        } else {
            $page->delete();
        }

        Flash::success(trans('offline.boxes::lang.flashes.deleted_successfully'));

        return $this->withState([
            '#boxes-box-form-container' => $this->makePartial('box_tab_empty'),
            '#boxes-page-form-container' => $this->makePartial('page_tab_empty'),
        ]);
    }

    public function onSaveBoxForm()
    {
        $box = $this->resolveBoxModel();

        // Handle the initial save of a reference box.
        $isReferenceSave = false;

        if ($box->partial === 'Boxes\Internal\Reference') {
            request()->validate([
                'BoxFinder.reference_box' => 'required|exists:offline_boxes_boxes,id',
            ], [
                'BoxFinder.reference_box.required' => trans('offline.boxes::lang.box_required'),
            ]);

            $box->references_box_id = post('BoxFinder.reference_box');
            $isReferenceSave = true;
        }

        $widget = $this->buildBoxForm($box);

        $box->fill($widget->getSaveData());
        $box->save(null, post('_session_key'));

        if ($addBefore = post('Box._add_before')) {
            $box->moveBefore(Box::findOrFail($addBefore));
        } elseif ($box->wasRecentlyCreated) {
            $lastBoxOfParent = Box::query()
                ->where('holder_id', $box->holder_id)
                ->where('holder_type', $box->holder_type)
                ->where('parent_id', $box->parent_id)
                ->where('id', '<>', $box->id)
                ->orderBy('nest_left', 'desc')
                ->first();

            if ($lastBoxOfParent) {
                $box->moveAfter($lastBoxOfParent);
            }
        }

        $boxId = $box->id;

        if ($isReferenceSave) {
            $boxId = $box->references_box_id;
        }

        $publishingInfo = [];

        if ($box->holder_type === Page::class) {
            $publishingInfo = $this->handlePagePublishing($box->holder, 'onSaveBoxForm', $boxId);
        }

        return $this->withState([
            'id' => $boxId,
            'publish' => $publishingInfo,
            'origin' => 'onSaveBoxForm',
        ]);
    }
    
    /**
     * @inheritDoc
     */
    public function loadAssets()
    {
        if (config('offline.boxes::dev.enabled')) {
            $this->addJS('http://localhost:9009/src/main.ts', ['type' => 'module']);

            return;
        }

        $contents = file_get_contents(__DIR__ . '/../assets/editor/manifest.json');
        $manifest = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

        $base = '/plugins/offline/boxes/assets/editor/';

        $this->addJs($base . $manifest->{'src/main.ts'}->file, ['type' => 'module']);

        if (property_exists($manifest->{'src/main.ts'}, 'css')) {
            foreach (array_wrap($manifest->{'src/main.ts'}?->css) as $css) {
                $this->addCss($base . $css);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return FormField::NO_SAVE_DATA;
    }
    
    /**
     * Checks if a given feature is enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return Features::instance()->isEnabled($feature);
    }

    /**
     * Publishes a page or returns the publishing info for the confirmation popup.
     * @param mixed $formId
     */
    protected function handlePagePublishing(Page $page, string $handler, $formId): array
    {
        if (!Features::instance()->revisions) {
            Flash::success(trans('offline.boxes::lang.flashes.saved_successfully'));

            return [];
        }

        $current = Page::getPublishedRevision($page->origin_page_id);

        // This is a normal save action, no publishing is needed.
        if (!post('publish')) {
            Flash::success(trans('offline.boxes::lang.flashes.saved_successfully'));

            return [];
        }

        // If the current version has been published recently (or there is no version published)
        // publish the new version without confirmation.
        if (!$current || $current->published_at?->diffInMinutes() < 15) {
            $page->publishDraft();

            Flash::success(trans('offline.boxes::lang.flashes.published_successfully'));

            return [];
        }

        return $this->getPublishingInfo($page, $current, [
            'handler' => $handler,
            'id' => $formId,
        ]);
    }
    
    /**
     * Set the config values on the class.
     */
    protected function processConfig()
    {
        if (property_exists($this->config, 'mode')) {
            $this->mode = $this->config->mode;
        }

        if (property_exists($this->config, 'previewLayout')) {
            $this->previewLayout = $this->config->previewLayout;
        }

        if (property_exists($this->config, 'partialContexts')) {
            $this->partialContexts = array_wrap($this->config->partialContexts);

            if (!count($this->partialContexts)) {
                $this->partialContexts = ['default'];
            }
        }
    }

    protected function buildState()
    {
        $pageModel = $this->resolvePageModel();

        $previewType = $this->isFullMode() ? 'page' : 'content';

        $pages = $this->isFullMode()
            ? Page::currentDrafts()->orderBy('nest_left')->get()->toNested(false)
            : collect([]);

        Event::fire(Events::EDITOR_EXTEND_PAGES, [&$pages]);

        return [
            'pages' => $pages->values(),
            'partials' => PartialReader::instance()->listPartials([]),
            'i18n' => trans('offline.boxes::lang'),
            'previewUrl' => url()->to(Controller::PREVIEW_URL . $previewType),
            'baseUrl' => url()->to('/'),
            'mode' => $this->mode,
            'initialPageId' => $pageModel->id,
            'initialBoxId' => get('boxes_box'),
            'boxes' => $pageModel->boxes->toNested()->values(),
            'sessionKey' => $this->isSingleMode() ? $this->sessionKey : '',
            'settings' => BoxesSetting::editorState(),
            'draftParam' => Controller::DRAFT_ID_PARAM,
            'partialContexts' => $this->partialContexts,
            'features' => Features::instance()->toArray(),
        ];
    }

    /**
     * @throws SystemException
     */
    protected function buildBoxForm(Box $box): Form
    {
        $config = $this->makeConfig('$/offline/boxes/models/box/fields.yaml');

        $config->model = $box;
        $config->arrayName = 'Box';

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();

        // Store the current Box's partial in the session to be able to
        // access it for AJAX requests from form widgets.
        Session::put(self::CURRENT_PARTIAL_KEY, $box->partial);

        return $widget;
    }

    /**
     * @throws SystemException
     */
    protected function buildPageForm(Page $page): Form
    {
        $config = $this->makeConfig('$/offline/boxes/models/page/fields.yaml');

        $config->model = $page;
        $config->arrayName = 'Page';

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();

        $revisionsWidget = $this->buildPageRevisionsWidget($page);

        $widget->vars['revisionsWidget'] = $revisionsWidget;

        return $widget;
    }

    protected function resolvePageModel(): Page|Content
    {
        $id = post('Page.id', post('Box.holder_id', get('boxes_page')));

        $page = Page::with('boxes')->findOrNew($id);

        // Set the default layout for new pages.
        if (!$page->exists) {
            $layouts = $page->getLayoutOptions();

            $page->layout = isset($layouts['default']) ? 'default' : array_key_first($layouts);
        }

        if (!$page->parent_id && $parent = post('Page.parent_id')) {
            $page->parent_id = $parent;
        }

        return $page;
    }
    
    /**
     * Resolve the Box model from the incoming request.
     *
     * Some requests, like AJAX requests from form widgets, don't
     * include any identifying Box information. In this case, we fall
     * back to a Box with an associated partial that is stored in the session
     * from the last time a Box form was rendered.
     *
     * Associating at least the partial with the model ensures that the
     * appropriate form fields are attached to the controller.
     * @param mixed $allowPartialFromSession
     */
    protected function resolveBoxModel($allowPartialFromSession = false): Box
    {
        $box = new Box();

        // Try to find the Box by the ID
        if ($id = post('Box.id')) {
            $box = Box::findOrFail($id);
        }
        // In case of a onSaveAttachmentConfig handler, we can find the Box by the attachment
        // ID that is being edited in this request.
        elseif (post('file_id') && post('Box') && str_contains($this->getController()->getAjaxHandler(), 'onSaveAttachmentConfig')) {
            $box = File::findOrFail(post('file_id'))->attachment;
        }

        if ($partial = post('Box.partial')) {
            $box->partial = $partial;
        } elseif ($allowPartialFromSession && $partial = Session::get(self::CURRENT_PARTIAL_KEY)) {
            $box->partial = $partial;
        }

        if ($parentId = post('Box.parent_id')) {
            $box->parent_id = $parentId;
        }

        if (!$box->holder_id && $holder = post('Box.holder_id')) {
            $box->holder_id = $holder;
        }

        if (!$box->holder_type) {
            $box->holder_type = $this->getHolderType();
        }

        if ($before = post('Box._add_before')) {
            $box->_add_before = $before;
        }

        return $box;
    }

    protected function isFullMode()
    {
        return $this->mode === 'full';
    }

    protected function isSingleMode()
    {
        return !$this->isFullMode();
    }
    
    /**
     * Return the holder Model type.
     */
    protected function getHolderType(): string
    {
        return $this->isFullMode() ? Page::class : Content::class;
    }

    /**
     * Build the Page revisions list widget.
     */
    protected function buildPageRevisionsWidget(Page $page): \Backend\Classes\WidgetBase
    {
        $revisionsConfig = $this->makeConfig('$/offline/boxes/models/page/columns_revisions.yaml');
        $revisionsConfig->model = new Page();
        $revisionsConfig->context = 'relation';
        $revisionsConfig->recordsPerPage = 10;
        $revisionsConfig->customPageName = 'revisions_page';
        $revisionsConfig->recordOnClick = 'javascript:void(0);';

        $revisionsWidget = $this->makeWidget(Lists::class, $revisionsConfig);

        $revisionsWidget->bindEvent('list.injectRowClass', function ($record) {
            return match ($record->published_state) {
                PublishedState::PUBLISHED => 'positive',
                PublishedState::DRAFT => 'frozen',
                default => 'disabled strike',
            };
        });

        $revisionsWidget->bindEvent('list.extendQueryBefore', function ($query) use ($page) {
            $query
                ->with('published_by_user', 'updated_by_user')
                ->where('origin_page_id', $page->origin_page_id)
                ->where('published_state', '<>', PublishedState::DRAFT)
                ->orderBy('version', 'desc')
                ->orderBy('published_at', 'desc')
                ->orderBy('updated_at', 'desc');
        });

        $revisionsWidget->bindEvent('list.overrideRecordAction', function ($record) {
            return [
                'onclick' => "\$.popup({ handler: 'onShowRevisionDetail', extraData: { id: {$record->id} } })",
            ];
        });

        $revisionsWidget->bindToController();

        return $revisionsWidget;
    }
}

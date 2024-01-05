<?php

namespace OFFLINE\Boxes;

use Backend;
use Cms\Classes\Controller as CmsController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Support\Facades\Block;
use October\Rain\Support\Facades\Config;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\CMS\CmsPageParams;
use OFFLINE\Boxes\Classes\CMS\Controller;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\Partial\ExternalPartial;
use OFFLINE\Boxes\Classes\Partial\Partial;
use OFFLINE\Boxes\Classes\PatchedTreeCollection;
use OFFLINE\Boxes\Classes\Search\SiteSearch;
use OFFLINE\Boxes\Components\BoxesPage;
use OFFLINE\Boxes\Components\BoxesPageEditor;
use OFFLINE\Boxes\FormWidgets\BoxesDataForm;
use OFFLINE\Boxes\FormWidgets\BoxesEditor;
use OFFLINE\Boxes\FormWidgets\BoxFinder;
use OFFLINE\Boxes\FormWidgets\HiddenInput;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\BoxesSetting;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;
use System\Classes\PluginBase;
use Url;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    public function __construct($app)
    {
        parent::__construct($app);

        // RainLab.Translate is an optional dependency.
        // But if it is enabled, we need to require it to make sure
        // that all migrations are run in the right order.
        if (class_exists(\RainLab\Translate\Plugin::class)) {
            $this->require[] = 'RainLab.Translate';
        }
    }

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Boxes Free',
            'description' => 'Visual Page Builder for October CMS',
            'author' => 'OFFLINE',
            'icon' => 'icon-cube',
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConsoleCommand('boxes.heal', Console\HealBoxesTreeCommand::class);
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('cms.page.beforeRenderPartial', function ($controller, $partial) {
            // Allow external partials to be loaded by the CMS. This allows
            // third-party plugins to provide custom partials for the plugin.
            if (starts_with($partial, Partial::EXTERNAL_PREFIX)) {
                $filename = str_replace(Partial::EXTERNAL_PREFIX, '', $partial);

                if (file_exists($filename)) {
                    return ExternalPartial::load('', $filename);
                }
            }
        });

        // Dynamically create a CMS page that is available via the Controller::PREVIEW_URL.
        Event::listen('cms.router.beforeRoute', function ($url) {
            $site = Site::getSiteFromContext();

            // Ignore the route prefix if it is set.
            if ($site->is_prefixed) {
                $prefix = $site->route_prefix;

                if (!str_ends_with($prefix, '/')) {
                    $prefix .= '/';
                }

                // Make sure to only replace the prefix at the beginning of the URL.
                $pos = strpos($url, $prefix);

                if ($pos !== false) {
                    $url = substr_replace($url, '', $pos, strlen($prefix));
                }
            }

            if (str_contains($url, Controller::PREVIEW_URL) && Backend\Facades\BackendAuth::getUser()) {
                return Controller::instance()->getPreviewPage($url);
            }

            return Controller::instance()->getCmsPageForUrl($url);
        });

        // If the visited page is a boxes page, replace the page content with the rendered boxes.
        Event::listen('cms.page.beforeRenderPage', function (CmsController $controller, $page) {
            $isBoxesPage = isset($page->apiBag[CmsPageParams::BOXES_PAGE_ID]);

            // If the current page is a boxes page, return the rendered content.
            if ($isBoxesPage) {
                $isEditor = isset($page->apiBag[CmsPageParams::BOXES_IS_EDITOR]) || $this->isEditModeRequest();

                return $controller->renderComponent($isEditor ? 'boxesPageEditor' : 'boxesPage');
            }

            return '';
        });

        // Add the BoxList component to any page that serves a boxes page.
        Event::listen('cms.page.init', function (CmsController $controller) {
            $page = $controller->getPage();

            if (isset($page->apiBag[CmsPageParams::BOXES_PAGE_ID])) {
                $isEditor = isset($page->apiBag[CmsPageParams::BOXES_IS_EDITOR]) || $this->isEditModeRequest();

                $controller->addComponent(
                    $isEditor ? BoxesPageEditor::class : BoxesPage::class,
                    $isEditor ? 'boxesPageEditor' : 'boxesPage',
                    [
                        'id' => $page->apiBag[CmsPageParams::BOXES_PAGE_ID] ?? 0,
                        'modelType' => $page->apiBag[CmsPageParams::BOXES_MODEL_TYPE] ?? Page::class,
                    ],
                    true
                );
            }
        });

        $this->registerPageFinder();

        // OFFLINE.SiteSearch
        Event::listen('offline.sitesearch.extend', fn () => new SiteSearch());

        // Multi-site support
        Event::listen('cms.sitePicker.overridePattern', function ($page, $pattern, $currentSite, $proposedSite) {
            if (!isset($page->apiBag[CmsPageParams::BOXES_PAGE_ID]) || $page->apiBag[CmsPageParams::BOXES_MODEL_TYPE] !== Page::class) {
                return;
            }

            $boxesPage = Page::withoutGlobalScope(MultisiteScope::class)
                ->findOrFail(
                    $page->apiBag[CmsPageParams::BOXES_PAGE_ID]
                );

            return Cache::rememberForever(
                Page::multisiteCacheKey($boxesPage->id, $proposedSite->id),
                fn () => $boxesPage->findForSite($proposedSite->id)?->url,
            );
        });

        // Add Seeder behavior to the models if the Seeder plugin is installed.
        if (class_exists(\OFFLINE\Seeder\Behaviors\HasSeederFactoryBehavior::class)) {
            Box::extend(static function ($model) {
                $model->implement[] = \OFFLINE\Seeder\Behaviors\HasSeederFactoryBehavior::class;
            });

            if (class_exists(Content::class)) {
                Content::extend(static function ($model) {
                    $model->implement[] = \OFFLINE\Seeder\Behaviors\HasSeederFactoryBehavior::class;
                });
            }
        }

        if (App::runningInBackend()) {
            // Inject global CSS styles.
            Block::set('head', '<link href="' . Url::to('plugins/offline/boxes/assets/css/offline.boxes.backend.css') . '" rel="stylesheet">');
        }
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            BoxesPage::class => 'boxesPage',
            BoxesPageEditor::class => 'boxesPageEditor',
        ];
    }

    public function registerFormWidgets()
    {
        return [
            HiddenInput::class => 'hidden',
            BoxesDataForm::class => 'boxesdataform',
            BoxesEditor::class => 'boxes',
            BoxFinder::class => 'boxfinder',
        ];
    }
    
    /**
     * Registers backend navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        $label = BoxesSetting::get('main_menu_label');

        if (!$label) {
            $label = Lang::get('offline.boxes::lang.content');
        }

        $counter = 0;

        if (Features::instance()->revisions && method_exists(Page::class, 'getUnpublishedDraftCount')) {
            $counter = Page::getUnpublishedDraftCount();
        }

        return [
            'boxes' => [
                'label' => $label,
                'url' => Backend::url('offline/boxes/editorcontroller'),
                'iconSvg' => '/plugins/offline/boxes/assets/img/cube.svg',
                'permissions' => ['offline.boxes.access_editor'],
                'order' => Config::get('offline.boxes::config.main_menu_order', 500),
                'counter' => $counter,
                'counterLabel' => Lang::get('offline.boxes::lang.unpublished_changes'),
            ],
        ];
    }

    /**
     * Register new Twig variables
     * @return array
     */
    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'boxesPage' => [Page::class, 'getAttributeWhereSlug'],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'offline.boxes.manage_settings' => [
                'label' => trans('offline.boxes::lang.permissions.manage_settings'),
                'tab' => 'Boxes',
            ],
            'offline.boxes.access_editor' => [
                'label' => trans('offline.boxes::lang.permissions.access_editor'),
                'tab' => 'Boxes',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'boxes' => [
                'label' => Lang::get('offline.boxes::lang.settings_label'),
                'description' => Lang::get('offline.boxes::lang.settings_description'),
                'category' => 'Boxes',
                'icon' => 'icon-cube',
                'class' => BoxesSetting::class,
                'order' => 500,
                'keywords' => 'boxes',
                'permissions' => ['offline.boxes.manage_settings'],
            ],
        ];
    }

    protected function isEditModeRequest()
    {
        return get(Controller::PREVIEW_PARAM) && Backend\Facades\BackendAuth::getUser();
    }

    /**
     * Add support for RainLab.Pages and the integrated Page Finder.
     */
    protected function registerPageFinder(): void
    {
        $listTypes = function () {
            return [
                Page::MENU_TYPE_PAGES => 'OFFLINE.Boxes: ' . trans('offline.boxes::lang.pages'),
                Page::MENU_TYPE_ALL_PAGES => 'OFFLINE.Boxes: ' . trans('offline.boxes::lang.all_pages'),
            ];
        };

        $getTypeInfo = function ($type) {
            if ($type === Page::MENU_TYPE_PAGES) {
                $refs = Page::query()
                    ->current()
                    ->with([
                        'children' => fn ($q) => $q->where('url', '<>', ''),
                    ])
                    ->get()
                    ->pipe(fn ($pages) => new PatchedTreeCollection($pages))
                    ->listsNested('name', 'slug', ' - ');

                return [
                    'references' => $refs,
                    'nesting' => true,
                    'dynamicItems' => true,
                ];
            }

            if ($type === Page::MENU_TYPE_ALL_PAGES) {
                return [
                    'nesting' => true,
                    'dynamicItems' => true,
                ];
            }

            return [];
        };

        $resolveItem = function ($type, $item, $url) {
            if ($type === Page::MENU_TYPE_PAGES || $type === Page::MENU_TYPE_ALL_PAGES) {
                return Page::resolveMenuItem($item, $url);
            }

            return null;
        };

        Event::listen('pages.menuitem.listTypes', $listTypes);
        Event::listen('cms.pageLookup.listTypes', $listTypes);

        Event::listen('pages.menuitem.getTypeInfo', $getTypeInfo);
        Event::listen('cms.pageLookup.getTypeInfo', $getTypeInfo);

        Event::listen('pages.menuitem.resolveItem', $resolveItem);
        Event::listen('cms.pageLookup.resolveItem', $resolveItem);
    }
}

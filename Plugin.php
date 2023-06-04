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

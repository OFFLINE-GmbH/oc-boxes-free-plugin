<?php

namespace OFFLINE\Boxes\Models;

use Backend\Facades\BackendAuth;
use Backend\Models\User;
use Backend\Widgets\Form;
use Closure;
use Cms\Classes\Page as CmsPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Model;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Element\ElementHolder;
use October\Rain\Exception\ValidationException;
use October\Rain\Support\Facades\Str;
use OFFLINE\Boxes\Classes\CMS\CmsPageParams;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\Classes\PatchedTreeCollection;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Classes\Scopes\ThemeScope;
use Site;
use System\Models\File;
use System\Models\SiteDefinition;

/**
 * @property mixed|string $boxes
 * @property integer|null $id
 * @property string $name
 * @property string $meta_title
 * @property string $meta_description
 * @property string $url
 * @property string $canonical_url
 * @property string $layout
 * @property string $og_title
 * @property string $og_description
 * @property string $og_type
 * @property boolean $is_hidden
 * @property boolean $has_pending_changes
 * @property null|int $version
 * @property string $slug
 * @property string $theme
 * @property string $published_state
 * @property integer $published_by
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property integer $origin_page_id
 * @property integer $site_root_id
 * @mixin Builder
 * @mixin \OFFLINE\Boxes\Classes\Traits\HasRevisions
 * @mixin \OFFLINE\Boxes\Classes\Traits\HasMultisiteSupport
 */
class Page extends Model
{
    use \OFFLINE\Boxes\Classes\Traits\HasSlug;
    use \OFFLINE\Boxes\Classes\Traits\HasBoxes;
    use \October\Rain\Database\Traits\Nullable;
    use \OFFLINE\Boxes\Classes\Traits\HasSearch;
    use \October\Rain\Database\Traits\Validation;
    use \OFFLINE\Boxes\Classes\Traits\HasNestedTreeStructure;
    use \OFFLINE\Boxes\Classes\Traits\HasMenuItems;
    
    public const MENU_TYPE_ALL_PAGES = 'offline-boxes-all-pages';

    public const MENU_TYPE_PAGES = 'offline-boxes-pages';

    /**
     * @var string table associated with the model
     */
    public $table = 'offline_boxes_pages';

    public $implement = ['@RainLab.Translate.Behaviors.TranslatableModel'];

    public $nullable = [
        'parent_id',
        'site_root_id',
    ];

    public $casts = [
        'has_pending_changes' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public $rules = [
        'name' => 'required:is_pending_content,1',
        'url' => ['nullable', 'regex:/^[a-z0-9\/_\-\.]*$/i'],
    ];

    public $fillable = [
        'name',
        'url',
        'canonical_url',
        'layout',
        'slug',
        'meta_title',
        'meta_description',
        'meta_robots',
        'og_title',
        'og_description',
        'og_type',
        'is_hidden',
        'parent_id',
        'nest_left',
        'nest_right',
        'nest_depth',
        'custom_config',
        'site_id',
        'site_root_id',
        'revision_id',
        'revision_published_at',
        'is_hidden_in_navigation',
    ];

    public $morphMany = [
        'boxes' => [Box::class, 'replicate' => true, 'delete' => true, 'name' => 'holder'],
    ];

    public $attachOne = [
        'og_image' => [File::class, 'replicate' => true, 'delete' => true],
    ];

    public $belongsTo = [
        'updated_by_user' => [User::class, 'key' => 'updated_by'],
    ];

    public $attachMany = [
        'images' => [File::class, 'replicate' => true, 'delete' => true],
    ];

    public $jsonable = [
        'meta_robots',
        'custom_config',
    ];

    public $translatable = [];

    /**
     * @var bool
     */
    private bool $multisiteScopeEnabled = true;

    /**
     * Add event listeners.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Only set the translatable fields for RainLab.Translate < 2.0
        if (class_exists(\RainLab\Translate\Models\Locale::class)) {
            $this->translatable = [
                'name',
                ['url', 'index' => true],
                'meta_title',
                'meta_description',
            ];
        }

        // Force a starting slash on translated URLs.
        $this->bindEvent('model.translate.resolveComputedFields', function ($locale) {
            if (method_exists($this, 'getAttributeTranslated')) {
                $url = $this->getAttributeTranslated('url', $locale);

                if ($url && !starts_with($url, '/')) {
                    return ['url' => '/' . $url];
                }
            }

            return [];
        });

        $this->bindEvent('model.form.filterFields', function (Form $widget, ElementHolder $holder) {
            // Hide Revisions Tab if feature is disabled.
            if (!Features::instance()->revisions) {
                // Move it to an existing tab so there is no flash of the tab before it is hidden.
                $holder['revisions']->tab = 'CMS';
                $holder['revisions']->hidden = true;
            }

            // Hide multisite field if feature is disabled.
            if (!Features::instance()->multisite) {
                $holder['site_root_id']->hidden = true;
            }

            // Remove the template field if no templates are registered.
            if ($widget->model->exists || !$holder->get('template')?->options()) {
                $holder->get('template')->config['hidden'] = true;
            }
        });
    }

    /**
     * Clean up validation rules for content pages.
     */
    public function beforeValidate()
    {
        $this->cleanUrl();
    }

    public function afterValidate()
    {
        if ($this->url && $this->published_state === PublishedState::DRAFT) {
            $pagesWithSameUrl = self::query()
                ->currentPublished()
                ->where('url', $this->url)
                ->where('site_id', $this->site_id ?? Site::getSiteIdFromContext())
                ->when($this->id, fn ($q) => $q->where('id', '<>', $this->id))
                ->when($this->slug, fn ($q) => $q->where('slug', '<>', $this->slug))
                ->count();

            if ($pagesWithSameUrl > 0) {
                throw new ValidationException([
                    'url' => trans('validation.unique', ['attribute' => 'url']),
                ]);
            }
        }
    }

    public function afterCreate()
    {
        if (!$this->origin_page_id) {
            $this->origin_page_id = $this->id;
            $this->save(null, $this->sessionKey);
        }
    }

    /**
     * Cleanup the page data before saving.
     */
    public function beforeSave()
    {
        $this->cleanUrl();

        // Enforce the theme value.
        if (!$this->theme) {
            $this->theme = ThemeResolver::instance()?->getThemeCode();
        }

        if (BackendAuth::getUser()) {
            $this->updated_by = BackendAuth::getUser()->id;
        }

        if (!$this->site_id && !Features::instance()->multisite) {
            $this->site_id = Site::getPrimarySite()?->id;
        }
    }

    public function afterSave()
    {
        $this->clearCache();

        // Set the site_root_id to the current page id if the multisite feature is disabled.
        // This ensures that the database is in a consistent state even if the feature is enabled later.
        if (!Features::instance()->multisite) {
            DB::table($this->table)->where('id', $this->id)->update(['site_root_id' => $this->id]);
        }
    }

    public function beforeDelete()
    {
        $this->boxes?->each(function (Box $box) {
            // Disable the nested tree structure trait so that the boxes
            // are not moved around in the tree after deletion.
            $box->useNestedTreeStructure = false;
            $box->delete();
        });
    }

    public function afterDelete()
    {
        $this->clearCache();
    }

    /**
     * Return the effective canonical URL.
     */
    public function getRealCanonicalUrlAttribute(): string
    {
        if ($this->canonical_url) {
            return starts_with($this->canonical_url, 'http')
                ? $this->canonical_url
                : URL::to($this->canonical_url);
        }

        return trim($this->url)
            ? URL::to($this->url)
            : '';
    }

    /**
     * Create a copy of this page.
     */
    public function duplicate()
    {
        $clone = $this->replicateWithRelations();
        $clone->useNestedTreeStructure = true;

        $clone->name .= ' (' . trans('offline.boxes::lang.copy_noun') . ')';

        if ($clone->url) {
            $clone->url .= '-copy';
        }

        $clone->slug = $this->slug . '-' . Str::random(5);
        $clone->is_hidden = true;

        // Use the new page id as site_root_id on main sites.
        // If the site_root_id and id are equal, this page is for the main site.
        if ($this->id === $this->site_root_id) {
            $clone->site_root_id = null;
        }

        $clone->save();

        $clone->origin_page_id = $clone->id;
        $clone->save();

        $clone->moveAfter($this);

        $this->moveNestedBoxesToNewParents($this, $clone);

        return $clone;
    }

    /**
     * Build a CMS page from this model instance.
     *
     * @return CmsPage
     */
    public function buildCmsPage(): CmsPage
    {
        $cmsPage = CmsPage::inTheme($this->theme);
        $cmsPage->title = $this->name;

        if (class_exists(\RainLab\Translate\Models\Locale::class)) {
            $cmsPage->url = $this->lang(\RainLab\Translate\Models\Locale::getDefault()->code)->url;
        } else {
            $cmsPage->url = $this->url;
        }

        $cmsPage->layout = $this->layout;
        $cmsPage->meta_title = $this->meta_title;
        $cmsPage->meta_description = $this->meta_description;
        $cmsPage->is_hidden = $this->is_hidden;

        $cmsPage->apiBag[CmsPageParams::BOXES_PAGE_ID] = $this->id;
        $cmsPage->apiBag[CmsPageParams::BOXES_MODEL_TYPE] = self::class;

        $cmsPage->fireEvent('model.afterFetch');

        if (class_exists(\RainLab\Translate\Models\Locale::class)) {
            $urls = collect(\RainLab\Translate\Models\Locale::listEnabled())
                ->mapWithKeys(fn ($name, $code) => [$code => $this->lang($code)->url])
                ->toArray();

            $cmsPage->attributes['viewBag'] = [
                'localeUrl' => $urls,
            ];
        }

        return $cmsPage;
    }

    /**
     * Override the open graph image relation.
     */
    public function setOpenGraphImage(File $file): void
    {
        $this->setRelation('og_image', $file);
    }

    /**
     * Get all available layout options.
     */
    public function getLayoutOptions()
    {
        return (new CmsPage())->getLayoutOptions();
    }

    /**
     * Get all pages as dropdown options.
     *
     * @param mixed $key
     */
    public static function getPageOptions()
    {
        return self::getPageOptionsKeyBy('slug');
    }

    /**
     * Get all pages as dropdown options with a custom key.
     *
     * @param mixed $key
     */
    public static function getPageOptionsKeyBy($key = 'slug')
    {
        return self::query()
            ->currentDrafts()
            ->get()
            ->pipe(fn ($pages) => new PatchedTreeCollection($pages))
            ->listsNested('name', $key, ' - ');
    }

    /**
     * Return the absolute URL to this page. Empty if no URL is available.
     */
    public function getAbsoluteUrlAttribute(): string
    {
        if (!$this->url) {
            return '';
        }

        return \Url::to($this->url);
    }

    /**
     * Returns the URL for a page with a given $slug.
     *
     * @param array{attribute?: string} $args
     */
    public static function getAttributeWhereSlug(string $slug, array $args = []): string
    {
        $routePrefix = '';

        $page = self::withoutGlobalScope(MultisiteScope::class)->where('slug', $slug)->first();

        if (!$page) {
            return '';
        }

        $currentSite = Site::getSiteFromContext();

        if ($currentSite->is_prefixed) {
            $routePrefix = $currentSite->route_prefix;
        }

        // If the current site is not the primary site, try to find the related page for the current site.
        // This allows a user to always use a page slug from the primary site, and the `boxesPage` filter
        // will return the "translated" data automatically.
        if (!$currentSite->is_primary && $page->site_id !== $currentSite->id && $page->current_site_page) {
            $page = $page->current_site_page;
        }

        if (!$page) {
            return '';
        }

        $attribute = array_get($args, 'attribute', 'url');

        $value = $page->getAttribute($attribute);

        if ($attribute !== 'url') {
            return $value;
        }

        return rtrim($routePrefix, '/') . '/' . ltrim($value, '/');
    }

    public function getParentIdOptions()
    {
        $query = self::newQuery()->where('category_id', $this->category_id ?? get('category_id'));

        if ($this->exists) {
            $query->where('id', '<>', $this->id);
        }

        return [null => '-- ' . trans('offline.boxes::lang.top_level')] + $query->listsNested('name', 'id');
    }

    /**
     * Return all page template options.
     */
    public function getTemplateOptions()
    {
        $contexts = ['default'];

        $templates = PartialReader::instance()->getBoxesConfig()->get('templates');

        return $templates
            ->filter(fn ($template) => array_intersect($template['contexts'], $contexts))
            ->mapWithKeys(fn ($template) => [$template['handle'] => $template['name']])
            ->toArray();
    }

    public function scopeCurrent($query, ?int $siteId = null): void
    {
        if (method_exists($this, 'revisionScopeCurrent')) {
            $this->revisionScopeCurrent($query, $siteId);
        }
    }

    public function scopeCurrentPublished($query, ?int $siteId = null): void
    {
        if (method_exists($this, 'revisionScopeCurrentPublished')) {
            $this->revisionScopeCurrentPublished($query, $siteId);
        }
    }

    public function scopeCurrentDrafts($query, ?int $siteId = null): void
    {
        if (method_exists($this, 'revisionScopeCurrentDrafts')) {
            $this->revisionScopeCurrentDrafts($query, $siteId);
        } else {
            $query->where('published_state', PublishedState::DRAFT);
        }
    }

    public function withoutMultisiteScope(Closure $fn)
    {
        if (method_exists($this, 'multisiteWithoutMultisiteScope')) {
            return $this->multisiteWithoutMultisiteScope($fn);
        }

        return $fn();
    }

    /**
     * Returns a unique cache key for a given page and site id.
     *
     * @param mixed $pageId
     * @param mixed $siteId
     */
    public static function multisiteCacheKey($pageId, $siteId)
    {
        return sprintf('boxes.pages.multisite.%d.%d', $pageId, $siteId);
    }

    /**
     * Make sure the URL is formatted in a uniform style.
     */
    protected function cleanUrl()
    {
        // Force a starting slash on the URL.
        if ($this->url && !starts_with($this->url, '/')) {
            $this->url = '/' . $this->url;
        }

        // Remove the trailing slash of the URL.
        if ($this->url && $this->url !== '/' && ends_with($this->url, '/')) {
            $this->url = rtrim($this->url, '/');
        }
    }

    protected static function booted()
    {
        static::addGlobalScope(new ThemeScope());
    }

    /**
     * Clears the cache for this Collection for all locales.
     */
    protected function clearCache()
    {
        $locales = ['default'];

        if (class_exists(\RainLab\Translate\Models\Locale::class)) {
            $locales = array_merge($locales, array_wrap(array_keys(\RainLab\Translate\Models\Locale::listAvailable())));
        }

        foreach ($locales as $locale) {
            Cache::forget(self::menuItemCacheKey($this->id, $locale));
        }

        foreach (SiteDefinition::get() as $site) {
            Cache::forget(self::multisiteCacheKey($this->id, $site->id));
        }
    }

    /**
     * newNestedTreeQuery creates a new query for nested sets
     */
    protected function newNestedTreeQuery()
    {
        $query = $this->newQuery();

        // Scope the query to the current site/theme.
        if ($this->exists) {
            if ($this->site_id) {
                $query->where('site_id', $this->site_id);
            }

            if ($this->theme) {
                $query->where('theme', $this->theme);
            }

            if ($this->published_state) {
                $query->where('published_state', $this->published_state);
            }
        }

        return $query;
    }
}

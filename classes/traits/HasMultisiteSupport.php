<?php

namespace OFFLINE\Boxes\Classes\Traits;

use Backend\Widgets\Form;
use Closure;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Element\ElementHolder;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\PatchedTreeCollection;
use OFFLINE\Boxes\Models\Page;
use System\Models\SiteDefinition;

/**
 * Implements October's native multisite support.
 */
trait HasMultisiteSupport
{
    use \October\Rain\Database\Traits\Multisite;

    /**
     * @see \October\Rain\Database\Traits\Multisite
     */
    public $propagatable = [];

    /**
     * Initialize the trait.
     */
    public function initializeHasMultisiteSupport()
    {
        $this->belongsTo['root_page'] = [
            Page::class,
            'key' => 'site_root_id',
        ];

        $this->hasMany['multisite_pages'] = [
            Page::class,
            'key' => 'id',
            'otherKey' => 'site_root_id',
            'scope' => 'relatedPagesAllSites',
            'replicate' => false,
        ];

        $this->hasOne['current_site_page'] = [
            Page::class,
            'key' => 'id',
            'otherKey' => 'site_root_id',
            'scope' => 'relatedPages',
            'replicate' => false,
        ];

        $this->belongsTo['site'] = [
            SiteDefinition::class,
        ];

        $this->bindEvent('model.form.filterFields', function (Form $widget, ElementHolder $holder) {
            if (!$widget->model instanceof Page || $holder->get('site_root_id') === null) {
                return;
            }
            // Hide the root page field if no multisite is configured.
            if (!Site::hasMultiSite()) {
                $holder->get('site_root_id')->config['hidden'] = true;
            }
            // Remove the template field if no templates are registered.
            if ($widget->model->exists || !$holder->get('template')?->options()) {
                $holder->get('template')->config['hidden'] = true;
            }
        });
    }

    /**
     * Add the required query parameters to the multisite_pages relation.
     * @param mixed $query
     * @param mixed $page
     */
    public function scopeRelatedPagesAllSites($query, $page)
    {
        return $query
            ->withoutGlobalScope(MultisiteScope::class)
            ->relatedPages($page);
    }

    /**
     * Fetch all related pages in a single query.
     * @param mixed $query
     * @param mixed $page
     */
    public function scopeRelatedPages($query, $page)
    {
        return $query
            ->orWhere(
                fn ($q) => $q->where('id', $page->site_root_id)->orWhere('site_root_id', $page->site_root_id)
            );
    }

    /**
     * Return all possible root pages for a site. If a page id is passed
     * the site from that page will be used.
     * @param null|mixed $pageId
     */
    public function getSiteRootIdOptions($pageId = null)
    {
        $useSite = Site::getPrimarySite()->id;

        if ($pageId) {
            $siteOverride = Page::withoutGlobalScope(MultisiteScope::class)->find($pageId)?->site_id;

            if ($siteOverride) {
                $useSite = $siteOverride;
            }
        }

        return [null => '-- ' . trans('offline.boxes::lang.no_root_page')]
            + Page::withoutGlobalScope(MultisiteScope::class)
                ->where('site_id', $useSite)
                ->get()
                ->pipe(fn ($pages) => new PatchedTreeCollection($pages))
                ->listsNested('name', 'id');
    }

    /**
     * Run a function without the multisite scope. This is required
     * so that the root page can be queried for new pages by the
     * NestedTree trait.
     */
    public function withoutMultisiteScope(Closure $fn)
    {
        $this->multisiteScopeEnabled = false;

        $result = $fn();

        $this->multisiteScopeEnabled = true;

        return $result;
    }

    public function isMultisiteEnabled()
    {
        return $this->multisiteScopeEnabled && !app()->runningInConsole();
    }
}

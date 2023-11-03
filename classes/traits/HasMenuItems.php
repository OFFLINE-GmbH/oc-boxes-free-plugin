<?php

namespace OFFLINE\Boxes\Classes\Traits;

use Backend\Facades\BackendAuth;
use Cache;
use Closure;
use Illuminate\Support\Facades\URL;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Models\Page;
use RainLab\Translate\Classes\Translator;

trait HasMenuItems
{
    /**
     * Integration for RainLab.Pages and RainLab.Sitemap
     *
     * @param mixed $item
     * @param mixed $currentUrl
     */
    public static function resolveMenuItem($item, $currentUrl)
    {
        $iterator = self::iterateChildMenuItems($currentUrl, $item->nesting);

        if ($item->type === Page::MENU_TYPE_ALL_PAGES) {
            $query = self::query()
                ->current()
                ->where('url', '<>', '')
                ->where('is_hidden', false)
                ->where('is_hidden_in_navigation', false);

            if ($item->nesting) {
                $allPages = $query->get()->toNested(false);
            } else {
                $allPages = $query->where('parent_id', null)->get();
            }

            return [
                'title' => 'OFFLINE.Boxes: ' . trans('offline.boxes::lang.all_pages'),
                'items' => $iterator($allPages),
            ];
        }

        $site = Site::getSiteFromContext();

        $query = static fn () => Page::current()
            ->withoutGlobalScope(MultisiteScope::class)
            ->where(fn ($q) => $q->where('id', $item->reference)->orWhere('slug', $item->reference))
            ->where('is_hidden', false)
            ->where('is_hidden_in_navigation', false)
            ->first();

        $page = new Page();

        // Cache the resolved menu item (if no user is logged in).
        if (BackendAuth::getUser()) {
            $page = $query();
        } else {
            $data = Cache::rememberForever(
                self::menuItemCacheKey($item->reference, $site->id),
                fn () => $query()?->toArray()
            );

            if ($data) {
                $page->forceFill(array_except($data, ['created_at', 'updated_at', 'published_at', 'translations']));
            }
        }

        // Ignore broken references.
        if (!$page?->id) {
            return;
        }

        // If the page is not in the current site, we need to get the current site's version of the page.
        if ($page->site_id !== $site->id) {
            $page = $page->current_site_page ?? null;

            // The page does not exist for the current site.
            if (!$page) {
                return;
            }
        }

        $pageUrl = URL::to($site->base_url . $page->url);

        $menuItem = [
            'url' => $pageUrl,
            'isActive' => $pageUrl === $currentUrl,
        ];

        // Add the child pages to this item as well if nesting is active.
        if ($item->nesting) {
            $children = $page->allChildren()->current()->where('url', '<>', '')->get()->toNested(false);

            $menuItem['items'] = $iterator($children);
        }

        return $menuItem;
    }

    /**
     * Returns an iterator that iterates over a Collection and builds
     * nested menu items for RainLab.Pages.
     * @param mixed $currentUrl
     */
    public static function iterateChildMenuItems($currentUrl, ?bool $allowNesting = true): Closure
    {
        $iterator = function (\October\Rain\Database\Collection $children) use (
            &$iterator,
            $currentUrl,
            $allowNesting
        ) {
            $branch = [];

            foreach ($children as $child) {
                $pageUrl = URL::to($child->url);
                $item = [
                    'url' => $pageUrl,
                    'title' => $child->name,
                    'isActive' => $pageUrl === $currentUrl,
                    'viewBag' => ['isHidden' => (bool)$child->is_hidden],
                ];

                if ($allowNesting && $child->children->count() > 0) {
                    $item['items'] = $iterator($child->children);
                }

                $branch[] = $item;
            }

            return $branch;
        };

        // Make sure php-cs-fixer does not strip out the $iterator variable.
        // It is used as a reference in the closure above.
        $_ = $iterator;

        return $iterator;
    }

    /**
     * Returns a unique cache key for a given id.
     * Also considers the current locale if RainLab.Translate is installed.
     *
     * @param mixed $id
     * @param mixed $locale
     */
    protected static function menuItemCacheKey($id, $locale = 'default')
    {
        if ($locale === 'default' && class_exists(Translator::class)) {
            $locale = Translator::instance()->getLocale();
        }

        return sprintf('boxes.instance.menu.%s.%s', $id, $locale);
    }
}

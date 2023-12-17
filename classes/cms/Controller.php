<?php

namespace OFFLINE\Boxes\Classes\CMS;

use Backend\Facades\BackendAuth;
use Cms\Classes\Page as CmsPage;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Support\Traits\Singleton;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;
use RainLab\Translate\Classes\Translator;
use Site;
use System\Classes\SiteManager;
use System\Models\SiteDefinition;

class Controller
{
    use Singleton;

    public const PREVIEW_URL = '/__boxes-preview/';

    public const PREVIEW_PARAM = 'boxes-preview';

    public const DRAFT_ID_PARAM = '_boxes-draft';

    /**
     * Creates a virtual CMS page for a given $url.
     */
    public function getCmsPageForUrl(string $url): ?CmsPage
    {
        if (!$url) {
            return null;
        }

        $draftId = null;

        if (BackendAuth::getUser()) {
            $draftId = get(self::DRAFT_ID_PARAM);
        }

        $page = Page::query()
            ->when(
                $draftId,
                fn ($q) => $q->withoutGlobalScope(MultisiteScope::class)->where('id', $draftId),
            )
            ->when(!$draftId, fn ($q) => $q->when(
                class_exists(\RainLab\Translate\Models\Locale::class),
                fn ($q) => $q->transWhere('url', $url)->with('translations'),
                fn ($q) => $q->where('url', $url)
            ))
            ->when(
                !$draftId && Features::instance()->revisions,
                fn ($q) => $q->current()
            )
            ->first();

        if (!$page) {
            return null;
        }

        // Make sure the active Site is always the site the page belongs to.
        // Drafts can be viewed on hostnames that could technically belong to
        // another site. This makes sure that the draft is always displayed
        // using the right site context.
        if ($draftId && Site::getActiveSite()?->id !== $page->site_id) {
            Site::setActiveSiteId($page->site_id);
        }

        if (class_exists(\RainLab\Translate\Models\Locale::class)) {
            $page->translateContext(Translator::instance()->getLocale());
        }

        return $page->buildCmsPage();
    }

    /**
     * Returns a virtual CMS page to host the BoxesPageEditor component.
     */
    public function getPreviewPage(string $url): ?CmsPage
    {
        // Remove all Site prefixes from the URL. This is necessary
        // since when editing a disabled Site, October does not provide this site
        // in the Context. This makes it impossible to strip the Site prefix
        // since we don't know what Site the URL belongs to.
        SiteManager::instance()->listSites()->each(function (SiteDefinition $site) use (&$url) {
            if ($site->is_prefixed) {
                $url = str_replace($site->route_prefix, '', $url);
            }
        });

        $previewParts = array_filter(explode('/', str_replace(self::PREVIEW_URL, '', $url)));

        if (count($previewParts) !== 2) {
            return null;
        }

        [$previewType, $pageId] = $previewParts;

        $model = $previewType === 'page' ? Page::withoutGlobalScope(MultisiteScope::class) : Content::query();

        $page = $model->find($pageId);

        if (!$page) {
            return null;
        }

        // Make sure the active Site is always the site the page belongs to.
        // This is required if the backend is viewed on a hostname that
        // technically belongs to another site.
        if (Site::getActiveSite()?->id !== $page->site_id) {
            Site::setActiveSiteId($page->site_id);
        }

        $cmsPage = $page->buildCmsPage();
        $cmsPage->url = $url;
        $cmsPage->title = 'OFFLINE.Boxes Preview';

        $cmsPage->apiBag[CmsPageParams::BOXES_IS_EDITOR] = true;

        return $cmsPage;
    }
}

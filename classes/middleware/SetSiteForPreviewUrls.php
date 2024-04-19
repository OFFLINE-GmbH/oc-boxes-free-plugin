<?php

namespace OFFLINE\Boxes\Classes\Middleware;

use Backend\Facades\BackendAuth;
use Closure;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\CMS\Controller;
use OFFLINE\Boxes\Classes\Scopes\ThemeScope;
use OFFLINE\Boxes\Models\Page;

/**
 * SetSiteForPreviewUrls sets the active site based on the request parameters
 */
class SetSiteForPreviewUrls
{
    /**
     * handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (BackendAuth::getUser()) {
            $this->changeSite($request);
        }

        return $next($request);
    }

    private function changeSite(\Illuminate\Http\Request $request)
    {
        $draft = get(Controller::DRAFT_ID_PARAM);

        if (!$draft) {
            $previewParts = Controller::getPreviewPartsFromUrl(request()->path());

            if (count($previewParts) === 2 && $previewParts[0] === 'page') {
                $draft = $previewParts[1];
            }
        }

        if (!$draft) {
            return;
        }

        $page = Page::withoutGlobalScopes([MultisiteScope::class, ThemeScope::class])
            ->find($draft);

        if (!$page) {
            return;
        }

        if (Site::getActiveSite()?->id !== $page->site_id) {
            Site::setActiveSiteId($page->site_id);
            Site::applyActiveSite(Site::getActiveSite());
        }
    }
}

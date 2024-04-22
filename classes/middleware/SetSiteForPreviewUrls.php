<?php

namespace OFFLINE\Boxes\Classes\Middleware;

use Backend\Facades\BackendAuth;
use Closure;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\CMS\Controller;

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
        $page = Controller::resolvePreviewPage($request->get(Controller::PREVIEW_PARAM));

        if (!$page) {
            return;
        }

        if (Site::getActiveSite()?->id !== $page->site_id) {
            Site::setActiveSiteId($page->site_id);
            Site::applyActiveSite(Site::getActiveSite());
        }
    }
}

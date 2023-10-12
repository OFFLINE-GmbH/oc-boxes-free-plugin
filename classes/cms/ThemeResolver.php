<?php

namespace OFFLINE\Boxes\Classes\CMS;

use Cms\Classes\Theme;
use October\Rain\Support\Facades\Event;
use October\Rain\Support\Facades\Site;
use October\Rain\Support\Traits\Singleton;

/**
 * Resolves the current theme.
 *
 * The Multisite feature makes it hard to determine the active theme as
 * it may be overridden by the site configuration. Depending on the
 * point we are in the request lifecycle, the Theme::getActiveTheme()
 * method might return the wrong theme.
 *
 * This class tries to mitigate this problem.
 */
class ThemeResolver
{
    use Singleton;

    protected string $themeCode = '';

    /**
     * Cached theme paths per theme code.
     * @var array
     */
    protected array $themePaths = [];

    public function getThemeCode(): string
    {
        return $this->themeCode;
    }

    public function init()
    {
        $themeCode = $this->getThemeCodeFromContext();

        $this->themeCode = $themeCode ?? '';

        // Update the currently active theme when the site or theme is changed.
        Event::listen('system.site.setEditSite', function () {
            $this->themeCode = $this->getThemeCodeFromContext();
        });

        Event::listen('cms.theme.setEditTheme', function ($code) {
            $this->themeCode = $code;
        });
    }

    public function getThemePaths()
    {
        if (isset($this->themePaths[$this->themeCode])) {
            return $this->themePaths[$this->themeCode];
        }

        $theme = Theme::load($this->themeCode);

        if (!$theme) {
            return [];
        }

        $themePaths = [
            $theme->getPath(),
        ];

        if ($parentTheme = $theme->getParentTheme()) {
            $themePaths[] = $parentTheme->getPath();
        }

        // Normalize Windows paths.
        foreach ($themePaths as $key => $themePath) {
            $themePaths[$key] = str_replace('\\', '/', $themePath);
        }

        return $this->themePaths[$this->themeCode] = $themePaths;
    }

    protected function getThemeCodeFromContext()
    {
        return Site::getSiteFromContext()?->theme ?: Theme::getActiveTheme()?->getId();
    }
}

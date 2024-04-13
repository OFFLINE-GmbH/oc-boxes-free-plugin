<?php

namespace OFFLINE\Boxes\Classes;

use October\Rain\Support\Traits\Singleton;
use OFFLINE\Boxes\Models\BoxesSetting;

class Features
{
    use Singleton;

    public bool $revisions;

    public bool $multisite;

    public bool $references;

    public bool $placeholderPreviews;

    public bool $isProVersion;

    public function init(): void
    {
        $this->isProVersion = config('offline.boxes::features.isProVersion', false);
        $this->revisions = $this->isProVersion && BoxesSetting::get('revisions_enabled', false);
        $this->multisite = $this->isProVersion && config('offline.boxes::features.multisite', false);
        $this->references = $this->isProVersion && config('offline.boxes::features.references', false);
        $this->placeholderPreviews = config('offline.boxes::features.placeholderPreviews', true);
    }

    public function isEnabled(string $feature)
    {
        return $this->{$feature} ?? false;
    }

    public function toArray()
    {
        return [
            'revisions' => $this->revisions,
            'multisite' => $this->multisite,
            'references' => $this->references,
            'placeholderPreviews' => $this->placeholderPreviews,
        ];
    }
}

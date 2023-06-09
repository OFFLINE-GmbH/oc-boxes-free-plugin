<?php

namespace OFFLINE\Boxes\Classes;

use October\Rain\Support\Traits\Singleton;

class Features
{
    use Singleton;

    public bool $revisions;

    public bool $multisite;

    public bool $references;

    public bool $placeholderPreviews;

    public function init(): void
    {
        $this->revisions = config('offline.boxes::features.revisions', false);
        $this->multisite = config('offline.boxes::features.multisite', false);
        $this->references = config('offline.boxes::features.references', false);
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

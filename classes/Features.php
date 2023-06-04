<?php

namespace OFFLINE\Boxes\Classes;

use October\Rain\Support\Traits\Singleton;

class Features
{
    use Singleton;

    public bool $revisions;

    public bool $multisite;

    public bool $references;

    public function init(): void
    {
        $this->revisions = config('offline.boxes::features.revisions', false);
        $this->multisite = config('offline.boxes::features.multisite', false);
        $this->references = config('offline.boxes::features.references', false);
    }

    public function toArray()
    {
        return [
            'revisions' => $this->revisions,
            'multisite' => $this->multisite,
            'references' => $this->references,
        ];
    }
}

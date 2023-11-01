<?php

namespace OFFLINE\Boxes\Classes\Traits;

use October\Rain\Database\Builder;

trait HasSearch
{
    /**
     * Search for pages that match $query.
     */
    public function scopeSearch(Builder $q, string $searchQuery): Builder
    {
        return $q
            ->current()
            ->where('is_hidden', false)
            ->where(function ($q) use ($searchQuery) {
                $q
                    ->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('meta_title', 'like', "%{$searchQuery}%")
                    ->orWhere('meta_description', 'like', "%{$searchQuery}%")
                    ->orWhere('og_title', 'like', "%{$searchQuery}%")
                    ->orWhere('og_description', 'like', "%{$searchQuery}%")
                    ->orWhere('slug', 'like', "%{$searchQuery}%")
                    ->orWhere('url', 'like', "%{$searchQuery}%")
                    ->orWhereHas('boxes', fn ($q) => $q->where('data', 'like', "%{$searchQuery}%"));
            });
    }
}

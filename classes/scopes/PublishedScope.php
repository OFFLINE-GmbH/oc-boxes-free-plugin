<?php

namespace OFFLINE\Boxes\Classes\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use OFFLINE\Boxes\Classes\PublishedState;

/**
 * Scope to only show published pages.
 */
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('published_state', PublishedState::PUBLISHED);
    }
}

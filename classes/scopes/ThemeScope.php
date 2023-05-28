<?php

namespace OFFLINE\Boxes\Classes\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;

/**
 * Scope to only show pages from the current theme.
 */
class ThemeScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $themeCode = ThemeResolver::instance()?->getThemeCode();

        if (!$themeCode) {
            return;
        }

        $builder->where(function ($q) use ($themeCode, $model) {
            $q->where($model->table . '.theme', $themeCode)
                ->orWhereNull($model->table . '.theme');
        });
    }
}

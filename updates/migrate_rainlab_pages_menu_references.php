<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Collection;
use OFFLINE\Boxes\Models\Page;
use RainLab\Pages\Classes\Menu;
use System\Helpers\Cache;

/**
 * Using `id` as references in RainLab.Pages menus does not
 * work when importing and exporting pages.
 * The IDs will change, but the slugs remain. This migration
 * updates all id menu references with the correct slug.
 */
class MigrateRainlabPagesMenuReferences extends Migration
{
    public function up()
    {
        if (!class_exists('RainLab\Pages\Classes\Menu')) {
            return;
        }

        $iterator = function (Collection $items) use (&$iterator) {
            return $items->map(function ($item) use (&$iterator) {
                if ($item->items) {
                    $item->items = $iterator(collect($item->items));
                }

                if ($item->type !== Page::MENU_TYPE_PAGES) {
                    return $item;
                }

                $page = Page::withoutGlobalScopes()->where('id', $item->reference)->first();

                if (!$page) {
                    return $item;
                }

                $item->reference = $page->slug;

                return $item;
            });
        };

        $map = function (Collection $items) use (&$map) {
            return $items->map(function ($item) use (&$map) {
                $return = $item->toArray();

                if ($item->items) {
                    $return['items'] = $map(collect($item->items));
                }

                return $return;
            })->toArray();
        };

        Menu::get()->each(function (Menu $menu) use ($iterator, $map) {
            $items = $iterator(collect($menu->items));

            $menu->item_data = $map($items);

            // Silence the Validation trait.
            $menu->code = $menu->code;

            $menu->save();
        });

        Cache::clear();
    }

    public function down()
    {
    }
}

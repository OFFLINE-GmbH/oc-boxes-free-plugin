<?php

namespace OFFLINE\Boxes\Classes;

use Closure;
use October\Rain\Database\TreeCollection;

class PatchedTreeCollection extends TreeCollection
{
    /**
     * We never want to remove orphans by default, so we
     * override the default attribute here.
     *
     * This patch also makes the listsNested() method
     * work as we need it to without removing orphans.
     * @param mixed $removeOrphans
     */
    public function toNested($removeOrphans = false)
    {
        return parent::toNested(false);
    }

    /**
     * listsNestedCallback is like listsNested but allows a callback to be specified.
     * @param mixed $indent
     */
    public function listsNestedCallback(Closure $callback, $indent = '&nbsp;&nbsp;&nbsp;')
    {
        /*
         * Recursive helper function
         */
        $buildCollection = function ($items, $depth = 0) use (&$buildCollection, $callback, $indent) {
            $result = [];

            $indentString = str_repeat($indent, $depth);

            foreach ($items as $item) {
                [$key, $value] = $callback($item);

                if ($key !== null) {
                    $result[$key] = $indentString . $value;
                } else {
                    $result[] = $indentString . $value;
                }

                /*
                 * Add the children
                 */
                $childItems = $item->getChildren();

                if ($childItems->count() > 0) {
                    $result = $result + $buildCollection($childItems, $depth + 1);
                }
            }

            return $result;
        };

        /*
         * Build a nested collection
         */
        $rootItems = $this->toNested();

        return $buildCollection($rootItems);
    }
}

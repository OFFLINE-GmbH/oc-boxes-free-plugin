<?php

namespace OFFLINE\Boxes\Classes\Traits;

use OFFLINE\Boxes\Classes\SoftDeletingScope;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Page;

/**
 * NestedTreeModel is modified to allow disabling the trait.
 *
 * Shamelessly copied from \Tailor\Traits\NestedTreeModel
 */
trait HasNestedTreeStructure
{
    use \October\Rain\Database\Traits\NestedTree;

    /**
     * Programmatically disable the nested tree structure.
     */
    public $useNestedTreeStructure = true;

    /**
     * initializeNestedTree disables the inherited trait
     */
    public function initializeNestedTree()
    {
    }

    /**
     * initializeNestedTreeModel constructor
     */
    public function initializeHasNestedTreeStructure()
    {
        // Define relationships
        $this->hasMany['children'] = [
            get_class($this),
            'key' => $this->getParentColumnName(),
            'replicate' => false,
            'scope' => 'currentThemeSiteAndStatus',
        ];

        $this->belongsTo['parent'] = [
            get_class($this),
            'key' => $this->getParentColumnName(),
            'replicate' => false,
        ];

        // Bind events
        $this->bindEvent('model.beforeCreate', function () {
            if (!$this->useNestedTreeStructure()) {
                return;
            }

            $this->setDefaultLeftAndRight();
        });

        $this->bindEvent('model.beforeSave', function () {
            // This makes the parent column nullable
            $this->storeNewParent();
        });

        $this->bindEvent('model.afterSave', function () {
            if (!$this->useNestedTreeStructure()) {
                return;
            }

            $this->moveToNewParent();
        });

        $this->bindEvent('model.beforeDelete', function () {
            if (!$this->useNestedTreeStructure()) {
                return;
            }

            $this->deleteDescendants();
        });

        if (static::hasGlobalScope(SoftDeletingScope::class)) {
            $this->bindEvent('model.beforeRestore', function () {
                if (!$this->useNestedTreeStructure()) {
                    return;
                }

                $this->shiftSiblingsForRestore();
            });

            $this->bindEvent('model.afterRestore', function () {
                if (!$this->useNestedTreeStructure()) {
                    return;
                }

                $this->restoreDescendants();
            });
        }
    }

    public function scopeCurrentThemeSiteAndStatus($query)
    {
        if (!$this->exists()) {
            return;
        }

        if (get_class($this) === Page::class) {
            $query
                ->where('site_id', $this->site_id)
                ->where('published_state', $this->published_state)
                ->where('theme', $this->theme);
        }

        if (get_class($this) === Box::class) {
            $query
                ->where('holder_id', $this->holder_id)
                ->where('holder_type', $this->holder_type);
        }
    }

    /**
     * scopeSiblings filters targeting all children of the parent, except self.
     * @param mixed $query
     * @param mixed $includeSelf
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSiblings($query, $includeSelf = false)
    {
        if (get_class($this) === Box::class) {
            // Scope the query only to the current holder's nodes.
            $query
                ->where('holder_id', $this->holder_id)
                ->where('holder_type', $this->holder_type);
        }

        $query->where($this->getParentColumnName(), $this->getParentId());

        return $includeSelf ? $query : $query->withoutSelf();
    }

    /**
     * useNestedTreeStructure
     */
    public function useNestedTreeStructure(): bool
    {
        return $this->useNestedTreeStructure;
    }
}

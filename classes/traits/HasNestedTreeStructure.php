<?php

namespace OFFLINE\Boxes\Classes\Traits;

use OFFLINE\Boxes\Classes\SoftDeletingScope;

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

    /**
     * useNestedTreeStructure
     */
    public function useNestedTreeStructure(): bool
    {
        return $this->useNestedTreeStructure;
    }
}

<?php

namespace OFFLINE\Boxes\Classes\Traits;

trait HasSlug
{
    public function initializeHasSlug()
    {
        // Set slugged attributes on new records and existing records if slug is missing.
        $this->bindEvent('model.saveInternal', function () {
            if (!$this->slug) {
                $this->slugAttributes();
            }
        });
    }

    protected function slugAttributes()
    {
        $slugs = ['slug' => 'name'];

        foreach ($slugs as $slugAttribute => $sourceAttribute) {
            // Slug exists already.
            if ($this->{$slugAttribute}) {
                return;
            }

            // Source attribute is missing.
            if (!$this->{$sourceAttribute}) {
                return;
            }

            $slug = str_slug($this->{$sourceAttribute});

            $this->{$slugAttribute} = $this->getSluggableUniqueAttributeValue($slugAttribute, $slug);
        }
    }

    protected function getSluggableUniqueAttributeValue($name, $value)
    {
        $counter = 1;
        $separator = '-';
        $_value = $value;

        while ($this->newSluggableQuery()->where($name, $_value)->count() > 0) {
            $counter++;
            $_value = $value . $separator . $counter;
        }

        return $_value;
    }

    /**
     * Returns a query that excludes the current record if it exists
     */
    protected function newSluggableQuery()
    {
        return $this->exists
            ? $this->newQueryWithoutScopes()->where($this->getKeyName(), '<>', $this->getKey())
            : $this->newQueryWithoutScopes();
    }
}

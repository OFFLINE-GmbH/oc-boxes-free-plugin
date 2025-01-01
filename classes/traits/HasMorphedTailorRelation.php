<?php

namespace OFFLINE\Boxes\Classes\Traits;

use October\Rain\Database\Relations\MorphTo;

/**
 * Tailor does not support morphed relations yet.
 * This trait patches the relevant model methods to make it work.
 */
trait HasMorphedTailorRelation
{
    protected string $morphTypeAttribute = 'content_type';

    /**
     * Always return the proper class name (without the blueprint UUID suffix) for the morph type attribute.j
     * @param mixed $key
     */
    public function getAttributeValue($key)
    {
        if ($key === $this->morphTypeAttribute && str_contains($this->attributes[$this->morphTypeAttribute], '@')) {
            return explode('@', $this->attributes[$this->morphTypeAttribute])[0];
        }

        return parent::getAttributeValue($key);
    }

    /**
     * Override the morphTo relation to use a model instance that is extended with the Tailor blueprint.
     * @param mixed $target
     * @param mixed $name
     * @param mixed $type
     * @param mixed $id
     * @param mixed $ownerKey
     */
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey)
    {
        $instance = $this->newRelatedInstance(
            static::getActualClassNameForMorph($target)
        );

        if (method_exists($instance, 'extendWithBlueprint') && $blueprint = $this->content_type_blueprint) {
            $instance->extendWithBlueprint($blueprint);
        }

        return new MorphTo(
            $instance->newQuery(),
            $this,
            $id,
            $ownerKey ?? $instance->getKeyName(),
            $type,
            $name
        );
    }

    /**
     * Get the blueprint UUID from the morph type attribute.
     */
    protected function getContentTypeBlueprintAttribute()
    {
        if (!str_contains($this->attributes[$this->morphTypeAttribute], '@')) {
            return null;
        }

        $parts = explode('@', $this->attributes[$this->morphTypeAttribute]);

        if (count($parts) < 2) {
            return null;
        }

        // Remove the Tailor table prefix and the trailing type identifier (j, c, r).
        $encoded = str_replace('xc_', '', substr($parts[1], 0, -1));

        // Restore proper UUID format.
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($encoded, 0, 8),
            substr($encoded, 8, 4),
            substr($encoded, 12, 4),
            substr($encoded, 16, 4),
            substr($encoded, 20)
        );
    }
}

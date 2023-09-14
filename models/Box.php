<?php

namespace OFFLINE\Boxes\Models;

use App;
use Backend\Facades\BackendAuth;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Model;
use October\Rain\Argon\Argon;
use October\Rain\Support\Facades\Event;
use OFFLINE\Boxes\Classes\Events;
use OFFLINE\Boxes\Classes\Exceptions\PartialNotFoundException;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\Partial\Partial;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\Classes\Partial\RenderContext;
use OFFLINE\Boxes\Classes\PatchedTreeCollection;
use RainLab\Translate\Classes\Translator;
use Schema;
use stdClass;
use System\Models\File;

/**
 * @property bool $is_enabled
 * @property string $partial
 * @property string $unique_id
 * @property stdClass $data
 * @property int $holder_id
 * @property string $holder_type
 * @property int $section_id
 * @property int $parent_id
 * @property int $nest_right
 * @property int $nest_left
 * @property int $nest_depth
 * @property bool $read_only
 * @property number $references_box_id
 * @property \Illuminate\Support\Collection<int, Box>|null $referenced_by
 * @property Box|null $references
 * @method static forCurrentlyPublishedPage() Builder
 *
 * @property-read  \Illuminate\Support\Collection<int, Box> $children
 * @property-read  ?Box $reference
 * @property-read  Page|mixed $holder
 */
class Box extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use \OFFLINE\Boxes\Classes\Traits\HasContext;
    use \OFFLINE\Boxes\Classes\Traits\HasNestedTreeStructure;
    use \Tailor\Traits\BlueprintRelationModel;

    public const SPACING_KEY_BEFORE = '_boxes_spacing_before';

    public const SPACING_KEY_AFTER = '_boxes_spacing_after';

    public $implement = ['@RainLab.Translate.Behaviors.TranslatableModel'];

    public $translatable = [];

    /**
     * @var string database table for this model
     */
    public $table = 'offline_boxes_boxes';

    /**
     * @var array jsonable data
     */
    public $jsonable = [
        'data',
        'locked',
    ];

    public $fillable = [
        'is_enabled',
        'unique_id',
        'holder_id',
        'holder_type',
        'parent_id',
        'partial',
        'data',
        'sort_order',
        'locked',
    ];

    public $nullable = [
        'parent_id',
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'is_enabled' => 'boolean',
        'partial' => 'required|string',
    ];

    public $attributeNames = [];

    public $customMessages = [];

    public $morphTo = [
        'holder' => [],
    ];

    public $hasOne = [
    ];

    public $hasMany = [
    ];

    public $belongsTo = [
        // Legacy definition for versions < 2.1.
        'page' => [Page::class, 'other_key' => 'holder_id'],
    ];

    public $attachOne = [
        'image' => [File::class, 'replicate' => true, 'delete' => true],
        'file' => [File::class, 'replicate' => true, 'delete' => true],
    ];

    public $attachMany = [
        'images' => [File::class, 'replicate' => true, 'delete' => true],
        'files' => [File::class, 'replicate' => true, 'delete' => true],
    ];

    /**
     * The decoded Box data, cached.
     */
    protected array $decodedData = [];

    /**
     * The parsed YAML config.
     */
    protected ?Partial $parsedPartial = null;

    /**
     * If true, the RainLab.Translate integration is ready.
     */
    protected bool $translatableReady = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->bindEvent('model.beforeCreate', function () {
            if (!$this->unique_id) {
                $this->unique_id = substr(sha1((string)Argon::now()->getPreciseTimestamp()), 0, 8);
            }
            $this->handleSpecialPartials();
        });

        $this->bindEvent('model.afterCreate', function () {
            if (!$this->origin_box_id) {
                $this->origin_box_id = $this->id;
                $this->save(null, $this->sessionKey);
            }
        });

        $this->bindEvent('model.beforeSetAttribute', function ($key, $value) {
            if ($key === 'data') {
                return $this->cleanupDataAttribute($value);
            }

            return $value;
        });

        $this->bindEvent('model.beforeReplicate', function () {
            $this->useNestedTreeStructure = false;
        });

        $this->bindEvent('model.beforeValidate', function () {
            $this->setRulesFromPartial();
        });

        $this->bindEvent('model.afterFetch', function () {
            $this->setTranslatableFromPartial();
        });

        $this->bindEvent('model.afterSave', function () {
            if ($this->holder_type === Page::class && $this->holder) {
                // Touch the Page to update the published_at timestamp.
                $this->holder->useNestedTreeStructure = false;
                $this->holder->save();
            }
        });

        $this->bindEvent('model.saveInternal', function () {
            // RainLab.Translate sets the translatable data attributes
            // directly on the model. Make sure to remove this before saving.
            if ($this->methodExists('setAttributeTranslated')) {
                foreach ($this->translatable as $attribute) {
                    unset($this->attributes[$attribute]);
                }
            }

            // Set the default locked values.
            if (Schema::hasColumn('offline_boxes_boxes', 'locked') && !$this->locked) {
                $this->locked = [];
            }

            unset($this->attributes['_add_before']);
        });
    }

    /**
     * Render this Box.
     *
     * @throws \Cms\Classes\CmsException
     */
    public function render(RenderContext|array $context): string
    {
        $context = $this->wrapContext($context);

        $context->data = (object)$this->data;

        Event::fire(Events::BEFORE_BOX_RENDER, [$this, $context]);

        $contents = $this->getPartial()->render($this, $context);

        Event::fire(Events::AFTER_BOX_RENDER, [$this, $context, &$contents]);

        return $contents;
    }

    /**
     * Render this Box and override some RenderContext.
     *
     * @throws \Cms\Classes\CmsException
     */
    public function renderMergeContext(RenderContext|array $context, array $newContext = []): string
    {
        $context = $this->wrapContext($context);

        // Merge data.
        foreach ($newContext as $key => $value) {
            $context->{$key} = $value;
        }

        return $this->render($context);
    }

    /**
     * Render this Box's children.
     */
    public function renderChildren(RenderContext|array $context): string
    {
        $context = $this->wrapContext($context);

        $count = $this->children->count();

        if ($count === 0) {
            return '';
        }

        $index = 0;
        $loop = $this->buildLoopHelper($count);

        return $this->children->implode(function (Box $child) use ($context, &$index, $loop) {
            $context->loop = $loop($index++);

            return $child->render($context);
        });
    }

    /**
     * Render a specific child of this Box.
     */
    public function renderChild(RenderContext|array $context, int $childIndex): string
    {
        $context = $this->wrapContext($context);

        return $this->children->get($childIndex, new Box())?->render($context) ?? '';
    }

    public function scopeForCurrentlyPublishedPage(Builder $query)
    {
        if (Features::instance()->references) {
            $query->whereHas('holder', function ($q) {
                if (BackendAuth::getUser()) {
                    $q->currentDrafts();
                } else {
                    $q->currentPublished();
                }
            });
        }
    }

    /**
     * Get all boxes as dropdown options.
     *
     * @param mixed $key
     */
    public static function getBoxOptions(Page $page)
    {
        return $page->boxes
            ->pipe(fn ($pages) => new PatchedTreeCollection($pages))
            ->listsNestedCallback(function (Box $box) {
                // Try to find a suitable value to display.
                $fields = ['title', 'label', 'heading', 'id'];

                $value = $box->parsedPartial?->config->name ?? $box->partial;

                foreach ($fields as $field) {
                    if ($dataValue = array_get($box->data, $field)) {
                        $value = $dataValue;
                        break;
                    }
                }

                return [$box->id, $value];
            }, ' - ');
    }

    /**
     * Returns the processed partial config, cached.
     * @throws PartialNotFoundException
     */
    public function getPartial(): Partial
    {
        if ($this->parsedPartial) {
            return $this->parsedPartial;
        }

        return $this->parsedPartial = PartialReader::instance()->findByHandle($this->partial);
    }

    /**
     * Returns defined assets for this partial.
     *
     * @param string $type JS or CSS
     */
    public function getAssets(string $type)
    {
        $partial = $this->getPartial();

        return $partial?->config->assets[$type] ?? [];
    }

    /**
     * Returns defined components for this partial.
     */
    public function getComponents()
    {
        $partial = $this->getPartial();

        return $partial?->config->components ?? [];
    }

    /**
     * Add the spacing classes configured via the "spacing"
     * option in the YAML.
     *
     * @return string
     */
    public function getSpacingClassesAttribute()
    {
        $before = $this->data[self::SPACING_KEY_BEFORE] ?? '';
        $after = $this->data[self::SPACING_KEY_AFTER] ?? '';

        $spacings = config('offline.boxes::config.spacing', []);

        $spacingBefore = array_get($spacings, 'before.' . $before . '.class', '');
        $spacingAfter = array_get($spacings, 'after.' . $after . '.class', '');

        return implode(' ', [$spacingBefore, $spacingAfter]);
    }

    /**
     * Forward any attribute call to the `data` property,
     * if it contains the attribute as key.
     * @param mixed $key
     */
    public function getAttribute($key)
    {
        $data = $this->getDecodedData();

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return parent::getAttribute($key);
    }

    /**
     * Make sure the data attribute stores relation data correctly.
     * @param mixed $values
     */
    public function cleanupDataAttribute($values)
    {
        if (!is_array($values)) {
            return $values;
        }

        $attachments = $this->extractAttachmentRelations();
        $locallyStored = $this->extractLocalRelations();
        $externallyStored = $this->extractExternalRelations();

        $values = collect($values)
            // Remove attachments
            ->filter(fn ($value, $key) => !in_array($key, $attachments, true))
            // Move all externally stored relations to the model itself, so it is handled by the framework.
            ->filter(function ($value, $key) use ($externallyStored) {
                if (in_array($key, $externallyStored, true)) {
                    $this->$key = $value;

                    return false;
                }

                return true;
            })
            // Convert all locally stored relation names to a proper foreign key name.
            ->mapWithKeys(function ($value, $key) use ($locallyStored) {
                if (in_array($key, $locallyStored, true)) {
                    $definition = $this->getRelationDefinition($key);
                    $key = $definition['key'] ?? ($key . '_id');
                }

                return [$key => $value];
            });

        return $values->toArray();
    }

    /**
     * Returns all defined attachment relation names of the model.
     */
    public function extractAttachmentRelations()
    {
        return array_merge(
            array_keys($this->attachOne),
            array_keys($this->attachMany)
        );
    }

    /**
     * Returns all locally stored relation names of the model.
     */
    public function extractLocalRelations()
    {
        return array_merge(
            array_keys($this->belongsTo),
            array_keys($this->hasOne),
            array_keys($this->hasOneThrough),
        );
    }

    /**
     * Returns all externally stored relation names of the model.
     */
    public function extractExternalRelations()
    {
        return array_merge(
            array_keys($this->hasMany),
            array_keys($this->hasManyThrough),
            array_keys($this->belongsToMany),
        );
    }

    /**
     * Returns all relation names of the model.
     */
    public function extractRelations()
    {
        return array_merge(
            ...array_map(
                fn ($type) => array_keys($this->$type),
                static::$relationTypes
            )
        );
    }

    /**
     * Return the decoded Box data, cached.
     */
    public function getDecodedData(): array
    {
        $data = $this->attributes['data'] ?? [];

        if (!$data) {
            return [];
        }

        if ($this->decodedData) {
            return $this->decodedData;
        }

        try {
            $array = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            // Make sure data is not cached until the RainLab.Translate
            // integration is ready.
            if (!$this->translatableReady) {
                return $array;
            }

            return $this->decodedData = $array;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Create a copy of this box.
     */
    public function duplicate()
    {
        $clone = $this->replicateWithRelations();
        $clone->is_enabled = false;
        $clone->unique_id = '';
        $clone->useNestedTreeStructure = true;
        $clone->save();

        $clone->moveAfter($this);

        $this->children->each(function (self $child) use ($clone) {
            $child->duplicate()?->makeChildOf($clone);
        });

        return $clone;
    }

    /**
     * Returns a unique alias for a component when placed on this box.
     */
    public function uniqueComponentAlias(string $component): string
    {
        return $component . $this->unique_id;
    }

    /**
     * Set the $rules property if it is defined in the YAML config.
     */
    protected function setRulesFromPartial()
    {
        try {
            $partial = $this->getPartial();
        } catch (PartialNotFoundException $e) {
            // Exit here, the missing partial will be handled further up the stack.
            return;
        }

        if (!$partial->config->validation) {
            return;
        }

        $validation = (object)$partial->config->validation;

        $relationNames = $this->extractRelations();

        // Only add the `data` prefix to fields that are not a relation.
        $prefix = static fn ($attribute) => !in_array($attribute, $relationNames, true)
            ? 'data.' . $attribute
            : $attribute;

        if (property_exists($validation, 'rules')) {
            foreach ($validation->rules as $attribute => $rule) {
                if (is_array($rule)) {
                    $rule = implode('|', $rule);
                }

                $this->rules[$prefix($attribute)] = $rule;

                // Try to guess the attribute's name from the partial config.
                $name = array_get($this->getPartial()->config->form, "fields.{$attribute}.label", ucfirst($attribute));
                $this->attributeNames[$prefix($attribute)] = $name;
            }
        }

        if (property_exists($validation, 'attributeNames')) {
            foreach ($validation->attributeNames as $attribute => $name) {
                $this->attributeNames[$prefix($attribute)] = $name;
            }
        }

        if (property_exists($validation, 'customMessages')) {
            foreach ($validation->customMessages as $attribute => $name) {
                // Extract the attribute name from the rule (e.g. `image.required_if` => `image`).
                $parts = explode('.', $attribute);
                $field = $prefix($parts[0]);

                // Put the new key with the prefixed field together.
                $key = $field . '.' . implode('.', array_slice($parts, 1));

                $this->customMessages[$key] = $name;
            }
        }
    }

    /**
     * Set the $translatable property if it is defined in the YAML config.
     */
    protected function setTranslatableFromPartial()
    {
        try {
            $partial = $this->getPartial();
        } catch (PartialNotFoundException $e) {
            // Exit here, the missing partial will be handled further up the stack.
            return;
        }

        if ($partial?->config->translatable) {
            $this->translatable = $partial?->config->translatable;
        }

        // Restore RainLab.Translate's translated attributes (only for pre 2.0).
        if (class_exists(\RainLab\Translate\Models\Locale::class) && !App::runningInBackend()) {
            $obj = $this->translations->first(fn ($value) => $value->attributes['locale'] === Translator::instance()->getLocale(true));
            $result = $obj ? json_decode($obj->attribute_data, true) : [];

            $data = $this->getDecodedData();

            foreach ($result as $attribute => $value) {
                if ($value) {
                    $data[$attribute] = $value;
                }
            }

            $this->attributes['data'] = json_encode($data, JSON_THROW_ON_ERROR);
            $this->translatableReady = true;
        } else {
            $this->translatableReady = true;
        }
    }

    /**
     * Handle special partial definitions.
     */
    protected function handleSpecialPartials(): void
    {
    }

    /**
     * newNestedTreeQuery creates a new query for nested sets
     */
    protected function newNestedTreeQuery()
    {
        $query = $this->newQuery();

        // Scope the query only to the current holder's nodes.
        if ($this->exists && $this->holder_id) {
            $query
                ->where('holder_id', $this->holder_id)
                ->where('holder_type', $this->holder_type);
        }

        return $query;
    }
}

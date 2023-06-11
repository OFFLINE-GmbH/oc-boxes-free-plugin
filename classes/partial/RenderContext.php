<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Iterator;
use October\Rain\Support\Facades\Config;
use OFFLINE\Boxes\Classes\Traits\HasContext;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;

/**
 * RenderContext contains all contextual data that is passed to a
 * Box when it is rendered.
 */
class RenderContext implements Iterator
{
    use HasContext;

    /**
     * The loop context of a Box inside a Collection.
     *
     * @var array{
     *      length: int,
     *      index0: int,
     *      index: int,
     *      first: bool,
     *      last: bool,
     *      revindex0: int,
     *      revindex: int,
     *  }
     */
    public array $loop = [];

    /**
     * User defined values for this Box.
     */
    public object $data;

    /**
     * The Box model itself, that is being rendered.
     */
    public Box $box;

    /**
     * Determines if the Box is rendered in the Editor.
     */
    public bool $isEditor = false;

    /**
     * Determines if the Boxes scaffolding should be rendered around partial outputs.
     */
    public bool $renderScaffolding = true;

    /**
     * The Partial that is being rendered.
     */
    public ?Partial $partial;

    /**
     * The Box a partial is a stand-in for.
     */
    public ?Box $referenceFor = null;

    /**
     * The model this Box is attached to.
     */
    public Content|Page $model;

    private $iteratorPosition = 0;

    /**
     * Construct a new RenderContext from a plain array.
     */
    public static function fromArray(array $context): self
    {
        $ctx = new self();

        $ctx->isEditor = array_get($context, 'isEditor', false);
        $ctx->partial = array_get($context, 'partial');
        $ctx->referenceFor = array_get($context, 'referenceFor');
        $ctx->data = (object)array_get($context, 'data', []);
        $ctx->renderScaffolding = array_get($context, 'renderScaffolding', Config::get('offline.boxes::config.render_scaffolding', true));
        $ctx->loop = array_get($context, 'loop', $ctx->buildLoopHelper(0)(0));

        return $ctx;
    }

    public function current(): mixed
    {
        $key = $this->iterableKeys()[$this->iteratorPosition];

        return $this->{$key};
    }

    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    public function key(): mixed
    {
        return $this->iteratorPosition;
    }

    public function valid(): bool
    {
        $keys = $this->iterableKeys();

        if ($this->iteratorPosition >= count($keys)) {
            return false;
        }

        $key = $keys[$this->iteratorPosition];

        return isset($this->{$key});
    }

    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }

    private function iterableKeys(): array
    {
        return [
            'loop',
            'data',
            'isEditor',
            'partial',
        ];
    }
}

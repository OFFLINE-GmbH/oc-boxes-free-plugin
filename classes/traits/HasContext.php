<?php

namespace OFFLINE\Boxes\Classes\Traits;

use Closure;
use OFFLINE\Boxes\Classes\Partial\RenderContext;

trait HasContext
{
    /**
     * Makes sure the RenderContext is a RenderContext instance.
     */
    protected function wrapContext(RenderContext|array $context): RenderContext
    {
        return is_array($context) ? RenderContext::fromArray($context) : $context;
    }

    /**
     * Builds a helper function to build a Twig loop variable.
     */
    protected function buildLoopHelper(int $count): Closure
    {
        // This function emulates Twig's loop variable.
        return fn (int $index) => [
            'length' => $count,
            'index0' => $index,
            'index' => $index + 1,
            'revindex0' => $count - $index - 1,
            'revindex' => $count - $index,
            'first' => $index === 0,
            'last' => $index + 1 === $count,
        ];
    }
}

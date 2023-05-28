<?php

namespace OFFLINE\Boxes\Classes\Exceptions;

use Exception;

class PartialNotFoundException extends Exception
{
    public function __construct(string $handle)
    {
        parent::__construct(sprintf('Could not find partial with handle "%s".', $handle));
    }
}

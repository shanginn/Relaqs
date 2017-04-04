<?php

namespace Shanginn\Relaqs\Eloquent\Exceptions;

class TooMuchColumnDelimitersException extends \Exception
{
    public function __construct()
    {
        parent::__construct("Too much semicolons in the filter string.");
    }
}

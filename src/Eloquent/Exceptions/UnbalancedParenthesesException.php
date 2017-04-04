<?php

namespace Shanginn\Relaqs\Eloquent\Exceptions;

class UnbalancedParenthesesException extends \Exception
{
    public function __construct()
    {
        parent::__construct("You provided an unbalanced amount of parentheses in you filter query.");
    }
}

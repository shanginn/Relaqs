<?php

namespace Shanginn\Relaqs\Eloquent\Exceptions;

class FieldDoesNotExistsException extends \Exception
{
    public function __construct($field)
    {
        parent::__construct("Field '$field' in filter query doest not exists.");
    }
}

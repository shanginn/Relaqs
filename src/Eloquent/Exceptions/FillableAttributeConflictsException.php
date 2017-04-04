<?php

namespace Shanginn\Relaqs\Eloquent\Exceptions;

use RuntimeException;

class FillableAttributeConflictsException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  mixed  $model
     * @param  string  $relation
     * @return static
     */
    public static function make($model, $relation)
    {
        $class = get_class($model);

        return new static("
            Attribute '$relation' conflicts with the '$relation' relation in the $class class.
        
            You can't name attribute same as defined relation.
            If you need to define fillable relation, do in 
            the protected \$fillableRelations property.
        ");
    }
}

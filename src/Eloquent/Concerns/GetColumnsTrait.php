<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use Schema;

trait GetColumnsTrait
{
    /**
     * Available columns of the models.
     *
     * @var array
     */
    protected static $availableColumns = [];

    public static function getColumns()
    {
        return static::$availableColumns[static::class] ??
            (static::$availableColumns[static::class] = Schema::getColumnListing((new static)->getTable()));
    }
}
<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shanginn\Relaqs\Eloquent\Filters\NestedStringFilter;

/**
 * @mixin Model
 * @mixin GetColumnsTrait
 */
trait RelaqsScopes
{
    public static function addFieldsScope($fields)
    {
        $columns = array_values(array_intersect(static::getColumns(), $fields)) + ['id'];
        $with = array_values(array_intersect($fields, array_keys((new static)->getEagerLoads())));

        static::addGlobalScope(function (Builder $builder) use ($columns, $with) {
            $table = $builder->getQuery()->from;

            // Reset all other eager loadings
            $builder
                ->addSelect(array_map(function ($column) use ($table) {
                    return $table . '.' . $column;
                }, $columns))
                ->setEagerLoads([])
                ->with($with);
        });
    }

    public static function addOrderByScope($orders)
    {
        static::addGlobalScope(function (Builder $builder) use ($orders) {
            foreach ($orders as $column => $rules) {
                $builder->orderBy($column, $rules['direction']);
            }
        });
    }

    public static function addFiltersScope($filters)
    {
        static::addGlobalScope(function (Builder $builder) use ($filters) {
            (new NestedStringFilter($filters))->applyTo($builder);
        });
    }
}
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
        $model = new static;
        $columns = array_values(array_intersect(static::getColumns(), $fields)) + [$model->getKeyName()];
        $with = array_values(array_intersect($fields, array_keys($model->getEagerLoads())));

        static::addGlobalScope(function (Builder $builder) use ($columns, $with) {
            $table = $builder->getQuery()->from;

            // Reset all other eager loadings
            $builder
                ->addSelect(array_map(function ($column) use ($table) {
                    //return $table . '.' . $column;
                    return $column;
                }, $columns))
                ->setEagerLoads([])
                ->with($with);
        });
    }

    public static function addOrderByScope($orders)
    {
        $orders = array_intersect(static::getColumns(), array_keys($orders));

        static::addGlobalScope(function (Builder $builder) use ($orders) {
            foreach ($orders as $column => $rules) {
                $builder->orderBy($column, $rules['direction']);
            }
        });
    }

    public static function addFiltersScope($filterString, $fields = [])
    {
        static::addGlobalScope(function (Builder $builder) use ($filterString, $fields) {
            (new NestedStringFilter($filterString, $fields))->applyTo($builder);
        });
    }
}
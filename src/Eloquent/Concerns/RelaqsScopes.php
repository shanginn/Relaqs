<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shanginn\Relaqs\Eloquent\Filters\NestedStringFilter;
use Relaqser;

/**
 * @mixin Model
 * @mixin GetColumnsTrait
 */
trait RelaqsScopes
{
    public static function addFieldsScope($fields = null)
    {
        $model = new static;

        if (is_null($fields)) {
            if ($fields = Relaqser::getFieldsFromRequest()) {
                $fields = Relaqser::normalizeArray(array_keys($fields), $model);
            } else {
                return;
            }
        }

        $columns = array_values(array_intersect(static::getColumns(), $fields)) + [$model->getKeyName()];
        $with = array_values(array_intersect($fields, array_keys($model->getEagerLoads())));

        static::addGlobalScope(function (Builder $builder) use ($columns, $with) {
            $table = $builder->getQuery()->from;

            // Reset all other eager loadings
            $builder
                ->addSelect(array_map(function ($column) use ($table) {
                    //TODO: Ambiguous column if has orderBy
                    return $table . '.' . $column;
                    return $column;
                }, $columns))
                ->setEagerLoads([])
                ->with($with);
        });
    }

    public static function addOrderByScope($orders)
    {
        $orders = array_intersect_key($orders, array_flip(static::getColumns()));

        static::addGlobalScope(function (Builder $builder) use ($orders) {
            foreach ($orders as $column => $rules) {
                $builder->orderBy($column, $rules['direction']);
            }
        });
    }

    public static function addFiltersScope($filterString, $fields = [], $ignoreMissingFields = false)
    {
        static::addGlobalScope(function (Builder $builder) use ($filterString, $fields, $ignoreMissingFields) {
            (new NestedStringFilter($filterString, $fields, $ignoreMissingFields))->applyTo($builder);
        });
    }
}
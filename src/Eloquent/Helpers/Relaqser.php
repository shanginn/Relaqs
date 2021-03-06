<?php

namespace Shanginn\Relaqs\Eloquent\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Request;
use Shanginn\Relaqs\Eloquent\Concerns\RelaqsScopes;
use Shanginn\Relaqs\Eloquent\Interfaces\Filtratable;

class Relaqser
{
    /**
     * Converts attributes keys to snake_case
     * and keeps relations in snakeCase
     *
     * @param mixed $data
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return array
     */
    public static function normalizeArray($data, Model $model)
    {
        if (!is_array($data)) {
            return $data;
        }

        $relations = method_exists($model, 'getAvailableRelations') ?
            array_intersect_key($data, $model->getAvailableRelations()) : [];

        $attributes = array_diff_key($data, $relations);
        $result = [];

        // Convert attributes to snake_case first
        foreach ($attributes as $key => $value) {
            $result[Str::snake($key)] = config('relaqs.nullify_empty_strings') && $value === '' ? null : $value;
        }

        // Walk through relations and recursively
        // use this function on them
        foreach ($relations as $relation => $relationData) {
            /** @var Model $related */
            $related = $model->$relation()->getRelated();

            // Check if $relationData is list of related objects
            $relatedList = is_array($relationData) &&
                array_key_exists(0, $relationData);

            //dump($relation);
            $result[$relation] = is_null($relationData) ? null : (

            $relatedList ?

                // If this is array of related entities (not assoc. array)
                array_map(function ($relationship) use ($related) {
                    return static::normalizeArray($relationship, $related);
                }, $relationData) :

                // If this is array with entity attributes
                static::normalizeArray($relationData, $related))
            ;
        }

        return $result;
    }

    public static function getOrderByFieldsFromRequest()
    {
        if ($orderBy = Request::get('order')) {
            $lang = app()->getLocale();

            return array_reduce(
                explode(',', $orderBy),
                function ($result, $field) use ($lang) {
                    $value = [];

                    list($field, $value['direction']) = $field[0] === '-' ?
                        [substr($field, 1), 'desc'] : [$field, 'asc'];

                    list($field, $value['lang']) = strpos($field, '.') ?
                        explode('.', $field) : [$field, $lang];

                    $result[Str::snake($field)] = $value;

                    return $result;
                },
                []
            );
        }

        return [];
    }

    /**
     * @return array|null
     */
    public static function getFieldsFromRequest()
    {
        return ($fields = Request::get('fields', false)) ?
            array_reduce(explode(',', $fields), function ($result, $field) {
                $parts = explode('.', $field);

                // clean up before each pass
                $array = &$result;

                while ($part = array_shift($parts)) {
                    $array = &$array[$part];
                }

                return $result;
            }, []) : null;
    }

    /**
     * @return array|null
     */
    public static function getWithFromRequest()
    {
        return ($with = Request::get('with', false)) ?
            explode(' ', $with) : null;
    }

    public static function getFilterStringFromRequest()
    {
        return Request::get('filter', '');
    }

    /**
     * @param Filtratable $model
     * @param array $fields
     */
    public static function filtrate(Filtratable $model, array $fields)
    {
        if ($filters = Relaqser::getFilterStringFromRequest()) {
            $model::addFiltersScope($filters, $fields, Request::get('ignoreMissingFields', false));
        }
    }

    public static function getLimitFromRequest()
    {
        return (int) Request::get('limit', 10);
    }

    /**
     * @param Builder $query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public function paginate(Builder $query)
    {
        return ($limit = static::getLimitFromRequest()) !== 0 ?
            $query->paginate($limit) :
            $query->get();
    }

    public static function getTransformFromRequest()
    {
        return Request::get('transform', null);
    }

    /**
     * Converts array keys from snake_case to camelCase
     *
     * @param array $array
     * @return array
     */
    public static function camelizeArray(array $array)
    {
        return array_reduce(
            array_keys($array),
            function ($result, $key) use ($array) {
                $result[self::camelizeStr($key)] =
                    // Is value array-like?
                    (is_array($value = $array[$key]) || is_object($value) && $value = (array) $value) ?
                        self::camelizeArray($value) : $value;

                return $result;
            },
            []
        );
    }

    /**
     * Converts array keys from camelCase to snake_case
     *
     * @param array $array
     * @return array
     */
    public static function snakefyArray(array $array)
    {
        return array_reduce(
            array_keys($array),
            function ($result, $key) use ($array) {
                $result[Str::snake($key)] =
                    // Is value array-like?
                    (is_array($value = $array[$key]) || is_object($value) && $value = (array) $value) ?
                        self::snakefyArray($value) : $value;

                return $result;
            },
            []
        );
    }

    /**
     * Converts string from snake_case to camelCase
     *
     * @param string $key
     * @return string
     */
    public static function camelizeStr(string $key)
    {
        return lcfirst(implode('', array_map('ucfirst', explode('_', $key))));
    }

    /**
     * Add prefix to each keys of the array
     *
     * @param array $array
     * @param $prefix
     * @return array
     */
    public static function prefixKeys(array $array, $prefix)
    {
        return array_combine(array_map(function ($key) use ($prefix) {
            return $prefix . $key;
        }, array_keys($array)), array_values($array));
    }

    /**
     * @param array $array
     * @return array
     */
    public static function removeUnderscoredKeys(array $array)
    {
        return array_reduce(array_keys($array), function ($result, $key) use ($array) {
            if (is_array($value = $array[$key])) {
                $value = static::removeUnderscoredKeys($value);
            }

            if ($key[0] !== '_') {
                $result[$key] = $value;
            }

            return $result;
        }, []);
    }

    public static function getMorphByClass($class)
    {
        return array_flip(Relation::morphMap())[$class];
    }
}
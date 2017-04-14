<?php

namespace Shanginn\Relaqs\Eloquent\Helpers;

use Illuminate\Database\Eloquent\Model;

use Request;

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
            $result[snake_case($key)] = $value;
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

                    $result[snake_case($field)] = $value;

                    return $result;
                },
                []
            );
        }

        return false;
    }

    public static function getFieldsFromRequest()
    {
        return ($fields = Request::get('fields', false)) ?
            array_flip(explode(',', $fields)) : false;
    }

    public static function getFilterStringFromRequest()
    {
        return Request::get('filter', '');
    }

    public static function getLimitFromRequest()
    {
        return (int) Request::get('limit', 10);
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
                $result[self::camelizeStr($key)] = is_array($value = $array[$key]) ?
                    self::camelizeArray($value) : $value;
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
}
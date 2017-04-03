<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\Relation;

trait FillableRelations
{
    /**
     * Available relationships for the models.
     *
     * @var array
     */
    protected static $availableRelations = [];

    /**
     * Gets list of available relations for this model
     * And stores it in the variable for future use
     *
     * @return array
     */
    public static function getAvailableRelations()
    {
        return static::$availableRelations[static::class] ?? static::setAvailableRelations(
            array_reduce(
                (new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC),
                function ($result, ReflectionMethod $method) {
                    // If this function has a return type
                    ($returnType = (string) $method->getReturnType()) &&

                    // And this function returns a relation
                    is_subclass_of($returnType, Relation::class) &&

                    // Add name of this method to the relations array
                    ($result = array_merge($result, [$method->getName() => $returnType]));

                    return $result;
                }, []
            )
        );
    }

    /**
     * Stores relationships for future use
     *
     * @param array $relations
     * @return array
     */
    public static function setAvailableRelations(array $relations)
    {
        static::$availableRelations[static::class] = $relations;

        return $relations;
    }

    /* * */

    /**
     * Get the fillable relations for this model
     *
     * @return array
     */
    public function getFillableRelations()
    {
        return ($this->fillableRelations ?? ['*']) === ['*'] ?
            static::getAvailableRelations() : $this->fillableRelations;
    }

    /**
     * Get the fillable relations of a given array.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableRelationsFromArray(array $attributes)
    {
        return static::filterRelationsByNames($attributes, $this->getFillableRelations());
    }


    /* * */

    /**
     * Filters available relations by relation class
     *
     * @param string $class
     * @param array|null $relations
     * @return array
     */
    public static function filterRelationsByType(string $class, array $relations = null)
    {
        return array_keys(
            array_filter(
                ($relations ?? static::getAvailableRelations()),
                function ($relationClass) use ($class) {
                    return $relationClass === $class;
                }
            )
        );
    }

    /**
     * Filters relations by given names
     *
     * @param array $names
     * @param array|null $relations
     * @return array
     */
    public static function filterRelationsByNames(array $names, array $relations = null)
    {
        return array_intersect_key(
            $relations ?? static::getAvailableRelations(),
            $names
        );
    }
}
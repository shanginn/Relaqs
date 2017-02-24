<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\Relation;

trait GetRelationships
{
    /**
     * Available relationships for the model.
     *
     * @var array
     */
    protected $availableRelations;

    /**
     * Gets list of available relations for this model
     * And stores it in the variable for future use
     *
     * @return array
     */
    public function getAvailableRelations()
    {
        return $this->availableRelations ?? $this->setAvailableRelations(
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
    public function setAvailableRelations(array $relations)
    {
        $this->availableRelations = $relations;

        return $relations;
    }

    public function filterRelationsByType(string $filterClass, array $availableRelations = null)
    {
        return array_keys(
            array_filter(
                ($availableRelations ?? $this->getAvailableRelations()),
                function ($relationshipClass) use ($filterClass) {
                    return $relationshipClass === $filterClass;
                }
            )
        );
    }

    public function filterRelationsByNames(array $names, array $availableRelations = null)
    {
        return array_intersect_key(
            $availableRelations ?? $this->getAvailableRelations(),
            $names
        );
    }
}
<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @mixin Model
 */
trait RelationshipsHandler
{
    protected static $relationsOrder = [
        BelongsToMany::class,
        HasManyThrough::class,
        BelongsTo::class,
        HasMany::class,
        HasOne::class,
    ];

    protected function toOrderedRelationsWithHandlers(array $relations)
    {
        return call_user_func_array(
            'array_merge',
            array_reduce(array_keys($relations), function ($handlers, $relation) use ($relations) {
                if ($handler = $this->getHandlerForRelation($relation)) {
                    $handlers[$relations[$relation]][$relation] = $handler;
                }

                return $handlers;
            }, array_fill_keys(static::$relationsOrder, []))
        );
    }

    /**
     * @param $relation
     *
     * @return bool|string
     */
    protected function getHandlerForRelation($relation)
    {
        /** @var Relation $related */
        $related = $this->$relation();

        return
            // First we will try to find specific handler for this relation.
            // For relation defined by function named 'city' we will look
            // for method named handleCityRelationship()
           $this->getRelationHandlerByRelationName($relation)

           // Then we will look for handler, specified for the related model
           // If our 'city' relation refers to 'City' model, we will look
           // for method named handleRelatedCity()
           ?? $this->getRelationHandlerByRelatedModelClass($related->getRelated())

           // Lastly we will look for generic handler for this relation type
           // If the 'city' relation is 'HasMany' relation, we will look
           // for method named handleHasMany()
           ?? $this->getRelationHandlerByRelationClass(get_class($related));
    }

    /**
     * @param $relationName
     *
     * @return bool|string
     */
    private function getRelationHandlerByRelationName($relationName)
    {
        return method_exists($this, $relationHandler = 'handle' . ucfirst($relationName) . 'Relationship')
            ? $relationHandler : null;
    }

    /**
     * @param $relatedModelClass
     *
     * @return bool|string
     */
    private function getRelationHandlerByRelatedModelClass($relatedModelClass)
    {
        return method_exists($this, $relationHandler = 'handleRelated' . class_basename($relatedModelClass))
            ? $relationHandler : null;
    }

    /**
     * @param $relationClass
     *
     * @return bool|string
     */
    private function getRelationHandlerByRelationClass($relationClass)
    {
        return method_exists($this, $relationHandler = 'handle' . class_basename($relationClass))
            ? $relationHandler : null;
    }

    protected function handleRelationship($relationHandler, $relation, $relationData, &$attributes)
    {
        //dump($relationData);
        // Check if this relationship has been created before in this request
        // And linked with UUID
        ($uuid = $relationData['uuid'] ?? false)

            // Attempt to get created relationship by UUID
            && ($relationship = $this->getCreatedRelationshipByUUID($uuid))

                // Insert key of the related model into attributes
                // and remove relationship itself
                && $this->swapRelationshipToKey($relation, $relationship, $attributes)

        // Create relationship if it does not exists already
        || $this->addCreatedRelationships($this->$relationHandler($relationData, $relation));
    }

    /**
     * Change relationship to it's key in the attributes
     *
     * @param string $relation
     * @param Model $relationship
     * @param array $attributes
     *
     * @return bool
     */
    protected function swapRelationshipToKey(string $relation, Model $relationship, array &$attributes)
    {
        if ($relationship->getKey() && $attributes[$relationship->getForeignKey()] = $relationship->getKey()) {
            unset($attributes[$relation]);

            return true;
        }

        return false;
    }

    /**
     * @param array|int  $relationData
     * @param string $relation
     *
     * @return array
     */
    protected function handleBelongsTo($relationData, $relation)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->createRelatedModel($relation, $relationData);

        /** @var BelongsTo $related */
        $related = $this->$relation();
        $related->associate($relatedModel);

        return $this->storeRelationship($relationData, $relatedModel);
    }

    /**
     * @param array|int  $relationData
     * @param string $relation
     *
     * @return array
     */
    protected function handleHasOne($relationData, $relation)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->getRelatedModel($relation, $relationData);

        $this->setRelation($relation, $relatedModel);

        return $this->storeRelationship($relationData, $this);
    }


    /**
     * @param array|int  $relationData
     * @param string $relation
     *
     * @return array
     */
    protected function handleHasMany($relationData, $relation)
    {
        $createdRelationships = [];

        $this->setRelation($relation, array_map(function ($data) use ($relation, $createdRelationships) {
            /** @var Model $relatedModel */
            $relatedModel = $this->getRelatedModel($relation, $data);

            $this->storeRelationship($data, $relatedModel, $createdRelationships);

            return $relatedModel;
        }, $relationData));

        return $createdRelationships;
    }

    /**
     * @param array|int  $relationData
     * @param string $relation
     *
     * @return array
     */
    protected function handleHasManyThrough($relationData, $relation)
    {
        $createdRelationships = [];

        foreach ($relationData as $data) {
            /** @var Model $relatedModel */
            $relatedModel = $this->createRelatedModel($relation, $data);

            $this->storeRelationship($data, $relatedModel, $createdRelationships);
        }

        return $createdRelationships;
    }

    /**
     * @param array|int  $relationData
     * @param string $relation
     *
     * @return array
     */
    protected function handleBelongsToMany($relationData, $relation)
    {
        //dd($relationData)
        $createdRelationships = [];

        $this->setRelation($relation, array_map(function ($data) use ($relation, $createdRelationships) {
            /** @var Model $relatedModel */
            $relatedModel = $this->createRelatedModel($relation, $data);

            if (is_array($data)) {
                $this->storeRelationship($data, $relatedModel, $createdRelationships);
            }

            return $relatedModel;
        }, $relationData));

        return $createdRelationships;
    }

    protected function addCreatedRelationships(array $relationships)
    {
        foreach ($relationships as $uuid => $relatedModel) {
            static::$createdRelationships[$uuid] = $relatedModel;
        }
    }

    /**
     * If new relationship has UUID we need to
     * store it
     *
     * @param array $data
     * @param Model $model
     * @param array $storage
     *
     * @return array
     */
    protected function storeRelationship($data, Model &$model, array &$storage = [])
    {
        if (is_array($data) && array_key_exists('uuid', $data)) {
            $storage[$data['uuid']] = $model;
        }

        return $storage;
    }

    /**
     * @return array
     */
    protected function getCreatedRelationships()
    {
        return static::$createdRelationships;
    }

    /**
     * Search through created in the same request models
     *
     * @param $uuid
     *
     * @return Model|null
     */
    protected function getCreatedRelationshipByUUID($uuid)
    {
        return $this->getCreatedRelationships()[$uuid] ?? null;
    }

    /**
     * Find existing or create new related model
     *
     * @param string $relation
     * @param array|int $attributes
     *
     * @return Model
     */
    public function getRelatedModel(string $relation, $attributes = [])
    {
        /** @var Model $related */
        $related = $this->$relation()->getRelated();

        $relatedKey = is_int($attributes) ?
            $attributes : $attributes[$related->getKeyName()]
                ?? false;

        return ($relatedKey && $model = $related->findOrFail($relatedKey)) ?
            (is_array($attributes) ? $model->fill($attributes) : $model) :
            $this->newRelatedModel($relation, $attributes);
    }

    /**
     * Create and save new related model
     *
     * @param string $relation
     * @param array|int $attributes
     *
     * @return Model
     */
    public function createRelatedModel(string $relation, $attributes = [])
    {
        return tap($this->getRelatedModel($relation, $attributes), function (Model $related) {
            $related->save();
        });
    }

    /**
     * Create new relationship instance
     * And fill it with attributes
     *
     * @param string $relation
     * @param array $attributes
     *
     * @return Model
     */
    public function newRelatedModel(string $relation, array $attributes = [])
    {
        /** @var Relation $relation */
        $relation = $this->$relation();

        return $relation->getRelated()->newInstance()->fill($attributes);
    }
}
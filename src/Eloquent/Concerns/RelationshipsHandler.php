<?php

namespace Shanginn\Relaqs\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait RelationshipsHandler
{


    /**
     * @param $relationType
     *
     * @return bool|string
     */
    protected function getRelationHandlerName($relationType)
    {
        return method_exists($this, $relationHandler = 'handle' . $relationType)
            ? $relationHandler : false;
    }

    protected function handleRelationship($relationHandler, $relation, $relationData, &$attributes)
    {
        // Check if this relationship has been created before
        // And linked with UUID
        ($uuid = $relationData['uuid'] ?? false) &&

        // Attempt to get created relationship by UUID
        ($relationship = $this->getCreatedRelationshipByUUID($uuid)) &&

        // Insert key of the related model into attributes
        // and remove relationship itself
        $this->swapRelationshipToKey($relation, $relationship, $attributes) ||

        // Create relationship if it does not exists already
        $this->$relationHandler($relation, $relationData);
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
     * @param string $relation
     * @param array $relationData
     */
    protected function handleBelongsTo($relation, array $relationData)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->createRelatedModel($relation, $relationData);

        /** @var BelongsTo $related */
        $related = $this->$relation();

        $related->associate($relatedModel);

        $this->addCreatedRelationship($relationData, $relatedModel);
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleHasOne($relation, array $relationData)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->getRelatedModel($relation, $relationData);

        $this->setRelation($relation, $relatedModel);

        $this->addCreatedRelationship($relationData, $this);
    }


    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleHasMany($relation, array $relationData)
    {
        $this->setRelation($relation, array_map(function ($data) use ($relation) {
            /** @var Model $relatedModel */
            $relatedModel = $this->getRelatedModel($relation, $data);

            if (is_array($data)) {
                $this->addCreatedRelationship($data, $relatedModel);
            }

            return $relatedModel;
        }, $relationData));
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleHasManyThrough($relation, array $relationData)
    {
        foreach ($relationData as $data) {
            /** @var Model $relatedModel */
            $relatedModel = $this->createRelatedModel($relation, $data);

            $this->addCreatedRelationship($data, $relatedModel);
        }
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleBelongsToMany($relation, array $relationData)
    {
        $this->setRelation($relation, array_map(function ($data) use ($relation) {
            /** @var Model $relatedModel */
            $relatedModel = $this->createRelatedModel($relation, $data);

            if (is_array($data)) {
                $this->addCreatedRelationship($data, $relatedModel);
            }

            return $relatedModel;
        }, $relationData));
    }

    /**
     * If new relationship has UUID we need to
     * store it
     *
     * @param array $data
     * @param Model $model
     */
    protected function addCreatedRelationship(array $data, Model &$model)
    {
        if (isset($data['uuid'])) {
            static::$createdRelationships[$data['uuid']] = $model;
        }
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
            $attributes : $attributes[$related->getKeyName()] ?? false;

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
            (!$related->exists || $related->isDirty()) && $related->save();
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
<?php

namespace Shanginn\Relaqs\Eloquent;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;

//TODO:
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

use Shanginn\Relaqs\Eloquent\Concerns\FillableRelations;

/**
 * @mixin Model
 */
trait Relaqs
{
    use FillableRelations;

    protected static $orderedRelations = [
        BelongsTo::class,
        HasManyThrough::class,
        HasMany::class,
        HasOne::class,
        BelongsToMany::class
    ];

    /**
     * The array of uuids of the created models.
     *
     * @var array
     */
    protected static $createdRelationships = [];

    /**
     * The relations that are mass assignable.
     *
     * @var array
     *
     protected $fillableRelations = ['*'];
     */

    static function bootRelaqs()
    {
        static::registerModelEvent('booted', function(Model $model) {
            /** $model @mixin FillableRelations */
            if (static::filterRelationsByNames(
                $model->getFillable(), $model->getFillableRelations()
            )) {
                throw new Exceptions\FillableAttributeConflictsException;
            }
        });

        static::saved(function (Model $model) {
            dump($model->relations);
            $newRelationships = $model->filterRelationsByNames(
                $model->getAvailableRelations(),
                $model->relations
            );

            foreach ($newRelationships as $relation => $relationship) {
                /** @var Relation $related */
                $related = $model->$relation();

                switch (get_class($related)) {
                    case HasOne::class:
                        $method = 'save';
                        break;
                    case HasMany::class:
                    case BelongsToMany::class:
                        $method = 'saveMany';
                        break;
                    case BelongsTo::class:
                        $method = 'associate';
                        break;
                    default:
                        $method = null;
                }

                if (method_exists($related, $method)) {
                    $related->$method($relationship);
                } else {
                    dd('FATALISHE');
                }
            }
        });
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        // Available relations in the given attributes
        if ($fillableRelations = $this->fillableRelationsFromArray($attributes)) {
            // We need to create relationships in strict order
            foreach (static::$orderedRelations as $relationClass) {
                // Check this Relation type for the handler presence
                ($relationHandler = $this->getRelationHandler($relationClass)) &&

                // Perform relation creating class by class
                ($relations = $this->filterRelationsByType($relationClass, $fillableRelations)) &&

                // Apply handler to each relation
                array_walk($relations, function ($relation) use ($relationHandler, &$attributes) {
                    ($relationData = $attributes[$relation] ?? false) &&
                    $this->handleRelationship($relationHandler, $relation, $relationData, $attributes);
                });
            }
        }

        return parent::fill($attributes);
    }

    protected function getRelationHandler($relationClass)
    {
        return method_exists($this, $relationHandler = 'handle' . class_basename($relationClass)) ? $relationHandler : false;
    }

    protected function handleRelationship($relationHandler, $relation, $relationData, &$attributes)
    {
        ($uuid = $relationData['uuid'] ?? false) &&
        ($relationship = $this->getCreatedRelationshipByUUID($uuid)) &&
        ($attributes[$relationship->getForeignKey()] = $relationship->getKey()) &&
        (($attributes[$relation] = null) || true) ||
        $this->$relationHandler($relation, $relationData);
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleBelongsTo($relation, array $relationData)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->createRelatedModel($relation, $relationData);

        /** @var BelongsTo $relationship */
        $relationship = $this->$relation();

        $relationship->associate($relatedModel);

        $this->addCreatedRelationship($relationData, $relatedModel);
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleHasOne($relation, array $relationData)
    {
        /** @var Model $relatedModel */
        $relatedModel = $this->createRelatedModel($relation, $relationData);

        $this->setRelation($relation, $relatedModel);

        $this->addCreatedRelationship($relationData, $this);
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
    protected function handleHasMany($relation, array $relationData)
    {
        foreach ($relationData as $data) {
            /** @var Model $relatedModel */
            $relatedModel = $this->newRelatedModel($relation, $data);

            $this->$relation->add($relatedModel);

            $this->addCreatedRelationship($data, $relatedModel);
        }
    }

    /**
     * @param string $relation
     * @param array $relationData
     */
    protected function handleBelongsToMany($relation, array $relationData)
    {
        $this->handleHasMany($relation, $relationData);
    }

    protected function addCreatedRelationship(array $data, Model $model)
    {
        if (isset($data['uuid'])) {
            static::$createdRelationships[$data['uuid']] = $model;
        }
    }

    protected function getCreatedRelationships()
    {
        return static::$createdRelationships;
    }

    protected function getCreatedRelationshipByUUID($uuid)
    {
        return $this->getCreatedRelationships()[$uuid] ?? null;
    }

    public function createRelatedModel(string $relation, array $attributes = [])
    {
        return tap($this->newRelatedModel($relation, $attributes),
            function(Model $related) {
                $related->save();
            }
        );
    }

    public function newRelatedModel(string $relation, array $attributes = [])
    {
        return $this->$relation()->getRelated()->newInstance()->fill($attributes);
    }

    //---
    //***
    //---

    protected function createHasManyRelationships($data, Model &$model)
    {
        foreach (static::getAvailableRelations() as $relation => $relationClass) {
            $relationSnake = Str::snake($relation);

            if ($relationClass === HasMany::class && isset($data[$relationSnake])) {
                $relatedData = $data[$relationSnake];
                /** @var HasMany $relationship */
                $relationship = $model->$relation();

                $relationship->create($relatedData);
            }
        }
    }

    protected function createBelongsToRelationships($data, Model &$model)
    {
        // If this model has BelongsTo relations
        // We need to create parent instances first
        foreach (static::getAvailableRelations() as $relation => $relationClass) {
            $relationSnake = Str::snake($relation);
            if ($relationClass === BelongsTo::class && isset($data[$relationSnake])) {
                $relatedData = $data[$relationSnake];

                /** @var BelongsTo $relationship */
                $relationship = $model->$relation();

                $related = $relationship->getRelated();

                // Assume that this is a new relationship
                // and create it
                if (is_array($relatedData)) {
                    $related->fill($relatedData)->save();
                }
                // Or assume that we have existing relationship
                else {
                    $related->load($relatedData);
                }

                $relationship->associate($related);
            }
        }
    }
}
<?php

namespace Shanginn\Relaqs\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//TODO:
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

use Shanginn\Relaqs\Eloquent\Concerns\FillableRelations;
use Shanginn\Relaqs\Eloquent\Concerns\GetColumnsTrait;
use Shanginn\Relaqs\Eloquent\Concerns\RelaqsScopes;
use Shanginn\Relaqs\Eloquent\Concerns\RelationshipsHandler;

/**
 * @mixin Model
 */
trait Relaqs
{
    use FillableRelations;
    use GetColumnsTrait;
    use RelationshipsHandler;
    use RelaqsScopes;

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
        static::registerModelEvent('booted', function (Model $model) {
            /** @var Relaqs $model */
            if (static::filterRelationsByNames(
                $model->getFillable(),
                $model->getFillableRelations()
            )) {
                throw new Exceptions\FillableAttributeConflictsException;
            }
        });

        static::saved(function (Model $model) {
            /** @var Relaqs $model */
            $newRelationships = $model->filterRelationsByNames(
                $model->getAvailableRelations(),
                $model->relations
            );

            foreach ($newRelationships as $relation => $relationship) {
                if (is_null($relationship)) {
                    continue;
                }

                /** @var Model|array $relationship */
                switch (get_class($related = $model->$relation())) {
                    case HasOne::class:
                        /** @var HasOne $related */
                        $related->save($relationship);
                        break;
                    case HasMany::class:
                        /** @var HasMany $related */
                        if (is_array($relationship)) {
                            $nullable = ! \DB::getDoctrineSchemaManager()
                                ->listTableDetails($related->getRelated()->getTable())
                                ->getColumn($related->getForeignKeyName())
                                ->getNotnull();

                            $relatedIds = collect($relationship)->pluck('id')->toArray();

                            foreach ($related->get() as $relatedModel) {
                                if (!in_array($relatedModel->id, $relatedIds)) {
                                    /** @var Model $relatedModel */
                                    if (!$nullable) {
                                        $relatedModel->delete();
                                    } else {
                                        $relatedModel->setAttribute(
                                            $related->getForeignKeyName(),
                                            null
                                        )->save();
                                    }
                                }
                            }

                            //$model->setRelation($relation, null);
                            $related->saveMany($relationship);
                        }

                        break;
                    case BelongsTo::class:
                        /** @var BelongsTo $related */
                        $related->associate($relationship);
                        if (!is_array($relationship) && !$relationship->exists) {
                            $relationship->save();
                        }
                        break;
                    case BelongsToMany::class:
                        /** @var BelongsToMany $related */
                        if (is_array($relationship)) {
                            $related->sync(array_map(function (Model $model) {
                                return $model->getKey();
                            }, $relationship));
                        }

                        break;
                }
            }

            $model->load(array_keys($newRelationships));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function fill(array $attributes)
    {
        // Available relations in the given attributes
        if ($fillableRelations = $this->fillableRelationsFromArray($attributes)) {
            // We need to create relationships in strict order
            $relations = $this->toOrderedRelationsWithHandlers($fillableRelations);

            foreach ($relations as $relation => $handler) {
                if (array_key_exists($relation, $attributes)) {
                    $this->handleRelationship($handler, $relation, $attributes[$relation], $attributes);
                }
            }
        }

        return parent::fill($attributes);
    }
}
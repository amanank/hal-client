<?php

namespace Amanank\HalClient\Models;

use Illuminate\Database\Eloquent\Relations\Relation;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class HalHasMany extends Relation {
    protected $entity;
    protected $related;
    protected $link;
    protected $relationsName;
    protected $cachedResults = null;

    public function __construct($entity, $related, $link, $relationsName) {
        $this->entity = $entity;
        $this->related = $related;
        $this->link = $link;
        $this->relationsName = $relationsName;
    }

    public function getResults() {
        if (!$this->link) {
            return collect();
        }
        
        // Return cached results if available
        if ($this->cachedResults !== null) {
            return $this->cachedResults;
        }
        
        try {
            $response = $this->entity->getConnection()->get($this->link);
            $response = json_decode($response->getBody(), true);

            $relatedModel = new $this->related();
            $embeddedKey = $this->resolveEndpoint($relatedModel)
                ?? $this->resolveEndpoint($this->entity)
                ?? basename($this->link);

            $entities = $response["_embedded"][$embeddedKey] ?? [];

            Log::debug('HalHasMany getResults', [
                'link' => $this->link,
                'embedded_keys' => array_keys($response['_embedded'] ?? []),
                'embedded_key_used' => $embeddedKey,
                'count' => is_array($entities) ? count($entities) : 0,
            ]);

            $this->cachedResults = collect($entities)->map(function ($item) use ($relatedModel) {
                $model = clone $relatedModel;
                $model->setRawAttributes((array) $item, true);
                $model->exists = true;
                return $model;
            });
            
            return $this->cachedResults;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return collect();
            }
            throw $e;
        }
    }

    /**
     * Get a query builder for this relation.
     * Filament needs this to return a Builder-like object.
     */
    public function getQuery() {
        // Return a builder wrapper that Filament can use
        return new FilamentHalQueryBuilder($this);
    }

    /**
     * Execute the query as a Collection.
     * Used by Filament's RelationManager to get the related records.
     */
    public function get($columns = ['*']) {
        return $this->getResults();
    }

    public function associate($model) {
        if (is_null($model)) {
            Log::error('Attempted to associate a null model.');
            throw new \InvalidArgumentException('Cannot associate a null model.');
        }

        if (!$model instanceof $this->related) {
            Log::error('Associate must be an instance of ' . $this->related);
            throw new \InvalidArgumentException('Associate must be an instance of ' . $this->related);
        }

        // Add the model to the entitie's relation
        $relations = $this->entity->{$this->relationsName};
        $relations->push($model);

        // Mark the entity as dirty
        $this->entity->setAttribute($this->relationsName, $relations);

        return $this->entity;
    }

    public function save($model) {

        if (!is_null($model) && $model instanceof Model && !$model->exists) {
            $model->save();
        }

        $this->associate($model);

        return $model;
    }

    public function saveMany(array $models) {
        foreach ($models as $model) {
            $this->save($model);
        }
        return $models;
    }

    public function attach($id) {
        $this->associate($this->related::find($id));
    }

    public function attachMany($ids) {
        foreach ($ids as $id) {
            $this->attach($id);
        }
    }

    public function detach($id = null) {
        if (is_null($id)) {
            $this->entity->setAttribute($this->relationsName, collect());
        } else {
            $this->entity->setAttribute($this->relationsName, $this->entity->{$this->relationsName}->filter(fn($model) => !$model->hasId($id)));
        }
        return $this->entity;
    }

    public function addConstraints() {
        // No constraints for HAL relations
    }

    public function addEagerConstraints(array $models) {
        // No eager constraints for HAL relations
    }

    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, collect());
        }
        return $models;
    }

    public function match(array $models, $results, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, $results);
        }
        return $models;
    }

    protected function resolveEndpoint($model): ?string
    {
        if (! $model) {
            return null;
        }

        if (! property_exists($model, '_endpoint')) {
            return null;
        }

        try {
            $ref = new \ReflectionProperty($model, '_endpoint');
            $ref->setAccessible(true);
            return $ref->getValue($model);
        } catch (\ReflectionException $e) {
            return null;
        }
    }
}


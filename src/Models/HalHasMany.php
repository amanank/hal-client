<?php

namespace Amanank\HalClient\Models;

use Illuminate\Database\Eloquent\Relations\Relation;
use GuzzleHttp\Exception\RequestException;

class HalHasMany extends Relation {
    protected $entity;
    protected $related;
    protected $link;
    protected $relationsName;

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
        try {
            $response = $this->entity->getConnection()->get($this->link);
            $embededProperty = basename($this->link);

            $response = json_decode($response->getBody(), true);

            $entities = $response["_embedded"][$embededProperty];

            $collection = collect($entities)->map(function ($item) {
                $model = new $this->related();
                $model->setRawAttributes((array) $item, true);
                return $model;
            });
            echo "HalHasMany Found related models: " . $this->related . " " . $collection->count() . "\n";
            return $collection;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
    }

    public function associate($model) {
        if (is_null($model)) {
            \Log::error('Attempted to associate a null model.');
            throw new \InvalidArgumentException('Cannot associate a null model.');
        }

        if (!$model instanceof $this->related) {
            \Log::error('Associate must be an instance of ' . $this->related);
            throw new \InvalidArgumentException('Associate must be an instance of ' . $this->related);
        }

        if (!$model->exists) {
            \Log::error('Attempted to associate a model that has not been saved.');
            throw new \InvalidArgumentException('Cannot associate a model that has not been saved.');
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
        throw new \Exception('Constraints not supported for HalHasMany relation');
    }

    public function addEagerConstraints(array $models) {
        throw new \Exception('Eager constraints not supported for HalHasMany relation');
    }

    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }
        return $models;
    }

    public function match(array $models, $results, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, $results);
        }
        return $models;
    }
}

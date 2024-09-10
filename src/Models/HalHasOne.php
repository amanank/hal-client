<?php

namespace Amanank\HalClient\Models;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class HalHasOne extends BelongsTo {
    protected $entity;
    protected $related;
    protected $link;
    protected $relationsName;

    public function __construct($entity, $related, $link, $relationsName) {
        $this->entity = $entity;
        $this->related = new $related();
        $this->link = $link;
        $this->relationsName = $relationsName;
    }

    public function getResults() {
        if (!$this->link) {
            return null;
        }
        try {
            $this->related->setRawAttributes($this->entity->getConnection()->getData($this->link), true);
            return $this->related;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Associate the model instance to the given entity.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate($model) {
        if (is_null($model)) {
            Log::error('Attempted to associate a null model.');
            throw new \InvalidArgumentException('Cannot associate a null model.');
        }

        if (!$model instanceof Model) {
            Log::error('Associate must be an instance of ' . Model::class);
            throw new \InvalidArgumentException('Associate must be an instance of ' . Model::class);
        }

        if (!$model->exists) {
            Log::error('Attempted to associate a model that has not been saved.');
            throw new \InvalidArgumentException('Cannot associate a model that has not been saved.');
        }

        // Set the related model
        $this->related = $model;

        //$ownerKey = $model instanceof Model ? $model->getSelfLink() : $model;
        $this->entity->setAttribute($this->relationsName, $model);

        // Set or unset the relation on the entity model
        if ($model instanceof Model) {
            $this->entity->setRelation($this->relationName, $model);
        } else {
            $this->entity->unsetRelation($this->relationName);
        }

        return $this->entity;
    }

    public function addConstraints() {
        throw new \Exception('Constraints not supported for HalHasOne relation');
    }

    public function addEagerConstraints(array $models) {
        throw new \Exception('Eager constraints not supported for HalHasOne relation');
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

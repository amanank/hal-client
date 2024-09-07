<?php

namespace Amanank\HalClient\Models;

use Illuminate\Database\Eloquent\Relations\Relation;
use GuzzleHttp\Exception\RequestException;

class HalHasMany extends Relation {
    protected $parent;
    protected $related;
    protected $link;

    public function __construct($parent, $related, $link) {
        $this->parent = $parent;
        $this->related = new $related();
        $this->link = $link;
    }

    public function getResults() {
        if (!$this->link) {
            return collect();
        }
        try {
            $response = $this->parent->getConnection()->get($this->link);
            $embededProperty = basename($this->link);

            $response = json_decode($response->getBody(), true);

            $entities = $response["_embedded"][$embededProperty];

            $collection = collect($entities)->map(function ($item) {
                $model = clone $this->related;
                $model->setRawAttributes((array) $item, true);
                return $model;
            });
            return $collection;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
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

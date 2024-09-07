<?php

namespace Amanank\HalClient\Models;

use Illuminate\Database\Eloquent\Relations\Relation;
use GuzzleHttp\Exception\RequestException;

class HalHasOne extends Relation {
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
            return null;
        }
        try {
            $response = $this->parent->getConnection()->get($this->link);
            $attributes = json_decode($response->getBody()->getContents(), true);
            $this->related->setRawAttributes((array) $attributes, true);
            return $this->related;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
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

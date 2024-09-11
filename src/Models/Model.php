<?php

namespace Amanank\HalClient\Models;


use Illuminate\Database\Eloquent\Model as EloquentModel;

use Amanank\HalClient\Client;
use Amanank\HalClient\Exceptions\ConstraintViolationException;
use Amanank\HalClient\Exceptions\ModelNotFoundException;
use Amanank\HalClient\Query\QueryBuilder;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Model extends EloquentModel {

    protected $_endpoint;
    protected $client;

    /**
     * This is needed to prevent eloquent model from trying to conigure the connection
     */
    public function getConnectionName() {
        return 'hal';
    }

    public function setConnection($client) {
        $this->client = $client;
    }

    public function getConnection(): Client {
        if (!$this->client) {
            $this->client = app(Client::class);
        }

        return $this->client;
    }

    public function getId() {
        return $this->getIdFromLink($this->getLinkHref('self'));
    }

    public function hasId($id) {
        return $this->getId() == $this->getIdFromLink($id);
    }

    public function getLink() {
        return "{$this->_endpoint}/{$this->getId()}";
    }

    protected function getLinkHref($rel) {
        return isset($this->attributes['_links'][$rel]) ? $this->attributes['_links'][$rel]['href'] : null;
    }

    public function hasOne($related, $property = null, $localKey = null) {
        $link = $this->getLinkHref($property);
        return new HalHasOne($this, $related, $link, $property);
    }

    public function hasMany($related, $property = null, $localKey = null) {
        $link = $this->getLinkHref($property);
        return new HalHasMany($this, $related, $link, $property);
    }


    public function getAttributesForSave() {
        $attributes = $this->getAttributes();
        unset($attributes['_links']);
        foreach ($attributes as $key => $value) {
            if (is_object($value) && method_exists($value, 'getLink')) {
                $attributes[$key] = $value->getLink();
            } elseif ($value instanceof Collection) {
                $attributes[$key] = $value->map(fn($item) => $item->getLink())->toArray();
            }
        }

        return $attributes;
    }

    protected function clearRelationCache() {
        foreach ($this->getRelations() as $relationName => $relation) {
            $this->unsetRelation($relationName);
        }
    }

    protected function performUpdate($query): bool {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {

            $this->getConnection()->update($this->getLinkHref('self'), $this->getAttributesForSave());

            $this->syncChanges();

            $this->clearRelationCache();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    public function refresh() {
        if (! $this->exists) {
            return $this;
        }

        $this->setRawAttributes(
            $this->getConnection()->getData($this->getLinkHref('self'))
        );

        //TODO: refresh relations

        $this->syncOriginal();

        return $this;
    }

    protected function performInsert($query): bool {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $selfHref = $this->getConnection()->create($this->_endpoint, $this->getAttributesForSave());

        $this->attributes['_links']['self']['href'] = $selfHref;

        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->clearRelationCache();

        $this->fireModelEvent('created', false);

        return true;
    }

    public function save(array $options = []) {
        try {
            return parent::save($options);
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 409) {
                throw (new ConstraintViolationException("Constraint violation", 409, $e))->setModel(
                    get_class($this),
                    $this->exists ? $this->getLink() : null
                );
            }
            throw $e;
        }
    }

    protected function performDeleteOnModel() {
        try {
            $this->getConnection()->remove($this->getLinkHref('self'));
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                throw (new ModelNotFoundException("Model not found", 404, $e))->setModel(
                    get_class($this),
                    $this->getLink()
                );
            }
            throw $e;
        }

        unset($this->attributes['_links']['self']);
        $this->exists = false;
    }

    protected function getIdFromLink($link) {
        if (is_numeric($link)) {
            return $link;
        }

        $parts = explode('/', $link);

        //check 2nd last part matches $this->_endpoint
        if (count($parts) < 2 || $parts[count($parts) - 2] != $this->_endpoint) {
            throw new \Exception("Link does not match this entity endpoint expected {$this->_endpoint} got {$parts[count($parts) - 2]}");
        }

        return end($parts);
    }

    public static function findOrFail($id) {
        $model = new static();
        $id = $model->getIdFromLink($id);
        try {
            $attributes = $model->getConnection()->getJson($model->_endpoint . "/{$id}");

            $model->setRawAttributes((array) $attributes, true);
            $model->exists = true;

            $model->fireModelEvent('retrieved', false);

            return $model;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                throw (new ModelNotFoundException("Model not found", 404, $e))->setModel(
                    get_class($model),
                    $id
                );
            }
            throw $e;
        }
    }

    public static function find($id) {
        try {
            return static::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function newModelQuery() {
        return new QueryBuilder();
    }

    public static function get($page = null, $size = null, $sort = null): LengthAwarePaginator {
        $model = new static();
        $response = $model->getConnection()->getJson($model->_endpoint, ['query' => compact('page', 'size', 'sort')]);
        $models = static::formatEmbededResponse($response['_embedded'][$model->_endpoint]);

        return new LengthAwarePaginator($models, $response['page']['totalElements'], $response['page']['size'], $response['page']['number']);
    }

    protected static function search($method, $params) {
        $model = new static();
        $attributes = $model->getConnection()->getJson($model->_endpoint . "/search/{$method}", ['query' => $params]);

        if (is_null($attributes)) {
            return null;
        }

        if (is_array($attributes) && isset($attributes['_embedded'])) {
            return static::formatEmbededResponse($attributes['_embedded']);
        } else if (is_scalar($attributes)) {
            return $attributes;
        } else {
            $model->setRawAttributes((array) $attributes, true);
            $model->exists = true;

            $model->fireModelEvent('retrieved', false);

            return $model;
        }
    }

    protected static function formatEmbededResponse($items): Collection {
        return (new Collection($items))
            ->map(fn($itemAttributes) => (new static())->setRawAttributes((array) $itemAttributes, true))
            ->each(fn($model) => $model->exists = true)
            ->each(fn($model) => $model->fireModelEvent('retrieved', false));
    }

    // TODO: Implement push() method to also save related entities
}

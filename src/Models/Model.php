<?php

namespace Amanank\HalClient\Models;


use Illuminate\Database\Eloquent\Model as EloquentModel;

use Amanank\HalClient\Client;
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
        $parts = explode('/', $this->getLinkHref('self'));
        return end($parts);
    }

    public function getLink() {
        return "{$this->_endpoint}/{$this->getId()}";
    }

    protected function getLinkHref($rel) {
        return isset($this->_links[$rel]) ? $this->_links[$rel]['href'] : null;
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
            if (is_object($value) && method_exists($value, 'getSelfLink')) {
                $attributes[$key] = $value->getLink();
            } elseif ($value instanceof Collection) {
                $attributes[$key] = $value->map(fn($item) => $item->getLink())->toArray();
            }
        }

        return $attributes;
    }


    protected function performUpdate($query): bool {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $this->getConnection()->update($this->attributes['_links']['self']['href'], $this->getAttributesForSave());

            echo "Updated entity!\n";

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;

        return false;
    }

    public function refresh() {
        if (! $this->exists) {
            return $this;
        }

        $this->setRawAttributes(
            $this->getConnection()->getData($this->attributes['_links']['self']['href'])
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

        echo "Created entity: $selfHref\n";

        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    protected function performDeleteOnModel() {
        $this->getConnection()->remove($this->attributes['_links']['self']['href']);

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

    public static function find($id) {
        $model = new static();
        $id = $model->getIdFromLink($id);
        try {
            $response = $model->getConnection()->get($model->_endpoint . "/{$id}");
            $attributes = json_decode($response->getBody()->getContents(), true);

            $model->setRawAttributes((array) $attributes, true);
            $model->exists = true;

            $model->fireModelEvent('retrieved', false);

            return $model;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
    }

    public static function findOrFail($id) {
        $model = static::find($id);
        if ($model) {
            return $model;
        }
        throw new \Exception("Model not found");
    }

    public function newModelQuery() {
        return null; //query is not supported
    }

    public static function search($method, $params) {
        $model = new static();
        $attributes = $model->getConnection()->getJson($model->_endpoint . "/search/{$method}", ['query' => $params]);

        if (is_null($attributes)) {
            return null;
        }

        $model->setRawAttributes((array) $attributes, true);
        $model->exists = true;

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    // TODO: Implement push() method to also save related entities
}

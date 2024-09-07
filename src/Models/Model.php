<?php

namespace Amanank\HalClient\Models;


use Illuminate\Database\Eloquent\Model as EloquentModel;

use Amanank\HalClient\Client;

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

    protected function getLinkHref($rel) {
        return isset($this->_links[$rel]) ? $this->_links[$rel]['href'] : null;
    }

    public function hasOne($related, $property = null, $localKey = null) {
        $link = $this->getLinkHref($property);
        return new HalHasOne($this, $related, $link);
    }

    public function hasMany($related, $property = null, $localKey = null) {
        $link = $this->getLinkHref($property);
        return new HalHasMany($this, $related, $link);
    }




    protected function performUpdate($query): bool {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $this->getConnection()->update($this->_endpoint, $this->getAttributes());

            echo "Updated entity!\n";

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;

        return false;
    }

    protected function performInsert($query): bool {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $attributes = $this->getAttributesForInsert();

        $selfHref = $this->getConnection()->create($this->_endpoint, $this->getAttributes());

        $this->attributes['_links']['self']['href'] = $selfHref;

        echo "Created entity: $selfHref\n";

        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    public static function find($id) {
        $model = new static();
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
}

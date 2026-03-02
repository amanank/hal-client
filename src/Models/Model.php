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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

    public function getKey() {
        try {
            return $this->getId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getRouteKey() {
        return (string) $this->getKey();
    }

    protected function getManyRelation() {
        return $this->hasMany(static::class, 'self');
    }

    protected function getLinkHref($rel) {
        return isset($this->attributes['_links'][$rel]) ? $this->attributes['_links'][$rel]['href'] : null;
    }

    protected function getRaltedUnderCurrentNamespace($related) {
        $namespace = substr(get_class($this), 0, strrpos(static::class, '\\'));
        $relatedClass = "$namespace\\" . class_basename($related);
        return class_exists($relatedClass) ? $relatedClass : $related;
    }

    public function hasOne($related, $property = null, $localKey = null) {
        $related = $this->getRaltedUnderCurrentNamespace($related);
        $link = $this->getLinkHref($property);
        return new HalHasOne($this, $related, $link, $property);
    }

    public function hasMany($related, $property = null, $localKey = null) {
        $related = $this->getRaltedUnderCurrentNamespace($related);
        $link = $this->getLinkHref($property);
        return new HalHasMany($this, $related, $link, $property);
    }

    protected static function isSelfRelation($relationName) {
        return $relationName == 'self' || $relationName == lcfirst(class_basename(static::class));
    }

    public function getAttributesForSave() {
        $attributes = $this->getAttributes();

        foreach ($attributes['_links'] ?? [] as $relationName => $link) { //load all relations that were not touched
            if (!static::isSelfRelation($relationName) && !isset($attributes[$relationName])) {
                $attributes[$relationName] = $this->$relationName;
            }
        }

        unset($attributes['_links']);
        unset($attributes['_embedded']);
        unset($attributes['id']);

        foreach ($attributes as $key => $value) {
            // Drop nested relation payloads that are not link lists.
            if ($key === 'attributes' && is_array($value) && array_is_list($value) === false) {
                unset($attributes[$key]);
                continue;
            }

            if ($value instanceof Collection) {
                $attributes[$key] = $value
                    ->map(fn($item) => $this->resolveRelationLink($item))
                    ->filter(fn($link) => !is_null($link))
                    ->values()
                    ->toArray();
            } elseif (is_object($value)) {
                $resolvedLink = $this->resolveRelationLink($value);
                if ($resolvedLink !== null) {
                    $attributes[$key] = $resolvedLink;
                } else {
                    unset($attributes[$key]);
                }
            } elseif (is_array($value)) {
                $attributes[$key] = collect($value)->map(function ($item) {
                    if (is_object($item)) {
                        return $this->resolveRelationLink($item);
                    }

                    if (is_array($item) && isset($item['_links']['self']['href'])) {
                        return $item['_links']['self']['href'];
                    }

                    return $item;
                })->filter(fn($link) => !is_null($link))->values()->toArray();
            }

        }

        // Debug outgoing attributes to help diagnose payload issues.
        Log::debug('HAL model payload', [
            'model' => static::class,
            'attributes' => $attributes,
        ]);

        return $attributes;
    }

    protected function resolveRelationLink($value): ?string {
        if (is_array($value) && isset($value['_links']['self']['href'])) {
            return $value['_links']['self']['href'];
        }

        if (!is_object($value)) {
            return null;
        }

        if (method_exists($value, 'getLink')) {
            try {
                return $value->getLink();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Fallback for plain objects carrying HAL attributes but not extending this model.
        if (method_exists($value, 'getAttribute')) {
            $links = $value->getAttribute('_links');
            if (is_array($links) && isset($links['self']['href'])) {
                return $links['self']['href'];
            }
        }

        if (method_exists($value, 'getAttributes')) {
            $rawAttributes = $value->getAttributes();
            if (is_array($rawAttributes) && isset($rawAttributes['_links']['self']['href'])) {
                return $rawAttributes['_links']['self']['href'];
            }
        }

        return null;
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
            $status = $e->getResponse()?->getStatusCode();

            if ($status === 400) {
                $errors = $this->extractValidationErrors($e);

                if (! empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }

                // If no structured errors, use response body as message
                $responseBody = (string) $e->getResponse()->getBody();
                try {
                    $decoded = json_decode($responseBody, true);
                    $message = $decoded['message'] ?? $decoded['debugMessage'] ?? 'Validation error from API';
                } catch (\Exception $ex) {
                    $message = 'Validation error from API';
                }
                throw ValidationException::withMessages(['form' => $message]);
            }

            if ($status === 409) {
                throw (new ConstraintViolationException("Constraint violation", 409, $e))->setModel(
                    get_class($this),
                    $this->exists ? $this->getLink() : null
                );
            }

            // For other errors, convert to exception with user-friendly message
            $responseBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
            $message = 'An error occurred while saving. Please try again.';

            if ($responseBody) {
                try {
                    $decoded = json_decode($responseBody, true);
                    if (isset($decoded['message'])) {
                        $message = $decoded['message'];
                    } elseif (isset($decoded['debugMessage'])) {
                        $message = $decoded['debugMessage'];
                    }
                } catch (\Exception $ex) {
                    // Keep default message
                }
            }

            throw new \Exception($message);
        }
    }

    /**
     * Map HAL API validation errors to Laravel's validation structure.
     */
    protected function extractValidationErrors(ClientException $e): array
    {
        $response = $e->getResponse();

        if (! $response) {
            return [];
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload)) {
            return [];
        }

        $errors = [];

        if (! empty($payload['subErrors']) && is_array($payload['subErrors'])) {
            foreach ($payload['subErrors'] as $error) {
                $field = $error['field'] ?? null;
                $message = $error['message'] ?? ($error['debugMessage'] ?? null);

                if ($field && $message) {
                    // Filament forms use `data.<field>` keys.
                    $errors["data.{$field}"][] = $message;
                }
            }
        }

        if (empty($errors) && ! empty($payload['message'])) {
            $errors['error'][] = $payload['message'];
        }

        return $errors;
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
        return new \Amanank\HalClient\Query\HalEloquentBuilder(static::class);
    }

    public static function get($page = null, $size = null, $sort = null): LengthAwarePaginator {
        $model = new static();

        // API is 0-based; Laravel pagination is 1-based.
        $apiPage = max(($page ?? 1) - 1, 0);
        $apiSize = $size ?? 15;

        $response = $model->getConnection()->getJson($model->_endpoint, [
            'query' => [
                'page' => $apiPage,
                'size' => $apiSize,
                'sort' => $sort,
            ],
        ]);

        $models = static::formatEmbededResponse($response['_embedded'][$model->_endpoint]);

        return new LengthAwarePaginator(
            $models,
            $response['page']['totalElements'],
            $response['page']['size'],
            $apiPage + 1,
        );
    }

    protected static function halSearch($method, $params) {
        $model = new static();
        $attributes = $model->getConnection()->getJson($model->_endpoint . "/search/{$method}", ['query' => $params]);

        if (is_null($attributes)) {
            return null;
        }

        if (is_array($attributes) && isset($attributes['_embedded'], $attributes['_embedded'][$model->_endpoint])) {
            return static::formatEmbededResponse($attributes['_embedded'][$model->_endpoint]);
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

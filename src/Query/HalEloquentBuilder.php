<?php

namespace Amanank\HalClient\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Facades\DB;

class HalEloquentBuilder extends Builder
{
    protected HalQueryBuilder $hal;
    public array $orders = [];

    public function __construct(string $modelClass)
    {
        $this->hal = new HalQueryBuilder($modelClass);
        $this->orders = [];

        $connection = DB::connection();
        $query = new BaseQueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor()
        );

        parent::__construct($query);

        $this->setModel(new $modelClass());
    }

    public function orderBy($column, $direction = 'asc')
    {
        $this->hal->orderBy($column, $direction);
        return $this;
    }

    public function where(...$args)
    {
        if (isset($args[0]) && is_callable($args[0])) {
            // Filament filters receive the Eloquent Builder; pass $this to satisfy the signature.
            $callback = $args[0];
            $callback($this);
            return $this;
        }

        $this->hal->where(...$args);
        return $this;
    }

    public function orWhere(...$args)
    {
        if (isset($args[0]) && is_callable($args[0])) {
            $callback = $args[0];
            $callback($this);
            return $this;
        }

        $this->hal->orWhere(...$args);
        return $this;
    }

    public function whereBelongsTo($related, $relationshipName = null, $boolean = 'and')
    {
        if (is_object($related) && method_exists($related, 'getId')) {
            $this->hal->setParentId($related->getId());
        }

        return $this;
    }

    public function withParentId($parentId): static
    {
        $this->hal->setParentId($parentId ? (string) $parentId : null);
        return $this;
    }

    public function whereIn(...$args)
    {
        return $this;
    }

    public function whereNull(...$args)
    {
        return $this;
    }

    public function count($columns = ['*'])
    {
        return $this->hal->count();
    }

    public function toBase()
    {
        // Return a lightweight object that supplies the count for Filament pagination.
        return new class($this->hal, $this->getModel())
        {
            public array $orders = [];

            public function __construct(protected HalQueryBuilder $hal, protected \Illuminate\Database\Eloquent\Model $model)
            {
            }

            public function getCountForPagination()
            {
                return $this->hal->count();
            }

            public function getModel(): \Illuminate\Database\Eloquent\Model
            {
                return $this->model;
            }
        };
    }

    public function getQuery()
    {
        // Provide orders + model access like a typical Eloquent Builder.
        $this->orders ??= [];
        return $this;
    }

    public function get($columns = ['*'])
    {
        return $this->hal->paginate(null, $columns)->getCollection();
    }

    public function getConnection()
    {
        // Return a dummy connection that satisfies Filament's expectation for search helpers.
        return app('db')->connection();
    }

    public function first($columns = ['*'])
    {
        return $this->hal->first($columns);
    }

    public function firstOrFail($columns = ['*'])
    {
        $first = $this->first($columns);

        if ($first) {
            return $first;
        }

        throw (new ModelNotFoundException())->setModel($this->model::class);
    }

    public function find($id, $columns = ['*'])
    {
        return $this->hal->find($id);
    }

    public function findOrFail($id, $columns = ['*'])
    {
        return $this->hal->findOrFail($id);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        // $total is ignored; HAL client returns total from API.
        return $this->hal->paginate($perPage, $columns, $pageName, $page);
    }

    public function clone()
    {
        return clone $this;
    }

    public function __clone()
    {
        $this->hal = clone $this->hal;
    }

    public function __call($method, $parameters)
    {
        // Gracefully ignore unsupported builder methods to retain compatibility with Filament.
        return $this;
    }

    public function __get($key)
    {
        if ($key === 'orders') {
            return $this->orders;
        }

        return parent::__get($key);
    }
}

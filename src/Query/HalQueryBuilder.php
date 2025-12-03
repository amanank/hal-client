<?php

namespace Amanank\HalClient\Query;

use Amanank\HalClient\Models\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class HalQueryBuilder
{
    protected string $modelClass;
    protected ?string $sort = null;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function orderBy($column, $direction = 'asc'): static
    {
        $this->sort = "{$column},{$direction}";
        return $this;
    }

    public function where(...$args): static
    {
        // Filtering not yet supported; no-op to keep compatibility.
        return $this;
    }

    public function whereIn(...$args): static
    {
        return $this;
    }

    public function whereNull(...$args): static
    {
        return $this;
    }

    public function count(): int
    {
        $paginator = $this->paginate(1);
        return $paginator->total();
    }

    public function get($columns = ['*'])
    {
        return $this->paginate(null)->getCollection();
    }

    public function first($columns = ['*'])
    {
        return $this->paginate(1)->first();
    }

    public function firstOrFail($columns = ['*'])
    {
        $first = $this->first($columns);
        if ($first) {
            return $first;
        }

        $model = $this->newModelInstance();
        throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel($this->modelClass);
    }

    public function find($id)
    {
        return $this->modelClass::find($id);
    }

    public function findOrFail($id)
    {
        return $this->modelClass::findOrFail($id);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        $page = $page ?? request()->input($pageName, 1);
        $perPage = $perPage ?? 15;

        return $this->modelClass::get($page, $perPage, $this->sort);
    }

    public function clone(): static
    {
        return clone $this;
    }

    public function __clone()
    {
        // No deep state to copy beyond scalars.
    }

    // Fallback for other builder calls: ignore and return self to avoid hard failures.
    public function __call($name, $arguments)
    {
        return $this;
    }

    protected function newModelInstance(): Model
    {
        $class = $this->modelClass;
        return new $class();
    }
}

// Backwards compatibility alias.
class QueryBuilder extends HalQueryBuilder {}

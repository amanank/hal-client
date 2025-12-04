<?php

namespace Amanank\HalClient\Query;

use Amanank\HalClient\Models\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class HalQueryBuilder
{
    protected string $modelClass;
    protected ?string $sort = null;
    protected array $searchTerms = [];

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
        if (isset($args[0]) && is_callable($args[0])) {
            $callback = $args[0];
            $callback($this);
            return $this;
        }

        $this->captureSearchTermFromArgs($args);

        return $this;
    }

    public function orWhere(...$args): static
    {
        $this->captureSearchTermFromArgs($args);

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
        $page = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
        $perPage = $perPage ?? 15;

        if (! empty($this->searchTerms) && method_exists($this->modelClass, 'searchByTerm')) {
            $term = $this->searchTerms[0];
            $results = $this->modelClass::searchByTerm($term);
            $collection = $results instanceof \Illuminate\Support\Collection ? $results : collect($results);
            $total = $collection->count();
            $items = $collection->forPage($page, $perPage)->values();

            return new LengthAwarePaginator($items, $total, $perPage, $page);
        }

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

    protected function captureSearchTermFromArgs(array $args): void
    {
        // Common patterns: where('column', 'like', '%term%') or where(function($q) use ($term) { ... }).
        if (count($args) >= 3 && is_string($args[2])) {
            $value = trim($args[2], '%');
            if ($value !== '') {
                $this->searchTerms[] = $value;
            }
            return;
        }

        if (count($args) >= 2 && is_string($args[1])) {
            $value = trim($args[1], '%');
            if ($value !== '') {
                $this->searchTerms[] = $value;
            }
        }
    }
}

// Backwards compatibility alias.
class QueryBuilder extends HalQueryBuilder {}

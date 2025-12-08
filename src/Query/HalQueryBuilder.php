<?php

namespace Amanank\HalClient\Query;

use Amanank\HalClient\Models\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HalQueryBuilder {
    protected string $modelClass;
    protected ?string $sort = null;
    protected array $searchTerms = [];
    protected ?string $parentId = null;

    public function __construct(string $modelClass) {
        $this->modelClass = $modelClass;
    }

    public function orderBy($column, $direction = 'asc'): static {
        $this->sort = "{$column},{$direction}";
        return $this;
    }

    public function setParentId(?string $parentId): static {
        $this->parentId = $parentId;

        return $this;
    }

    public function where(...$args): static {
        if (isset($args[0]) && is_callable($args[0])) {
            $callback = $args[0];
            $callback($this);
            return $this;
        }

        $this->captureSearchTermFromArgs($args);

        return $this;
    }

    public function orWhere(...$args): static {
        $this->captureSearchTermFromArgs($args);

        return $this;
    }

    public function whereIn(...$args): static {
        return $this;
    }

    public function whereNull(...$args): static {
        return $this;
    }

    public function count(): int {
        $paginator = $this->paginate(1);
        return $paginator->total();
    }

    public function get($columns = ['*']) {
        return $this->paginate(null)->getCollection();
    }

    public function first($columns = ['*']) {
        return $this->paginate(1)->first();
    }

    public function firstOrFail($columns = ['*']) {
        $first = $this->first($columns);
        if ($first) {
            return $first;
        }

        $model = $this->newModelInstance();
        throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel($this->modelClass);
    }

    public function find($id) {
        return $this->modelClass::find($id);
    }

    public function findOrFail($id) {
        return $this->modelClass::findOrFail($id);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator {
        $page = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
        $perPage = $perPage ?? 15;

        // Livewire rehydrates models by calling paginate() on the builder snapshot.
        // When we only have a single numeric search term, treat it as an ID lookup
        // instead of running the search endpoint, to avoid returning the wrong record.
        if (count($this->searchTerms) === 1 && is_numeric($this->searchTerms[0])) {
            $model = $this->modelClass::find($this->searchTerms[0]);
            $items = $model ? collect([$model]) : collect();

            return new LengthAwarePaginator($items, $items->count(), $perPage, $page);
        }

        if ($this->parentId && method_exists($this->modelClass, 'findChildrenByParentId')) {
            $results = $this->modelClass::findChildrenByParentId($this->parentId);
            $collection = $results instanceof Collection ? $results : collect($results);

            if (! empty($this->searchTerms)) {
                $term = Str::lower($this->searchTerms[0]);
                $collection = $collection->filter(function ($cat) use ($term) {
                    $name = Str::lower((string) ($cat->name ?? ''));
                    $slug = Str::lower((string) ($cat->slug ?? ''));
                    $code = Str::lower((string) ($cat->code ?? ''));
                    return Str::contains($name, $term) || Str::contains($slug, $term) || Str::contains($code, $term);
                });
            }

            $total = $collection->count();
            $items = $collection->forPage($page, $perPage)->values();

            return new LengthAwarePaginator($items, $total, $perPage, $page);
        }

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

    public function clone(): static {
        return clone $this;
    }

    public function __clone() {
        // No deep state to copy beyond scalars.
    }

    // Fallback for other builder calls: ignore and return self to avoid hard failures.
    public function __call($name, $arguments) {
        return $this;
    }

    protected function newModelInstance(): Model {
        $class = $this->modelClass;
        return new $class();
    }

    protected function captureSearchTermFromArgs(array $args): void {
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
class QueryBuilder extends HalQueryBuilder {
}

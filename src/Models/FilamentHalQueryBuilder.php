<?php

namespace Amanank\HalClient\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A Filament-compatible query builder for HAL relations.
 * Wraps HAL collection-based results to appear as an Eloquent Builder.
 */
class FilamentHalQueryBuilder extends Builder {
    protected $halRelation;
    protected $halResults;
    protected $filtered = false;

    public function __construct(HalHasMany $relation) {
        // Get results first to have a proper model
        $results = $relation->getResults();
        $model = $results->first();
        
        // Don't call parent constructor - just set minimal properties
        $this->halRelation = $relation;
        $this->halResults = $results;
        $this->model = $model; // Set to actual model or null
        $this->query = new \stdClass();
    }

    /**
     * Execute the query and get results
     */
    public function get($columns = ['*']) {
        return $this->halResults;
    }

    /**
     * Apply wheres and get filtered results
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and') {
        // If $column is a Closure, it's a complex where clause - just return without filtering
        if ($column instanceof \Closure) {
            return $this;
        }

        // Handle where($column, $value) syntax
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->halResults = $this->halResults->filter(function ($item) use ($column, $operator, $value) {
            try {
                $itemValue = $item->{$column} ?? null;
            } catch (\Exception $e) {
                return true;
            }
            
            switch ($operator) {
                case '=':
                    return $itemValue == $value;
                case '!=':
                case '<>':
                    return $itemValue != $value;
                case '>':
                    return $itemValue > $value;
                case '<':
                    return $itemValue < $value;
                case '>=':
                    return $itemValue >= $value;
                case '<=':
                    return $itemValue <= $value;
                case 'like':
                    return strpos((string)$itemValue, (string)$value) !== false;
                default:
                    return true;
            }
        });

        $this->filtered = true;
        return $this;
    }

    /**
     * Filter by ID list
     */
    public function whereIn($column, $values) {
        $this->halResults = $this->halResults->filter(function ($item) use ($column, $values) {
            return in_array($item->{$column} ?? null, $values);
        });

        return $this;
    }

    /**
     * Order by a column
     */
    public function orderBy($column, $direction = 'asc') {
        $this->halResults = $this->halResults->sortBy(function ($item) use ($column) {
            return $item->{$column} ?? '';
        }, SORT_REGULAR, $direction === 'desc');

        return $this;
    }

    /**
     * Count the results
     */
    public function count() {
        return $this->halResults->count();
    }

    /**
     * Get count for pagination
     */
    public function getCountForPagination($columns = ['*']) {
        return $this->count();
    }

    /**
     * Get the first result
     */
    public function first($columns = ['*']) {
        return $this->halResults->first();
    }

    /**
     * Find a specific model by ID
     */
    public function find($id, $columns = ['*']) {
        return $this->halResults->firstWhere('id', $id);
    }

    /**
     * Find multiple models by IDs
     */
    public function findMany($ids, $columns = ['*']) {
        return $this->halResults->whereIn('id', $ids);
    }

    /**
     * Paginate the results
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
        $perPage = $perPage ?? 15;
        $page = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
        $total = $total ?? $this->halResults->count();
        
        $items = $this->halResults->slice(($page - 1) * $perPage, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Simplify pagination - return all results without pagination
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        return $this->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Get query without executing
     */
    public function toBase() {
        return $this;
    }

    /**
     * Clone the query
     */
    public function clone() {
        return clone $this;
    }

    /**
     * Get raw results without modification
     */
    public function getRawResults() {
        return $this->halResults;
    }

    /**
     * Dynamically handle any other method calls
     */
    public function __call($method, $parameters) {
        if (method_exists($this->halResults, $method)) {
            return call_user_func_array([$this->halResults, $method], $parameters);
        }

        // Return $this for method chaining on unknown methods
        return $this;
    }
}


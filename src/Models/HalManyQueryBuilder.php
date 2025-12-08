<?php

namespace Amanank\HalClient\Models;

use Illuminate\Support\Collection;

/**
 * A query builder for HalHasMany relations that works with Filament's RelationManager.
 * This provides a minimal query-like interface over the HAL collection data.
 */
class HalManyQueryBuilder {
    protected $halRelation;
    protected $halResults;

    public function __construct(HalHasMany $relation, $model = null) {
        $this->halRelation = $relation;
        $this->halResults = $relation->getResults();
    }

    /**
     * Execute the query as a Collection.
     */
    public function get($columns = ['*']) {
        return $this->halResults;
    }

    /**
     * Get paginated results 
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
        $results = $this->get($columns);
        
        $perPage = $perPage ?? 15;
        $page = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
        $total = $total ?? $results->count();
        
        $items = $results->slice(($page - 1) * $perPage, $perPage)->values();
        
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
     * Add a where clause (for collection filtering)
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and') {
        // Handle where($column, $value) syntax
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->halResults = $this->halResults->filter(function ($item) use ($column, $operator, $value) {
            $itemValue = $item->{$column} ?? null;
            
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

        return $this;
    }

    /**
     * Add a whereIn clause
     */
    public function whereIn($column, $values) {
        $this->halResults = $this->halResults->filter(function ($item) use ($column, $values) {
            return in_array($item->{$column} ?? null, $values);
        });

        return $this;
    }

    /**
     * Count the results
     */
    public function count() {
        return $this->halResults->count();
    }

    /**
     * Get the first result
     */
    public function first($columns = ['*']) {
        return $this->halResults->first();
    }
}

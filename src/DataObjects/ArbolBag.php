<?php

namespace Calvient\Arbol\DataObjects;

use Illuminate\Database\Eloquent\Builder;

class ArbolBag
{
    public function __construct(
        public array $filters = [],
        public ?string $slice = null
    ) {}

    public function addFilter(string $field, string $filter)
    {
        $this->filters[$field][] = $filter;
    }

    public function addSlice(string $value)
    {
        $this->slice = $value;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getSlice(): string
    {
        return $this->slice;
    }

    public function isFilterSet(string $field, string $filter): bool
    {
        return in_array($filter, $this->filters[$field] ?? []);
    }

    public function applyFilters(array $allFilters, callable $callback): void
    {
        foreach ($allFilters as $field => $filters) {
            foreach ($filters as $filter => $func) {
                if ($this->isFilterSet($field, $filter)) {
                    $callback($func);
                }
            }
        }
    }

    public function applyQueryFilters(Builder $query, array $allFilters): Builder
    {
        foreach ($allFilters as $field => $filters) {
            // This allows the use of OR filters
            $query->where(function ($q) use ($field, $filters) {
                foreach ($filters as $filter => $func) {
                    if ($this->isFilterSet($field, $filter)) {
                        $func($q);
                    }
                }
            });
        }

        return $query;
    }
}

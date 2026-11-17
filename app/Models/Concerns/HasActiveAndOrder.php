<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Shared scopes for master tables with is_active (and optionally sort_order). */
trait HasActiveAndOrder
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }

    /** Next sort_order at the end of the list (optionally within a scope). */
    public static function nextSortOrder(?callable $scope = null): int
    {
        $query = static::query();
        if ($scope) {
            $scope($query);
        }

        return ((int) $query->max('sort_order')) + 1;
    }
}

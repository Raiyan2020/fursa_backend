<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasSoftFlags
{
    public function initializeHasSoftFlags(): void
    {
        $this->casts = array_merge($this->casts ?? [], [
            'is_deleted' => 'boolean',
            'deleted_at' => 'datetime',
        ]);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_deleted', false);
    }

    public function softDeleteFlags(): bool
    {
        $this->is_deleted = true;
        $this->deleted_at = Carbon::now();

        return $this->save();
    }

    public function restoreSoftFlags(): bool
    {
        $this->is_deleted = false;
        $this->deleted_at = null;

        return $this->save();
    }
}

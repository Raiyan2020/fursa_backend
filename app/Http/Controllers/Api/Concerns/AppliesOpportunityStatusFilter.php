<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\OpportunityStatus;
use Illuminate\Database\Eloquent\Builder;

trait AppliesOpportunityStatusFilter
{
    protected function normalizeOpportunityStatusFilter(?string $status): ?OpportunityStatus
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        $normalized = strtolower(trim(str_replace('-', '_', $status)));

        $aliases = [
            'upcoming' => OpportunityStatus::UPCOMING,
            'inprogress' => OpportunityStatus::INPROGRESS,
            'in_progress' => OpportunityStatus::INPROGRESS,
            'started' => OpportunityStatus::INPROGRESS,
            'completed' => OpportunityStatus::COMPLETED,
            'closed' => OpportunityStatus::COMPLETED,
            'finished' => OpportunityStatus::COMPLETED,
            'ended' => OpportunityStatus::COMPLETED,
            'cancelled' => OpportunityStatus::CANCELLED,
            'canceled' => OpportunityStatus::CANCELLED,
        ];

        return $aliases[$normalized] ?? OpportunityStatus::tryFrom($normalized);
    }

    /**
     * Filter by effective lifecycle status derived from start/end dates
     * (same rules as fursa:advance-statuses), not the stale DB column alone.
     */
    protected function applyOpportunityStatusFilter(
        Builder $query,
        string $status,
        string $statusColumn = 'opportunity_status'
    ): ?OpportunityStatus {
        $resolved = $this->normalizeOpportunityStatusFilter($status);
        if ($resolved === null) {
            return null;
        }

        if ($resolved === OpportunityStatus::CANCELLED) {
            $query->where($statusColumn, OpportunityStatus::CANCELLED);

            return $resolved;
        }

        $query->where(function (Builder $q) use ($statusColumn) {
            $q->where($statusColumn, '!=', OpportunityStatus::CANCELLED)
                ->orWhereNull($statusColumn);
        });

        $today = now()->toDateString();

        $query->whereNotNull('start_date')->whereNotNull('end_date');

        match ($resolved) {
            OpportunityStatus::UPCOMING => $query->whereDate('start_date', '>', $today),
            OpportunityStatus::INPROGRESS => $query
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today),
            OpportunityStatus::COMPLETED => $query->whereDate('end_date', '<', $today),
            default => $query->whereRaw('0 = 1'),
        };

        return $resolved;
    }
}

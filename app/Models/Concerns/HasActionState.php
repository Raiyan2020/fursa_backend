<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

/**
 * Single source of truth for the registration button state the client spelled
 * out (email of 21 Jun 2026):
 *
 *   register    — the opportunity is open
 *   unregister  — the current user is already registered
 *   full        — capacity reached
 *   closed      — due date passed or the publisher closed registration manually
 *   started     — started but not finished yet
 *   ended       — finished
 *
 * Precedence is deliberate: a finished/started opportunity wins over everything
 * (nothing to join), then an existing registration (the user must always be able
 * to leave), then the blockers, then the open state. Keeping it here instead of
 * in each frontend screen stops the states drifting apart.
 *
 * Requires the model to also use HasRegistrationWindow.
 */
trait HasActionState
{
    public function actionState(bool $isRegistered = false, ?int $registeredCount = null): string
    {
        if ($this->hasEnded()) {
            return 'ended';
        }

        if ($this->hasStarted()) {
            return 'started';
        }

        if ($isRegistered) {
            return 'unregister';
        }

        if (! $this->isRegistrationOpen()) {
            return 'closed';
        }

        if ($this->isAtCapacity($registeredCount)) {
            return 'full';
        }

        return 'register';
    }

    public function hasEnded(): bool
    {
        $status = $this->opportunity_status ?? $this->event_status ?? null;
        $statusValue = is_object($status) && property_exists($status, 'value')
            ? $status->value
            : (string) $status;

        if (in_array($statusValue, ['completed', 'cancelled'], true)) {
            return true;
        }

        $end = $this->end_date ?? null;

        return $end !== null && now()->gt(Carbon::parse($end)->endOfDay());
    }

    public function hasStarted(): bool
    {
        if ($this->hasEnded()) {
            return false;
        }

        $status = $this->opportunity_status ?? $this->event_status ?? null;
        $statusValue = is_object($status) && property_exists($status, 'value')
            ? $status->value
            : (string) $status;

        if ($statusValue === 'inprogress') {
            return true;
        }

        $start = $this->start_date ?? null;

        return $start !== null && now()->gte(Carbon::parse($start)->startOfDay());
    }

    /**
     * `participants_needed` of 0/null means "no cap", so it can never be full.
     */
    public function isAtCapacity(?int $registeredCount = null): bool
    {
        $capacity = (int) ($this->participants_needed ?? 0);
        if ($capacity <= 0) {
            return false;
        }

        $count = $registeredCount ?? $this->currentRegistrationCount();

        return $count >= $capacity;
    }

    protected function currentRegistrationCount(): int
    {
        if (! method_exists($this, 'registrations')) {
            return 0;
        }

        if ($this->relationLoaded('registrations')) {
            return $this->registrations
                ->filter(fn ($r) => ! ($r->is_deleted ?? false))
                ->count();
        }

        return (int) $this->registrations()->where('is_deleted', false)->count();
    }
}

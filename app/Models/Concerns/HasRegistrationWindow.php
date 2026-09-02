<?php

namespace App\Models\Concerns;

use App\Models\Config;
use Carbon\Carbon;

trait HasRegistrationWindow
{
    public function isRegistrationOpen(): bool
    {
        if ((bool) ($this->is_registration_closed ?? false)) {
            return false;
        }

        $closesAt = $this->registrationClosesAt();
        if (! $closesAt) {
            return true;
        }

        return now()->lte($closesAt);
    }

    public function registrationClosesAt(): ?Carbon
    {
        if ($this->due_date) {
            return Carbon::parse($this->due_date)->endOfDay();
        }

        if ($this->end_date) {
            return Carbon::parse($this->end_date)->endOfDay();
        }

        return null;
    }

    public function preparationValidUntil(): ?Carbon
    {
        if (! $this->end_date) {
            return null;
        }

        // An admin can push the window past its natural close; that wins when later.
        $reopenedUntil = ($this->preparation_reopened_until ?? null)
            ? Carbon::parse($this->preparation_reopened_until)
            : null;

        $natural = $this->naturalPreparationValidUntil();

        if ($reopenedUntil && (! $natural || $reopenedUntil->gt($natural))) {
            return $reopenedUntil;
        }

        return $natural;
    }

    /**
     * The window as configured, ignoring any manual admin reopen.
     *
     * Hours take precedence over the legacy day-based setting so the client's
     * 72-hour default is expressible exactly.
     */
    public function naturalPreparationValidUntil(): ?Carbon
    {
        if (! $this->end_date) {
            return null;
        }

        $config = Config::query()->first();

        $hours = (int) ($config?->preparation_validity_hours ?? 0);
        if ($hours > 0) {
            return Carbon::parse($this->end_date)->endOfDay()->addHours($hours);
        }

        // Falls back to the legacy day setting, then to the 72-hour default.
        $days = (int) ($config?->preparation_validity_days ?? 0) ?: 3;

        return Carbon::parse($this->end_date)->addDays($days)->endOfDay();
    }

    /**
     * True when the window has already closed and only an admin can reopen it.
     */
    public function isPreparationWindowClosed(): bool
    {
        $until = $this->preparationValidUntil();

        return $until !== null && now()->gt($until);
    }

    public function isWithinPreparationWindow(?string $date = null): bool
    {
        $check = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        // An opportunity that scheduled specific days only runs on those days,
        // so a check-in on a gap day is not valid attendance.
        if (method_exists($this, 'hasCustomSchedule') && $this->hasCustomSchedule()) {
            if (! $this->slotForDate($check->toDateString())) {
                return false;
            }
        }

        if ($this->start_date && $check->lt(Carbon::parse($this->start_date)->startOfDay())) {
            return false;
        }

        $until = $this->preparationValidUntil();
        if (! $until) {
            return true;
        }

        if ($check->gt($until->copy()->startOfDay())) {
            return false;
        }

        // Beyond the per-day check above, the window itself must still be open.
        return now()->lte($until);
    }
}

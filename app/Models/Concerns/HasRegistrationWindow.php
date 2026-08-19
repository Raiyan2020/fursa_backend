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

        $days = (int) (Config::query()->value('preparation_validity_days') ?: 7);

        return Carbon::parse($this->end_date)->addDays($days)->endOfDay();
    }

    public function isWithinPreparationWindow(?string $date = null): bool
    {
        $check = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        if ($this->start_date && $check->lt(Carbon::parse($this->start_date)->startOfDay())) {
            return false;
        }

        $until = $this->preparationValidUntil();
        if ($until && $check->gt($until->copy()->startOfDay())) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Support\Opportunity;

use App\Models\VolunteerOpportunity;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Shared per-day schedule writer for the API and dashboard (BE-44). */
class VolunteerOpportunitySchedule
{
    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    public static function sync(VolunteerOpportunity $opportunity, array $slots): void
    {
        $keptIds = [];

        foreach ($slots as $slot) {
            $date = Carbon::parse($slot['date'])->toDateString();
            $attributes = [
                'start_time' => self::normalizeTime($slot['start_time'] ?? $opportunity->start_time),
                'end_time' => self::normalizeTime($slot['end_time'] ?? $opportunity->end_time),
                'is_deleted' => false,
                'deleted_at' => null,
            ];

            $existing = $opportunity->timeSlots()->whereDate('date', $date)->first();
            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $opportunity->timeSlots()->create($attributes + ['date' => $date])->id;
            }
        }

        $opportunity->timeSlots()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->update(['is_deleted' => true, 'deleted_at' => now()]);
    }

    protected static function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = trim((string) $value);
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
            throw ValidationException::withMessages(['time_slots' => ['Time slots must use HH:mm times.']]);
        }

        return substr($time, 0, 5).':00';
    }
}

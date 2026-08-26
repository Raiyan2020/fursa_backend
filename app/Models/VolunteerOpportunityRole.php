<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerOpportunityRole extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'role_name_en', 'role_name_ar',
        'instructions_en', 'instructions_ar', 'participants_needed',
        'is_deleted', 'deleted_at',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerOpportunityAssignment::class, 'role_id');
    }

    /**
     * Seats already taken on this role. Mirrors exactly the count the
     * registration endpoint checks before rejecting with "no remaining slots",
     * so the client can disable a full role instead of discovering it via a 400.
     */
    public function assignedCount(): int
    {
        if ($this->relationLoaded('assignments')) {
            // Same predicate as the query below: a live assignment whose
            // registration is also live and on this opportunity.
            return $this->assignments
                ->filter(fn ($a) => ! ($a->is_deleted ?? false)
                    && $a->registration !== null
                    && ! ($a->registration->is_deleted ?? false)
                    && (int) $a->registration->opportunity_id === (int) $this->opportunity_id)
                ->count();
        }

        return (int) VolunteerOpportunityAssignment::query()
            ->where('role_id', $this->id)
            ->where('is_deleted', false)
            ->whereHas('registration', fn ($q) => $q
                ->where('is_deleted', false)
                ->where('opportunity_id', $this->opportunity_id))
            ->count();
    }

    public function remainingSlots(): int
    {
        return max(0, (int) $this->participants_needed - $this->assignedCount());
    }

    public function isFull(): bool
    {
        return (int) $this->participants_needed > 0
            && $this->assignedCount() >= (int) $this->participants_needed;
    }
}

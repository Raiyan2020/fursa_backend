<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnServeOpportunityAssignment extends Model
{
    use HasSoftFlags;

    protected $fillable = ['registration_id', 'time_slot_id', 'is_deleted', 'deleted_at'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunityRegistration::class, 'registration_id');
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunityTimeSlot::class, 'time_slot_id');
    }
}

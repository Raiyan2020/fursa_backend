<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnServeOpportunityTimeSlot extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'date', 'start_time', 'end_time', 'participants_needed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class, 'opportunity_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LearnServeOpportunityAssignment::class, 'time_slot_id');
    }
}

<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VolunteerOpportunityRegistration extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'user_id', 'registration_date', 'status', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'status' => ApprovalStatus::class,
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(VolunteerOpportunityAssignment::class, 'registration_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(VolunteerOpportunityAttendance::class, 'registration_id');
    }
}

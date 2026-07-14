<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LearnServeOpportunityRegistration extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'opportunity_id', 'user_id', 'registration_date', 'status',
        'certificate_image', 'is_certified', 'is_attended', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'status' => ApprovalStatus::class,
        'is_certified' => 'boolean',
        'is_attended' => 'boolean',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(LearnServeOpportunityAssignment::class, 'registration_id');
    }
}

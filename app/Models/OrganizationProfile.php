<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationProfile extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id',
        'nickname',
        'organizer_type_id',
        'registration_number',
        'license_number',
        'company_name',
        'sector_id',
        'organization_status',
        'rejection_reason',
        'latitude',
        'longitude',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'organization_status' => ApprovalStatus::class,
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizerType(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'organizer_type_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'sector_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class, 'organizer_profile_id');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(VolunteerProfile::class, 'organization_id');
    }

    public function isApproved(): bool
    {
        return $this->organization_status === ApprovalStatus::APPROVED;
    }
}

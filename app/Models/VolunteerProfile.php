<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VolunteerProfile extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id',
        'organization_id',
        'nickname',
        'gender_id',
        'uuid',
        'qr_code',
        'occupation',
        'experience',
        'health_concerns',
        'is_public',
        'is_verified',
        'total_volunteer_hours',
        'total_opportunities',
        'total_certificates',
        'opportunities_organized',
        'current_rank',
        'current_year_hours',
        'current_badge_id',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_verified' => 'boolean',
        'total_volunteer_hours' => 'float',
        'current_year_hours' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (VolunteerProfile $profile) {
            if (empty($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class, 'organization_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'gender_id');
    }

    public function currentBadge(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'current_badge_id');
    }
}

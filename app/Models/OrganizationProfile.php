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

    private ?object $allTimeStatisticCache = null;

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

    /** Yearly rollup rows (month = NULL) written by SyncService::syncOrganization(). */
    public function statistics(): HasMany
    {
        return $this->hasMany(OrganizationStatistic::class, 'user_id', 'user_id');
    }

    /**
     * All-time counters are not stored on this model — they are the sum of the
     * per-year rollup rows in organization_statistics. Cached per-instance so the
     * four accessors below share one query.
     */
    protected function allTimeStatistic(): object
    {
        if ($this->allTimeStatisticCache === null) {
            $this->allTimeStatisticCache = $this->statistics()
                ->whereNull('month')
                ->selectRaw('
                    COALESCE(SUM(organization_hours), 0) as organization_hours,
                    COALESCE(SUM(vol_opportunity_organized), 0) as vol_opportunity_organized,
                    COALESCE(SUM(learn_opportunity_organized), 0) as learn_opportunity_organized,
                    COALESCE(SUM(sponsored), 0) as sponsored
                ')
                ->first();
        }

        return $this->allTimeStatisticCache;
    }

    public function getOrganizationHoursAttribute(): float
    {
        return (float) $this->allTimeStatistic()->organization_hours;
    }

    public function getVolOpportunityOrganizedAttribute(): float
    {
        return (float) $this->allTimeStatistic()->vol_opportunity_organized;
    }

    public function getLearnOpportunityOrganizedAttribute(): float
    {
        return (float) $this->allTimeStatistic()->learn_opportunity_organized;
    }

    public function getSponsoredCountAttribute(): float
    {
        return (float) $this->allTimeStatistic()->sponsored;
    }
}

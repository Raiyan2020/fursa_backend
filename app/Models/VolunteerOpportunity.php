<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Models\Concerns\HasActionState;
use App\Models\Concerns\HasRegistrationWindow;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerOpportunity extends Model
{
    use HasActionState;
    use HasRegistrationWindow;
    use HasSoftFlags;

    protected $fillable = [
        'approval_status', 'opportunity_status', 'title_en', 'title_ar',
        'description_en', 'description_ar', 'due_date', 'start_date', 'end_date',
        'participants_needed', 'from_age', 'to_age', 'start_time', 'end_time',
        'latitude', 'longitude', 'link', 'is_calendar', 'primary_language',
        'rejected_reason', 'location_en', 'location_ar', 'map_desc', 'opportunity_nationality',
        'deletion_status', 'deletion_rejected_reason', 'is_kuwaitis', 'created_by',
        'volunteer_hours_per_day', 'gender_id', 'is_public', 'license_image',
        'is_relief', 'is_interview_needed', 'is_urgent', 'is_supports_disabled',
        'generated_link', 'location_url', 'is_registration_closed', 'is_deleted', 'deleted_at',
        'is_emergency', 'volunteer_category', 'beneficiaries_count',
        'preparation_reopened_until', 'preparation_reminder_sent_at', 'backup_alert_sent_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'approval_status' => ApprovalStatus::class,
        'opportunity_status' => OpportunityStatus::class,
        'deletion_status' => DeletionStatus::class,
        'primary_language' => Language::class,
        'due_date' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_calendar' => 'boolean',
        'is_kuwaitis' => 'boolean',
        'is_public' => 'boolean',
        'is_relief' => 'boolean',
        'is_interview_needed' => 'boolean',
        'is_urgent' => 'boolean',
        'is_supports_disabled' => 'boolean',
        'is_registration_closed' => 'boolean',
        'is_emergency' => 'boolean',
        'volunteer_category' => VolunteerCategory::class,
        'beneficiaries_count' => 'integer',
        'preparation_reopened_until' => 'datetime',
        'preparation_reminder_sent_at' => 'datetime',
        'backup_alert_sent_at' => 'datetime',
    ];

    /**
     * Why this opportunity is or is not live on the public site.
     *
     * The public list filters on notDeleted + is_public + approved, and sorts
     * past-due rows into the last bucket, where pagination can hide them. Admins
     * had no way to see which of those applied, so the dashboard shows it.
     *
     * @return array{live: bool, key: string, reason_en: string, reason_ar: string}
     */
    public function siteVisibility(): array
    {
        if ($this->is_deleted) {
            return [
                'live' => false,
                'key' => 'deleted',
                'reason_en' => 'Deleted',
                'reason_ar' => 'محذوفة',
            ];
        }

        if ($this->approval_status !== ApprovalStatus::APPROVED) {
            return [
                'live' => false,
                'key' => 'not_approved',
                'reason_en' => 'Not approved',
                'reason_ar' => 'غير مقبولة',
            ];
        }

        if (! $this->is_public) {
            return [
                'live' => false,
                'key' => 'not_public',
                'reason_en' => 'Not public',
                'reason_ar' => 'غير عامة',
            ];
        }

        $closesAt = $this->registrationClosesAt();

        if ((bool) $this->is_registration_closed) {
            return [
                'live' => true,
                'key' => 'registration_closed',
                'reason_en' => 'Registration closed manually',
                'reason_ar' => 'التسجيل مغلق يدوياً',
            ];
        }

        if ($closesAt && now()->gt($closesAt)) {
            return [
                'live' => true,
                'key' => 'expired',
                'reason_en' => 'Due date passed — ranks last',
                'reason_ar' => 'انتهى موعد التسجيل — يظهر في آخر الترتيب',
            ];
        }

        return [
            'live' => true,
            'key' => 'live',
            'reason_en' => 'Live on site',
            'reason_ar' => 'ظاهرة في الموقع',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'gender_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(VolunteerOpportunityRegistration::class, 'opportunity_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(VolunteerOpportunityTeam::class, 'opportunity_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(VolunteerOpportunityRole::class, 'opportunity_id');
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'interest_volunteer_opportunity');
    }

    public function images(): HasMany
    {
        return $this->hasMany(OpportunityImage::class);
    }

    public function sponsorImages(): HasMany
    {
        return $this->hasMany(OpportunitySponsorImage::class);
    }

    public function scanPermissions(): HasMany
    {
        return $this->hasMany(ScanPermission::class, 'opportunity_id');
    }
}

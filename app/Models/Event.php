<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'approval_status', 'rejected_reason', 'deletion_status', 'deletion_rejected_reason',
        'event_status', 'from_age', 'to_age', 'gender_id', 'attendance_type_id',
        'title_en', 'title_ar', 'event_type_id', 'due_date', 'start_date', 'end_date',
        'start_time', 'end_time', 'registration_required', 'participants_needed',
        'paid_registration', 'registration_fee', 'latitude', 'longitude',
        'location_en', 'location_ar', 'description_en', 'description_ar',
        'participation_type_id', 'registration_link', 'created_by', 'license_image',
        'view_count', 'primary_language', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'approval_status' => ApprovalStatus::class,
        'deletion_status' => DeletionStatus::class,
        'event_status' => OpportunityStatus::class,
        'primary_language' => Language::class,
        'due_date' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_required' => 'boolean',
        'paid_registration' => 'boolean',
        'registration_fee' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(EventTimeSlot::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'interest_event');
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class);
    }

    public function sponsorImages(): HasMany
    {
        return $this->hasMany(EventSponsorImage::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class);
    }

    public function scanPermissions(): HasMany
    {
        return $this->hasMany(ScanPermission::class);
    }
}

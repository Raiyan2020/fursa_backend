<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Models\Concerns\HasRegistrationWindow;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnServeOpportunity extends Model
{
    use HasRegistrationWindow;
    use HasSoftFlags;

    protected $fillable = [
        'approval_status', 'opportunity_status', 'title_en', 'title_ar',
        'description_en', 'description_ar', 'due_date', 'start_date', 'end_date',
        'participants_needed', 'from_age', 'to_age', 'start_time', 'end_time',
        'latitude', 'longitude', 'link', 'is_calendar', 'primary_language',
        'rejected_reason', 'location_en', 'location_ar', 'opportunity_nationality',
        'deletion_status', 'deletion_rejected_reason', 'is_kuwaitis', 'created_by',
        'learning_type_id', 'gender_id', 'format_id', 'certificate_type_id',
        'license_image', 'location_url', 'is_registration_closed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'approval_status' => ApprovalStatus::class,
        'opportunity_status' => OpportunityStatus::class,
        'deletion_status' => DeletionStatus::class,
        'primary_language' => Language::class,
        'due_date' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_calendar' => 'boolean',
        'is_kuwaitis' => 'boolean',
        'is_registration_closed' => 'boolean',
    ];

    /**
     * Learning types that run without a check-in step.
     *
     * Workshops and consultations have no attendance to take, but the client
     * still wants their hours in the organizer's counters, so registrations are
     * treated as attended once the opportunity completes.
     */
    public const NO_CHECK_IN_TYPES = ['workshop', 'consultation'];

    public function requiresCheckIn(): bool
    {
        $type = $this->learningType?->value_en;

        if (! $type) {
            return true;
        }

        return ! in_array(strtolower(trim($type)), self::NO_CHECK_IN_TYPES, true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function learningType(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'learning_type_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'gender_id');
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'format_id');
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'certificate_type_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(LearnServeOpportunityRegistration::class, 'opportunity_id');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(LearnServeOpportunityTimeSlot::class, 'opportunity_id');
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'interest_learn_serve_opportunity');
    }

    public function images(): HasMany
    {
        return $this->hasMany(OpportunityImage::class);
    }

    public function sponsorImages(): HasMany
    {
        return $this->hasMany(OpportunitySponsorImage::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(OpportunityFeedback::class, 'learn_serve_opportunity_id');
    }
}

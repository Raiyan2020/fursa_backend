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

class VolunteerOpportunity extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'approval_status', 'opportunity_status', 'title_en', 'title_ar',
        'description_en', 'description_ar', 'due_date', 'start_date', 'end_date',
        'participants_needed', 'from_age', 'to_age', 'start_time', 'end_time',
        'latitude', 'longitude', 'link', 'is_calendar', 'primary_language',
        'rejected_reason', 'location_en', 'location_ar', 'opportunity_nationality',
        'deletion_status', 'deletion_rejected_reason', 'is_kuwaitis', 'created_by',
        'volunteer_hours_per_day', 'gender_id', 'is_public', 'license_image',
        'is_relief', 'is_interview_needed', 'is_urgent', 'is_supports_disabled',
        'generated_link', 'is_deleted', 'deleted_at',
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
        'is_public' => 'boolean',
        'is_relief' => 'boolean',
        'is_interview_needed' => 'boolean',
        'is_urgent' => 'boolean',
        'is_supports_disabled' => 'boolean',
    ];

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
}

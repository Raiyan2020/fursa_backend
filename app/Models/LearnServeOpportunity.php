<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Models\Concerns\HasActionState;
use App\Models\Concerns\HasRegistrationWindow;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LearnServeOpportunity extends Model
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
        'learning_type_id', 'gender_id', 'format_id', 'certificate_type_id',
        'license_image', 'location_url', 'is_registration_closed', 'is_paid', 'is_deleted', 'deleted_at',
        'attendance_code', 'attendance_code_expires_at',
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
        'is_registration_closed' => 'boolean',
        'is_paid' => 'boolean',
        'attendance_code_expires_at' => 'datetime',
    ];

    /**
     * Learning types that run without a check-in step.
     *
     * Workshops and consultations have no attendance to take, but the client
     * still wants their hours in the organizer's counters, so registrations are
     * treated as attended once the opportunity completes.
     *
     * Production merged "Class" and "Workshop" into one choice, `Class/Workshop`
     * — this exact-match list predates that and silently stopped matching it
     * (BE-47 part 2). Kept alongside the legacy singular names in case any
     * environment still seeds those instead.
     */
    public const NO_CHECK_IN_TYPES = ['workshop', 'consultation', 'class', 'class/workshop'];

    public function requiresCheckIn(): bool
    {
        $type = $this->learningType?->value_en;

        if (! $type) {
            return true;
        }

        $normalized = strtolower(trim($type));

        // A label match against user-editable master data will keep drifting;
        // "contains" tolerates separator/spacing variants of the merged
        // choice ("Class/Workshop", "Class / Workshop", ...) without needing
        // to enumerate every one.
        foreach (self::NO_CHECK_IN_TYPES as $noCheckInType) {
            if (str_contains($normalized, $noCheckInType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * BE-62: match on the stable slug rather than the editable label — the
     * exact bug class this ticket exists to close (BE-47 part 2's
     * Class/Workshop merge, and the isConsultationType() typo before that).
     * Falls back to the old label match only if a row hasn't been backfilled
     * with a slug yet.
     */
    public function isInternship(): bool
    {
        $type = $this->learningType;

        if (! $type) {
            return false;
        }

        if ($type->slug) {
            return $type->slug === 'internship';
        }

        return strtolower(trim((string) $type->value_en)) === 'internship';
    }

    /**
     * BE-61 Part B: the single-code self check-in applies to Course,
     * Class/Workshop and Consultation — every type except Internship, which
     * stays manual-only.
     */
    public function qrAttendanceEligible(): bool
    {
        return ! $this->isInternship();
    }

    /**
     * Issue (or re-issue) the 2-hour code, invalidating whatever code was
     * live before. The client's assumption, pending confirmation, is that
     * the organizer can press the button again for a fresh window — so this
     * always overwrites rather than refusing while a code is still valid.
     */
    public function issueAttendanceCode(): void
    {
        do {
            $code = Str::random(40);
        } while (self::query()->where('attendance_code', $code)->exists());

        $this->attendance_code = $code;
        $this->attendance_code_expires_at = now()->addHours(2);
        $this->save();
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

    /**
     * Tags as the platform actually stores them: master_choices rows, the same
     * vocabulary /api/choices/* serves. `interests()` above is the legacy table
     * kept only so historical rows still resolve.
     */
    public function masterInterests(): BelongsToMany
    {
        return $this->belongsToMany(MasterChoice::class, 'master_choice_learn_serve_opportunity');
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

<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsor extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'sponsor_type_id',
        'org_name',
        'org_type_id',
        'person_name',
        'email',
        'country_code',
        'phone_number',
        'type_of_support_id',
        'sponsorship_details',
        'why_interested',
        'resources_expected',
        'sponsor_logo',
        'approval_status',
        'preferred_language',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'approval_status' => ApprovalStatus::class,
        'preferred_language' => Language::class,
    ];

    public function sponsorType(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'sponsor_type_id');
    }

    public function orgType(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'org_type_id');
    }

    public function typeOfSupport(): BelongsTo
    {
        return $this->belongsTo(MasterChoice::class, 'type_of_support_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SponsorDocument::class, 'partner_id');
    }
}

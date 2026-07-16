<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunitySponsorImage extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'volunteer_opportunity_id', 'learn_serve_opportunity_id', 'image',
        'organization_id', 'position', 'is_deleted', 'deleted_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class, 'organization_id');
    }

    public function volunteerOpportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class);
    }

    public function learnServeOpportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class);
    }
}

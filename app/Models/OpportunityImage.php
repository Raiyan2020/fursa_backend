<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityImage extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'volunteer_opportunity_id', 'learn_serve_opportunity_id', 'image',
        'is_after_completed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_after_completed' => 'boolean'];

    public function volunteerOpportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class);
    }

    public function learnServeOpportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSponsorImage extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'event_id', 'image', 'organization_id', 'position', 'is_deleted', 'deleted_at',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class, 'organization_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDocument extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'organizer_profile_id',
        'document',
        'uploaded_at',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class, 'organizer_profile_id');
    }
}

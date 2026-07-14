<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorDocument extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'partner_id',
        'document',
        'uploaded_at',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'partner_id');
    }
}

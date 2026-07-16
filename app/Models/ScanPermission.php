<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanPermission extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id', 'opportunity_id', 'event_id', 'is_allowed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_allowed' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

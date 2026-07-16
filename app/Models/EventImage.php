<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventImage extends Model
{
    use HasSoftFlags;

    protected $fillable = ['event_id', 'image', 'is_deleted', 'deleted_at'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

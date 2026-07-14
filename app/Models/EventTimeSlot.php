<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTimeSlot extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'event_id', 'date', 'start_time', 'end_time', 'participants_needed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['date' => 'date'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

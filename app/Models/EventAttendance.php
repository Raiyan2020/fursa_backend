<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    use HasSoftFlags;

    protected $table = 'event_attendances';

    protected $fillable = [
        'registration_id', 'attended_date', 'total_hours', 'is_attended', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'attended_date' => 'date',
        'is_attended' => 'boolean',
        'total_hours' => 'float',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}

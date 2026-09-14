<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'event_id', 'user_id', 'time_slot_id', 'registration_date',
        'registration_status', 'payment_status', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'registration_status' => ApprovalStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(EventTimeSlot::class, 'time_slot_id');
    }
}

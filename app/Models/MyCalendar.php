<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyCalendar extends Model
{
    use HasSoftFlags;

    protected $table = 'my_calendars';

    protected $fillable = [
        'user_id', 'volunteer_opportunity_id', 'learn_serve_opportunity_id',
        'event_id', 'is_saved', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_saved' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function volunteerOpportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class);
    }

    public function learnServeOpportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

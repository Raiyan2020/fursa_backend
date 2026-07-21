<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedbackLike extends Model
{
    use HasSoftFlags;

    protected $table = 'event_feedback_likes';

    protected $fillable = [
        'user_id', 'feedback_id', 'is_liked', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_liked' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(EventFeedback::class, 'feedback_id');
    }
}

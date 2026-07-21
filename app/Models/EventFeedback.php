<?php

namespace App\Models;

use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventFeedback extends Model
{
    use HasSoftFlags;

    protected $table = 'event_feedbacks';

    protected $fillable = [
        'user_id', 'event_id', 'rating', 'comment_en', 'comment_ar',
        'primary_language', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['primary_language' => Language::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(EventFeedbackLike::class, 'feedback_id');
    }
}

<?php

namespace App\Models;

use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpportunityFeedback extends Model
{
    use HasSoftFlags;

    protected $table = 'opportunity_feedbacks';

    protected $fillable = [
        'user_id', 'learn_serve_opportunity_id', 'rating', 'comment_en', 'comment_ar',
        'primary_language', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['primary_language' => Language::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(LearnServeOpportunity::class, 'learn_serve_opportunity_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(FeedbackLike::class, 'feedback_id');
    }
}

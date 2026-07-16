<?php

namespace App\Models;

use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id', 'title_en', 'title_ar', 'idea_text_en', 'idea_text_ar',
        'primary_language', 'proposing_idea', 'needs_support', 'is_funding_required',
        'is_displayed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'primary_language' => Language::class,
        'proposing_idea' => 'boolean',
        'needs_support' => 'boolean',
        'is_funding_required' => 'boolean',
        'is_displayed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CommunityTag::class, 'community_tag_post');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityLike::class);
    }
}

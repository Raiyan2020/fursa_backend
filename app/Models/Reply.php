<?php

namespace App\Models;

use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reply extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_id', 'post_id', 'parent_id', 'text_en', 'text_ar',
        'primary_language', 'is_displayed', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'primary_language' => Language::class,
        'is_displayed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reply::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Reply::class, 'parent_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReplyImage::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityLike::class);
    }
}

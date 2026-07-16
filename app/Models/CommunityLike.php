<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityLike extends Model
{
    use HasSoftFlags;

    protected $table = 'likes';

    protected $fillable = [
        'user_id', 'post_id', 'reply_id', 'is_liked', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['is_liked' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(Reply::class);
    }
}

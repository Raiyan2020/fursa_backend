<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostImage extends Model
{
    use HasSoftFlags;

    protected $fillable = ['post_id', 'image', 'is_deleted', 'deleted_at'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

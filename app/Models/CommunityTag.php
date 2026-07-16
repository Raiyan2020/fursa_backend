<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommunityTag extends Model
{
    use HasSoftFlags;

    protected $table = 'community_tags';

    protected $fillable = [
        'name',
        'is_deleted',
        'deleted_at',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'community_tag_post');
    }
}

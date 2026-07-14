<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class CommunityTag extends Model
{
    use HasSoftFlags;

    protected $table = 'community_tags';

    protected $fillable = [
        'name',
        'is_deleted',
        'deleted_at',
    ];
}

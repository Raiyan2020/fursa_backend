<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class BannerImage extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'image',
        'name',
        'banner_url',
        'is_deleted',
        'deleted_at',
    ];
}

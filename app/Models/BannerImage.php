<?php

namespace App\Models;

use App\Http\Traits\UploadTrait;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class BannerImage extends Model
{
    use HasSoftFlags, UploadTrait;

    /** Used by UploadTrait when assigning an UploadedFile to `image`. */
    protected string $uploadFolder = 'banners';

    protected $fillable = [
        'image',
        'name',
        'banner_url',
        'is_deleted',
        'deleted_at',
    ];
}

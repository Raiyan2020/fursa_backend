<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'slug',
        'title_en',
        'title_ar',
        'content_en',
        'content_ar',
        'is_deleted',
        'deleted_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

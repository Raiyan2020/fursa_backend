<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'slug',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'image',
        'sort_order',
        'is_deleted',
        'deleted_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

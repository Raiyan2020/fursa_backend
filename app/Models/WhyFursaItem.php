<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class WhyFursaItem extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'title_en',
        'title_ar',
        'icon',
        'sort_order',
        'is_deleted',
        'deleted_at',
    ];
}

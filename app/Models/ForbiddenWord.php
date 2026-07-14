<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class ForbiddenWord extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'word_en',
        'word_ar',
        'is_deleted',
        'deleted_at',
    ];
}

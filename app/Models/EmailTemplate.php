<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'name',
        'language',
        'subject',
        'content',
        'is_deleted',
        'deleted_at',
    ];
}

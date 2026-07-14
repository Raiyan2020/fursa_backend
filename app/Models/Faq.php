<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'question_en',
        'question_ar',
        'answer_en',
        'answer_ar',
        'is_deleted',
        'deleted_at',
    ];
}

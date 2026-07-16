<?php

namespace App\Models;

use App\Enums\Language;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class ContactUs extends Model
{
    use HasSoftFlags;

    protected $table = 'contact_us';

    protected $fillable = [
        'name_en', 'name_ar', 'email', 'message_en', 'message_ar',
        'primary_language', 'is_deleted', 'deleted_at',
    ];

    protected $casts = ['primary_language' => Language::class];
}

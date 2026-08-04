<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'tiktok_url',
        'twitter_url',
        'youtube_url',
        'instagram_url',
        'copyright_en',
        'copyright_ar',
        'contact_email',
        'contact_phone',
        'contact_whatsapp',
        'contact_address_en',
        'contact_address_ar',
        'contact_page_text_en',
        'contact_page_text_ar',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([]);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'title_en',
        'title_ar',
        'message_en',
        'message_ar',
        'is_deleted',
        'deleted_at',
    ];

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}

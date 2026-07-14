<?php

namespace App\Models;

use App\Enums\UserType;
use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class UserTypeApproval extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_type',
        'requires_approval',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'user_type' => UserType::class,
    ];
}

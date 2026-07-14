<?php

namespace App\Models;

use App\Models\Concerns\HasSoftFlags;
use Illuminate\Database\Eloquent\Model;

class UserRoleLicenseRequirement extends Model
{
    use HasSoftFlags;

    protected $fillable = [
        'user_role',
        'license_required',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'license_required' => 'boolean',
    ];
}

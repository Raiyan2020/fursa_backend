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

    public function roleLabel(): string
    {
        return match ($this->user_role) {
            'volunteer' => __('admin.user_types.volunteer'),
            'organization' => __('admin.user_types.organization'),
            'volunteer_team' => __('admin.user_types.volunteer_team'),
            'community' => 'Community',
            default => (string) $this->user_role,
        };
    }
}

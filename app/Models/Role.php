<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public const SUPER_ADMIN = 'super-admin';

    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}

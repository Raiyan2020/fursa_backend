<?php

namespace App\Models;

use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable implements CanResetPasswordContract
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    protected $guard_name = 'admin';

    public function isSuperAdmin(): bool
    {
        return (int) $this->id === 1 || $this->hasRole(Role::SUPER_ADMIN);
    }

    public function scopeWithoutSuperAdmin($query)
    {
        return $query->where('id', '!=', 1);
    }

    /**
     * Reset links point at the dashboard, not the public site.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }
}

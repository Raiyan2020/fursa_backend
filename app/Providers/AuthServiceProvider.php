<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\ExpiringToken;
use App\Models\Role;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user instanceof Admin && $user->hasRole(Role::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });

        Auth::viaRequest('expiring-token', function (Request $request) {
            $header = $request->header('Authorization');
            if (! $header) {
                return null;
            }

            $key = null;
            if (Str::startsWith($header, 'Bearer ')) {
                $key = trim(Str::after($header, 'Bearer '));
            } elseif (Str::startsWith($header, 'Token ')) {
                $key = trim(Str::after($header, 'Token '));
            }

            if ($key === null || $key === '') {
                return null;
            }

            $token = ExpiringToken::query()->with('user')->where('key', $key)->first();
            if (! $token || $token->isExpired() || ! $token->user) {
                return null;
            }

            return $token->user;
        });
    }
}

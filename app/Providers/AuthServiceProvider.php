<?php

namespace App\Providers;

use App\Models\ExpiringToken;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        Auth::viaRequest('expiring-token', function (Request $request) {
            $header = $request->header('Authorization');
            if (! $header) {
                return null;
            }

            if (! Str::startsWith($header, 'Token ')) {
                return null;
            }

            $key = trim(Str::after($header, 'Token '));
            if ($key === '') {
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

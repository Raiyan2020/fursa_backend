<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->prefix('dashboard')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Credential endpoints (login, OTP verification). Limited per account
        // *and* per IP: the IP limit alone let an attacker rotate addresses
        // while the cost to any one account stayed zero.
        RateLimiter::for('auth-attempts', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth-email:'.($email !== '' ? $email : $request->ip())),
            ];
        });

        // Endpoints that send mail on request. Tighter, because the abuse here
        // is using the platform to mail-bomb a third party rather than guessing.
        RateLimiter::for('auth-send', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(3)->by('send-ip:'.$request->ip()),
                Limit::perHour(10)->by('send-email:'.($email !== '' ? $email : $request->ip())),
            ];
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();

        // BE-59 — every admin page needs the unread-notifications badge, not
        // just the dashboard home; a view composer keeps that one query out
        // of every controller rather than duplicating it per action.
        View::composer('dashboard.layout.sidebar', function ($view) {
            $admin = Auth::guard('admin')->user();
            $view->with('adminUnreadNotificationsCount', $admin?->unreadNotificationsCount() ?? 0);
        });
    }
}

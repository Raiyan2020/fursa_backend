<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Localization
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', 'ar');

        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

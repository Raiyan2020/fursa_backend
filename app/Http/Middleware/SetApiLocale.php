<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Lang')
            ?? $request->header('Accept-Language')
            ?? $request->query('lang')
            ?? 'ar';

        // Accept-Language can be "ar-KW,ar;q=0.9"
        $locale = strtolower(substr(trim(explode(',', (string) $locale)[0]), 0, 2));

        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

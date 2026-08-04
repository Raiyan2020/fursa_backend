<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Prefer explicit client language headers/query over browser Accept-Language,
        // so UI language toggles (EN/AR) control digit localization correctly.
        $locale = $request->header('Lang')
            ?? $request->header('X-Lang')
            ?? $request->query('lang')
            ?? $request->header('Accept-Language')
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

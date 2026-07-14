<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        return route('login');
    }

    protected function unauthenticated($request, array $guards)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            throw new HttpResponseException(ApiResponse::error(
                'Authentication credentials were not provided.',
                'لم يتم تقديم بيانات الاعتماد.',
                401
            ));
        }

        parent::unauthenticated($request, $guards);
    }
}

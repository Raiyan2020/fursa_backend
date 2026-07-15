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

        if ($request->is('dashboard*')) {
            return route('admin.login');
        }

        return route('admin.login');
    }

    protected function unauthenticated($request, array $guards)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            throw new HttpResponseException(ApiResponse::fail(
                __('apis.unauthenticated'),
                401
            ));
        }

        parent::unauthenticated($request, $guards);
    }
}

<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;
use ValueError;

class Handler extends ExceptionHandler
{
    protected $levels = [];

    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::fail(
                    __('apis.validation_error'),
                    422,
                    $e->errors()
                );
            }
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::fail(__('apis.unauthenticated'), 401);
            }
        });

        $this->renderable(function (ValueError $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::fail(
                    __('apis.validation_error'),
                    422,
                    ['error' => [$e->getMessage()]]
                );
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}

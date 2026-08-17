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

        $this->renderable(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            $message = __('The uploaded file is too large. Please upload an image smaller than 10 MB.');

            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::fail($message, 413);
            }

            return response()->view('errors.413', [], 413);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}

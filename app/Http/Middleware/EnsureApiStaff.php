<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the administrative API routes on the authenticated user's staff flag.
 *
 * BE-29: the contact-us read/update/delete methods and the sponsor mutations sat
 * outside every auth group with no authorization in either controller, so anyone
 * could list, edit or delete contact submissions and sponsors. Runs after
 * `auth:api`, so a missing token is already a 401 by the time this is reached.
 */
class EnsureApiStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_staff) {
            return ApiResponse::error(
                'This action requires staff privileges.',
                'هذا الإجراء يتطلب صلاحيات إدارية.',
                403
            );
        }

        return $next($request);
    }
}

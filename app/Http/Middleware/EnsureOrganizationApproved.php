<?php

namespace App\Http\Middleware;

use App\Support\OrganizationApprovalGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks organization approval on privileged writes.
 *
 * Login alone was not enough: a token issued while approved kept working after
 * the admin rejected the organization, so the rejection had no effect for up to
 * 30 days. Checking per request closes that window even for tokens that predate
 * the status change.
 */
class EnsureOrganizationApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($denial = OrganizationApprovalGate::denialResponse($request->user())) {
            return $denial;
        }

        return $next($request);
    }
}

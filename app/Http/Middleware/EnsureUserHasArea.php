<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Declarative route-level area gate, for business rules that are permanently
 * restricted to a single area (today: Oil Audit is WWD-only) — NOT a
 * general-purpose authorization mechanism for the many-area case, which is
 * handled by App\Support\AreaAuthorizationScope / per-model scopes instead.
 *
 * Usage: 'area:WWD' alongside 'role:...' in a route middleware group. ADMIN
 * always passes (see User::hasArea()).
 */
class EnsureUserHasArea
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        if (! Auth::check()) {
            abort(403, 'Unauthorized');
        }

        if (! Auth::user()->hasArea($area)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}

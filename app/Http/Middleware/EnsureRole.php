<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only for users with one of the given roles, e.g. "role:admin" or "role:learner,admin".
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role, array_map(Role::from(...), $roles), true), 403);

        return $next($request);
    }
}

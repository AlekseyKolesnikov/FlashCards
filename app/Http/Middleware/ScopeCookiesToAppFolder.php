<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without SESSION_PATH, the login and XSRF cookies belong to the folder the app is served from,
 * so they do not clash with a website around it (SPECIFICATION.md §10.2).
 */
class ScopeCookiesToAppFolder
{
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(config('session.path'))) {
            config(['session.path' => $request->getBasePath() ?: '/']);
        }

        return $next($request);
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The API runs in the web middleware group: the React app logs in with
        // Laravel's session cookie and sends the XSRF-TOKEN cookie back as a header.
        then: function () {
            Route::middleware('web')->prefix('api')->name('api.')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        $middleware->web(prepend: [\App\Http\Middleware\ScopeCookiesToAppFolder::class]);

        // The browser sets the theme cookie (resources/js/theme.ts), so it is not encrypted.
        $middleware->encryptCookies(except: ['theme']);

        // Full URLs, so the redirect stays inside the folder the app runs in.
        $middleware->redirectGuestsTo(fn () => url('/app/login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();

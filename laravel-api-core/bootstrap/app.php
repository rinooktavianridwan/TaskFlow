<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        });
    })
    ->create();


<?php

use App\Domain\Identity\Http\Middleware\CheckPermission;
use App\Domain\Identity\Http\Middleware\EnsureEmailIsVerified;
use App\Domain\Identity\Http\Middleware\EnsureMemberApproved;
use App\Support\Api\Http\Middleware\AssignRequestId;
use App\Support\Api\Http\Middleware\TryAuthenticateSanctum;
use App\Support\Api\Http\Middleware\VerifyFrontendSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            AssignRequestId::class,
            VerifyFrontendSecret::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'optional-auth' => TryAuthenticateSanctum::class,
            // Overrides Laravel's default `verified` alias, which returns a
            // differently-shaped `{"message": "..."}` body — this app's
            // other 403s (CheckPermission) always shape errors as
            // `{"errors": [{"code", "message"}]}`, so this keeps that
            // consistent.
            'verified' => EnsureEmailIsVerified::class,
            'approved' => EnsureMemberApproved::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();

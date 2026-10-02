<?php

namespace App\Support\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a request as coming from the Next.js BFF's trusted server-to-server
 * channel. The secret is a static shared value; rotating/short-lived signing
 * is documented remaining work.
 */
class VerifyFrontendSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.frontend.internal_secret');

        if ($expected && hash_equals($expected, (string) $request->header('X-Internal-Secret'))) {
            $request->attributes->set('frontend_internal_call', true);
        }

        return $next($request);
    }
}

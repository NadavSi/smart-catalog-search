<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Defence in depth: even if an XSS slipped in, the CSP keeps foreign scripts and connections out. */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' https://fonts.googleapis.com",
        'font-src https://fonts.gstatic.com',
        // Product images come from whatever hosts the catalog points at; data: is the placeholder and favicon.
        'img-src https: data:',
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'none'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'Content-Security-Policy' => implode('; ', self::CSP),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);

        return $response;
    }
}

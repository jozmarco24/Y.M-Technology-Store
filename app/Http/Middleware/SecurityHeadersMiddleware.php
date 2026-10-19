<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach production security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Cabeceras recomendadas de seguridad OWASP
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS solo en HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy compatible con Culqi, Niubiz y CDN
        $csp = "default-src 'self' https: data:; ".
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://checkout.culqi.com https://js.culqi.com https://cdn.tailwindcss.com; ".
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
               "font-src 'self' https://fonts.gstatic.com data:; ".
               "img-src 'self' data: https: blob:; ".
               "connect-src 'self' https://api.culqi.com https://checkout.culqi.com; ".
               "frame-src 'self' https://checkout.culqi.com;";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

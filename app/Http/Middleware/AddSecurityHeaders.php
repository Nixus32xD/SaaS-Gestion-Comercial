<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setHeaderIfMissing($response, 'X-Frame-Options', 'SAMEORIGIN');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production')) {
            $this->setHeaderIfMissing($response, 'Content-Security-Policy', $this->contentSecurityPolicy());

            if ($request->isSecure()) {
                $this->setHeaderIfMissing($response, 'Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "script-src 'self' 'unsafe-inline'",
            "connect-src 'self' https://api.mercadopago.com",
            'upgrade-insecure-requests',
        ]);
    }

    private function setHeaderIfMissing(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}

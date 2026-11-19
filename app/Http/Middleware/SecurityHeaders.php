<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the response headers that stop a page from being framed, sniffed as
 * another type, or running script we did not write.
 *
 * The CSP is enforced, not report-only, so every inline <script> must carry the
 * per-response nonce exposed to Blade as $cspNonce. Inline *style attributes*
 * (34 of them) still need 'unsafe-inline' in style-src; removing that is a
 * separate cleanup.
 */
class SecurityHeaders
{
    /**
     * Hosts the pages load fonts from today. Self-hosting them would let the
     * policy drop to 'self' only; see README "Known gaps".
     */
    private const FONT_STYLES = [
        'https://fonts.googleapis.com',
        'https://db.onlinewebfonts.com',
        'https://cdnjs.cloudflare.com',
    ];

    /**
     * The sign-in and 2FA pages play their background clip from here. Moving the
     * file under public/ would let media-src drop to 'self'.
     */
    private const HERO_MEDIA = 'https://d8j0ntlcm91z4.cloudfront.net';

    private const FONT_FILES = [
        'https://fonts.gstatic.com',
        'https://db.onlinewebfonts.com',
        'https://cdnjs.cloudflare.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        $request->attributes->set('cspNonce', $nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($nonce),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        foreach ($headers as $name => $value) {
            // Never overwrite a header a controller set on purpose.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // HSTS on a plain-HTTP local install would pin the browser to https for
        // localhost; only send it once the request actually arrived over TLS.
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$nonce."'",
            "style-src 'self' 'unsafe-inline' ".implode(' ', self::FONT_STYLES),
            "font-src 'self' data: ".implode(' ', self::FONT_FILES),
            "img-src 'self' data: blob:",
            "media-src 'self' ".self::HERO_MEDIA,
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}

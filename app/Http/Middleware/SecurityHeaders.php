<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening headers for every response (design D3).
 *
 * Registered on the GLOBAL middleware stack, not the `web` group: Filament's
 * panel routes carry their own middleware list, so a group-scoped
 * registration would leave /admin — the surface that matters most — bare.
 *
 * No Content-Security-Policy. Filament and Livewire emit inline scripts and
 * styles, so a correct policy needs nonce propagation through Filament's
 * asset pipeline; a wrong one silently breaks the panel. Deferred with
 * rationale in DESPLIEGUE.md.
 */
class SecurityHeaders
{
    /**
     * One year. Long enough to be meaningful, and it only ever reaches a
     * browser that already spoke HTTPS to us.
     */
    private const HSTS_MAX_AGE = 31536000;

    private const HEADERS = [
        // Stops a browser re-interpreting an uploaded file as HTML/JS
        // regardless of the type it was served with — the second layer behind
        // the upload allow-list.
        'X-Content-Type-Options' => 'nosniff',
        // SAMEORIGIN rather than DENY: the panel is same-origin throughout,
        // and DENY would break any future preview pane.
        'X-Frame-Options' => 'SAMEORIGIN',
        // Keeps /admin/... paths out of third-party referrer logs.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // The app uses none of these; denying them costs nothing.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Only over a connection that is already secure. Sending HSTS over
        // plain HTTP, or before a certificate exists, pins every visiting
        // browser to HTTPS for a year against a site that may not serve it —
        // an outage that cannot be withdrawn by removing the header.
        // `preload` is omitted deliberately: it is irreversible on a timescale
        // measured in browser release cycles.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains',
            );
        }

        return $response;
    }
}

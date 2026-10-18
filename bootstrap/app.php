<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global stack, not ->web(append: ...): Filament's panel routes run on
        // the panel's own middleware list and would otherwise be left without
        // hardening headers.
        $middleware->append(SecurityHeaders::class);

        // Proxies: NONE trusted by default. In Hostinger, LiteSpeed receives
        // the visitor directly, so an X-Forwarded-For header can only come from
        // the visitor — and trusting it (the old `'*'`, inherited from Vercel,
        // whose edge overwrote that header) let anyone pick their own IP: a
        // fresh 60 req/min bucket per request, five fresh login attempts per
        // request, and made-up addresses in the `login.failed` log lines.
        //
        // If a CDN or proxy is ever put in front, list its ranges in
        // TRUSTED_PROXIES (config/trustedproxy.php, which Laravel's
        // TrustProxies middleware reads by itself). The two pieces still go
        // together: without the proxy trusted, X-Forwarded-Proto is ignored
        // too, and APP_FORCE_HTTPS is what keeps generated URLs on https.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `expectsJson()` además de api/*: esto REEMPLAZA la regla de Laravel,
        // no la amplía. Sólo con api/*, un error de validación de
        // /push/subscribe —que `push.js` pide en JSON— volvía como una
        // redirección a HTML, y el navegador se quedaba sin el mensaje.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

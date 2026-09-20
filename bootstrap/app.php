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

        // Vercel terminates TLS and proxies every request, so untrusted the
        // application sees the proxy instead of the visitor. That has two
        // consequences, and the second is the dangerous one:
        //
        //   (a) URLs. X-Forwarded-Proto is ignored, Laravel generates http://
        //       URLs and can enter a redirect loop. This is the other half of
        //       APP_FORCE_HTTPS — applying one without the other is a half fix.
        //
        //   (b) Rate limiting. ThrottleRequests keys anonymous visitors on
        //       $request->ip(). Untrusted, that returns the proxy's address for
        //       everyone, so the 60 req/min of routes/web.php would stop being
        //       per visitor and become one global cap for the whole site: 429
        //       for everybody under modest traffic.
        //
        // '*' rather than an address list because Vercel publishes no stable
        // proxy range. It is safe here only because the container is
        // unreachable except through Vercel's edge, which overwrites
        // X-Forwarded-For instead of passing a client-supplied one through. On
        // a host without that guarantee, '*' would let anyone forge a fresh
        // throttle bucket per request by varying the header.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

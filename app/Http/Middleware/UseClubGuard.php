<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el guard `club` como el de la petición dentro del panel del técnico.
 *
 * Filament ya lo hace, pero en su middleware `Authenticate`, que corre DESPUÉS
 * de la pila del panel. Y en esa pila hay uno que sí depende del guard por
 * defecto: `AuthenticateSession` lee `$request->user()` y
 * `password_hash_<guard>`. Sin esto, en una sesión con las dos puertas abiertas
 * —un administrador en una pestaña y un técnico en otra, que es justo lo que el
 * guard propio viene a permitir— vigilaría la sesión del ADMINISTRADOR mientras
 * atiende una petición del técnico, y la del técnico no la vigilaría nadie.
 */
class UseClubGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('club');

        return $next($request);
    }
}

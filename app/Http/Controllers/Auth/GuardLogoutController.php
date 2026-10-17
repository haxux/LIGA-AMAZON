<?php

namespace App\Http\Controllers\Auth;

use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * Salir de UNA puerta sin tirar la otra.
 *
 * El cierre de sesión de Filament hace `session()->invalidate()`, que vacía la
 * sesión ENTERA. Con dos guards en el mismo navegador —un administrador en
 * `/admin` y un técnico en `/club`, que es justo lo que el guard propio de cada
 * panel viene a permitir— salir de uno cerraba también el otro, y el que
 * seguía trabajando se encontraba con el login sin haber hecho nada.
 *
 * Aquí sale sólo el guard del panel. La sesión se invalida entera únicamente
 * cuando era la última puerta abierta; si queda otra, se cambia el id de sesión
 * (contra fijación) y se conserva el resto.
 */
class GuardLogoutController extends LogoutController
{
    public function __invoke(): LogoutResponse
    {
        $guard = Filament::getAuthGuard();

        Filament::auth()->logout();

        // `logout()` quita al usuario pero deja la huella de contraseña que
        // `AuthenticateSession` vigila: sin borrarla, la próxima sesión de ese
        // guard se compararía con un hash que ya no es suyo.
        session()->forget('password_hash_'.$guard);

        $otherDoorOpen = collect(array_keys(config('auth.guards')))
            ->reject(fn (string $other): bool => $other === $guard)
            ->contains(fn (string $other): bool => Auth::guard($other)->check());

        $otherDoorOpen ? session()->regenerate() : session()->invalidate();
        session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}

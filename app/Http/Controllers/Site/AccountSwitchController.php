<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * El selector de cuentas de la cabecera (decisión del propietario,
 * 2026-10-23): pasar de la cuenta de técnico a la de administrador y vuelta
 * sin volver a escribir la contraseña, añadir la otra cuenta y salir de una
 * sin tirar la otra.
 *
 * Cambiar no abre ninguna sesión: sólo elige cuál de las YA abiertas usa el
 * sitio (ver SiteAccount). Entrar en una cuenta sigue pidiendo su contraseña,
 * siempre, en su propia puerta.
 */
class AccountSwitchController extends Controller
{
    /** Adónde volver al entrar desde «Añadir otra cuenta» (PanelLoginResponse). */
    public const RETURN_KEY = 'site.account_return';

    /** Las pantallas de acceso de cada puerta. */
    private const LOGIN_ROUTES = [
        'club' => 'filament.club.auth.login',
        'web' => 'filament.admin.auth.login',
    ];

    public function switch(Request $request, SiteAccount $accounts): RedirectResponse
    {
        $data = $request->validate(['guard' => ['required', Rule::in(array_keys(SiteAccount::GUARDS))]]);

        $accounts->switchTo($data['guard']);

        return redirect()->back(fallback: route('site.home'));
    }

    public function add(Request $request, string $guard): RedirectResponse
    {
        abort_unless(array_key_exists($guard, self::LOGIN_ROUTES), 404);

        session()->put(self::RETURN_KEY, url()->previous(route('site.home')));

        return redirect()->route(self::LOGIN_ROUTES[$guard]);
    }

    /**
     * Sale sólo de la cuenta activa, y la otra —si hay— pasa a ser la activa.
     * Igual que GuardLogoutController: la sesión entera se invalida únicamente
     * si era la última cuenta abierta.
     */
    public function logout(Request $request, SiteAccount $accounts): RedirectResponse
    {
        $guard = $accounts->activeGuard();

        if ($guard !== null) {
            Auth::guard($guard)->logout();
            session()->forget('password_hash_'.$guard);
        }

        $otherOpen = $accounts->accounts()->isNotEmpty();

        $otherOpen ? session()->regenerate() : session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('site.home');
    }
}

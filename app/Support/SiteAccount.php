<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Las cuentas abiertas en este navegador y cuál está usando el sitio público.
 *
 * Un mismo navegador puede tener a la vez la sesión de técnico (guard `club`)
 * y la de administrador (guard `web`): cada panel entra por su puerta. Hasta el
 * 2026-10-23 cada pantalla elegía por su cuenta —«con las dos, manda la del
 * técnico»— y quien tiene las dos cuentas, como el presidente que además
 * dirige un club, no podía usar el sitio como presidente sin cerrar la otra.
 *
 * Ahora la cuenta activa se elige (selector de cuentas de la cabecera, ver
 * AccountSwitchController) y se guarda en la sesión. Sin elección manda la de
 * técnico, que es lo que el sitio hacía siempre. Y todo el sitio pregunta aquí,
 * en un solo sitio, en vez de repetir la regla.
 */
final class SiteAccount
{
    /** Las puertas que el sitio conoce, y cómo se llama cada una. */
    public const GUARDS = [
        'club' => 'Técnico',
        'web' => 'Administración',
    ];

    private const SESSION_KEY = 'site.account';

    /**
     * Las cuentas abiertas que el sitio puede usar: la de técnico si dirige un
     * club, la de administrador si lo es. Cada una con su puerta.
     *
     * @return Collection<string, User>  por guard
     */
    public function accounts(): Collection
    {
        $coach = auth('club')->user();
        $admin = auth('web')->user();

        return collect([
            'club' => $coach?->isCoach() ? $coach : null,
            'web' => $admin?->isAdmin() ? $admin : null,
        ])->filter();
    }

    public function activeGuard(): ?string
    {
        $accounts = $this->accounts();
        $chosen = session(self::SESSION_KEY);

        if (is_string($chosen) && $accounts->has($chosen)) {
            return $chosen;
        }

        return $accounts->keys()->first();
    }

    /** La cuenta con la que el sitio funciona ahora, o null sin sesión. */
    public function active(): ?User
    {
        $guard = $this->activeGuard();

        return $guard === null ? null : $this->accounts()->get($guard);
    }

    /** El técnico, sólo si es la cuenta activa. */
    public function coach(): ?User
    {
        return $this->activeGuard() === 'club' ? $this->active() : null;
    }

    /** El administrador, sólo si es la cuenta activa. */
    public function admin(): ?User
    {
        return $this->activeGuard() === 'web' ? $this->active() : null;
    }

    /** Pasa a usar otra cuenta ya abierta. False si esa puerta no tiene sesión. */
    public function switchTo(string $guard): bool
    {
        if (! $this->accounts()->has($guard)) {
            return false;
        }

        session()->put(self::SESSION_KEY, $guard);

        return true;
    }

    /** Recién entrada por una puerta, esa pasa a ser la activa. */
    public function remember(string $guard): void
    {
        if (array_key_exists($guard, self::GUARDS)) {
            session()->put(self::SESSION_KEY, $guard);
        }
    }

    /**
     * Una huella de qué cuentas hay abiertas: la usa el navegador para volver
     * a apuntar el teléfono a los avisos del chat cuando cambian (push.js).
     */
    public function fingerprint(): string
    {
        return sha1($this->accounts()->map(fn (User $user) => $user->getKey())->sort()->implode(','));
    }
}

<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UseClubGuard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * El panel del director técnico.
 *
 * Es un panel aparte y no `/admin` con el menú recortado (design D4): esconder
 * un recurso deja su ruta viva, y basta teclear la URL. Aquí los recursos del
 * administrador sencillamente NO están registrados, así que no existen para
 * quien entra por esta puerta. Las políticas por registro son la segunda
 * cerradura, la que impide llegar al club de otro.
 *
 * En español, a diferencia de `/admin`: lo usan los técnicos (design D13).
 */
class ClubPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('club')
            ->path('club')
            // Guard propio, no el `web` de /admin: ver config/auth.php. Sin
            // esto, un administrador con sesión abierta impide que un técnico
            // llegue siquiera al formulario de acceso.
            ->authGuard('club')
            ->login()
            ->brandName('Mi club')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Club/Resources'), for: 'App\Filament\Club\Resources')
            ->discoverPages(in: app_path('Filament/Club/Pages'), for: 'App\Filament\Club\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Antes de AuthenticateSession, que mira el guard por defecto.
                UseClubGuard::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

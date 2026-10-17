<?php

namespace App\Providers;

use App\Http\Controllers\Auth\GuardLogoutController;
use App\Http\Responses\PanelLoginResponse;
use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El ciclo de acceso común a las dos puertas. Filament registra sus
        // piezas por contrato y por clase, y aquí se sustituyen las dos que se
        // enredaban: ver cada una.
        $this->app->bind(LogoutController::class, GuardLogoutController::class);
        $this->app->bind(LoginResponse::class, PanelLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fixes generated URLs only. This is not a redirect, and it does not by
        // itself trust a proxy's forwarded headers — ->trustProxies() in
        // bootstrap/app.php is the matching piece, and its correct value
        // depends on the host, so it is recorded in DESPLIEGUE.md rather than
        // guessed at here. Applying one without the other is a half fix.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}

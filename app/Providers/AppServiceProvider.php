<?php

namespace App\Providers;

use App\Http\Controllers\Auth\GuardLogoutController;
use App\Http\Responses\PanelLoginResponse;
use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\StandingZone;
use App\Models\Team;
use App\Services\StandingsService;
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
        // Fixes generated URLs only. This is not a redirect, and it does not
        // trust any proxy's forwarded headers: that is TRUSTED_PROXIES
        // (config/trustedproxy.php), empty in Hostinger because nothing sits
        // in front of the web server — see bootstrap/app.php.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // La clasificación va en caché (StandingsService::tablesFor). Todo lo
        // que puede cambiar una tabla la invalida al guardarse o borrarse: un
        // resultado, una anulación, una jornada nueva, un equipo que cambia de
        // división, el escudo o el nombre de un club, una zona de ascenso…
        // Nada de esto se toca con actualizaciones en bloque que se salten los
        // eventos (comprobado el 2026-10-21); si alguna vez se hace, hay que
        // llamar a StandingsService::flush() a mano.
        foreach ([Game::class, Matchday::class, Team::class, Club::class, Division::class, StandingZone::class, Season::class] as $model) {
            $model::saved(fn () => StandingsService::flush());
            $model::deleted(fn () => StandingsService::flush());
        }
    }
}

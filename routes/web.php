<?php

use App\Http\Controllers\Site\ChatController;
use App\Http\Controllers\Site\ClubProfileController;
use App\Http\Controllers\Site\ClubsController;
use App\Http\Controllers\Site\CupController;
use App\Http\Controllers\Site\CupsController;
use App\Http\Controllers\Site\FixturesController;
use App\Http\Controllers\Site\GameController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\PlayerController;
use App\Http\Controllers\Site\ScorersController;
use App\Http\Controllers\Site\StandingsController;
use Illuminate\Support\Facades\Route;

/**
 * 60 requests/minute/IP: generous for a reader, a real ceiling on a crawler
 * hammering the uncached standings fold. The panel is deliberately excluded —
 * Filament already throttles login at 5 attempts, and a blanket limit would
 * fire on an administrator doing bulk data entry. /up is registered in
 * bootstrap/app.php, outside this group, so uptime monitoring is never
 * throttled.
 */
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/', StandingsController::class)->name('site.standings');
    Route::get('/partidos', FixturesController::class)->name('site.fixtures');
    // El detalle de un partido, al que se llega desde cualquier tarjeta.
    Route::get('/partidos/{game}', GameController::class)->name('site.games.show');
    Route::get('/estadisticas', ScorersController::class)->name('site.scorers');
    // La ficha de un jugador, a la que se llega desde la plantilla de un club y
    // desde las listas de goleadores y asistentes.
    Route::get('/jugadores/{player}', PlayerController::class)->name('site.players.show');
    Route::get('/equipos', ClubsController::class)->name('site.clubs.index');
    // La pestaña va en la ruta y no en la query: es una página que se enlaza y
    // se comparte, no un filtro. La temporada sí es un filtro, y viaja en
    // ?temporada como en el resto del sitio.
    Route::get('/equipos/{club}/{tab?}', ClubProfileController::class)->name('site.clubs.show');
    // La única página del sitio que pide sesión: el chat de técnicos y
    // presidentes. Estaba dentro de los dos paneles y allí una conversación
    // competía con el menú lateral por un carril estrecho.
    // Las copas, al lado de los equipos: son la otra competición de la
    // temporada, y no caben en la clasificación porque no tienen tabla.
    Route::get('/copas', CupsController::class)->name('site.cups.index');
    Route::get('/copas/{cup}', CupController::class)->name('site.cups.show');
    Route::get('/chat', ChatController::class)->name('site.chat');
    Route::get('/noticias', [NewsController::class, 'index'])->name('site.news.index');
    Route::get('/noticias/{slug}', [NewsController::class, 'show'])->name('site.news.show');
});

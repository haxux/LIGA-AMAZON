<?php

use App\Http\Controllers\Site\ClubProfileController;
use App\Http\Controllers\Site\ClubsController;
use App\Http\Controllers\Site\FixturesController;
use App\Http\Controllers\Site\NewsController;
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
    Route::get('/goleadores', ScorersController::class)->name('site.scorers');
    Route::get('/equipos', ClubsController::class)->name('site.clubs.index');
    // La pestaña va en la ruta y no en la query: es una página que se enlaza y
    // se comparte, no un filtro. La temporada sí es un filtro, y viaja en
    // ?temporada como en el resto del sitio.
    Route::get('/equipos/{club}/{tab?}', ClubProfileController::class)->name('site.clubs.show');
    Route::get('/noticias', [NewsController::class, 'index'])->name('site.news.index');
    Route::get('/noticias/{slug}', [NewsController::class, 'show'])->name('site.news.show');
});

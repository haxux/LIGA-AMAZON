<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Site\AccountSwitchController;
use App\Http\Controllers\Site\ChatController;
use App\Http\Controllers\Site\ClubProfileController;
use App\Http\Controllers\Site\ClubsController;
use App\Http\Controllers\Site\CupController;
use App\Http\Controllers\Site\CupsController;
use App\Http\Controllers\Site\FixturesController;
use App\Http\Controllers\Site\GameCommentController;
use App\Http\Controllers\Site\GameController;
use App\Http\Controllers\Site\HistoryController;
use App\Http\Controllers\Site\HomeController;
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
// El manifest de la web instalable (ver ManifestController). Fuera del límite
// de peticiones: el navegador lo pide solo, al cargar cualquier página.
Route::get('/manifest.webmanifest', ManifestController::class)->name('site.manifest');

Route::middleware('throttle:60,1')->group(function (): void {
    // La raíz es la portada, que es donde lleva el botón de la marca. Hasta el
    // 2026-10-13 era Clasificación; su nombre de ruta no cambió, sólo la
    // dirección, así que los enlaces del sitio siguen valiendo.
    Route::get('/', HomeController::class)->name('site.home');
    Route::get('/clasificacion', StandingsController::class)->name('site.standings');
    // Las clasificaciones de las temporadas cerradas. Aparte de Clasificación y
    // no como un filtro suyo: la tabla de ahora es lo que viene a ver todo el
    // mundo, y el archivo es otra intención.
    Route::get('/historia', HistoryController::class)->name('site.history');
    Route::get('/partidos', FixturesController::class)->name('site.fixtures');
    // El detalle de un partido, al que se llega desde cualquier tarjeta.
    Route::get('/partidos/{game}', GameController::class)->name('site.games.show');
    // Los comentarios de los técnicos sobre el partido. Con un tope propio
    // además del general: es lo único que el sitio deja escribir.
    Route::post('/partidos/{game}/comentarios', [GameCommentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('site.games.comments.store');
    Route::delete('/comentarios/{comment}', [GameCommentController::class, 'destroy'])->name('site.comments.destroy');
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

    // El selector de cuentas de la cabecera (ver AccountSwitchController).
    Route::post('/cuenta/cambiar', [AccountSwitchController::class, 'switch'])->name('site.account.switch');
    Route::get('/cuenta/anadir/{guard}', [AccountSwitchController::class, 'add'])->name('site.account.add');
    Route::post('/cuenta/salir', [AccountSwitchController::class, 'logout'])->name('site.account.logout');
    Route::get('/noticias', [NewsController::class, 'index'])->name('site.news.index');
    Route::get('/noticias/{slug}', [NewsController::class, 'show'])->name('site.news.show');

    // Activar/desactivar notificaciones push desde el navegador (ver
    // PushSubscriptionController): "partidos" es anónimo, "chat" pide la
    // misma sesión que /chat.
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

// GET y no POST: el trabajo cron de hPanel la pide con un `curl` sin cookie de
// sesión (DESPLIEGUE.md §4), y un POST así siempre fallaría la verificación
// CSRF del grupo 'web' —pensada para formularios de navegador, no para esto—.
// Se autentica con el secreto compartido (ver CronController), no con una
// cookie.
Route::get('/cron/tick', [CronController::class, 'tick'])->name('cron.tick');

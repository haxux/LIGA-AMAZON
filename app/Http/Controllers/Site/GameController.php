<?php

namespace App\Http\Controllers\Site;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\SquadMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * El detalle de un partido: lo que pasó en él.
 *
 * Cuando no hay eventos cargados —que es lo normal en un partido recién
 * jugado— la página se queda en el resultado y lo dice, en lugar de enseñar un
 * hueco vacío.
 */
class GameController extends SiteController
{
    public function __invoke(Game $game): View
    {
        $game->load([
            'matchday.division',
            'matchday.season',
            'homeTeam.club.stadium',
            'awayTeam.club',
            'events.player',
            'events.assist.player',
        ]);

        return view('site.games.show', [
            'game' => $game,
            'events' => $this->events($game),
        ]);
    }

    /**
     * Los eventos en el orden del partido, cada uno sabiendo de qué lado cae.
     *
     * El lado no se deduce del club propietario del jugador sino de la
     * plantilla de esa temporada: con una cesión, el dueño es otro club y el
     * gol lo marcó igualmente quien lo alineó (Fase 9).
     *
     * @return Collection<int, array{event: GameEvent, side: string}>
     */
    private function events(Game $game): Collection
    {
        $homeSquad = $this->squadOf($game->home_team_id);
        $awaySquad = $this->squadOf($game->away_team_id);

        return $game->events
            // La portería a cero es una cifra de temporada, no un momento del
            // partido: se queda en las estadísticas del club y del jugador.
            // Una asistencia ya enlazada a su gol se enseña debajo de él y no
            // como fila propia; las de antes de ese enlace siguen sueltas.
            ->reject(fn (GameEvent $event) => $event->type === GameEvent::TYPE_CLEAN_SHEET)
            ->reject(fn (GameEvent $event) => $event->type === GameEvent::TYPE_ASSIST && $event->related_event_id !== null)
            ->sortBy(fn (GameEvent $event) => [
                // Una portería a cero no lleva minuto (lo borra el guard de
                // GameEvent), así que cierra la lista en vez de abrirla.
                $event->minute ?? PHP_INT_MAX,
                $event->getKey(),
            ])
            ->map(fn (GameEvent $event) => [
                'event' => $event,
                'side' => match (true) {
                    $homeSquad->contains($event->player_id) => 'home',
                    $awaySquad->contains($event->player_id) => 'away',
                    default => 'unknown',
                },
            ])
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function squadOf(?int $teamId): Collection
    {
        if ($teamId === null) {
            return collect();
        }

        return SquadMembership::query()->where('team_id', $teamId)->pluck('player_id');
    }
}

<?php

namespace App\Services;

use App\Models\Club;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que un jugador lleva hecho, derivado de `game_events` en el momento.
 *
 * Sin tabla de estadísticas, como la clasificación y como las cifras de un club
 * (design D12): un total guardado se desincroniza en cuanto alguien corrige un
 * evento, y nadie se entera.
 *
 * NO se cuentan partidos jugados: esta aplicación no registra alineaciones por
 * partido, así que cualquier número que se pusiera ahí sería inventado. El once
 * ideal es una alineación tipo, no una lista de convocados.
 */
final class PlayerStatsService
{
    /**
     * Los totales de una temporada, o de toda la carrera si no se pasa ninguna.
     *
     * @return array<string, int>
     */
    public function totals(Player $player, ?Season $season = null): array
    {
        $counts = GameEvent::query()
            ->where('player_id', $player->getKey())
            ->when($season, fn (Builder $query, Season $season) => $query->whereHas(
                'game.matchday',
                fn (Builder $matchday) => $matchday->where('season_id', $season->getKey()),
            ))
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            GameEvent::TYPE_GOAL => (int) ($counts[GameEvent::TYPE_GOAL] ?? 0),
            GameEvent::TYPE_ASSIST => (int) ($counts[GameEvent::TYPE_ASSIST] ?? 0),
            GameEvent::TYPE_YELLOW_CARD => (int) ($counts[GameEvent::TYPE_YELLOW_CARD] ?? 0),
            GameEvent::TYPE_RED_CARD => (int) ($counts[GameEvent::TYPE_RED_CARD] ?? 0),
            GameEvent::TYPE_CLEAN_SHEET => (int) ($counts[GameEvent::TYPE_CLEAN_SHEET] ?? 0),
        ];
    }

    /**
     * Una fila por temporada en la que estuvo inscrito, con el club en el que
     * jugó ESA temporada —que con una cesión no es el club propietario— y lo que
     * hizo en ella.
     *
     * Las temporadas salen de las pertenencias y no de los eventos: un jugador
     * que estuvo en una plantilla sin marcar ni ver una tarjeta también jugó esa
     * temporada, y su fila debe estar, en blanco.
     *
     * @return Collection<int, array{season: Season, club: ?Club, shirt_number: ?int, totals: array<string, int>}>
     */
    public function bySeason(Player $player): Collection
    {
        return SquadMembership::query()
            ->where('player_id', $player->getKey())
            ->with(['team.season', 'team.club'])
            ->get()
            ->filter(fn (SquadMembership $membership) => $membership->team?->season !== null)
            ->sortByDesc(fn (SquadMembership $membership) => [
                $membership->team->season->start_date,
                $membership->team->season->getKey(),
            ])
            ->map(fn (SquadMembership $membership) => [
                'season' => $membership->team->season,
                'club' => $membership->team->club,
                'shirt_number' => $membership->shirt_number,
                'totals' => $this->totals($player, $membership->team->season),
            ])
            ->values();
    }

    /**
     * Sus eventos, el más reciente primero, con el partido al que pertenecen
     * para poder enlazarlo.
     *
     * @return Collection<int, GameEvent>
     */
    public function events(Player $player, ?Season $season = null, int $limit = 20): Collection
    {
        return GameEvent::query()
            ->where('player_id', $player->getKey())
            ->when($season, fn (Builder $query, Season $season) => $query->whereHas(
                'game.matchday',
                fn (Builder $matchday) => $matchday->where('season_id', $season->getKey()),
            ))
            ->with(['game.matchday', 'game.homeTeam.club', 'game.awayTeam.club'])
            ->get()
            ->sortByDesc(fn (GameEvent $event) => [
                $event->game?->matchday?->number ?? 0,
                $event->getKey(),
            ])
            ->take($limit)
            ->values();
    }

    /**
     * El club con el que jugó una temporada concreta: el de su pertenencia, con
     * el club propietario como respaldo (mismo criterio que `ScorerRow`).
     */
    public function clubIn(Player $player, ?Season $season): ?Club
    {
        if ($season === null) {
            return $player->club;
        }

        return $player->memberships()
            ->whereHas('team', fn (Builder $query) => $query->where('season_id', $season->getKey()))
            ->with('team.club')
            ->first()?->team?->club ?? $player->club;
    }
}

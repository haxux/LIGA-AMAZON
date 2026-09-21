<?php

namespace App\Services;

use App\Models\CupGroup;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lo que una copa deriva de sus partidos (Fase 15).
 *
 * El global de una eliminatoria y la tabla de un grupo se calculan en el
 * momento, como la clasificación de una liga: no hay marcador global guardado
 * que pueda discrepar de los partidos que lo forman.
 *
 * Lo único que SÍ se guarda es la decisión de un empate, porque no se deriva de
 * nada: la toma una persona y lleva su motivo.
 */
final class CupService
{
    public function __construct(private readonly StandingsService $standings) {}

    /**
     * Cómo va un cruce. El global suma los partidos jugados desde el lado de
     * cada equipo, que en una ida y vuelta no es el marcador de ninguno de los
     * dos partidos.
     */
    public function result(CupTie $tie): TieResult
    {
        $games = $tie->games()->get();
        $played = $games->filter(fn (Game $game) => $game->home_score !== null && $game->away_score !== null);

        $home = 0;
        $away = 0;

        foreach ($played as $game) {
            $tieHomeIsGameHome = (int) $game->home_team_id === (int) $tie->home_team_id;
            $home += (int) ($tieHomeIsGameHome ? $game->home_score : $game->away_score);
            $away += (int) ($tieHomeIsGameHome ? $game->away_score : $game->home_score);
        }

        $legs = $tie->round?->legs ?? 1;
        $finished = $played->count() >= $legs;

        $winner = match (true) {
            $tie->winner_team_id !== null => $tie->winner,
            $finished && $home > $away => $tie->homeTeam,
            $finished && $away > $home => $tie->awayTeam,
            default => null,
        };

        return new TieResult(
            homeGoals: $home,
            awayGoals: $away,
            played: $played->count(),
            legs: $legs,
            winner: $winner,
            note: $tie->decision_note,
        );
    }

    /**
     * La decisión del administrador sobre una eliminatoria empatada: quién pasa
     * y por qué. El motivo no es un adorno — es lo que se consulta después,
     * cuando nadie se acuerda de por qué pasó aquél.
     */
    public function decide(CupTie $tie, User $admin, Team $winner, string $note): void
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['winner_team_id' => 'Sólo un administrador resuelve una eliminatoria.']);
        }

        if (blank($note)) {
            throw ValidationException::withMessages(['decision_note' => 'Hace falta decir por qué pasa.']);
        }

        $tie->update([
            'winner_team_id' => $winner->getKey(),
            'decision_note' => $note,
        ]);
    }

    /**
     * La tabla de un grupo, con el mismo código que la de una división: un
     * grupo de copa es una liga pequeña, y duplicar el cómputo sería garantizar
     * que los dos se separen.
     *
     * @return Collection<int, StandingRow>
     */
    public function groupTable(CupGroup $group): Collection
    {
        $teams = Team::query()
            ->whereIn('id', $group->participants()->pluck('team_id'))
            ->with('club')
            ->orderBy('id')
            ->get();

        return $this->standings->fromGames($teams, $group->games()->get());
    }
}

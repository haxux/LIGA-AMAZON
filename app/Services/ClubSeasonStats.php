<?php

namespace App\Services;

/**
 * Lo que un club hizo en una temporada, derivado en el momento de `games` y
 * `game_events` (design D12). No hay tabla de estadísticas: mismo criterio que
 * la clasificación, que se deriva y no se guarda (ARQUITECTURA.md §3).
 */
final class ClubSeasonStats
{
    public readonly int $goal_difference;

    public readonly int $points;

    public function __construct(
        public readonly int $played = 0,
        public readonly int $won = 0,
        public readonly int $drawn = 0,
        public readonly int $lost = 0,
        public readonly int $goals_for = 0,
        public readonly int $goals_against = 0,
        public readonly int $yellow_cards = 0,
        public readonly int $red_cards = 0,
        public readonly int $clean_sheets = 0,
    ) {
        // Derivados en el constructor, como en StandingRow, para que no puedan
        // discrepar de los contadores de los que salen.
        $this->goal_difference = $this->goals_for - $this->goals_against;
        $this->points = ($this->won * 3) + $this->drawn;
    }
}

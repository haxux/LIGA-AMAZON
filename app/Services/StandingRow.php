<?php

namespace App\Services;

use App\Models\Team;

/**
 * Immutable per-team standings row. `points` and `goal_difference` are
 * derived in the constructor from the raw counters so they can never
 * disagree with them — see design D1.
 */
final class StandingRow
{
    public readonly int $goal_difference;

    public readonly int $points;

    public function __construct(
        public readonly Team $team,
        public readonly int $played = 0,
        public readonly int $won = 0,
        public readonly int $drawn = 0,
        public readonly int $lost = 0,
        public readonly int $goals_for = 0,
        public readonly int $goals_against = 0,
    ) {
        $this->goal_difference = $this->goals_for - $this->goals_against;
        $this->points = ($this->won * 3) + $this->drawn;
    }
}

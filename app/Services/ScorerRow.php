<?php

namespace App\Services;

use App\Models\Player;

/**
 * Immutable per-player leaderboard row — mirrors StandingRow (Fase 4).
 */
final class ScorerRow
{
    public function __construct(
        public readonly Player $player,
        public readonly int $count = 0,
    ) {}
}

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
        /**
         * El club con el que jugó ESA temporada, que con una cesión no es el
         * club propietario del jugador. La vista no debería tener que decidir
         * eso, así que se decide aquí (design D1b).
         */
        public readonly ?string $clubShortName = null,
    ) {}
}

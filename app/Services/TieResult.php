<?php

namespace App\Services;

use App\Models\Team;

/**
 * Cómo va una eliminatoria: el global de los dos lados y quién pasa.
 *
 * Inmutable y derivado, como `StandingRow` y `ClubSeasonStats`: no hay un
 * marcador global guardado en ningún sitio que pueda discrepar de sus partidos.
 */
final class TieResult
{
    public function __construct(
        public readonly int $homeGoals = 0,
        public readonly int $awayGoals = 0,
        public readonly int $played = 0,
        public readonly int $legs = 1,
        public readonly ?Team $winner = null,
        public readonly ?string $note = null,
    ) {}

    public function isFinished(): bool
    {
        return $this->played >= $this->legs;
    }

    /**
     * Empatada y jugada entera: aquí es donde la aplicación se para y espera al
     * administrador, en vez de inventarse un ganador por goles fuera o por
     * penaltis que nadie ha registrado.
     */
    public function needsDecision(): bool
    {
        return $this->isFinished() && $this->homeGoals === $this->awayGoals && $this->winner === null;
    }

    public function isDecided(): bool
    {
        return $this->winner !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Un cruce del cuadro: dos equipos, uno o dos partidos, y uno que pasa.
 *
 * Quién pasa se deriva del global mientras el global diga algo. Cuando la
 * eliminatoria acaba empatada, la aplicación NO inventa un ganador: lo decide
 * el administrador y deja escrito el motivo (decisión del propietario). No se
 * modelan penaltis ni el valor doble de los goles fuera — lo que hace falta
 * consultar después es quién pasó y por qué.
 */
#[Fillable(['cup_round_id', 'home_team_id', 'away_team_id', 'winner_team_id', 'decision_note'])]
class CupTie extends Model
{
    /**
     * Invariante de entidad: un equipo no se elimina a sí mismo, y quien pasa
     * es uno de los dos que juegan.
     */
    protected static function booted(): void
    {
        static::saving(function (CupTie $tie): void {
            if ((int) $tie->home_team_id === (int) $tie->away_team_id) {
                throw ValidationException::withMessages([
                    'away_team_id' => 'Un equipo no se enfrenta a sí mismo.',
                ]);
            }

            if ($tie->winner_team_id !== null
                && ! in_array((int) $tie->winner_team_id, [(int) $tie->home_team_id, (int) $tie->away_team_id], true)) {
                throw ValidationException::withMessages([
                    'winner_team_id' => 'Sólo puede pasar uno de los dos equipos del cruce.',
                ]);
            }
        });
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(CupRound::class, 'cup_round_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'cup_tie_id');
    }
}

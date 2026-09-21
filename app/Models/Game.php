<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'matchday_id', 'cup_tie_id', 'cup_group_id', 'group_matchday',
    'home_team_id', 'away_team_id', 'kickoff_at', 'home_score', 'away_score',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    /**
     * Entity invariant: a team cannot play against itself. Enforced here
     * (not a raw SQL CHECK, see design D2) because every write path goes
     * through Eloquent. The (int) cast is load-bearing: form input arrives
     * as strings, so a naive === would miss e.g. 3 vs '3'.
     *
     * NOTE: DatabaseSeeder uses WithoutModelEvents, which mutes this guard
     * during seeding (see design D3) — the seeder guarantees distinct teams
     * by construction (circle-method round-robin), not by this guard.
     */
    protected static function booted(): void
    {
        static::saving(function (Game $game): void {
            if ($game->away_team_id !== null && (int) $game->home_team_id === (int) $game->away_team_id) {
                throw ValidationException::withMessages([
                    'away_team_id' => 'A team cannot play against itself.',
                ]);
            }

            // Un partido pertenece a UNA competición: la jornada de una liga, el
            // cruce de una copa o el grupo de una copa. Ni a ninguna —quedaría
            // fuera de toda clasificación y de todo cuadro, invisible— ni a dos,
            // que es como el mismo gol acabaría contando dos veces.
            $keys = $game->competitionKeys();

            if (count($keys) !== 1) {
                throw ValidationException::withMessages([
                    'matchday_id' => $keys === []
                        ? 'Un partido tiene que pertenecer a una jornada, a un cruce de copa o a un grupo de copa.'
                        : 'Un partido pertenece a una sola competición, y este apunta a '.count($keys).'.',
                ]);
            }
        });
    }

    /**
     * A qué competición pertenece este partido: una jornada de liga, un cruce
     * de copa o un grupo de copa.
     *
     * @return array<int, string>
     */
    public function competitionKeys(): array
    {
        return array_keys(array_filter([
            'matchday_id' => $this->matchday_id,
            'cup_tie_id' => $this->cup_tie_id,
            'cup_group_id' => $this->cup_group_id,
        ]));
    }

    public function isCupGame(): bool
    {
        return $this->cup_tie_id !== null || $this->cup_group_id !== null;
    }

    /**
     * Cómo se llama la competición de este partido, dicha para una persona:
     * «Jornada 7», «Copa Amazonas · Semifinal» o «Copa Amazonas · Grupo A».
     *
     * Vive aquí y no en cada vista porque lo pintan tres: el calendario de un
     * club, el detalle del partido y el cuadro de la copa.
     */
    public function competitionLabel(): string
    {
        if ($this->cup_tie_id !== null) {
            $tie = $this->cupTie;

            return trim(($tie?->round?->cup?->name ?? 'Copa').' · '.($tie?->round?->name ?? ''), ' ·');
        }

        if ($this->cup_group_id !== null) {
            $group = $this->cupGroup;

            return trim(($group?->cup?->name ?? 'Copa').' · Grupo '.($group?->name ?? ''), ' ·');
        }

        return $this->matchday === null ? 'Sin jornada' : 'Jornada '.$this->matchday->number;
    }

    public function cup(): ?Cup
    {
        return $this->cupTie?->round?->cup ?? $this->cupGroup?->cup;
    }

    public function cupTie(): BelongsTo
    {
        return $this->belongsTo(CupTie::class, 'cup_tie_id');
    }

    public function cupGroup(): BelongsTo
    {
        return $this->belongsTo(CupGroup::class, 'cup_group_id');
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}

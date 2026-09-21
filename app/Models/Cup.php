<?php

namespace App\Models;

use Database\Factories\CupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una copa: la competición de la temporada que no es una liga.
 *
 * Cruza equipos de divisiones distintas, se juega por rondas y termina en un
 * cuadro. Puede llevar una fase de grupos antes, y eso lo decide el
 * administrador copa por copa (decisión del propietario).
 */
#[Fillable(['season_id', 'name', 'has_group_stage'])]
class Cup extends Model
{
    /** @use HasFactory<CupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'has_group_stage' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CupGroup::class)->orderBy('name');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CupTeam::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(CupRound::class)->orderBy('position');
    }

    /**
     * Los equipos apuntados, que pueden venir de divisiones distintas: es
     * justamente lo que una división no sabe hacer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<Team, CupTeam, $this>
     */
    public function teams()
    {
        return $this->hasManyThrough(Team::class, CupTeam::class, 'cup_id', 'id', 'id', 'team_id');
    }
}

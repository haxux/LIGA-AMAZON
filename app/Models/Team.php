<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['season_id', 'division_id', 'club_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * La identidad vive en el club desde la Fase 9, pero decenas de puntos
     * —vistas públicas, tablas del panel, servicios y tests— leen
     * `$team->name`. Estos accesores los dejan funcionando sin tocarlos: lo que
     * desaparece es escribir esos campos en `teams`, no leerlos desde el equipo.
     */
    public function getNameAttribute(): ?string
    {
        return $this->club?->name;
    }

    public function getShortNameAttribute(): ?string
    {
        return $this->club?->short_name;
    }

    public function getCrestPathAttribute(): ?string
    {
        return $this->club?->crest_path;
    }

    public function getFoundedYearAttribute(): ?int
    {
        return $this->club?->founded_year;
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function stadium(): HasOne
    {
        return $this->hasOne(Stadium::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }
}

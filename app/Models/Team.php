<?php

namespace App\Models;

use App\Observers\TeamObserver;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['season_id', 'division_id', 'club_id'])]
#[ObservedBy(TeamObserver::class)]
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

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
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

    /**
     * La plantilla de ESTA temporada: pertenencias, no jugadores sueltos. La
     * identidad de cada uno cuelga del club (Fase 9).
     */
    public function lineup(): HasOne
    {
        return $this->hasOne(Lineup::class);
    }

    /**
     * La plantilla de AHORA: sólo las pertenencias abiertas.
     *
     * Por omisión y no por casualidad. Desde que una salida cierra la fila en
     * vez de borrarla, «plantilla» y «quién pasó por la plantilla» son dos
     * preguntas distintas, y la que hace casi todo el mundo es la primera. La
     * otra se pide a propósito con `allMemberships()`, y así este cambio no
     * obliga a revisar los diecinueve sitios que leen plantillas.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SquadMembership::class)->whereNull('left_at');
    }

    /**
     * Todas las que hubo, cerradas incluidas: quién estuvo en algún momento.
     * Es lo que necesita el formulario de eventos para dejar anotar un partido
     * anterior a un traspaso.
     */
    public function allMemberships(): HasMany
    {
        return $this->hasMany(SquadMembership::class);
    }

    public function players(): HasManyThrough
    {
        return $this->hasManyThrough(Player::class, SquadMembership::class, 'team_id', 'id', 'id', 'player_id');
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

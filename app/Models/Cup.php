<?php

namespace App\Models;

use App\Enums\CupDesign;
use App\Enums\CupScope;
use Database\Factories\CupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

/**
 * Una copa: la competición de la temporada que no es una liga.
 *
 * Cruza equipos de divisiones distintas, se juega por rondas y termina en un
 * cuadro. Puede llevar una fase de grupos antes, y eso lo decide el
 * administrador copa por copa (decisión del propietario).
 */
#[Fillable(['season_id', 'name', 'scope', 'design', 'logo_path', 'background_path', 'has_group_stage'])]
class Cup extends Model
{
    /** @use HasFactory<CupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'has_group_stage' => 'boolean',
            'scope' => CupScope::class,
            'design' => CupDesign::class,
        ];
    }

    public function isInternational(): bool
    {
        return $this->scope === CupScope::International;
    }

    /**
     * El diseño que se ve, o null si la copa es nacional: un diseño que quedó
     * guardado de antes de volverla nacional no debe teñir nada.
     */
    public function theme(): ?CupDesign
    {
        return $this->isInternational() ? $this->design : null;
    }

    /** Los tokens de color del tema, como atributo `style`; vacío en las nacionales. */
    public function themeStyle(): string
    {
        return $this->theme()?->style() ?? '';
    }

    public function logoUrl(): ?string
    {
        return $this->imageUrl($this->logo_path);
    }

    public function backgroundUrl(): ?string
    {
        return $this->imageUrl($this->background_path);
    }

    private function imageUrl(?string $path): ?string
    {
        return $this->theme() !== null && $path !== null
            ? Storage::disk(config('filesystems.uploads'))->url($path)
            : null;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CupGroup::class)->orderBy('name');
    }

    /**
     * Las bandas de la tabla de cada grupo (pasa a octavos, a la repesca…),
     * ordenadas de arriba abajo. Son de la copa y no de cada grupo: todos
     * juegan con las mismas reglas.
     */
    public function standingZones(): HasMany
    {
        return $this->hasMany(StandingZone::class)
            ->withAttributes(['stage' => StandingZone::STAGE_GROUPS])
            ->orderBy('from_position');
    }

    /**
     * Las bandas de la tabla de los mejores terceros: otra tabla, con sus
     * propias posiciones (el mejor tercero es el 1.º, sea de qué grupo sea).
     */
    public function thirdsStandingZones(): HasMany
    {
        return $this->hasMany(StandingZone::class)
            ->withAttributes(['stage' => StandingZone::STAGE_THIRDS])
            ->orderBy('from_position');
    }

    /**
     * Si la copa tiene mejores terceros: con 3 grupos o más (ver
     * `CupService::bestThirds()`).
     */
    public function hasBestThirds(): bool
    {
        return $this->has_group_stage && $this->groups()->count() >= 3;
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
     * @return HasManyThrough<Team, CupTeam, $this>
     */
    public function teams()
    {
        return $this->hasManyThrough(Team::class, CupTeam::class, 'cup_id', 'id', 'id', 'team_id');
    }
}

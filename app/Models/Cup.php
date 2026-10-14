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
        $palette = $this->theme()?->palette();

        if ($palette === null) {
            return '';
        }

        return sprintf(
            '--color-brand:%s;--color-surface:%s;--color-surface-alt:%s;--color-surface-muted:%s;',
            $palette['brand'], $palette['surface'], $palette['alt'], $palette['muted'],
        );
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

<?php

namespace App\Models;

use Database\Factories\LineupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * El once ideal de un equipo en una temporada.
 */
#[Fillable(['team_id', 'formation'])]
class Lineup extends Model
{
    /** @use HasFactory<LineupFactory> */
    use HasFactory;

    /**
     * Formaciones disponibles, cada una descrita por sus líneas de campo — sin
     * contar al portero, que siempre es el hueco 1.
     *
     * Es lo que permite dibujar el campo sin guardar coordenadas: un 4-3-3 son
     * cuatro defensas, tres centrocampistas y tres delanteros, y de ahí salen
     * las posiciones en pantalla.
     *
     * @var array<string, array<int, int>>
     */
    public const FORMATIONS = [
        '4-4-2' => [4, 4, 2],
        '4-3-3' => [4, 3, 3],
        '4-2-3-1' => [4, 2, 3, 1],
        '4-1-4-1' => [4, 1, 4, 1],
        '4-5-1' => [4, 5, 1],
        '3-5-2' => [3, 5, 2],
        '3-4-3' => [3, 4, 3],
        '5-3-2' => [5, 3, 2],
    ];

    public const SLOTS = 11;

    protected static function booted(): void
    {
        static::saving(function (Lineup $lineup): void {
            if (! array_key_exists($lineup->formation, self::FORMATIONS)) {
                throw ValidationException::withMessages([
                    'formation' => "La formación {$lineup->formation} no existe.",
                ]);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(LineupSlot::class)->orderBy('slot');
    }

    /**
     * Las líneas de la formación, con el portero delante: [1, 4, 4, 2] para un
     * 4-4-2. Los huecos se numeran 1..11 recorriéndolas en ese orden.
     *
     * @return array<int, int>
     */
    public function rows(): array
    {
        return [1, ...self::FORMATIONS[$this->formation]];
    }
}

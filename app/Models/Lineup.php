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

    /**
     * Las líneas de una formación, cada una con los números de hueco que le
     * tocan, empezando por el portero. Vive aquí y no en la página del panel
     * porque la ficha pública dibuja el mismo campo: dos copias de este
     * reparto se separarían en cuanto una formación cambiara.
     *
     * @return array<int, array<int, int>>
     */
    public static function rowsFor(string $formation): array
    {
        $rows = [];
        $slot = 1;

        foreach ([1, ...(self::FORMATIONS[$formation] ?? [])] as $count) {
            $row = [];

            for ($i = 0; $i < $count; $i++) {
                $row[] = $slot++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Las líneas de ESTA alineación: [[1], [2,3,4,5], [6,7,8], [9,10,11]] para
     * un 4-3-3. Sustituye a la versión que devolvía sólo el tamaño de cada
     * línea, que no usaba nadie y obligaba a rehacer la numeración en la vista.
     *
     * @return array<int, array<int, int>>
     */
    public function rows(): array
    {
        return self::rowsFor($this->formation);
    }

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
}

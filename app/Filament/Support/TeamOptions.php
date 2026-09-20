<?php

namespace App\Filament\Support;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

/**
 * Opciones de un selector de equipos, etiquetadas con el nombre de su club.
 *
 * Desde la Fase 9 `teams` no tiene columna `name`, y Filament necesita que el
 * atributo de título de `->relationship()` sea una columna real de la tabla
 * consultada: hace `pluck()` sobre ella y filtra por ella al buscar. Por eso
 * estos selectores se construyen con `->options()` y esta clase, en lugar de
 * pelearse con alias de SQL que MySQL no admite en un WHERE.
 *
 * Las ligas de este proyecto son de doce clubes, así que cargar las opciones
 * enteras y buscar en memoria es más barato que cualquier alternativa.
 */
final class TeamOptions
{
    /**
     * @param  (callable(Builder): mixed)|null  $scope
     * @return array<int, string>
     */
    public static function for(?callable $scope = null): array
    {
        $query = Team::query()->with('club');

        if ($scope !== null) {
            $scope($query);
        }

        return $query->get()
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}

<?php

namespace App\Filament\Support;

use App\Models\Club;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buscar y ordenar por el nombre de un equipo.
 *
 * Hace falta porque desde la Fase 9 `teams` NO tiene nombre: lo lleva su club, y
 * `Team::getNameAttribute()` lo delega. Eso basta para pintar una columna, pero
 * no para una consulta —buscar por `teams.name` revienta con «Unknown column
 * 'name'»—, así que toda búsqueda y toda ordenación tienen que pasar por el
 * club.
 */
class TeamName
{
    /**
     * Para `->searchable(query: ...)` sobre una relación a un equipo.
     */
    public static function search(string $relation): Closure
    {
        return static fn (Builder $query, string $search): Builder => $query->whereHas(
            $relation.'.club',
            static fn (Builder $club) => $club
                ->where('name', 'like', "%{$search}%")
                ->orWhere('short_name', 'like', "%{$search}%"),
        );
    }

    /**
     * Para `->sortable(query: ...)`: ordena por el nombre del club del equipo al
     * que apunta esa clave ajena.
     */
    public static function sort(string $foreignKey): Closure
    {
        return static fn (Builder $query, string $direction): Builder => $query->orderBy(
            Club::query()
                ->select('clubs.name')
                ->join('teams', 'teams.club_id', '=', 'clubs.id')
                ->whereColumn('teams.id', $query->getModel()->getTable().'.'.$foreignKey)
                ->limit(1),
            $direction,
        );
    }
}

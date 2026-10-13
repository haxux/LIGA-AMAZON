<?php

namespace App\Services;

use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves the "active" season for the public site — is_current first,
 * falling back to the most recently created season, or null if none exist.
 * The HTTP concern (404 when null) lives in the controller layer
 * (SiteController::activeSeason()), per config.yaml rules.design.
 */
final class SeasonResolver
{
    public function active(): ?Season
    {
        return Season::query()->where('is_current', true)->first()
            ?? Season::query()->latest('id')->first();
    }

    /**
     * Las temporadas que Historia puede enseñar: las anteriores a la vigente
     * que de verdad se jugaron, de la más reciente a la más antigua.
     *
     * Una temporada sin partidos jugados queda FUERA a propósito (decisión del
     * propietario). Las dos de 2025 existen en la base vacías —cero
     * divisiones, cero jornadas, cero partidos— y ofrecerlas en el desplegable
     * sería prometer una tabla que al elegirla sale a ceros. El archivo se
     * llena solo al cerrar cada temporada.
     *
     * Se excluye la que `active()` devuelva y no simplemente `is_current`:
     * cuando ninguna lleva la marca, la vigente es la última por id, y si no
     * se excluyera ésa aparecería a la vez en Clasificación y en Historia.
     *
     * @return Collection<int, Season>
     */
    public function archived(): Collection
    {
        $active = $this->active();

        return Season::query()
            ->when($active, fn (Builder $query) => $query->whereKeyNot($active->getKey()))
            ->whereHas('matchdays.games', fn (Builder $game) => $game->played())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }
}

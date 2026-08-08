<?php

namespace App\Services;

use App\Models\Season;

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
}

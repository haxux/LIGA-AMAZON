<?php

namespace App\Services;

use App\Models\Matchday;
use Illuminate\Support\Collection;

/**
 * Picks the matchday a visitor most likely came to see, so the public
 * fixtures page opens on "right now" instead of on jornada 1. Domain rule,
 * hence the Services layer — the controller only turns it into a filter
 * default (see SeasonResolver for the same split).
 */
final class MatchdayResolver
{
    /**
     * The earliest matchday still to be played, counting today itself; once
     * the calendar is over, the most recent one instead. Matchdays with no
     * date carry no signal about when they are played, so they only decide
     * the outcome when nothing is dated at all — then the lowest number wins.
     *
     * @param  Collection<int, Matchday>  $matchdays
     */
    public function current(Collection $matchdays): ?Matchday
    {
        $dated = $matchdays->filter(fn (Matchday $matchday): bool => $matchday->date !== null)
            ->sortBy('date')
            ->values();

        $upcoming = $dated->first(fn (Matchday $matchday): bool => $matchday->date->startOfDay()->gte(today()));

        return $upcoming
            ?? $dated->last()
            ?? $matchdays->sortBy('number')->first();
    }
}

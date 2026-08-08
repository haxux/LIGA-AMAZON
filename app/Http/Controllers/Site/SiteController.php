<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\SeasonResolver;

/**
 * Namespace is Site, not Public — `public` is a PHP reserved word and is a
 * fatal parse error as a namespace segment (design D5).
 */
abstract class SiteController extends Controller
{
    public function __construct(protected readonly SeasonResolver $seasons) {}

    /**
     * The is_current → latest('id') → 404 chain, in one place for every
     * public page. Domain rule (which season) lives in the Services layer;
     * the HTTP concern (404 when none exist) lives here.
     */
    protected function activeSeason(): Season
    {
        return $this->seasons->active() ?? abort(404);
    }
}

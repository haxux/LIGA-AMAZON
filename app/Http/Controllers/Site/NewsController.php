<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\News;
use App\Models\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * Las noticias, con un filtro discreto por temporada y por equipo.
     *
     * Una noticia es de un equipo (`team_id`), y un equipo es la participación
     * de un club en una temporada: de ahí salen las dos preguntas. Por equipo se
     * filtra el CLUB, para que valga a través de las temporadas. Un valor que no
     * exista se ignora y se enseñan todas, como en el resto de filtros del
     * sitio.
     */
    public function index(Request $request): View
    {
        $base = News::published();

        // Sólo se ofrece lo que tiene noticias: un filtro que lleva a una
        // página vacía es ruido.
        $seasons = Season::query()
            ->whereHas('teams.news', fn ($news) => $news->published())
            ->orderByDesc('start_date')->orderByDesc('id')
            ->get();

        $clubs = Club::query()
            ->whereHas('teams.news', fn ($news) => $news->published())
            ->orderBy('name')
            ->get();

        $season = $seasons->firstWhere('id', $request->integer('temporada'));
        $club = $clubs->firstWhere('id', $request->integer('equipo'));

        $items = $base
            ->when($season, fn ($query) => $query->whereHas('team', fn ($team) => $team->where('season_id', $season->getKey())))
            ->when($club, fn ($query) => $query->whereHas('team', fn ($team) => $team->where('club_id', $club->getKey())))
            ->latest('published_at')
            ->get();

        return view('site.news.index', [
            'items' => $items,
            'seasons' => $seasons,
            'clubs' => $clubs,
            'selectedSeason' => $season,
            'selectedClub' => $club,
        ]);
    }

    /**
     * Plain string $slug, not route-model binding on {news:slug} — implicit
     * binding would resolve a draft item and then need a second manual
     * abort, splitting the visibility rule across two places (design D5).
     */
    public function show(string $slug): View
    {
        $item = News::published()->where('slug', $slug)->firstOrFail();

        return view('site.news.show', ['item' => $item]);
    }
}

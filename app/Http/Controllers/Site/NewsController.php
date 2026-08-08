<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Contracts\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        return view('site.news.index', [
            'items' => News::published()->latest('published_at')->get(),
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

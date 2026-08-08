<?php

use App\Http\Controllers\Site\FixturesController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\ScorersController;
use App\Http\Controllers\Site\StandingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', StandingsController::class)->name('site.standings');
Route::get('/partidos', FixturesController::class)->name('site.fixtures');
Route::get('/goleadores', ScorersController::class)->name('site.scorers');
Route::get('/noticias', [NewsController::class, 'index'])->name('site.news.index');
Route::get('/noticias/{slug}', [NewsController::class, 'show'])->name('site.news.show');

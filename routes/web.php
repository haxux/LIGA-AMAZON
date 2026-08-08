<?php

use App\Http\Controllers\Site\FixturesController;
use App\Http\Controllers\Site\StandingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', StandingsController::class)->name('site.standings');
Route::get('/partidos', FixturesController::class)->name('site.fixtures');

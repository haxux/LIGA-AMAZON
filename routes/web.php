<?php

use App\Http\Controllers\Site\StandingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', StandingsController::class)->name('site.standings');

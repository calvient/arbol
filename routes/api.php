<?php

use Calvient\Arbol\Http\Controllers\API\SeriesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'api'])->prefix('/api/arbol')->group(function () {
    Route::get('/series-data', [SeriesController::class, 'getSeriesData']);
});

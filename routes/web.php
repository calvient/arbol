<?php

use Calvient\Arbol\Http\Controllers\ReportsController;
use Calvient\Arbol\Http\Controllers\SectionsController;
use Calvient\Arbol\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web', HandleInertiaRequests::class])->prefix('/arbol')->as('arbol.')->group(function () {
    Route::resource('reports', ReportsController::class)
        ->only(['index', 'show', 'create', 'store']);

    Route::resource('reports.sections', SectionsController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy']);
});

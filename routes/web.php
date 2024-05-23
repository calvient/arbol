<?php

use Calvient\Arbol\Http\Controllers\ReportsController;
use Calvient\Arbol\Http\Controllers\SectionsController;
use Calvient\Arbol\Http\Middleware\HandleInertiaRequests;
use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'web', HandleInertiaRequests::class])->prefix('/arbol')->as('arbol.')->group(function () {
    // Resource routes
    Route::resource('reports', ReportsController::class)
        ->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::resource('reports.sections', SectionsController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy']);

    // Handle various routes that are not directly related to the Arbol UI
    Route::get('/', function () {
        return redirect()->route('arbol.reports.index');
    });

    Route::get('/reports/{report}/sections', function (ArbolReport $report) {
        return redirect()->route('arbol.reports.show', $report);
    });

    Route::get('/reports/{report}/sections/{section}', function (ArbolReport $report, ArbolSection $section) {
        return redirect()->route('arbol.reports.sections.edit', [$report, $section]);
    });
});

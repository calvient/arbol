<?php

use Calvient\Arbol\Jobs\LoadSectionData;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

test('it dispatches load section data job', function () {
    Queue::fake();

    $section = ArbolSection::factory()->withSeries('Test Series')->create();

    LoadSectionData::dispatch(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    Queue::assertPushed(LoadSectionData::class);
});

test('it loads data and stores in cache', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    $arbolService = app(ArbolService::class);
    $job->handle($arbolService);

    $data = $arbolService->getDataFromCache($section);

    expect($data)->not->toBeNull()
        ->and($data)->toHaveKey('All');
});

test('it applies slice when loading data', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'State',
        user: null,
    );

    $arbolService = app(ArbolService::class);
    $job->handle($arbolService);

    $data = $arbolService->getDataFromCache($section);

    expect($data)->not->toBeNull()
        ->and($data)->toHaveKeys(['CA', 'NY', 'TX', 'FL']);
});

test('it sets running flag during execution', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    expect($arbolService->getIsRunning($section))->toBeFalse();

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    $job->handle($arbolService);

    // After completion, running should be false
    expect($arbolService->getIsRunning($section))->toBeFalse();
});

test('it stores last run duration', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    expect($arbolService->getLastRunDuration($section))->toBeNull();

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    $job->handle($arbolService);

    // After completion, duration should be set
    expect($arbolService->getLastRunDuration($section))->not->toBeNull();
});

test('it generates unique id based on section and timestamp', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    $uniqueId = $job->uniqueId();

    expect($uniqueId)->toContain((string) $section->id);
});

test('it handles non-existent series gracefully', function () {
    $section = ArbolSection::factory()->withSeries('Non Existent Series')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Non Existent Series',
        filters: [],
        slice: null,
        user: null,
    );

    // Should not throw, just return early
    $job->loadData($arbolService);

    // No data should be cached
    expect($arbolService->getDataFromCache($section))->toBeNull();
});

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

test('it stores formatted data when format is bar', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'State',
        user: null,
        format: 'bar',
        aggregator: 'Default',
        chartSlice: null,
    );

    $job->handle($arbolService);

    // Raw data should be stored
    $rawData = $arbolService->getDataFromCache($section);
    expect($rawData)->not->toBeNull();

    // Formatted data should also be stored
    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()
        ->and($formattedData)->toBeArray()
        ->and($formattedData[0])->toHaveKeys(['name', 'value']);
});

test('it does not store formatted data when format is table', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
        format: 'table',
    );

    $job->handle($arbolService);

    // Raw data should be stored
    $rawData = $arbolService->getDataFromCache($section);
    expect($rawData)->not->toBeNull();

    // Formatted data should NOT be stored for table format
    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->toBeNull();
});

test('it stores formatted data for pie format with aggregator', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'State',
        user: null,
        format: 'pie',
        aggregator: 'Sum',
        chartSlice: null,
    );

    $job->handle($arbolService);

    // Formatted data should be stored with aggregated values
    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()
        ->and($formattedData)->toBeArray();

    // Check that values are aggregated (Sum aggregator sums the 'value' field)
    $caItem = collect($formattedData)->firstWhere('name', 'CA');
    expect($caItem)->not->toBeNull()
        ->and((float) $caItem['value'])->toBe(100.0); // Test data has CA with value 100
});

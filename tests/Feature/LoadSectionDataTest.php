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

test('it applies xaxis_group percentage mode to bar chart data', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('Month')->create();
    $arbolService = app(ArbolService::class);

    // Data: Jan has CA(1) and NY(1), Feb has TX(1) and FL(1)
    // With Default aggregator (count), grouped by Month (slice), sub-divided by State (chartSlice)
    // Jan: {CA: 1, NY: 1} → 50% each
    // Feb: {TX: 1, FL: 1} → 50% each
    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'Month',
        user: null,
        format: 'bar',
        aggregator: 'Default',
        chartSlice: 'State',
        percentageMode: 'xaxis_group',
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()
        ->and($formattedData)->toBeArray();

    $janItem = collect($formattedData)->firstWhere('name', 'Jan');
    expect($janItem)->not->toBeNull();

    // Each state in Jan should be 50% (1 out of 2)
    $janValues = collect($janItem)->except('name')->filter(fn ($v) => is_numeric($v));
    $janSum = $janValues->sum();
    expect(round($janSum, 2))->toBe(100.0);

    $febItem = collect($formattedData)->firstWhere('name', 'Feb');
    expect($febItem)->not->toBeNull();

    // Each state in Feb should be 50% (1 out of 2)
    $febValues = collect($febItem)->except('name')->filter(fn ($v) => is_numeric($v));
    $febSum = $febValues->sum();
    expect(round($febSum, 2))->toBe(100.0);
});

test('it applies total percentage mode to bar chart data', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('Month')->create();
    $arbolService = app(ArbolService::class);

    // Grand total count = 4 (1 per state)
    // Each state = 1/4 = 25%
    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'Month',
        user: null,
        format: 'bar',
        aggregator: 'Default',
        chartSlice: 'State',
        percentageMode: 'total',
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()
        ->and($formattedData)->toBeArray();

    // Sum of ALL numeric values across ALL rows should be 100%
    $grandTotal = collect($formattedData)->sum(function ($row) {
        return collect($row)->except('name')->filter(fn ($v) => is_numeric($v))->sum();
    });
    expect(round($grandTotal, 2))->toBe(100.0);

    // States present in Jan (CA, NY) should each be 25% (1 out of 4 total)
    // States not present in Jan (TX, FL) should be 0%
    $janItem = collect($formattedData)->firstWhere('name', 'Jan');
    $janNonZero = collect($janItem)->except('name')->filter(fn ($v) => is_numeric($v) && $v > 0);
    foreach ($janNonZero as $value) {
        expect(round($value, 2))->toBe(25.0);
    }
    expect($janNonZero)->toHaveCount(2); // CA and NY
});

test('it preserves existing behavior when percentage_mode is null', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('Month')->create();
    $arbolService = app(ArbolService::class);

    // Without percentage mode, values should be raw counts
    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'Month',
        user: null,
        format: 'bar',
        aggregator: 'Default',
        chartSlice: 'State',
        percentageMode: null,
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()
        ->and($formattedData)->toBeArray();

    $janItem = collect($formattedData)->firstWhere('name', 'Jan');
    expect($janItem)->not->toBeNull();

    // States present in Jan (CA, NY) should each have count 1
    // States not present in Jan (TX, FL) should be 0
    $janNonZero = collect($janItem)->except('name')->filter(fn ($v) => is_numeric($v) && $v > 0);
    foreach ($janNonZero as $value) {
        expect((float) $value)->toBe(1.0);
    }
    expect($janNonZero)->toHaveCount(2); // CA and NY
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

<?php

use Calvient\Arbol\Jobs\LoadSectionData;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

/*
|--------------------------------------------------------------------------
| Chart truncation tests
|--------------------------------------------------------------------------
*/

test('chart data is truncated when groups exceed max_chart_groups', function () {
    config()->set('arbol.max_chart_groups', 3);

    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    // TestSeries has 4 states (CA, NY, TX, FL) — exceeds limit of 3
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

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull();

    // Should have 3 data rows + 1 meta row = 4 items
    expect($formattedData)->toHaveCount(4);

    // Last item should be the truncation metadata
    $meta = end($formattedData);
    expect($meta['_meta'])->toBe('truncated');
    expect($meta['_total'])->toBe(4);
    expect($meta['_shown'])->toBe(3);

    // First 3 items should be normal chart data
    expect($formattedData[0])->toHaveKey('name');
    expect($formattedData[0])->toHaveKey('value');
    expect($formattedData[1])->toHaveKey('name');
    expect($formattedData[2])->toHaveKey('name');
});

test('chart data is not truncated when within max_chart_groups', function () {
    config()->set('arbol.max_chart_groups', 100);

    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    // TestSeries has 4 states — well within limit of 100
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

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull();

    // Should have exactly 4 data rows, no meta
    expect($formattedData)->toHaveCount(4);

    foreach ($formattedData as $item) {
        expect($item)->toHaveKey('name');
        expect($item)->not->toHaveKey('_meta');
    }
});

test('truncation is disabled when max_chart_groups is null', function () {
    config()->set('arbol.max_chart_groups', null);

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

    $formattedData = $arbolService->getFormattedDataFromCache($section);

    // All 4 states should be present, no meta
    expect($formattedData)->toHaveCount(4);
    foreach ($formattedData as $item) {
        expect($item)->not->toHaveKey('_meta');
    }
});

test('pie chart data is also truncated', function () {
    config()->set('arbol.max_chart_groups', 2);

    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'State',
        user: null,
        format: 'pie',
        aggregator: 'Default',
        chartSlice: null,
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);

    // 2 data rows + 1 meta = 3
    expect($formattedData)->toHaveCount(3);
    $meta = end($formattedData);
    expect($meta['_meta'])->toBe('truncated');
    expect($meta['_total'])->toBe(4);
    expect($meta['_shown'])->toBe(2);
});

test('table format is never truncated', function () {
    config()->set('arbol.max_chart_groups', 2);

    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'State',
        user: null,
        format: 'table',
    );

    $job->handle($arbolService);

    // Table format stores raw data, not formatted — should have all 4 states
    $rawData = $arbolService->getDataFromCache($section);
    expect($rawData)->toHaveCount(4);
});

/*
|--------------------------------------------------------------------------
| JSON key preservation tests
|--------------------------------------------------------------------------
*/

test('numeric string keys are preserved through cache round-trip', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    // Simulate data with numeric string keys (like location IDs)
    $data = collect([
        '24' => [['name' => 'Location A', 'value' => 100]],
        '161' => [['name' => 'Location B', 'value' => 200]],
        '322' => [['name' => 'Location C', 'value' => 300]],
    ]);

    $arbolService->storeDataInCache($section, $data);
    $retrieved = $arbolService->getDataFromCache($section);

    // Keys should be preserved as strings, not lost to array indexing
    expect($retrieved)->toHaveKey('24');
    expect($retrieved)->toHaveKey('161');
    expect($retrieved)->toHaveKey('322');
    expect($retrieved['24'])->toBe([['name' => 'Location A', 'value' => 100]]);
});

test('regular string keys are preserved through cache round-trip', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    $data = collect([
        'California' => [['name' => 'Test', 'value' => 100]],
        'New York' => [['name' => 'Test', 'value' => 200]],
    ]);

    $arbolService->storeDataInCache($section, $data);
    $retrieved = $arbolService->getDataFromCache($section);

    expect($retrieved)->toHaveKey('California');
    expect($retrieved)->toHaveKey('New York');
});

test('mixed numeric and string keys are preserved through cache round-trip', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    $data = collect([
        'All' => [['name' => 'Test', 'value' => 100]],
        '42' => [['name' => 'Test', 'value' => 200]],
        'Location X' => [['name' => 'Test', 'value' => 300]],
    ]);

    $arbolService->storeDataInCache($section, $data);
    $retrieved = $arbolService->getDataFromCache($section);

    expect($retrieved)->toHaveKey('All');
    expect($retrieved)->toHaveKey('42');
    expect($retrieved)->toHaveKey('Location X');
});

<?php

use Calvient\Arbol\Jobs\LoadSectionData;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

/*
|--------------------------------------------------------------------------
| Fix 1: Hoisted $allSliceValues — verify correctness
|--------------------------------------------------------------------------
|
| The key behavioral requirement: when chartSlice is used, ALL possible
| slice values must appear in EVERY x-axis group (backfilled with 0).
| e.g. Jan has CA,NY but NOT TX,FL → TX and FL should still appear as 0.
|
*/

test('formatForChart backfills all slice values across all groups', function () {
    // Test data: sliced by Month (Jan, Feb), chartSlice by State
    // Jan has CA, NY; Feb has TX, FL
    // ALL states must appear in ALL months (backfilled with 0)
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('Month')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'Month',
        user: null,
        format: 'bar',
        aggregator: 'Default',
        chartSlice: 'State',
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull()->and($formattedData)->toBeArray();

    $janItem = collect($formattedData)->firstWhere('name', 'Jan');
    $febItem = collect($formattedData)->firstWhere('name', 'Feb');

    expect($janItem)->not->toBeNull();
    expect($febItem)->not->toBeNull();

    // Jan should have CA and NY with value 1, and TX and FL backfilled to 0
    expect((float) $janItem['CA'])->toBe(1.0);
    expect((float) $janItem['NY'])->toBe(1.0);
    expect($janItem['TX'])->toBe(0);
    expect($janItem['FL'])->toBe(0);

    // Feb should have TX and FL with value 1, and CA and NY backfilled to 0
    expect((float) $febItem['TX'])->toBe(1.0);
    expect((float) $febItem['FL'])->toBe(1.0);
    expect($febItem['CA'])->toBe(0);
    expect($febItem['NY'])->toBe(0);
});

test('formatForChart without chartSlice returns simple name/value pairs', function () {
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
    expect($formattedData)->not->toBeNull();

    // Without chartSlice, each entry should have just 'name' and 'value'
    foreach ($formattedData as $item) {
        expect($item)->toHaveKeys(['name', 'value']);
        expect(array_keys($item))->toHaveCount(2);
    }
});

test('formatForChart with Sum aggregator produces correct values across slices', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('Month')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: 'Month',
        user: null,
        format: 'bar',
        aggregator: 'Sum',
        chartSlice: 'State',
    );

    $job->handle($arbolService);

    $formattedData = $arbolService->getFormattedDataFromCache($section);
    expect($formattedData)->not->toBeNull();

    $janItem = collect($formattedData)->firstWhere('name', 'Jan');

    // Jan: CA has value 100, NY has value 200 (from TestSeries data)
    expect((float) $janItem['CA'])->toBe(100.0);
    expect((float) $janItem['NY'])->toBe(200.0);
    // TX and FL are only in Feb, should be backfilled to 0
    expect($janItem['TX'])->toBe(0);
    expect($janItem['FL'])->toBe(0);

    $febItem = collect($formattedData)->firstWhere('name', 'Feb');

    // Feb: TX has value 150, FL has value 250
    expect((float) $febItem['TX'])->toBe(150.0);
    expect((float) $febItem['FL'])->toBe(250.0);
    // CA and NY are only in Jan, should be backfilled to 0
    expect($febItem['CA'])->toBe(0);
    expect($febItem['NY'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Fix 2: set_time_limit(0) — just verify the job completes
|--------------------------------------------------------------------------
|
| This is a safety-net fix. We can't easily unit-test set_time_limit()
| behavior, but we verify the job doesn't break from the added call.
|
*/

test('job handle completes successfully with set_time_limit', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    // Should complete without error (set_time_limit(0) is called inside handle)
    $job->handle($arbolService);

    expect($arbolService->getDataFromCache($section))->not->toBeNull();
    expect($arbolService->getIsRunning($section))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Fix 3: Controller dispatches job for chart formats when raw cache
|         exists but formatted cache is missing
|--------------------------------------------------------------------------
*/

test('controller returns 202 and dispatches job when raw cache exists but formatted cache is missing for chart format', function () {
    Queue::fake();

    $user = createTestUser();
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    // Seed the raw cache manually (simulating a previous table-format job)
    $rawData = ['CA' => [['name' => 'Test 1', 'state' => 'CA', 'value' => 100]]];
    $arbolService->storeDataInCache($section, collect($rawData));

    // Do NOT store formatted cache — this simulates the missing formatted cache scenario

    $response = $this->actingAs($user)->getJson('/api/arbol/series-data?' . http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'bar',
        'slice' => 'State',
        'aggregator' => 'Default',
    ]));

    // Should get 202 instead of blocking the web thread to format inline
    $response->assertStatus(202);
    $response->assertJsonStructure(['message', 'estimated_time']);

    // A LoadSectionData job should have been dispatched
    Queue::assertPushed(LoadSectionData::class);
});

test('controller returns formatted data directly when formatted cache exists for chart format', function () {
    $user = createTestUser();
    $section = ArbolSection::factory()->withSeries('Test Series')->withSlice('State')->create();
    $arbolService = app(ArbolService::class);

    // Seed both raw and formatted cache
    $rawData = ['CA' => [['name' => 'Test 1', 'state' => 'CA', 'value' => 100]]];
    $arbolService->storeDataInCache($section, collect($rawData));

    $formattedData = [['name' => 'CA', 'value' => 1.0]];
    $arbolService->storeFormattedDataInCache($section, $formattedData);

    $response = $this->actingAs($user)->getJson('/api/arbol/series-data?' . http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'bar',
        'slice' => 'State',
        'aggregator' => 'Default',
    ]));

    // Should return 200 with the cached formatted data
    $response->assertStatus(200);
    $response->assertJson([['name' => 'CA', 'value' => 1.0]]);
});

test('controller returns table data inline when raw cache exists for table format', function () {
    $user = createTestUser();
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    // Seed raw cache only
    $rawData = ['All' => [['name' => 'Test 1', 'state' => 'CA', 'value' => 100]]];
    $arbolService->storeDataInCache($section, collect($rawData));

    $response = $this->actingAs($user)->getJson('/api/arbol/series-data?' . http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    // Table format should still return inline (it's cheap)
    $response->assertStatus(200);
});

test('controller returns 202 when no cache exists at all', function () {
    Queue::fake();

    $user = createTestUser();
    $section = ArbolSection::factory()->withSeries('Test Series')->create();

    $response = $this->actingAs($user)->getJson('/api/arbol/series-data?' . http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    // Should dispatch job and return 202
    $response->assertStatus(202);
    Queue::assertPushed(LoadSectionData::class);
});

/*
|--------------------------------------------------------------------------
| Fix 4: Last run duration is stored as integer
|--------------------------------------------------------------------------
*/

test('last run duration is stored as an integer', function () {
    $section = ArbolSection::factory()->withSeries('Test Series')->create();
    $arbolService = app(ArbolService::class);

    $job = new LoadSectionData(
        arbolSection: $section,
        series: 'Test Series',
        filters: [],
        slice: null,
        user: null,
    );

    $job->handle($arbolService);

    $duration = $arbolService->getLastRunDuration($section);
    expect($duration)->not->toBeNull();
    expect($duration)->toBeInt();
});

<?php

use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Calvient\Arbol\Tests\Series\TestSeries;
use Calvient\Arbol\Tests\Series\UnrelatedTestSeries;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

test('it gets all series', function () {
    $service = new ArbolService;
    $series = $service->getSeries();
    $testSeries = collect($series)->firstWhere('name', 'Test Series');

    expect($series)->toHaveCount(2)
        ->and($testSeries)->toBeArray()
        ->and($testSeries)->toHaveKeys(['name', 'description', 'slices', 'filters', 'aggregators'])
        ->and($testSeries['name'])->toBe('Test Series')
        ->and($testSeries['description'])->toBe('Test Series Description')
        ->and($testSeries['slices'])->toContain('State', 'City')
        ->and($testSeries['aggregators'])->toContain('Default', 'Sum', 'Average');
});

test('it throws an exception if the directory is invalid', function () {
    config()->set('arbol.series_path', __DIR__.'/../Invalid');
    $service = new ArbolService;
    $service->getSeries();
})->throws(InvalidArgumentException::class);

test('it gets series by name', function () {
    TestSeries::$nameCalls = 0;
    UnrelatedTestSeries::$filterCalls = 0;

    $service = new ArbolService;
    $series = $service->getSeriesByName('Test Series');

    expect($series)->toBeArray()
        ->and($series)->toHaveKeys(['class', 'name', 'description', 'slices', 'filters', 'aggregators'])
        ->and($series['class'])->toBe(TestSeries::class)
        ->and($series['name'])->toBe('Test Series')
        ->and($series['description'])->toBe('Test Series Description')
        ->and($series['slices'])->toContain('State', 'City')
        ->and($series['filters'])->toBe([
            'dob' => ['Before 1990', 'After 1990'],
        ])
        ->and($series['aggregators'])->toContain('Default', 'Sum', 'Average')
        ->and(TestSeries::$nameCalls)->toBe(1)
        ->and(UnrelatedTestSeries::$filterCalls)->toBe(0);
});

test('it returns null for non-existent series name', function () {
    $service = new ArbolService;
    $series = $service->getSeriesByName('Non Existent Series');

    expect($series)->toBeNull();
});

test('it gets series class by name', function () {
    $service = new ArbolService;
    $class = $service->getSeriesClassByName('Test Series');

    expect($class)->toBe(TestSeries::class);
});

test('it returns null for non-existent series class', function () {
    $service = new ArbolService;
    $class = $service->getSeriesClassByName('Non Existent Series');

    expect($class)->toBeNull();
});

test('it stores and retrieves data from cache', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    $data = [
        'California' => [['name' => 'Test', 'state' => 'CA']],
    ];

    $service->storeDataInCache($section, $data);

    $retrieved = $service->getDataFromCache($section);

    expect($retrieved)->toBe($data);
});

test('it distinguishes an empty cached result from a cache miss', function () {
    $service = new ArbolService;
    $cachedSection = ArbolSection::factory()->create();
    $missingSection = ArbolSection::factory()->create();

    $service->storeDataInCache($cachedSection, []);

    expect($service->getDataFromCache($cachedSection))->toBe([])
        ->and($service->getDataFromCache($missingSection))->toBeNull();
});

test('it stores and retrieves formatted data from cache', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    $formattedData = [
        ['name' => 'California', 'value' => 100],
        ['name' => 'New York', 'value' => 200],
    ];

    $service->storeFormattedDataInCache($section, $formattedData);

    $retrieved = $service->getFormattedDataFromCache($section);

    expect($retrieved)->toBe($formattedData);
});

test('it returns null when no cached data exists', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    $retrieved = $service->getDataFromCache($section);

    expect($retrieved)->toBeNull();
});

test('it returns null when no formatted cached data exists', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    $retrieved = $service->getFormattedDataFromCache($section);

    expect($retrieved)->toBeNull();
});

test('it tracks running state', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    expect($service->getIsRunning($section))->toBeFalse();

    $service->setIsRunning($section, true);
    expect($service->getIsRunning($section))->toBeTrue();

    $service->setIsRunning($section, false);
    expect($service->getIsRunning($section))->toBeFalse();
});

test('it stores and retrieves last run duration', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    expect($service->getLastRunDuration($section))->toBeNull();

    $service->setLastRunDuration($section, 120);
    expect($service->getLastRunDuration($section))->toBe(120);
});

test('it clears cache for section', function () {
    $service = new ArbolService;
    $section = ArbolSection::factory()->create();

    // Set up some cached data
    $service->storeDataInCache($section, ['test' => 'data']);
    $service->storeFormattedDataInCache($section, [['name' => 'test', 'value' => 1]]);
    $service->setIsRunning($section, true);
    $service->setLastRunDuration($section, 60);

    // Verify data is cached
    expect($service->getDataFromCache($section))->not->toBeNull();
    expect($service->getFormattedDataFromCache($section))->not->toBeNull();
    expect($service->getIsRunning($section))->toBeTrue();
    expect($service->getLastRunDuration($section))->toBe(60);

    // Clear cache
    $service->clearCacheForSection($section);

    // Verify data is cleared
    expect($service->getDataFromCache($section))->toBeNull();
    expect($service->getFormattedDataFromCache($section))->toBeNull();
    expect($service->getIsRunning($section))->toBeFalse();
    expect($service->getLastRunDuration($section))->toBeNull();
});

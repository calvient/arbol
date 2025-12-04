<?php

use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

test('it gets all series', function () {
    $service = new ArbolService;
    $series = $service->getSeries();

    expect($series)->toHaveCount(1)
        ->and($series[0])->toBeArray()
        ->and($series[0])->toHaveKeys(['name', 'description', 'slices', 'filters', 'aggregators'])
        ->and($series[0]['name'])->toBe('Test Series')
        ->and($series[0]['description'])->toBe('Test Series Description')
        ->and($series[0]['slices'])->toContain('State', 'City')
        ->and($series[0]['aggregators'])->toContain('Default', 'Sum', 'Average');
});

test('it throws an exception if the directory is invalid', function () {
    config()->set('arbol.series_path', __DIR__.'/../Invalid');
    $service = new ArbolService;
    $service->getSeries();
})->throws(InvalidArgumentException::class);

test('it gets series by name', function () {
    $service = new ArbolService;
    $series = $service->getSeriesByName('Test Series');

    expect($series)->toBeArray()
        ->and($series['name'])->toBe('Test Series')
        ->and($series['description'])->toBe('Test Series Description');
});

test('it returns null for non-existent series name', function () {
    $service = new ArbolService;
    $series = $service->getSeriesByName('Non Existent Series');

    expect($series)->toBeNull();
});

test('it gets series class by name', function () {
    $service = new ArbolService;
    $class = $service->getSeriesClassByName('Test Series');

    expect($class)->toBe(\Calvient\Arbol\Tests\Series\TestSeries::class);
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

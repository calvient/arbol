<?php

use Calvient\Arbol\Services\ArbolService;

test('it gets all series', function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
    $service = new ArbolService();
    $series = $service->getSeries();

    expect($series)->toHaveCount(1)
        ->and($series[0])->toBeArray()
        ->and($series[0])->toHaveKeys(['name', 'description', 'slices', 'filters'])
        ->and($series[0]['name'])->toBe('Test Series');
});

test('it throws an exception if the directory is invalid', function () {
    config()->set('arbol.series_path', __DIR__.'/../Invalid');
    $service = new ArbolService();
    $service->getSeries();
})->throws(InvalidArgumentException::class);

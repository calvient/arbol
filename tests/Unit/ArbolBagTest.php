<?php

use Calvient\Arbol\DataObjects\ArbolBag;

test('it can be created with empty defaults', function () {
    $bag = new ArbolBag;

    expect($bag->getFilters())->toBe([])
        ->and($bag->getSlice())->toBeNull();
});

test('it can be created with filters and slice', function () {
    $filters = ['status' => ['active']];
    $slice = 'State';

    $bag = new ArbolBag($filters, $slice);

    expect($bag->getFilters())->toBe($filters)
        ->and($bag->getSlice())->toBe($slice);
});

test('it can add filters', function () {
    $bag = new ArbolBag;

    $bag->addFilter('status', 'active');
    $bag->addFilter('status', 'pending');
    $bag->addFilter('type', 'user');

    expect($bag->getFilters())->toBe([
        'status' => ['active', 'pending'],
        'type' => ['user'],
    ]);
});

test('it can add slice', function () {
    $bag = new ArbolBag;

    $bag->addSlice('State');

    expect($bag->getSlice())->toBe('State');
});

test('it can check if filter is set', function () {
    $bag = new ArbolBag;

    $bag->addFilter('status', 'active');
    $bag->addFilter('status', 'pending');

    expect($bag->isFilterSet('status', 'active'))->toBeTrue()
        ->and($bag->isFilterSet('status', 'pending'))->toBeTrue()
        ->and($bag->isFilterSet('status', 'inactive'))->toBeFalse()
        ->and($bag->isFilterSet('type', 'user'))->toBeFalse();
});

test('it can apply filters via callback', function () {
    $bag = new ArbolBag;
    $bag->addFilter('status', 'active');

    $appliedFilters = [];

    $allFilters = [
        'status' => [
            'active' => fn () => 'active_handler',
            'inactive' => fn () => 'inactive_handler',
        ],
        'type' => [
            'user' => fn () => 'user_handler',
        ],
    ];

    $bag->applyFilters($allFilters, function ($func) use (&$appliedFilters) {
        $appliedFilters[] = $func();
    });

    expect($appliedFilters)->toBe(['active_handler']);
});

test('it can apply multiple filters', function () {
    $bag = new ArbolBag;
    $bag->addFilter('status', 'active');
    $bag->addFilter('status', 'pending');
    $bag->addFilter('type', 'user');

    $appliedFilters = [];

    $allFilters = [
        'status' => [
            'active' => fn () => 'active_handler',
            'pending' => fn () => 'pending_handler',
            'inactive' => fn () => 'inactive_handler',
        ],
        'type' => [
            'user' => fn () => 'user_handler',
            'admin' => fn () => 'admin_handler',
        ],
    ];

    $bag->applyFilters($allFilters, function ($func) use (&$appliedFilters) {
        $appliedFilters[] = $func();
    });

    expect($appliedFilters)->toBe(['active_handler', 'pending_handler', 'user_handler']);
});

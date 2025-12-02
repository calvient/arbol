<?php

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;

test('it can create an arbol report', function () {
    $report = ArbolReport::factory()->create([
        'name' => 'Sales Report',
        'description' => 'Monthly sales data',
    ]);

    expect($report)->toBeInstanceOf(ArbolReport::class)
        ->and($report->name)->toBe('Sales Report')
        ->and($report->description)->toBe('Monthly sales data');
});

test('it has sections relationship', function () {
    $report = ArbolReport::factory()->create();
    $section = ArbolSection::factory()->forReport($report)->create();

    expect($report->sections)->toHaveCount(1)
        ->and($report->sections->first()->id)->toBe($section->id);
});

test('it casts user_ids to json', function () {
    $report = ArbolReport::factory()->create([
        'user_ids' => [1, 2, 3],
    ]);

    $report->refresh();

    expect($report->user_ids)->toBeArray()
        ->and($report->user_ids)->toBe([1, 2, 3]);
});

test('it scopes mine for author', function () {
    $this->actingAs(createTestUser(['id' => 1]));

    ArbolReport::factory()->forAuthor(1)->create();
    ArbolReport::factory()->forAuthor(2)->create();

    $reports = ArbolReport::mine()->get();

    expect($reports)->toHaveCount(1);
});

test('it scopes mine for shared users', function () {
    $this->actingAs(createTestUser(['id' => 1]));

    ArbolReport::factory()->forAuthor(2)->sharedWith([1])->create();
    ArbolReport::factory()->forAuthor(3)->create();

    $reports = ArbolReport::mine()->get();

    expect($reports)->toHaveCount(1);
});

test('it scopes mine for everyone access', function () {
    $this->actingAs(createTestUser(['id' => 1]));

    ArbolReport::factory()->forAuthor(2)->sharedWithEveryone()->create();
    ArbolReport::factory()->forAuthor(3)->create();

    $reports = ArbolReport::mine()->get();

    expect($reports)->toHaveCount(1);
});

test('it formats timestamps correctly', function () {
    $report = ArbolReport::factory()->create();

    expect($report->created_at->format('Y-m-d H:i'))->toBeString();
});

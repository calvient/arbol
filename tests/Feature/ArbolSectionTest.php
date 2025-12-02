<?php

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;

test('it can create an arbol section', function () {
    $section = ArbolSection::factory()->create([
        'name' => 'Sales by Region',
        'description' => 'Regional breakdown',
        'series' => 'Test Series',
        'format' => 'pie',
    ]);

    expect($section)->toBeInstanceOf(ArbolSection::class)
        ->and($section->name)->toBe('Sales by Region')
        ->and($section->description)->toBe('Regional breakdown')
        ->and($section->series)->toBe('Test Series')
        ->and($section->format)->toBe('pie');
});

test('it belongs to a report', function () {
    $report = ArbolReport::factory()->create();
    $section = ArbolSection::factory()->forReport($report)->create();

    expect($section->report)->toBeInstanceOf(ArbolReport::class)
        ->and($section->report->id)->toBe($report->id);
});

test('it casts filters to json', function () {
    $filters = [
        ['field' => 'status', 'value' => 'active'],
        ['field' => 'type', 'value' => 'premium'],
    ];

    $section = ArbolSection::factory()->withFilters($filters)->create();
    $section->refresh();

    expect($section->filters)->toBeArray()
        ->and($section->filters)->toBe($filters);
});

test('it can be created with different formats', function () {
    $tableSection = ArbolSection::factory()->asTable()->create();
    $pieSection = ArbolSection::factory()->asPieChart()->create();
    $lineSection = ArbolSection::factory()->asLineChart()->create();
    $barSection = ArbolSection::factory()->asBarChart()->create();

    expect($tableSection->format)->toBe('table')
        ->and($pieSection->format)->toBe('pie')
        ->and($lineSection->format)->toBe('line')
        ->and($barSection->format)->toBe('bar');
});

test('it has default sequence of 0', function () {
    $section = ArbolSection::factory()->create();

    expect($section->sequence)->toBe(0);
});

test('it can have custom sequence', function () {
    $section = ArbolSection::factory()->withSequence(5)->create();

    expect($section->sequence)->toBe(5);
});

test('it can have nullable slice and xaxis_slice', function () {
    $section = ArbolSection::factory()->create([
        'slice' => null,
        'xaxis_slice' => null,
    ]);

    expect($section->slice)->toBeNull()
        ->and($section->xaxis_slice)->toBeNull();
});

test('it can have slice and xaxis_slice', function () {
    $section = ArbolSection::factory()->create([
        'slice' => 'State',
        'xaxis_slice' => 'Month',
    ]);

    expect($section->slice)->toBe('State')
        ->and($section->xaxis_slice)->toBe('Month');
});

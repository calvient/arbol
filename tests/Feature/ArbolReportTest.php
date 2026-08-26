<?php

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

test('it returns an Inertia v3 response for the reports index', function () {
    $user = createTestUser();
    ArbolReport::factory()->forAuthor($user->id)->create();

    $response = $this->actingAs($user)->get('/arbol/reports');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports', 1)
        );
});

test('it does not load series metadata when table sections have no report filters', function () {
    $user = createTestUser(['client_id' => 1]);
    $report = ArbolReport::factory()->forAuthor($user->id)->forClient(1)->create();
    ArbolSection::factory()->forReport($report)->asTable()->withFilters([])->create();

    mock(ArbolService::class)->shouldNotReceive('getSeriesByName');

    $this->actingAs($user)
        ->get("/arbol/reports/{$report->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Show')
            ->where('allFilters', [])
            ->where('defaultFilters', [])
        );
});

test('it loads series metadata for configured table filters', function () {
    $user = createTestUser(['client_id' => 1]);
    $report = ArbolReport::factory()->forAuthor($user->id)->forClient(1)->create();
    ArbolSection::factory()->forReport($report)->asTable()->withFilters([
        ['field' => 'Status', 'value' => 'Open'],
    ])->create();

    mock(ArbolService::class)
        ->shouldReceive('getSeriesByName')
        ->once()
        ->with('Test Series')
        ->andReturn([
            'filters' => [
                'Status' => ['Open', 'Closed'],
            ],
        ]);

    $this->actingAs($user)
        ->get("/arbol/reports/{$report->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Show')
            ->where('allFilters', ['Status' => ['Open', 'Closed']])
            ->where('defaultFilters', [['field' => 'Status', 'value' => 'Open']])
        );
});

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

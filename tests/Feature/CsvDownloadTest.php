<?php

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;

beforeEach(function () {
    config()->set('arbol.series_path', __DIR__.'/../Series');
});

/*
|--------------------------------------------------------------------------
| CSV Download Tests
|--------------------------------------------------------------------------
|
| These tests verify the CSV download functionality, including:
| - UTF-8 BOM for proper encoding in Excel
| - Explicit fputcsv escape parameter (PHP 8.4 deprecation fix)
| - Correct CSV structure and content
|
*/

test('csv download includes UTF-8 BOM', function () {
    $user = createTestUser();
    $report = ArbolReport::factory()->create(['author_id' => $user->id]);
    $section = ArbolSection::factory()->create([
        'arbol_report_id' => $report->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]);

    // Seed the cache with test data
    $arbolService = app(ArbolService::class);
    $arbolService->storeDataInCache($section, [
        'All' => [
            ['name' => 'Alice', 'state' => 'CA'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/arbol/series-data/download?'.http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    $response->assertOk();

    // Capture the streamed content
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // First 3 bytes should be the UTF-8 BOM
    $bom = substr($content, 0, 3);
    expect($bom)->toBe("\xEF\xBB\xBF");
});

test('csv download contains correct headers and data', function () {
    $user = createTestUser();
    $report = ArbolReport::factory()->create(['author_id' => $user->id]);
    $section = ArbolSection::factory()->create([
        'arbol_report_id' => $report->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]);

    $arbolService = app(ArbolService::class);
    $arbolService->storeDataInCache($section, [
        'All' => [
            ['name' => 'Alice', 'state' => 'CA'],
            ['name' => 'Bob', 'state' => 'NY'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/arbol/series-data/download?'.http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // Strip the BOM before parsing
    $content = substr($content, 3);
    $lines = array_filter(explode("\n", trim($content)));

    // Header row + 2 data rows
    expect($lines)->toHaveCount(3);

    // Parse CSV lines
    $headers = str_getcsv($lines[0]);
    expect($headers)->toContain('key', 'name', 'state');

    $row1 = str_getcsv($lines[1]);
    expect($row1)->toContain('All', 'Alice', 'CA');

    $row2 = str_getcsv($lines[2]);
    expect($row2)->toContain('All', 'Bob', 'NY');
});

test('csv download handles special characters correctly', function () {
    $user = createTestUser();
    $report = ArbolReport::factory()->create(['author_id' => $user->id]);
    $section = ArbolSection::factory()->create([
        'arbol_report_id' => $report->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]);

    $arbolService = app(ArbolService::class);
    $arbolService->storeDataInCache($section, [
        'All' => [
            ['name' => 'José García', 'state' => 'CA'],
            ['name' => 'François Müller', 'state' => 'NY'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/arbol/series-data/download?'.http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // Strip BOM
    $content = substr($content, 3);

    // The special characters should be preserved as-is (UTF-8)
    expect($content)->toContain('José García');
    expect($content)->toContain('François Müller');
});

test('csv download does not produce fputcsv escape deprecation warnings', function () {
    $user = createTestUser();
    $report = ArbolReport::factory()->create(['author_id' => $user->id]);
    $section = ArbolSection::factory()->create([
        'arbol_report_id' => $report->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]);

    $arbolService = app(ArbolService::class);
    $arbolService->storeDataInCache($section, [
        'All' => [
            ['name' => 'Test', 'value' => 'with\\backslash'],
            ['name' => 'Another', 'value' => 'with"quote'],
        ],
    ]);

    // Convert warnings to exceptions so the test fails if any occur
    set_error_handler(function ($severity, $message) {
        throw new \ErrorException($message, 0, $severity);
    }, E_WARNING | E_DEPRECATED);

    try {
        $response = $this->actingAs($user)->get('/arbol/series-data/download?'.http_build_query([
            'section_id' => $section->id,
            'series' => 'Test Series',
            'format' => 'table',
        ]));

        $response->assertOk();

        ob_start();
        $response->sendContent();
        ob_get_clean();
    } finally {
        restore_error_handler();
    }

    // If we got here without an exception, no deprecation warnings were triggered
    expect(true)->toBeTrue();
});

test('csv download formats numeric values with commas', function () {
    $user = createTestUser();
    $report = ArbolReport::factory()->create(['author_id' => $user->id]);
    $section = ArbolSection::factory()->create([
        'arbol_report_id' => $report->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]);

    $arbolService = app(ArbolService::class);
    $arbolService->storeDataInCache($section, [
        'All' => [
            ['name' => 'Big Number', 'amount' => 1234567.89],
        ],
    ]);

    $response = $this->actingAs($user)->get('/arbol/series-data/download?'.http_build_query([
        'section_id' => $section->id,
        'series' => 'Test Series',
        'format' => 'table',
    ]));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    $content = substr($content, 3);

    // number_format with commas
    expect($content)->toContain('"1,234,567.89"');
});

<?php

use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Support\Facades\File;

describe('ClearArbolCache Command', function () {
    beforeEach(function () {
        config()->set('arbol.series_path', __DIR__.'/../Series');
    });

    test('it clears cache for all sections', function () {
        $section1 = ArbolSection::factory()->create();
        $section2 = ArbolSection::factory()->create();

        $arbolService = app(ArbolService::class);
        $arbolService->storeDataInCache($section1, ['test' => 'data1']);
        $arbolService->storeDataInCache($section2, ['test' => 'data2']);

        expect($arbolService->getDataFromCache($section1))->not->toBeNull()
            ->and($arbolService->getDataFromCache($section2))->not->toBeNull();

        $this->artisan('arbol:clear')
            ->assertSuccessful();

        expect($arbolService->getDataFromCache($section1))->toBeNull()
            ->and($arbolService->getDataFromCache($section2))->toBeNull();
    });

    test('it clears cache for specific section', function () {
        $section1 = ArbolSection::factory()->create();
        $section2 = ArbolSection::factory()->create();

        $arbolService = app(ArbolService::class);
        $arbolService->storeDataInCache($section1, ['test' => 'data1']);
        $arbolService->storeDataInCache($section2, ['test' => 'data2']);

        $this->artisan('arbol:clear', ['--section' => $section1->id])
            ->assertSuccessful();

        expect($arbolService->getDataFromCache($section1))->toBeNull()
            ->and($arbolService->getDataFromCache($section2))->not->toBeNull();
    });

    test('it fails for non-existent section', function () {
        $this->artisan('arbol:clear', ['--section' => 99999])
            ->assertFailed();
    });
});

describe('MakeArbolSeries Command', function () {
    afterEach(function () {
        // Clean up any created files
        $files = [
            app_path('Arbol/TestGeneratedSeries.php'),
            app_path('Arbol/MyCustomSeries.php'),
        ];

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        // Remove directory if empty
        if (File::isDirectory(app_path('Arbol')) && count(File::files(app_path('Arbol'))) === 0) {
            File::deleteDirectory(app_path('Arbol'));
        }
    });

    test('it creates a new series file', function () {
        $this->artisan('make:arbol-series', ['name' => 'TestGenerated'])
            ->assertSuccessful();

        expect(File::exists(app_path('Arbol/TestGeneratedSeries.php')))->toBeTrue();

        $content = File::get(app_path('Arbol/TestGeneratedSeries.php'));
        expect($content)
            ->toContain('class TestGeneratedSeries implements IArbolSeries')
            ->toContain('public function name(): string')
            ->toContain('public function data(ArbolBag $arbolBag, $user = null): array')
            ->toContain('public function slices(): array')
            ->toContain('public function filters(): array')
            ->toContain('public function aggregators(): array');
    });

    test('it appends Series to class name if not present', function () {
        $this->artisan('make:arbol-series', ['name' => 'MyCustom'])
            ->assertSuccessful();

        expect(File::exists(app_path('Arbol/MyCustomSeries.php')))->toBeTrue();

        $content = File::get(app_path('Arbol/MyCustomSeries.php'));
        expect($content)->toContain('class MyCustomSeries implements IArbolSeries');
    });
});

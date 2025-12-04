<?php

namespace Calvient\Arbol\Services;

use Calvient\Arbol\Contracts\IArbolSeries;
use Calvient\Arbol\Models\ArbolSection;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use ReflectionClass;
use RegexIterator;

class ArbolService
{
    public function getSeries()
    {
        $classes = $this->getSeriesClasses();
        $series = [];

        foreach ($classes as $class) {
            $seriesInstance = new $class;
            $series[] = [
                'class' => $class,
                'name' => $seriesInstance->name(),
                'description' => $seriesInstance->description(),
                'slices' => array_keys($seriesInstance->slices()),
                'filters' => collect($seriesInstance->filters())->mapWithKeys(function ($filters, $key) {
                    return [$key => array_keys($filters)];
                })->toArray(),
                'aggregators' => array_keys($seriesInstance->aggregators()),
            ];
        }

        return $series;
    }

    public function getSeriesByName(string $name): ?array
    {
        $allSeries = $this->getSeries();

        foreach ($allSeries as $series) {
            if ($series['name'] === $name) {
                return $series;
            }
        }

        return null;
    }

    public function getSeriesClassByName(string $name)
    {
        $classes = $this->getSeriesClasses();
        foreach ($classes as $class) {
            $seriesInstance = new $class;
            if ($seriesInstance->name() === $name) {
                return $class;
            }
        }

        return null;
    }

    public function storeDataInCache(ArbolSection $arbolSection, mixed $data): void
    {
        Cache::put("arbol:section:{$arbolSection->id}", json_encode($data), now()->addDays(14));
    }

    public function storeFormattedDataInCache(ArbolSection $arbolSection, mixed $data): void
    {
        Cache::put("arbol:section:{$arbolSection->id}:formatted", json_encode($data), now()->addDays(14));
    }

    public function getDataFromCache(ArbolSection $arbolSection): mixed
    {
        $data = Cache::get("arbol:section:{$arbolSection->id}");

        return $data ? json_decode($data, true) : null;
    }

    public function getFormattedDataFromCache(ArbolSection $arbolSection): mixed
    {
        $data = Cache::get("arbol:section:{$arbolSection->id}:formatted");

        return $data ? json_decode($data, true) : null;
    }

    public function setIsRunning(ArbolSection $arbolSection, bool $flag)
    {
        Cache::put("arbol:section:{$arbolSection->id}:is_running", $flag, now()->addMinutes(30));
    }

    public function getIsRunning(ArbolSection $arbolSection): bool
    {
        return Cache::get("arbol:section:{$arbolSection->id}:is_running") ?? false;
    }

    public function setLastRunDuration(ArbolSection $arbolSection, int $duration): void
    {
        Cache::put("arbol:section:{$arbolSection->id}:last_run_duration", $duration, now()->addDays(14));
    }

    public function getLastRunDuration(ArbolSection $arbolSection): ?int
    {
        return Cache::get("arbol:section:{$arbolSection->id}:last_run_duration");
    }

    public function clearCacheForSection(ArbolSection $arbolSection): void
    {
        Cache::forget("arbol:section:{$arbolSection->id}");
        Cache::forget("arbol:section:{$arbolSection->id}:formatted");
        Cache::forget("arbol:section:{$arbolSection->id}:last_kicked_off");
        Cache::forget("arbol:section:{$arbolSection->id}:is_running");
        Cache::forget("arbol:section:{$arbolSection->id}:last_run_duration");
    }

    /**
     * Series are all classes anywhere that implement the IArbolSeries interface.
     *
     * This function returns an array of the classes.
     */
    private function getSeriesClasses(): array
    {
        $directory = config('arbol.series_path', app_path('Arbol'));

        $series = [];

        if (! is_dir($directory)) {
            throw new \InvalidArgumentException("Invalid directory path: $directory");
        }

        // Get all PHP files in the specified directory
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            require_once $file[0];
            $classes = get_declared_classes();
            foreach ($classes as $class) {
                $reflect = new ReflectionClass($class);
                if ($reflect->implementsInterface(IArbolSeries::class) && ! in_array($class, $series)) {
                    $series[] = $class;
                }
            }
        }

        return $series;
    }
}

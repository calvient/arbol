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

    /**
     * Compute a stable hash from a filters array for use in cache keys.
     */
    public static function computeFilterHash(array $filters): string
    {
        // Sort filters to ensure consistent hashing regardless of order
        $normalized = collect($filters)
            ->sortBy(fn ($f) => ($f['field'] ?? '') . ':' . ($f['value'] ?? ''))
            ->values()
            ->toArray();

        return md5(json_encode($normalized));
    }

    /**
     * Build the cache key prefix for a section, optionally scoped by filter hash.
     */
    private function cacheKey(ArbolSection $arbolSection, ?string $filterHash = null): string
    {
        $base = "arbol:section:{$arbolSection->id}";

        return $filterHash ? "{$base}:fh:{$filterHash}" : $base;
    }

    public function storeDataInCache(ArbolSection $arbolSection, mixed $data, ?string $filterHash = null): void
    {
        // Ensure group keys are preserved through the JSON round-trip.
        // When groupBy produces numeric keys (e.g. location IDs like 24, 161),
        // json_encode may produce a JSON array instead of an object, losing the keys.
        // Re-keying with string-cast keys forces JSON to produce an object.
        $dataArray = $data instanceof \Illuminate\Support\Collection ? $data->toArray() : $data;
        $preserved = [];
        foreach ($dataArray as $key => $value) {
            $preserved[(string) $key] = $value;
        }

        Cache::put($this->cacheKey($arbolSection, $filterHash), json_encode((object) $preserved), now()->addDays(14));
    }

    public function storeFormattedDataInCache(ArbolSection $arbolSection, mixed $data, ?string $filterHash = null): void
    {
        Cache::put($this->cacheKey($arbolSection, $filterHash) . ':formatted', json_encode($data), now()->addDays(14));
    }

    public function getDataFromCache(ArbolSection $arbolSection, ?string $filterHash = null): mixed
    {
        $data = Cache::get($this->cacheKey($arbolSection, $filterHash));

        return $data ? json_decode($data, true) : null;
    }

    public function getFormattedDataFromCache(ArbolSection $arbolSection, ?string $filterHash = null): mixed
    {
        $data = Cache::get($this->cacheKey($arbolSection, $filterHash) . ':formatted');

        return $data ? json_decode($data, true) : null;
    }

    public function setIsRunning(ArbolSection $arbolSection, bool $flag, ?string $filterHash = null)
    {
        Cache::put($this->cacheKey($arbolSection, $filterHash) . ':is_running', $flag, now()->addMinutes(30));
    }

    public function getIsRunning(ArbolSection $arbolSection, ?string $filterHash = null): bool
    {
        return Cache::get($this->cacheKey($arbolSection, $filterHash) . ':is_running') ?? false;
    }

    public function setLastRunDuration(ArbolSection $arbolSection, int $duration, ?string $filterHash = null): void
    {
        Cache::put($this->cacheKey($arbolSection, $filterHash) . ':last_run_duration', $duration, now()->addDays(14));
    }

    public function getLastRunDuration(ArbolSection $arbolSection, ?string $filterHash = null): ?int
    {
        return Cache::get($this->cacheKey($arbolSection, $filterHash) . ':last_run_duration');
    }

    public function clearCacheForSection(ArbolSection $arbolSection, ?string $filterHash = null): void
    {
        $key = $this->cacheKey($arbolSection, $filterHash);
        Cache::forget($key);
        Cache::forget("{$key}:formatted");
        Cache::forget("{$key}:last_kicked_off");
        Cache::forget("{$key}:is_running");
        Cache::forget("{$key}:last_run_duration");
    }

    // --- Hash-based cache methods for stateless (no section_id) usage ---

    /**
     * Build a cache key for stateless section access using a config hash.
     */
    public function hashCacheKey(string $configHash, ?string $filterHash = null): string
    {
        $base = "arbol:stateless:{$configHash}";

        return $filterHash ? "{$base}:fh:{$filterHash}" : $base;
    }

    public function storeDataInCacheByHash(string $configHash, mixed $data, ?string $filterHash = null): void
    {
        $dataArray = $data instanceof \Illuminate\Support\Collection ? $data->toArray() : $data;
        $preserved = [];
        foreach ($dataArray as $key => $value) {
            $preserved[(string) $key] = $value;
        }

        Cache::put($this->hashCacheKey($configHash, $filterHash), json_encode((object) $preserved), now()->addDays(14));
    }

    public function getDataFromCacheByHash(string $configHash, ?string $filterHash = null): mixed
    {
        $data = Cache::get($this->hashCacheKey($configHash, $filterHash));

        return $data ? json_decode($data, true) : null;
    }

    public function setIsRunningByHash(string $configHash, bool $flag, ?string $filterHash = null): void
    {
        Cache::put($this->hashCacheKey($configHash, $filterHash) . ':is_running', $flag, now()->addMinutes(30));
    }

    public function getIsRunningByHash(string $configHash, ?string $filterHash = null): bool
    {
        return Cache::get($this->hashCacheKey($configHash, $filterHash) . ':is_running') ?? false;
    }

    public function setLastRunDurationByHash(string $configHash, int $duration, ?string $filterHash = null): void
    {
        Cache::put($this->hashCacheKey($configHash, $filterHash) . ':last_run_duration', $duration, now()->addDays(14));
    }

    public function getLastRunDurationByHash(string $configHash, ?string $filterHash = null): ?int
    {
        return Cache::get($this->hashCacheKey($configHash, $filterHash) . ':last_run_duration');
    }

    public function clearCacheByHash(string $configHash, ?string $filterHash = null): void
    {
        $key = $this->hashCacheKey($configHash, $filterHash);
        Cache::forget($key);
        Cache::forget("{$key}:formatted");
        Cache::forget("{$key}:is_running");
        Cache::forget("{$key}:last_run_duration");
    }

    /**
     * Compute a stable config hash for stateless section access.
     */
    public static function computeConfigHash(string $series, string $format = 'table'): string
    {
        return md5(json_encode(['series' => $series, 'format' => $format]));
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

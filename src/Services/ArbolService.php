<?php

namespace Calvient\Arbol\Services;

use Calvient\Arbol\Contracts\IArbolSeries;
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
            $seriesInstance = new $class();
            $series[] = [
                'class' => $class,
                'name' => $seriesInstance->name(),
                'description' => $seriesInstance->description(),
                'slices' => array_keys($seriesInstance->slices()),
                'filters' => collect($seriesInstance->filters())->mapWithKeys(function ($filters, $key) {
                    return [$key => array_keys($filters)];
                })->toArray(),
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
            $seriesInstance = new $class();
            if ($seriesInstance->name() === $name) {
                return $class;
            }
        }

        return null;
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

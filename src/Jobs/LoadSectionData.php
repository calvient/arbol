<?php

namespace Calvient\Arbol\Jobs;

use Calvient\Arbol\DataObjects\ArbolBag;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LoadSectionData implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public function __construct(public ArbolSection $arbolSection, public string $series, public array $filters, public ?string $slice, public $user = null)
    {
    }

    public function handle(ArbolService $arbolService): void
    {
        try {
            $arbolService->setIsRunning($this->arbolSection, true);

            $this->loadData($arbolService);
        } catch (\Exception $e) {
            $arbolService->setIsRunning($this->arbolSection, false);

            // Let the exception bubble up so that the job can be retried
            throw $e;
        }
    }

    public function loadData(ArbolService $arbolService)
    {
        // Retrieve the series class by name
        $seriesClass = $arbolService->getSeriesClassByName($this->series);
        if (! $seriesClass) {
            return;
        }

        // Start the timer
        $start = microtime(true);

        // Create instance and ArbolBag with filters and slice
        $seriesInstance = new $seriesClass();
        $arbolBag = $this->createArbolBag();

        // Get data and apply slice
        $data = collect($seriesInstance->data($arbolBag, $this->user));
        $data = $this->slice ? $this->applySlice($data, $seriesInstance) : $data->groupBy(fn () => 'All');

        // Store data in cache
        $arbolService->storeDataInCache($this->arbolSection, $data);

        // End the timer
        $end = microtime(true);

        // Store the run time in cache in seconds
        $arbolService->setLastRunDuration($this->arbolSection, $end - $start);

        // Set the semaphore to indicate that the job is no longer running
        $arbolService->setIsRunning($this->arbolSection, false);
    }

    protected function createArbolBag(): ArbolBag
    {
        $arbolBag = new ArbolBag();

        // Add filters to ArbolBag
        collect($this->filters)->each(fn ($filter) => $arbolBag->addFilter($filter['field'], $filter['value']));

        // Add slice to ArbolBag if it exists
        if ($this->slice) {
            $arbolBag->addSlice($this->slice);
        }

        return $arbolBag;
    }

    protected function applySlice($data, $seriesInstance): Collection
    {
        foreach ($seriesInstance->slices() as $name => $callback) {
            if ($name === $this->slice) {
                return $data->groupBy(fn ($item) => $callback($item));
            }
        }

        return $data;
    }
}

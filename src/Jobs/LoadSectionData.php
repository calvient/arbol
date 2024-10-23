<?php

namespace Calvient\Arbol\Jobs;

use Calvient\Arbol\DataObjects\ArbolBag;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LoadSectionData implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public $timeout = 300;

    public function __construct(public ArbolSection $arbolSection, public string $series, public array $filters, public ?string $slice, public $user = null) {}

    public function handle(ArbolService $arbolService): void
    {
        try {
            $arbolService->setIsRunning($this->arbolSection, true);

            $this->loadData($arbolService);
        } catch (\Exception $e) {
            $arbolService->setIsRunning($this->arbolSection, false);
            logger()->error($e->getMessage());

            // Let the exception bubble up so that the job can be retried
            throw $e;
        }
    }

    public function loadData(ArbolService $arbolService)
    {
        logger()->info("Starting to load data for section {$this->arbolSection->name}");

        // Retrieve the series class by name
        $seriesClass = $arbolService->getSeriesClassByName($this->series);
        if (! $seriesClass) {
            return;
        }

        // Start the timer
        $start = microtime(true);

        // Create instance and ArbolBag with filters and slice
        $seriesInstance = new $seriesClass;
        $arbolBag = $this->createArbolBag();

        // Get data and apply slice
        logger()->info("Getting data for section {$this->arbolSection->name}");
        try {
            $data = collect($seriesInstance->data($arbolBag, $this->user));
            $data = $this->slice ? $this->applySlice($data, $seriesInstance) : $data->groupBy(fn () => 'All');
        } catch (\Exception $e) {
            $arbolService->setIsRunning($this->arbolSection, false);
            logger()->error('ARBOL ERROR: '.$e->getMessage());

            return;
        } finally {
            // End the timer
            $end = microtime(true);
            $seconds = $end - $start;
            logger()->info("Data for section {$this->arbolSection->name} loaded in {$seconds} seconds");
        }

        logger()->info("Data for section {$this->arbolSection->name} loaded");

        // Store data in cache
        $arbolService->storeDataInCache($this->arbolSection, $data);
        logger()->info("Data for section {$this->arbolSection->name} stored");

        // Store the run time in cache in seconds
        $arbolService->setLastRunDuration($this->arbolSection, $seconds);

        // Set the semaphore to indicate that the job is no longer running
        $arbolService->setIsRunning($this->arbolSection, false);
    }

    protected function createArbolBag(): ArbolBag
    {
        $arbolBag = new ArbolBag;

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

    public function uniqueId(): string
    {
        return (string) $this->arbolSection->id;
    }
}

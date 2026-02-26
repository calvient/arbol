<?php

namespace Calvient\Arbol\Jobs;

use Calvient\Arbol\DataObjects\ArbolBag;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadStatelessSectionData implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public $timeout = 600;

    public function __construct(
        public string $configHash,
        public string $series,
        public array $filters,
        public $user = null,
        public ?string $filterHash = null,
    ) {}

    public function handle(ArbolService $arbolService): void
    {
        ini_set('memory_limit', '2G');

        try {
            $arbolService->setIsRunningByHash($this->configHash, true, $this->filterHash);

            $this->loadData($arbolService);
        } catch (\Exception $e) {
            $arbolService->setIsRunningByHash($this->configHash, false, $this->filterHash);
            logger()->error($e->getMessage());

            throw $e;
        }
    }

    public function loadData(ArbolService $arbolService): void
    {
        $label = "stateless:{$this->configHash}";
        logger()->info("Starting to load data for {$label}");

        $seriesClass = $arbolService->getSeriesClassByName($this->series);
        if (! $seriesClass) {
            return;
        }

        $start = microtime(true);

        $seriesInstance = new $seriesClass;
        $arbolBag = $this->createArbolBag();

        logger()->info("Getting data for {$label}");
        try {
            $data = collect($seriesInstance->data($arbolBag, $this->user));
            $data = $data->groupBy(fn () => 'All');
        } catch (\Exception $e) {
            $arbolService->setIsRunningByHash($this->configHash, false, $this->filterHash);
            logger()->error('ARBOL ERROR: '.$e->getMessage());

            return;
        } finally {
            $end = microtime(true);
            $seconds = $end - $start;
            logger()->info("Data for {$label} loaded in {$seconds} seconds");
        }

        logger()->info("Data for {$label} loaded");

        $arbolService->storeDataInCacheByHash($this->configHash, $data, $this->filterHash);
        logger()->info("Data for {$label} raw data stored");

        $arbolService->setLastRunDurationByHash($this->configHash, (int) round($seconds), $this->filterHash);
        $arbolService->setIsRunningByHash($this->configHash, false, $this->filterHash);
    }

    protected function createArbolBag(): ArbolBag
    {
        $arbolBag = new ArbolBag;

        collect($this->filters)->each(fn ($filter) => $arbolBag->addFilter($filter['field'], $filter['value']));

        return $arbolBag;
    }

    public function uniqueId(): string
    {
        $timestamp = intdiv(now()->timestamp, 300) * 300;

        $base = "stateless_{$this->configHash}_{$timestamp}";

        return $this->filterHash ? "{$base}_{$this->filterHash}" : $base;
    }
}

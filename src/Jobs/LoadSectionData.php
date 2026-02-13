<?php

namespace Calvient\Arbol\Jobs;

use Calvient\Arbol\Contracts\IArbolSeries;
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

    public $timeout = 600;

    public function __construct(
        public ArbolSection $arbolSection,
        public string $series,
        public array $filters,
        public ?string $slice,
        public $user = null,
        public string $format = 'table',
        public string $aggregator = 'Default',
        public ?string $chartSlice = null,
        public ?string $percentageMode = null,
    ) {}

    public function handle(ArbolService $arbolService): void
    {
        // Increase memory limit and disable execution time limit for this job
        ini_set('memory_limit', '2G');
        set_time_limit(0);

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

        // Store raw data in cache (for table format and downloads)
        $arbolService->storeDataInCache($this->arbolSection, $data);
        logger()->info("Data for section {$this->arbolSection->name} raw data stored");

        // Format and store formatted data for charts (to avoid expensive formatting on each request)
        if ($this->format !== 'table') {
            $formattedData = $this->formatData($data->toArray(), $seriesInstance);
            $arbolService->storeFormattedDataInCache($this->arbolSection, $formattedData);
            logger()->info("Data for section {$this->arbolSection->name} formatted data stored");
        }

        // Store the run time in cache in seconds
        $arbolService->setLastRunDuration($this->arbolSection, (int) round($seconds));

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

    protected function formatData(array $data, IArbolSeries $seriesInstance): array
    {
        $formatted = match ($this->format) {
            'table' => $data,
            'line', 'bar' => $this->formatForChart($data, $seriesInstance),
            'pie' => $this->formatForPie($data, $seriesInstance),
            default => $data,
        };

        if ($this->percentageMode && in_array($this->format, ['line', 'bar'])) {
            $formatted = $this->applyPercentageMode($formatted);
        }

        // Apply chart group truncation to prevent browser overload
        if ($this->format !== 'table') {
            $formatted = $this->truncateChartData($formatted);
        }

        return $formatted;
    }

    protected function truncateChartData(array $data): array
    {
        $maxGroups = config('arbol.max_chart_groups');

        if (! $maxGroups || count($data) <= $maxGroups) {
            return $data;
        }

        $totalCount = count($data);
        $truncated = array_slice($data, 0, $maxGroups);

        // Append a metadata marker that the frontend can detect
        $truncated[] = [
            '_meta' => 'truncated',
            '_total' => $totalCount,
            '_shown' => $maxGroups,
        ];

        return $truncated;
    }

    protected function applyPercentageMode(array $data): array
    {
        if ($this->percentageMode === 'xaxis_group') {
            // Calculate percentage within each x-axis group (row)
            return array_map(function ($row) {
                $numericTotal = 0;
                foreach ($row as $key => $value) {
                    if ($key !== 'name' && is_numeric($value)) {
                        $numericTotal += $value;
                    }
                }

                if ($numericTotal == 0) {
                    return $row;
                }

                $result = [];
                foreach ($row as $key => $value) {
                    if ($key !== 'name' && is_numeric($value)) {
                        $result[$key] = round(($value / $numericTotal) * 100, 2);
                    } else {
                        $result[$key] = $value;
                    }
                }

                return $result;
            }, $data);
        }

        if ($this->percentageMode === 'total') {
            // Calculate percentage against grand total across all rows
            $grandTotal = 0;
            foreach ($data as $row) {
                foreach ($row as $key => $value) {
                    if ($key !== 'name' && is_numeric($value)) {
                        $grandTotal += $value;
                    }
                }
            }

            if ($grandTotal == 0) {
                return $data;
            }

            return array_map(function ($row) use ($grandTotal) {
                $result = [];
                foreach ($row as $key => $value) {
                    if ($key !== 'name' && is_numeric($value)) {
                        $result[$key] = round(($value / $grandTotal) * 100, 2);
                    } else {
                        $result[$key] = $value;
                    }
                }

                return $result;
            }, $data);
        }

        return $data;
    }

    protected function formatForChart(array $data, IArbolSeries $seriesInstance): array
    {
        $slices = $seriesInstance->slices();
        $aggregators = $seriesInstance->aggregators();
        $aggregatorFn = $aggregators[$this->aggregator] ?? $aggregators['Default'];
        $slice = $this->chartSlice;

        // Compute all unique slice values ONCE before the loop (O(N) instead of O(N*M))
        $allSliceValues = [];
        if ($slice && $slice !== 'All' && $slice !== 'None' && $slice !== 'null' && isset($slices[$slice])) {
            $allSliceValues = collect($data)
                ->flatMap(function ($rows) use ($slices, $slice) {
                    $isArray = is_array($rows[0] ?? null);

                    return collect($isArray ? $rows : [$rows])
                        ->filter(fn ($row) => count($row) > 0)
                        ->map(fn ($row) => $slices[$slice]($row))
                        ->unique()
                        ->values();
                })
                ->unique()
                ->values()
                ->toArray();
        }

        return collect($data)
            ->map(function ($rows, $key) use ($slice, $aggregatorFn, $slices, $allSliceValues) {
                if (! $slice || $slice === 'All' || $slice === 'None' || $slice === 'null' || ! isset($slices[$slice])) {
                    return [
                        'name' => $key,
                        'value' => round($aggregatorFn($rows), 2),
                    ];
                }

                $isArray = is_array($rows[0] ?? null);

                // Get the count for each slice key
                $totals = collect($isArray ? $rows : [$rows])
                    ->filter(fn ($row) => count($row) > 0)
                    ->groupBy($slices[$slice])
                    ->map(fn ($rows) => round($aggregatorFn($rows), 2))
                    ->toArray();

                // Ensure all possible slice values are included with 0 as default
                foreach ($allSliceValues as $sliceValue) {
                    if (! isset($totals[$sliceValue])) {
                        $totals[$sliceValue] = 0;
                    }
                }

                $totals['name'] = $key;

                return $totals;
            })
            ->values()
            ->toArray();
    }

    protected function formatForPie(array $data, IArbolSeries $seriesInstance): array
    {
        $aggregators = $seriesInstance->aggregators();

        // If no aggregator is defined or 'Default' is not in the aggregators list, fall back to counting rows
        if (! isset($aggregators[$this->aggregator]) && ! isset($aggregators['Default'])) {
            return collect($data)
                ->map(fn ($value, $key) => [
                    'name' => $key,
                    'value' => count($value),
                ])
                ->values()
                ->toArray();
        }

        $aggregatorFn = $aggregators[$this->aggregator] ?? $aggregators['Default'];

        return collect($data)
            ->map(function ($value, $key) use ($aggregatorFn) {
                $aggregatedValue = $aggregatorFn($value);
                $aggregatedValue = is_string($aggregatedValue)
                    ? (float) $aggregatedValue
                    : $aggregatedValue;

                return [
                    'name' => $key,
                    'value' => round($aggregatedValue, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    public function uniqueId(): string
    {
        // Round current timestamp down to nearest 5 minutes for id uniqueness
        $timestamp = intdiv(now()->timestamp, 300) * 300;

        return "{$this->arbolSection->id}_{$timestamp}";
    }
}

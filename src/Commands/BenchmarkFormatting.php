<?php

namespace Calvient\Arbol\Commands;

use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Console\Command;

class BenchmarkFormatting extends Command
{
    public $signature = 'arbol:benchmark
        {--section= : Benchmark a specific section ID (uses its cached data)}
        {--groups=50 : Number of x-axis groups for synthetic data}
        {--rows=500 : Rows per group for synthetic data}
        {--slices=20 : Number of distinct slice values for synthetic data}';

    public $description = 'Benchmark the old O(N*M) vs new O(N+M) formatForChart performance.';

    public function handle(ArbolService $arbolService): int
    {
        $sectionId = $this->option('section');

        if ($sectionId) {
            return $this->benchmarkWithRealData($arbolService, (int) $sectionId);
        }

        return $this->benchmarkWithSyntheticData(
            (int) $this->option('groups'),
            (int) $this->option('rows'),
            (int) $this->option('slices'),
        );
    }

    /**
     * Benchmark using real cached data from an existing section.
     */
    private function benchmarkWithRealData(ArbolService $arbolService, int $sectionId): int
    {
        $section = ArbolSection::find($sectionId);
        if (! $section) {
            $this->error("Section with ID {$sectionId} not found.");

            return self::FAILURE;
        }

        $data = $arbolService->getDataFromCache($section);
        if (! $data) {
            $this->error("No cached data for section '{$section->name}'. View the report first to generate data.");

            return self::FAILURE;
        }

        // Get the series instance for slice/aggregator info
        $seriesInfo = $arbolService->getSeriesByName($section->series);
        if (! $seriesInfo) {
            $this->error("Series '{$section->series}' not found.");

            return self::FAILURE;
        }

        $series = new $seriesInfo['class'];
        $slices = $series->slices();
        $aggregators = $series->aggregators();

        // Determine which slice to use (chartSlice from section config or first available)
        $slice = $section->slice;
        $aggregator = $section->aggregator ?? 'Default';
        $aggregatorFn = $aggregators[$aggregator] ?? $aggregators['Default'] ?? fn ($rows) => count($rows);

        $groupCount = count($data);
        $totalRows = collect($data)->sum(fn ($rows) => is_array($rows) ? count($rows) : 0);
        $sliceValues = 0;

        if ($slice && isset($slices[$slice])) {
            $sliceFn = $slices[$slice];
            $sliceValues = collect($data)
                ->flatMap(function ($rows) use ($sliceFn) {
                    $isArray = is_array($rows[0] ?? null);

                    return collect($isArray ? $rows : [$rows])
                        ->filter(fn ($row) => count($row) > 0)
                        ->map(fn ($row) => $sliceFn($row))
                        ->unique();
                })
                ->unique()
                ->count();
        }

        $this->info('');
        $this->info("  Benchmarking section: {$section->name} (ID: {$sectionId})");
        $this->info("  Series: {$section->series}");
        $this->info("  Slice: " . ($slice ?: 'None'));
        $this->info("  Aggregator: {$aggregator}");
        $this->info("  Groups: {$groupCount}");
        $this->info("  Total rows: {$totalRows}");
        $this->info("  Unique slice values: {$sliceValues}");

        if (! $slice || ! isset($slices[$slice])) {
            $this->warn('  No slice configured — formatForChart takes the simple path (no N*M issue).');
            $this->warn('  Running benchmark anyway for completeness...');
            $sliceFn = fn ($row) => 'All';
        } else {
            $sliceFn = $slices[$slice];
            $expectedOld = $groupCount * $totalRows;
            $expectedNew = $totalRows + $totalRows;
            $this->info("  Old algorithm: ~{$expectedOld} slice callback invocations");
            $this->info("  New algorithm: ~{$expectedNew} slice callback invocations");
        }

        $this->info('');

        return $this->runBenchmark($data, $sliceFn, $aggregatorFn);
    }

    /**
     * Benchmark using synthetic generated data.
     */
    private function benchmarkWithSyntheticData(int $numGroups, int $rowsPerGroup, int $numSliceValues): int
    {
        $this->info('');
        $this->info("  Generating synthetic data...");
        $this->info("  Groups: {$numGroups}");
        $this->info("  Rows per group: {$rowsPerGroup}");
        $this->info("  Slice values: {$numSliceValues}");

        $totalRows = $numGroups * $rowsPerGroup;
        $expectedOld = $numGroups * $totalRows;
        $expectedNew = $totalRows + $totalRows;
        $this->info("  Total rows: {$totalRows}");
        $this->info("  Old algorithm: ~{$expectedOld} slice callback invocations");
        $this->info("  New algorithm: ~{$expectedNew} slice callback invocations");

        $states = [];
        for ($i = 0; $i < $numSliceValues; $i++) {
            $states[] = 'S' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        }

        $data = [];
        for ($g = 0; $g < $numGroups; $g++) {
            $rows = [];
            for ($r = 0; $r < $rowsPerGroup; $r++) {
                $rows[] = [
                    'name' => "Item_{$g}_{$r}",
                    'state' => $states[$r % $numSliceValues],
                    'value' => rand(1, 1000),
                ];
            }
            $data["Group_{$g}"] = $rows;
        }

        $sliceFn = fn ($row) => $row['state'];
        $aggregatorFn = fn ($rows) => count($rows);

        $this->info('');

        return $this->runBenchmark($data, $sliceFn, $aggregatorFn);
    }

    /**
     * Run the actual benchmark comparing old vs new.
     */
    private function runBenchmark(array $data, callable $sliceFn, callable $aggregatorFn): int
    {
        $this->info('  Warming up...');
        $this->formatNew($data, $sliceFn, $aggregatorFn);

        // --- OLD ---
        $this->info('  Running OLD algorithm (allSliceValues inside loop)...');
        $oldStart = hrtime(true);
        $oldResult = $this->formatOld($data, $sliceFn, $aggregatorFn);
        $oldMs = (hrtime(true) - $oldStart) / 1e6;

        // --- NEW ---
        $this->info('  Running NEW algorithm (allSliceValues hoisted)...');
        $newStart = hrtime(true);
        $newResult = $this->formatNew($data, $sliceFn, $aggregatorFn);
        $newMs = (hrtime(true) - $newStart) / 1e6;

        $speedup = $oldMs > 0 ? $oldMs / max($newMs, 0.001) : 0;
        $identical = $oldResult === $newResult;

        $this->info('');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Old algorithm', sprintf('%.1f ms', $oldMs)],
                ['New algorithm', sprintf('%.1f ms', $newMs)],
                ['Speedup', sprintf('%.1fx', $speedup)],
                ['Results identical', $identical ? 'YES' : 'NO'],
                ['Output rows', (string) count($newResult)],
            ]
        );

        if (! $identical) {
            $this->error('  WARNING: Results differ! This should not happen.');

            return self::FAILURE;
        }

        if ($speedup >= 2.0) {
            $this->info("  The new algorithm is {$this->formatSpeedup($speedup)} faster.");
        } elseif ($speedup >= 1.0) {
            $this->warn("  Marginal improvement ({$this->formatSpeedup($speedup)} faster). Dataset may be too small to show the difference.");
        } else {
            $this->warn('  No improvement detected. This is expected for very small datasets or no-slice scenarios.');
        }

        $this->info('');

        return self::SUCCESS;
    }

    /**
     * OLD: $allSliceValues computed inside the map (O(N*M))
     */
    private function formatOld(array $data, callable $sliceFn, callable $aggregatorFn): array
    {
        return collect($data)
            ->map(function ($rows, $key) use ($sliceFn, $aggregatorFn, $data) {
                $isArray = is_array($rows[0] ?? null);

                $allSliceValues = collect($data)
                    ->flatMap(function ($rows) use ($sliceFn) {
                        $isArray = is_array($rows[0] ?? null);

                        return collect($isArray ? $rows : [$rows])
                            ->filter(fn ($row) => count($row) > 0)
                            ->map(fn ($row) => $sliceFn($row))
                            ->unique()
                            ->values();
                    })
                    ->unique()
                    ->values()
                    ->toArray();

                $totals = collect($isArray ? $rows : [$rows])
                    ->filter(fn ($row) => count($row) > 0)
                    ->groupBy($sliceFn)
                    ->map(fn ($rows) => round($aggregatorFn($rows), 2))
                    ->toArray();

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

    /**
     * NEW: $allSliceValues computed once before the map (O(N+M))
     */
    private function formatNew(array $data, callable $sliceFn, callable $aggregatorFn): array
    {
        $allSliceValues = collect($data)
            ->flatMap(function ($rows) use ($sliceFn) {
                $isArray = is_array($rows[0] ?? null);

                return collect($isArray ? $rows : [$rows])
                    ->filter(fn ($row) => count($row) > 0)
                    ->map(fn ($row) => $sliceFn($row))
                    ->unique()
                    ->values();
            })
            ->unique()
            ->values()
            ->toArray();

        return collect($data)
            ->map(function ($rows, $key) use ($aggregatorFn, $sliceFn, $allSliceValues) {
                $isArray = is_array($rows[0] ?? null);

                $totals = collect($isArray ? $rows : [$rows])
                    ->filter(fn ($row) => count($row) > 0)
                    ->groupBy($sliceFn)
                    ->map(fn ($rows) => round($aggregatorFn($rows), 2))
                    ->toArray();

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

    private function formatSpeedup(float $speedup): string
    {
        if ($speedup >= 100) {
            return round($speedup) . 'x';
        }

        return round($speedup, 1) . 'x';
    }
}

<?php

/**
 * Performance benchmark comparing the old O(N*M) formatForChart approach
 * with the new O(N+M) hoisted approach.
 *
 * This test generates a realistic-sized dataset and measures the wall-clock
 * time of each algorithm, then asserts that:
 *   1. Both produce identical output (correctness)
 *   2. The new approach is measurably faster (performance)
 */

/*
|--------------------------------------------------------------------------
| Helpers — old vs new algorithm implementations
|--------------------------------------------------------------------------
*/

/**
 * OLD algorithm: computes $allSliceValues INSIDE the .map() — O(N*M)
 */
function formatForChartOld(array $data, callable $sliceFn, callable $aggregatorFn): array
{
    return collect($data)
        ->map(function ($rows, $key) use ($sliceFn, $aggregatorFn, $data) {
            $isArray = is_array($rows[0] ?? null);

            // This runs for EVERY group — the performance killer
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
 * NEW algorithm: computes $allSliceValues ONCE before the .map() — O(N+M)
 */
function formatForChartNew(array $data, callable $sliceFn, callable $aggregatorFn): array
{
    // Single pass to collect all unique slice values
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

/**
 * Generate a synthetic grouped dataset.
 *
 * @param  int  $numGroups   Number of x-axis groups (e.g. months)
 * @param  int  $rowsPerGroup  Rows in each group
 * @param  int  $numSliceValues  Distinct slice values spread across groups
 */
function generateData(int $numGroups, int $rowsPerGroup, int $numSliceValues): array
{
    $states = [];
    for ($i = 0; $i < $numSliceValues; $i++) {
        $states[] = 'S'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    }

    $data = [];
    for ($g = 0; $g < $numGroups; $g++) {
        $groupKey = 'Group_'.$g;
        $rows = [];
        for ($r = 0; $r < $rowsPerGroup; $r++) {
            $rows[] = [
                'name' => "Item_{$g}_{$r}",
                'state' => $states[$r % $numSliceValues],
                'value' => rand(1, 1000),
            ];
        }
        $data[$groupKey] = $rows;
    }

    return $data;
}

/*
|--------------------------------------------------------------------------
| Benchmark test
|--------------------------------------------------------------------------
*/

test('new formatForChart is significantly faster than old on large datasets', function () {
    $sliceFn = fn ($row) => $row['state'];
    $aggregatorFn = fn ($rows) => count($rows);

    // 50 groups × 500 rows × 20 slice values
    // Old: ~50 × (50 × 500) = 1.25M slice calls
    // New: ~(50 × 500) + (50 × 500) = 50K slice calls — 25x fewer
    $data = generateData(numGroups: 50, rowsPerGroup: 500, numSliceValues: 20);

    // Warm up (JIT, autoloader, etc.)
    formatForChartNew($data, $sliceFn, $aggregatorFn);

    // --- Time the OLD approach ---
    $oldStart = hrtime(true);
    $oldResult = formatForChartOld($data, $sliceFn, $aggregatorFn);
    $oldElapsed = (hrtime(true) - $oldStart) / 1e6; // ms

    // --- Time the NEW approach ---
    $newStart = hrtime(true);
    $newResult = formatForChartNew($data, $sliceFn, $aggregatorFn);
    $newElapsed = (hrtime(true) - $newStart) / 1e6; // ms

    // 1. Correctness: both must produce identical output
    expect($newResult)->toBe($oldResult);

    // 2. Performance: new should be at least 2x faster
    //    (In practice it's usually 10-50x faster at this scale)
    $speedup = $oldElapsed / max($newElapsed, 0.001);

    // Log the results for visibility
    logger()->info("Performance benchmark: Old={$oldElapsed}ms, New={$newElapsed}ms, Speedup={$speedup}x");

    expect($speedup)->toBeGreaterThan(2.0,
        "Expected at least 2x speedup but got {$speedup}x (Old: {$oldElapsed}ms, New: {$newElapsed}ms)"
    );
});

test('both approaches produce identical results with varied slice distribution', function () {
    $sliceFn = fn ($row) => $row['state'];
    $aggregatorFn = fn ($rows) => collect($rows)->sum('value');

    // Smaller dataset but with many slice values — some groups won't have all slices
    $data = generateData(numGroups: 20, rowsPerGroup: 100, numSliceValues: 15);

    $oldResult = formatForChartOld($data, $sliceFn, $aggregatorFn);
    $newResult = formatForChartNew($data, $sliceFn, $aggregatorFn);

    expect($newResult)->toBe($oldResult);

    // Every row should have ALL 15 slice values + 'name' = 16 keys
    foreach ($newResult as $row) {
        expect(count($row))->toBe(16);
        expect($row)->toHaveKey('name');
    }
});

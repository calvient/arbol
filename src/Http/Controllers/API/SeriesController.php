<?php

namespace Calvient\Arbol\Http\Controllers\API;

use Calvient\Arbol\Jobs\LoadSectionData;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SeriesController extends Controller
{
    public function __construct(public ArbolService $arbolService)
    {
    }

    public function getSeriesData(): JsonResponse
    {
        // Validate the request inputs
        $validator = Validator::make(request()->all(), [
            'section_id' => 'required|integer',
            'series' => 'required|string',
            'slice' => 'nullable|string',
            'xaxis_slice' => 'nullable|string',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string',
            'filters.*.value' => 'required|string',
            'format' => 'required|string',
            'force_refresh' => 'nullable|boolean',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the section from the database
        $section = ArbolSection::findOrFail(request('section_id'));

        // Clear the cache if the force_refresh parameter is set
        if (request('force_refresh')) {
            $this->arbolService->clearCacheForSection($section);
        }

        // Get the cached data
        $data = $this->arbolService->getDataFromCache(
            arbolSection: $section
        );

        // Return the data if it exists
        if ($data) {
            return match (request('format')) {
                'table' => $this->formatForTable($data),
                'line', 'bar' => $this->formatForChart($data, request('slice')),
                'pie' => $this->formatForPie($data),
                default => response()->json(['error' => 'Invalid format'], 400),
            };
        }

        if (! $this->isCurrentlyRunning() || request('force_refresh')) {
            LoadSectionData::dispatch(
                arbolSection: $section,
                series: request('series'),
                filters: request('filters', []),
                // xaxis_slice is used for line and bar charts
                slice: request('format') === 'line' || request('format') === 'bar'
                                ? request('xaxis_slice')
                                : request('slice'),
            );
        }

        return response()->json(
            [
                'message' => 'We are currently processing your request. Please try again in a few seconds.',
                'estimated_time' => $this->arbolService->getLastRunDuration(
                    arbolSection: $section,
                ) ?? 60,
            ],
            202,
        );
    }

    private function formatForTable(array $data): JsonResponse
    {
        return response()->json($data);
    }

    private function formatForChart(array $data, string $slice = ''): JsonResponse
    {
        $formattedData = collect($data)
            ->map(function ($rows, $key) use ($slice) {
                $seriesInfo = $this->arbolService->getSeriesByName(request('series'));
                $series = new $seriesInfo['class']();
                $slices = $series->slices();

                if (! $slice || $slice === 'All' || $slice === 'None' || $slice === 'null' || ! isset($slices[$slice])) {
                    return [
                        'name' => $key,
                        'value' => count($rows),
                    ];
                }

                $isArray = is_array($rows[0] ?? null);

                // Get the count for each slice key
                $totals = collect($isArray ? $rows : [$rows])
                    ->groupBy($slices[$slice])
                    ->map(fn ($rows) => count($rows))
                    ->toArray();
                $totals['name'] = $key;

                return $totals;
            })
            ->values()
            ->toArray();

        return response()->json($formattedData);
    }

    private function formatForPie(array $data): JsonResponse
    {
        $formattedData = collect($data)
            ->map(fn ($value, $key) => [
                'name' => $key,
                'value' => count($value),
            ])
            ->values()
            ->toArray();

        return response()->json($formattedData);
    }

    private function isCurrentlyRunning(): bool
    {
        return $this->arbolService->getIsRunning(
            arbolSection: ArbolSection::findOrFail(request('section_id')),
        );
    }
}

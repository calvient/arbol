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
            'aggregator' => 'nullable|string',
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
        if (!is_null($data)) {
            if(empty($data)){
                $data = ['No data found' => []];
            }

            $formattedData = match (request('format')) {
                'table' => $this->formatForTable($data),
                'line', 'bar' => $this->formatForChart($data, request('slice'), request('aggregator')),
                'pie' => $this->formatForPie($data),
                default => abort(400, 'Invalid format parameter'),
            };

            return response()->json($formattedData);
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
                user: auth()->user(),
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

    public function downloadData()
    {
        // Validate the request inputs
        $validator = Validator::make(request()->all(), [
            'section_id' => 'required|integer',
            'series' => 'required|string',
            'slice' => 'nullable|string',
            'xaxis_slice' => 'nullable|string',
            'aggregator' => 'nullable|string',
            'format' => 'required|string',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the section from the database
        $section = ArbolSection::findOrFail(request('section_id'));

        // Get the cached data
        $data = $this->arbolService->getDataFromCache(
            arbolSection: $section
        );
        if (! $data) {
            abort(404, 'Data not found');
        }

        $formattedData = match (request('format')) {
            'table' => $this->formatForTable($data),
            'line', 'bar' => $this->formatForChart($data, request('slice'), request('aggregator')),
            'pie' => $this->formatForPie($data),
            default => abort(400, 'Invalid format parameter'),
        };

        return $this->downloadCsv($formattedData);
    }

    private function formatForTable(array $data): array
    {
        return $data;
    }

    private function formatForChart(array $data, string $slice = '', string $aggregator = 'Default'): array
    {
        $formattedData = collect($data)
            ->map(function ($rows, $key) use ($slice, $aggregator) {
                $seriesInfo = $this->arbolService->getSeriesByName(request('series'));
                $series = new $seriesInfo['class']();
                $slices = $series->slices();
                $aggregators = $series->aggregators();
                $aggregatorFn = $aggregators[$aggregator] ?? $aggregators['Default'];

                if (! $slice || $slice === 'All' || $slice === 'None' || $slice === 'null' || ! isset($slices[$slice])) {
                    return [
                        'name' => $key,
                        'value' => round($aggregatorFn($rows), 2),
                    ];
                }

                $isArray = is_array($rows[0] ?? null);

                // Get the count for each slice key
                $totals = collect($isArray ? $rows : [$rows])
                    ->groupBy($slices[$slice])
                    ->map(fn ($rows) => round($aggregatorFn($rows), 2))
                    ->toArray();
                $totals['name'] = $key;

                return $totals;
            })
            ->values()
            ->toArray();

        return $formattedData;
    }

    private function formatForPie(array $data): array
    {
        return collect($data)
            ->map(fn ($value, $key) => [
                'name' => $key,
                'value' => count($value),
            ])
            ->values()
            ->toArray();

    }

    private function isCurrentlyRunning(): bool
    {
        return $this->arbolService->getIsRunning(
            arbolSection: ArbolSection::findOrFail(request('section_id')),
        );
    }

    private function downloadCsv(array $data)
    {
        $data = $this->flattenArray($data);

        // Output to a csv and return it as a download
        $csv = fopen('php://temp', 'r+');

        // Write the column headers
        $columnHeaders = [];
        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                if (! in_array($key, $columnHeaders)) {
                    $columnHeaders[] = $key;
                }
            }
        }

        fputcsv($csv, $columnHeaders);

        // Write the data to the csv
        foreach ($data as $row) {
            $rowData = [];
            foreach ($columnHeaders as $header) {
                $rowData[] = $row[$header] ?? '';
            }
            fputcsv($csv, $rowData);
        }

        // Rewind the file pointer, in order to read the file
        rewind($csv);

        // Return the csv as a download
        return response()->streamDownload(function () use ($csv) {
            fpassthru($csv);
        }, 'data.csv');
    }

    private function flattenArray(array $data)
    {
        // We need the data to be flat for the CSV
        // If the rows are under keys like ['key' => $rows] then we need to flatten them
        // and make the key a column on the row
        $flattenedData = [];
        foreach ($data as $key => $rows) {
            if (is_array($rows[0] ?? null)) {
                foreach ($rows as $row) {
                    $flattenedData[] = array_merge(['key' => $key], $row);
                }
            } else {
                $flattenedData[] = $rows;
            }
        }

        return $flattenedData;
    }
}

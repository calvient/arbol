<?php

namespace Calvient\Arbol\Http\Controllers\API;

use Calvient\Arbol\Jobs\LoadStatelessSectionData;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatelessSectionController extends Controller
{
    public function __construct(public ArbolService $arbolService) {}

    public function getData(): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'series' => 'required|string',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string',
            'filters.*.value' => 'required|string',
            'format' => 'required|string|in:table',
            'force_refresh' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $filters = request('filters', []);
        $configHash = ArbolService::computeConfigHash(request('series'), request('format'));
        $filterHash = ! empty($filters) ? ArbolService::computeFilterHash($filters) : null;

        if (request('force_refresh')) {
            $this->arbolService->clearCacheByHash($configHash, $filterHash);
        }

        // Check raw cache
        $data = $this->arbolService->getDataFromCacheByHash($configHash, $filterHash);

        if (! is_null($data)) {
            if (empty($data)) {
                $data = ['No data found' => []];
            }

            return response()->json($data);
        }

        // Dispatch job if not already running
        if (! $this->arbolService->getIsRunningByHash($configHash, $filterHash) || request('force_refresh')) {
            LoadStatelessSectionData::dispatch(
                configHash: $configHash,
                series: request('series'),
                filters: $filters,
                user: auth()->user(),
                filterHash: $filterHash,
            );
        }

        return response()->json(
            [
                'message' => 'We are currently processing your request. Please try again in a few seconds.',
                'estimated_time' => $this->arbolService->getLastRunDurationByHash($configHash, $filterHash) ?? 300,
            ],
            202,
        );
    }

    public function download(): StreamedResponse
    {
        $validator = Validator::make(request()->all(), [
            'series' => 'required|string',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string',
            'filters.*.value' => 'required|string',
            'format' => 'required|string|in:table',
            'slice_key' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            abort(400, 'Invalid parameters.');
        }

        $filters = request('filters', []);
        $configHash = ArbolService::computeConfigHash(request('series'), request('format'));
        $filterHash = ! empty($filters) ? ArbolService::computeFilterHash($filters) : null;

        $data = $this->arbolService->getDataFromCacheByHash($configHash, $filterHash);

        if (! $data) {
            abort(404, 'Data not found. Please view the section first to generate the data.');
        }

        // Filter to specific slice key if provided
        $sliceKey = request('slice_key');
        if ($sliceKey && isset($data[$sliceKey])) {
            $data = [$sliceKey => $data[$sliceKey]];
        }

        return $this->downloadCsv($data);
    }

    private function downloadCsv(array $data): StreamedResponse
    {
        $data = $this->flattenArray($data);

        $csv = fopen('php://temp', 'r+');

        // UTF-8 BOM for Excel compatibility
        fwrite($csv, "\xEF\xBB\xBF");

        // Collect all column headers
        $columnHeaders = [];
        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                if (! in_array($key, $columnHeaders)) {
                    $columnHeaders[] = $key;
                }
            }
        }

        fputcsv($csv, $columnHeaders, ',', '"', '');

        foreach ($data as $row) {
            $rowData = [];
            foreach ($columnHeaders as $header) {
                $value = $row[$header] ?? '';
                if (is_numeric($value)) {
                    $value = number_format((float) $value, 2, '.', ',');
                }
                $rowData[] = $value;
            }
            fputcsv($csv, $rowData, ',', '"', '');
        }

        rewind($csv);

        return response()->streamDownload(function () use ($csv) {
            fpassthru($csv);
        }, 'data.csv');
    }

    private function flattenArray(array $data): array
    {
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

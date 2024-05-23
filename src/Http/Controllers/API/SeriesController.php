<?php

namespace Calvient\Arbol\Http\Controllers\API;

use Calvient\Arbol\DataObjects\ArbolBag;
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
            'series' => 'required|string',
            'slice' => 'nullable|string',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string',
            'filters.*.value' => 'required|string',
            'format' => 'required|string',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = $this->getRawData(
            request('series'),
            request('filters', []),
            request('slice')
        );

        return match (request('format')) {
            'table' => $this->formatForTable($data),
            'pie', 'line', 'bar' => $this->formatForChart($data),
            default => response()->json(['error' => 'Invalid format'], 400),
        };
    }

    protected function getRawData(string $series, array $filters, ?string $slice): ?array
    {
        // Retrieve the series class by name
        $seriesClass = $this->arbolService->getSeriesClassByName($series);
        if (! $seriesClass) {
            return null;
        }

        // Create instance and ArbolBag with filters and slice
        $seriesInstance = new $seriesClass();
        $arbolBag = $this->createArbolBag($filters, $slice);

        // Get data and apply slice
        $data = collect($seriesInstance->data($arbolBag));
        $data = $slice ? $this->applySlice($data, $seriesInstance, $slice) : $data->groupBy(fn () => 'All');

        return $data->toArray();
    }

    protected function createArbolBag(array $filters, ?string $slice): ArbolBag
    {
        $arbolBag = new ArbolBag();

        // Add filters to ArbolBag
        collect($filters)->each(fn ($filter) => $arbolBag->addFilter($filter['field'], $filter['value']));

        // Add slice to ArbolBag if it exists
        if ($slice) {
            $arbolBag->addSlice($slice);
        }

        return $arbolBag;
    }

    protected function applySlice($data, $seriesInstance, $slice): \Illuminate\Support\Collection
    {
        foreach ($seriesInstance->slices() as $name => $callback) {
            if ($name === $slice) {
                return $data->groupBy(fn ($item) => $callback($item));
            }
        }

        return $data;
    }

    private function formatForTable(array $data): JsonResponse
    {
        return response()->json($data);
    }

    private function formatForChart(array $data): JsonResponse
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
}

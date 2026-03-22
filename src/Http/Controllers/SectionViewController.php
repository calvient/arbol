<?php

namespace Calvient\Arbol\Http\Controllers;

use Calvient\Arbol\Services\ArbolService;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SectionViewController extends Controller
{
    public function __construct(private ArbolService $arbolService) {}

    public function show(): Response
    {
        $seriesName = request('series');

        abort_unless($seriesName, 400, 'The "series" query parameter is required.');

        $seriesData = $this->arbolService->getSeriesByName($seriesName);

        abort_unless($seriesData, 404, "Series \"{$seriesName}\" not found.");

        // Build filter bar configuration from URL params
        // Supports comma-separated syntax: filters=Created Date:This Week,Status
        //   - "Group:Value" → show filter with default value
        //   - "Group" (no colon) → show filter with no default
        //   - Repeated groups for multi-select: Status:Active,Status:Closed
        $filtersParam = request('filters', '');

        $allFilters = [];
        $defaultFilters = [];

        if (is_string($filtersParam) && $filtersParam !== '') {
            $entries = array_map('trim', explode(',', $filtersParam));

            foreach ($entries as $entry) {
                if ($entry === '') {
                    continue;
                }

                // Split on the first colon only — value may contain colons
                $parts = explode(':', $entry, 2);
                $group = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : '';

                if (! $group || ! isset($seriesData['filters'][$group])) {
                    continue;
                }

                // Add available values for this filter group (from series metadata)
                if (! isset($allFilters[$group])) {
                    $allFilters[$group] = [];
                }
                $allFilters[$group] = array_values(array_unique(
                    array_merge($allFilters[$group], $seriesData['filters'][$group])
                ));

                // If a default value is set, collect it
                if ($value !== '') {
                    $defaultFilters[] = ['field' => $group, 'value' => $value];
                }
            }
        }

        return Inertia::render('Section/Show', [
            'series' => $seriesName,
            'allFilters' => $allFilters,
            'defaultFilters' => $defaultFilters,
        ]);
    }
}

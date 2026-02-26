<?php

namespace Calvient\Arbol\Http\Controllers;

use Calvient\Arbol\Contracts\ArbolAccess;
use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(private ArbolAccess $arbolAccess, private ArbolService $arbolService) {}

    public function index(): Response
    {
        $query = ArbolReport::with(['author:id,name'])->mine();

        if ($this->getUserClientId()) {
            $query->where('client_id', $this->getUserClientId());
        }

        return Inertia::render('Reports/Index', [
            'reports' => $query->get(),
        ]);
    }

    public function show(ArbolReport $report): Response
    {
        $this->validateReportAccess($report);

        // Ensure user can only access reports within their client
        if ($this->getUserClientId() && $report->client_id !== $this->getUserClientId()) {
            abort(403);
        }

        $report->load([
            'author:id,name',
            'sections' => function ($query) {
                $query->orderBy('sequence');
            },
        ]);

        // Collect report filter configuration from table sections
        // Table sections' filters define which filter groups appear in the report filter bar
        $allFilters = [];
        $defaultFilters = [];

        foreach ($report->sections as $section) {
            if ($section->format !== 'table') {
                continue;
            }

            $seriesData = $this->arbolService->getSeriesByName($section->series);
            if (! $seriesData || empty($seriesData['filters'])) {
                continue;
            }

            foreach ($section->filters ?? [] as $filter) {
                $group = $filter['field'] ?? null;
                if (! $group || ! isset($seriesData['filters'][$group])) {
                    continue;
                }

                // Add available values for this filter group
                if (! isset($allFilters[$group])) {
                    $allFilters[$group] = [];
                }
                $allFilters[$group] = array_values(array_unique(
                    array_merge($allFilters[$group], $seriesData['filters'][$group])
                ));

                // If a default value is set, collect it
                if (! empty($filter['value'])) {
                    $defaultFilters[] = ['field' => $group, 'value' => $filter['value']];
                }
            }
        }

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'users' => $report->users()->pluck('name', 'id')->toArray(),
            'allFilters' => $allFilters,
            'defaultFilters' => $defaultFilters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Reports/Create');
    }

    public function store()
    {
        request()->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable',
        ]);

        $report = ArbolReport::create([
            'name' => request('name'),
            'description' => request('description'),
            'author_id' => Auth::id(),
            'user_ids' => [Auth::id()],
            'team_ids' => $this->arbolAccess->getUserTeamIds(Auth::user()),
        ]);
        $report->client_id = $this->getUserClientId();
        $report->save();

        return redirect()->route('arbol.reports.index');
    }

    public function edit(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        return Inertia::render('Reports/Edit', [
            'report' => $report,
            'allUsers' => $this->getArbolUsers(),
            'allTeams' => $this->getArbolTeams(),
        ]);
    }

    public function update(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        request()->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable',
            'user_ids' => 'nullable|array',
            'team_ids' => 'nullable|array',
        ]);

        $report->update([
            'name' => request('name'),
            'description' => request('description'),
            'user_ids' => request('user_ids', [$report->author_id]),
            'team_ids' => request('team_ids', $this->arbolAccess->getUserTeamIds(Auth::user())),
        ]);

        return redirect()->route('arbol.reports.show', $report);
    }

    public function destroy(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        $report->delete();

        return redirect()->route('arbol.reports.index');
    }

    private function validateReportAccess(ArbolReport $report): void
    {
        abort_unless(
            $this->arbolAccess->userCanAccessReport(Auth::user(), $report),
            403
        );
    }

    private function getArbolUsers()
    {
        return $this->arbolAccess->getUsers();
    }

    private function getArbolTeams()
    {
        return $this->arbolAccess->getTeams();
    }

    private function getUserClientId(): ?int
    {
        $user = Auth::user();

        if ($user && isset($user->client_id)) {
            return $user->client_id;
        }

        return null;
    }
}

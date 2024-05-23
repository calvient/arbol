<?php

namespace Calvient\Arbol\Http\Controllers;

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class SectionsController extends Controller
{
    public function __construct(public ArbolService $arbolService)
    {
    }

    public function create(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        return Inertia::render('Reports/Sections/Create', [
            'report' => $report->load(['author:id,name']),
            'series' => $this->arbolService->getSeries(),
        ]);
    }

    public function store(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        request()->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable',
            'series' => 'required|string',
            'slice' => 'nullable|string',
            'filters' => 'nullable|array',
            'format' => 'required|string',
        ]);

        ArbolSection::create([
            'arbol_report_id' => $report->id,
            'name' => request('name'),
            'description' => request('description'),
            'series' => request('series'),
            'slice' => request('slice'),
            'filters' => request('filters'),
            'format' => request('format'),
        ]);

        return redirect()->route('arbol.reports.show', $report);
    }

    public function edit(ArbolReport $report, ArbolSection $section)
    {
        $this->validateReportAccess($report);

        return Inertia::render('Reports/Sections/Edit', [
            'report' => $report,
            'section' => $section,
            'series' => $this->arbolService->getSeriesByName($section->series),
        ]);
    }

    public function update(ArbolReport $report, ArbolSection $section)
    {
        $this->validateReportAccess($report);

        request()->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable',
            'series' => 'required|string',
            'slice' => 'nullable|string',
            'filters' => 'nullable|array',
            'format' => 'required|string',
            'sequence' => 'nullable|numeric|min:0',
        ]);

        $section->update([
            'name' => request('name'),
            'description' => request('description'),
            'series' => request('series'),
            'slice' => request('slice'),
            'filters' => request('filters'),
            'format' => request('format'),
            'sequence' => request('sequence'),
        ]);

        return redirect()->route('arbol.reports.show', $report);
    }

    public function destroy(ArbolReport $report, ArbolSection $section)
    {
        $this->validateReportAccess($report);

        $section->delete();

        return redirect()->route('arbol.reports.show', $report);
    }

    private function validateReportAccess(ArbolReport $report): void
    {
        abort_if($report->author_id !== auth()->id() && ! in_array(auth()->id(), $report->user_ids), 403);
    }
}

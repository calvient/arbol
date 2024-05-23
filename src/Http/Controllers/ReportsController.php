<?php

namespace Calvient\Arbol\Http\Controllers;

use Calvient\Arbol\Models\ArbolReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/Index', [
            'reports' => ArbolReport::with(['author:id,name'])->mine()->get(),
        ]);
    }

    public function show(ArbolReport $report): Response
    {
        $this->validateReportAccess($report);

        return Inertia::render('Reports/Show', [
            'report' => $report->load(['author:id,name', 'sections']),
            'users' => $report->users()->pluck('name', 'id')->toArray(),
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

        ArbolReport::create([
            'name' => request('name'),
            'description' => request('description'),
            'author_id' => auth()->id(),
            'user_ids' => [auth()->id()],
        ]);

        return redirect()->route('arbol.reports.index');
    }

    private function validateReportAccess(ArbolReport $report): void
    {
        abort_if($report->author_id !== auth()->id() && ! in_array(auth()->id(), $report->user_ids), 403);
    }
}

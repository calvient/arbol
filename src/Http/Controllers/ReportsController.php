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

    public function edit(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        return Inertia::render('Reports/Edit', [
            'report' => $report,
            'allUsers' => $this->getArbolUsers(),
        ]);
    }

    public function update(ArbolReport $report)
    {
        $this->validateReportAccess($report);

        request()->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable',
            'users' => 'nullable|array',
        ]);

        $report->update([
            'name' => request('name'),
            'description' => request('description'),
            'user_ids' => request('user_ids', [$report->author_id]),
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
        abort_if($report->author_id !== auth()->id() && ! in_array(auth()->id(), $report->user_ids), 403);
    }

    private function getArbolUsers()
    {
        $userModelClass = config('arbol.user_model');
        $query = $userModelClass::query();

        // Check if the arbol scope exists and apply it if it does
        if (method_exists($userModelClass, 'scopeArbol')) {
            $query = $userModelClass::arbol();
        }

        return $query->get(['id', 'name']);
    }
}

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

        return Inertia::render('Reports/Show', [
            'report' => $report->load([
                'author:id,name',
                'sections' => function ($query) {
                    $query->orderBy('sequence');
                },
            ]),
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

        $report = ArbolReport::create([
            'name' => request('name'),
            'description' => request('description'),
            'author_id' => auth()->id(),
            'user_ids' => [auth()->id()],
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
        abort_if($report->author_id !== auth()->id()
            && ! in_array(auth()->id(), $report->user_ids)
            && ! in_array(-1, $report->user_ids),
            403);
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

    private function getUserClientId(): ?int
    {
        $user = auth()->user();

        if ($user && isset($user->client_id)) {
            return $user->client_id;
        }

        return null;
    }
}

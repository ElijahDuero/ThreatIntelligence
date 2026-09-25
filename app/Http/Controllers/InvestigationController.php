<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Investigation;
use App\Services\Investigation\EntityGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestigationController extends Controller
{
    public function index(): Response
    {
        $investigations = Investigation::withCount(['searchQueries', 'bookmarks'])
            ->latest()
            ->get();

        $bookmarks = Bookmark::with('investigation')
            ->latest()
            ->get();

        return Inertia::render('Investigations/Index', [
            'investigations' => $investigations,
            'bookmarks' => $bookmarks,
        ]);
    }

    public function show(int $id, EntityGraphService $graphService): Response
    {
        $investigation = Investigation::withCount(['bookmarks', 'searchQueries'])->findOrFail($id);
        $graph = $graphService->buildGraph($investigation);

        return Inertia::render('Investigations/Show', [
            'investigation' => $investigation,
            'bookmarks' => $investigation->bookmarks()->latest()->paginate(20, ['*'], 'evidence_page')->withQueryString(),
            'queries' => $investigation->searchQueries()->latest()->paginate(20, ['*'], 'queries_page')->withQueryString(),
            'graph' => $graph,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'tags' => 'nullable|array',
        ]);

        $investigation = Investigation::create($validated);

        return redirect()->route('investigations.show', $investigation->id)
            ->with('success', 'Investigation case dossier initialized.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $investigation = Investigation::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,closed,archived',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'tags' => 'nullable|array',
        ]);

        $investigation->update($validated);

        return response()->json([
            'success' => true,
            'investigation' => $investigation,
        ]);
    }

    public function updateGraph(Request $request, int $id, EntityGraphService $graphService): JsonResponse
    {
        $investigation = Investigation::findOrFail($id);

        $validated = $request->validate([
            'nodes' => 'nullable|array',
            'edges' => 'nullable|array',
        ]);

        $investigation->update([
            'graph_data' => [
                'nodes' => $validated['nodes'] ?? [],
                'edges' => $validated['edges'] ?? [],
            ],
        ]);

        $graph = $graphService->buildGraph($investigation);

        return response()->json([
            'success' => true,
            'graph' => $graph,
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $investigation = Investigation::findOrFail($id);
        $investigation->delete();

        return redirect()->route('investigations.index')
            ->with('success', 'Investigation case dossier removed.');
    }

    public function exportReport(int $id, Request $request, EntityGraphService $graphService): HttpResponse|JsonResponse
    {
        $investigation = Investigation::with(['bookmarks', 'searchQueries'])->findOrFail($id);
        $graph = $graphService->buildGraph($investigation);
        $format = $request->query('format', 'markdown');

        if ($format === 'json') {
            $data = [
                'case_reference' => 'CASE #'.$investigation->id,
                'title' => $investigation->title,
                'description' => $investigation->description,
                'status' => $investigation->status,
                'priority' => $investigation->priority,
                'tags' => $investigation->tags,
                'created_at' => $investigation->created_at,
                'updated_at' => $investigation->updated_at,
                'graph' => $graph,
                'evidence_bookmarks' => $investigation->bookmarks,
                'search_leads' => $investigation->searchQueries,
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="dossier-case-'.$investigation->id.'.json"',
            ]);
        }

        $markdown = $graphService->generateMarkdownReport($investigation, $graph);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="dossier-case-'.$investigation->id.'.md"',
        ]);
    }

    public function bookmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'url' => 'required|string',
            'notes' => 'nullable|string',
            'severity' => 'nullable|string',
            'investigation_id' => 'nullable|exists:investigations,id',
            'search_result_id' => 'nullable|exists:search_results,id',
        ]);

        $bookmark = Bookmark::create($validated);

        return response()->json([
            'success' => true,
            'bookmark' => $bookmark,
        ]);
    }

    public function deleteBookmark(int $id): JsonResponse
    {
        Bookmark::destroy($id);

        return response()->json(['success' => true]);
    }
}

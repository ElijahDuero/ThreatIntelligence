<?php

namespace App\Http\Controllers;

use App\Models\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueryRegistryController extends Controller
{
    /**
     * Display a paginated and filterable list of all recorded search queries.
     */
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $engine = (string) $request->query('engine', '');
        $routing = (string) $request->query('routing', '');
        $status = (string) $request->query('status', '');
        $sort = (string) $request->query('sort', 'newest');

        $query = SearchQuery::query()
            ->with('investigation:id,title')
            ->withCount('results');

        if ($search !== '') {
            $query->search($search);
        }

        if ($engine !== '') {
            $query->engine($engine);
        }

        if ($routing === 'tor') {
            $query->tor(true);
        } elseif ($routing === 'clearnet') {
            $query->tor(false);
        }

        if ($status !== '') {
            $query->status($status);
        }

        switch ($sort) {
            case 'oldest':
                $query->oldest();
                break;
            case 'results_desc':
                $query->orderByDesc('results_count');
                break;
            case 'results_asc':
                $query->orderBy('results_count');
                break;
            case 'time_desc':
                $query->orderByDesc('execution_time_seconds');
                break;
            case 'time_asc':
                $query->orderBy('execution_time_seconds');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $queries = $query->paginate(15)->withQueryString();

        $statsRow = SearchQuery::query()
            ->selectRaw('
                COUNT(*) as total_queries,
                COALESCE(SUM(results_count), 0) as total_results,
                COUNT(CASE WHEN use_tor = 1 THEN 1 END) as tor_queries,
                COUNT(CASE WHEN use_tor = 0 THEN 1 END) as clearnet_queries,
                ROUND(COALESCE(AVG(execution_time_seconds), 0), 2) as avg_execution_time,
                COUNT(CASE WHEN status = ? THEN 1 END) as failed_queries
            ', ['failed'])
            ->first();

        $stats = [
            'total_queries' => (int) ($statsRow->total_queries ?? 0),
            'total_results' => (int) ($statsRow->total_results ?? 0),
            'tor_queries' => (int) ($statsRow->tor_queries ?? 0),
            'clearnet_queries' => (int) ($statsRow->clearnet_queries ?? 0),
            'avg_execution_time' => (float) ($statsRow->avg_execution_time ?? 0.0),
            'failed_queries' => (int) ($statsRow->failed_queries ?? 0),
        ];

        $availableEngines = SearchQuery::distinct()->pluck('engine')->filter()->values();

        return Inertia::render('Queries/Index', [
            'queries' => $queries,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'engine' => $engine,
                'routing' => $routing,
                'status' => $status,
                'sort' => $sort,
            ],
            'availableEngines' => $availableEngines,
        ]);
    }

    /**
     * Remove a specific query record from the registry.
     */
    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $searchQuery = SearchQuery::findOrFail($id);
        $searchQuery->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Investigation query removed from registry.');
    }

    /**
     * Bulk remove selected query records.
     */
    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:search_queries,id',
        ]);

        SearchQuery::whereIn('id', $validated['ids'])->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', count($validated['ids']).' queries removed from registry.');
    }

    /**
     * Clear all recorded queries from the registry.
     */
    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        SearchQuery::query()->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'All query records cleared from registry.');
    }

    /**
     * Stream a CSV or JSON export of all registered queries.
     */
    public function export(Request $request): StreamedResponse
    {
        $format = strtolower($request->query('format', 'csv'));
        $queries = SearchQuery::latest()->get();

        $filename = 'darkdump_query_registry_'.date('Ymd_His');

        if ($format === 'json') {
            $headers = [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ];

            return response()->stream(function () use ($queries) {
                echo json_encode($queries, JSON_PRETTY_PRINT);
            }, 200, $headers);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        return response()->stream(function () use ($queries) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Query', 'Engine', 'Scan Type', 'Tor Routed', 'Results Count', 'Execution Time (s)', 'Status', 'Recorded At']);

            foreach ($queries as $q) {
                fputcsv($file, [
                    $q->id,
                    $q->query,
                    $q->engine,
                    $q->scan_type,
                    $q->use_tor ? 'YES' : 'NO',
                    $q->results_count,
                    $q->execution_time_seconds,
                    $q->status,
                    $q->created_at?->toIso8601String(),
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }
}

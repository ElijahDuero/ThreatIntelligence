<?php

namespace App\Http\Controllers;

use App\Models\SearchQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function export(Request $request, int $searchId): Response
    {
        $format = strtolower($request->query('format', 'json'));
        $search = SearchQuery::with('results')->findOrFail($searchId);

        $filename = 'darkdump_'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $search->query).'_'.date('Ymd_His');

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            ];

            $callback = function () use ($search) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Index', 'Title', 'URL', 'Description', 'Engine', 'Severity', 'Category', 'Is Onion', 'Blacklisted']);

                foreach ($search->results as $idx => $r) {
                    fputcsv($file, [
                        $idx + 1,
                        $r->title,
                        $r->url,
                        $r->description,
                        $r->engine,
                        $r->severity ?? 'INFO',
                        $r->category ?? 'N/A',
                        $r->is_onion ? 'YES' : 'NO',
                        $r->is_blacklisted ? 'YES' : 'NO',
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        if ($format === 'markdown' || $format === 'txt') {
            $lines = [
                '# Darkdump OSINT Forensic Report',
                "**Query:** {$search->query}",
                "**Engine:** {$search->engine}",
                "**Scan Type:** {$search->scan_type}",
                "**Execution Time:** {$search->execution_time_seconds}s",
                '**Timestamp:** '.$search->created_at->toIso8601String(),
                '**Total Results:** '.count($search->results),
                '',
                '---',
                '## Findings',
                '',
            ];

            foreach ($search->results as $idx => $r) {
                $num = $idx + 1;
                $onionTag = $r->is_onion ? ' `[.onion]`' : '';
                $lines[] = "### {$num}. {$r->title}{$onionTag}";
                $lines[] = "- **URL:** `{$r->url}`";
                $lines[] = "- **Engine:** `{$r->engine}`";
                if ($r->severity) {
                    $lines[] = "- **Severity:** **{$r->severity}** | Category: `{$r->category}`";
                }
                $lines[] = "- **Description:** {$r->description}";
                $lines[] = '';
            }

            return response(implode("\n", $lines), 200, [
                'Content-Type' => 'text/markdown',
                'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
            ]);
        }

        // Default JSON
        return response()->json([
            'query' => $search->query,
            'engine' => $search->engine,
            'scan_type' => $search->scan_type,
            'execution_time_seconds' => $search->execution_time_seconds,
            'created_at' => $search->created_at,
            'total_results' => count($search->results),
            'results' => $search->results,
        ], 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
        ]);
    }
}

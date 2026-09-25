<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Investigation;
use App\Models\ReconAlert;
use App\Models\ReconMonitor;
use App\Models\ScrapedTarget;
use App\Models\SearchQuery;
use App\Models\SearchResult;
use App\Services\DarkWeb\TorClient;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TorClient $torClient): Response
    {
        $recentQueries = SearchQuery::latest()
            ->take(5)
            ->withCount('results')
            ->get();

        $recentBookmarks = Bookmark::latest()
            ->take(5)
            ->get();

        $recentAlerts = ReconAlert::with('monitor')
            ->where('is_read', false)
            ->latest()
            ->take(3)
            ->get();

        $stats = [
            'total_queries' => SearchQuery::count(),
            'total_results' => SearchResult::count(),
            'total_investigations' => Investigation::count(),
            'total_bookmarks' => Bookmark::count(),
            'total_scraped' => ScrapedTarget::count(),
            'total_monitors' => ReconMonitor::where('is_active', true)->count(),
            'unread_alerts' => ReconAlert::where('is_read', false)->count(),
        ];

        return Inertia::render('Dashboard/Index', [
            'recentQueries' => $recentQueries,
            'recentBookmarks' => $recentBookmarks,
            'recentAlerts' => $recentAlerts,
            'stats' => $stats,
            'torStatus' => $torClient->checkTorStatus(),
        ]);
    }
}

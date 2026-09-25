<?php

namespace App\Http\Controllers;

use App\Models\Investigation;
use App\Models\ReconAlert;
use App\Models\ReconMonitor;
use App\Services\Monitors\ReconMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconMonitorController extends Controller
{
    public function index(): Response
    {
        $monitors = ReconMonitor::with('investigation')
            ->latest()
            ->get();

        $recentAlerts = ReconAlert::with(['monitor', 'investigation'])
            ->latest()
            ->take(40)
            ->get();

        $investigations = Investigation::select('id', 'title')
            ->latest()
            ->get();

        $unreadCount = ReconAlert::where('is_read', false)->count();

        return Inertia::render('Monitors/Index', [
            'monitors' => $monitors,
            'recentAlerts' => $recentAlerts,
            'investigations' => $investigations,
            'stats' => [
                'active_monitors' => $monitors->where('is_active', true)->count(),
                'total_monitors' => $monitors->count(),
                'total_findings' => $monitors->sum('findings_count'),
                'unread_alerts' => $unreadCount,
            ],
        ]);
    }

    public function store(Request $request, ReconMonitorService $service): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_type' => 'required|string|in:telegram,reddit,persona,onion,domain,ip,infrastructure',
            'target_value' => 'required|string|max:255',
            'frequency' => 'required|string|in:15m,hourly,daily',
            'webhook_url' => 'nullable|url|max:500',
            'investigation_id' => 'nullable|exists:investigations,id',
        ]);

        $monitor = ReconMonitor::create($validated);

        // Run baseline scan immediately on creation
        $service->pollMonitor($monitor);

        return redirect()->back()->with('success', "Recon monitor \"{$monitor->title}\" deployed and initialized.");
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $monitor = ReconMonitor::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'frequency' => 'nullable|string|in:15m,hourly,daily',
            'is_active' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:500',
            'investigation_id' => 'nullable|exists:investigations,id',
        ]);

        $monitor->update($validated);

        return response()->json([
            'success' => true,
            'monitor' => $monitor,
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $monitor = ReconMonitor::findOrFail($id);
        $monitor->delete();

        return redirect()->back()->with('success', 'Recon monitor removed.');
    }

    public function poll(int $id, ReconMonitorService $service): JsonResponse
    {
        $monitor = ReconMonitor::findOrFail($id);
        $result = $service->pollMonitor($monitor);

        return response()->json([
            'success' => $result['success'],
            'new_alerts_count' => $result['new_alerts_count'],
            'error' => $result['error'] ?? null,
            'monitor' => $monitor->fresh(),
        ]);
    }

    public function pollAll(ReconMonitorService $service): JsonResponse
    {
        $monitors = ReconMonitor::where('is_active', true)->get();
        $totalNew = 0;

        foreach ($monitors as $m) {
            $res = $service->pollMonitor($m);
            $totalNew += $res['new_alerts_count'] ?? 0;
        }

        return response()->json([
            'success' => true,
            'total_polled' => $monitors->count(),
            'new_alerts_count' => $totalNew,
        ]);
    }

    public function getAlerts(): JsonResponse
    {
        $unreadCount = ReconAlert::where('is_read', false)->count();
        $alerts = ReconAlert::with('monitor')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'unread_count' => $unreadCount,
            'alerts' => $alerts,
        ]);
    }

    public function markAlertRead(int $id): JsonResponse
    {
        $alert = ReconAlert::findOrFail($id);
        $alert->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllAlertsRead(): JsonResponse
    {
        ReconAlert::where('is_read', false)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function deleteAlert(int $id): JsonResponse
    {
        ReconAlert::destroy($id);

        return response()->json(['success' => true]);
    }
}

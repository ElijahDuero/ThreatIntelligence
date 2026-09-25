<?php

namespace App\Http\Controllers;

use App\Models\Investigation;
use App\Services\Infrastructure\InfrastructureReconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfrastructureReconController extends Controller
{
    public function __construct(
        protected InfrastructureReconService $reconService
    ) {}

    /**
     * Render the Infrastructure Recon workspace.
     */
    public function index(): Response
    {
        $investigations = Investigation::select(['id', 'title', 'status', 'priority'])
            ->latest('updated_at')
            ->get();

        return Inertia::render('Infrastructure/Index', [
            'investigations' => $investigations,
        ]);
    }

    /**
     * Inspect domain infrastructure (DNS, Subdomains, RDAP, GeoIP).
     */
    public function inspectDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $result = $this->reconService->inspectDomain($validated['domain']);

        return response()->json($result);
    }

    /**
     * Inspect IP address infrastructure (Reverse DNS, GeoIP, ASN, RDAP).
     */
    public function inspectIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:64',
        ]);

        $result = $this->reconService->inspectIp($validated['ip']);

        return response()->json($result);
    }

    /**
     * Inspect passive open ports and CVE vulnerability intelligence.
     */
    public function inspectPorts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:64',
        ]);

        $result = $this->reconService->fetchPassivePortsAndCves($validated['ip']);

        return response()->json([
            'success' => true,
            'ip' => $validated['ip'],
            'intel' => $result,
        ]);
    }

    /**
     * Inject discovered infrastructure nodes & edges directly into an Investigation Case Dossier.
     */
    public function linkToInvestigation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'investigation_id' => 'required|integer|exists:investigations,id',
            'nodes' => 'required|array',
            'nodes.*.id' => 'required|string',
            'nodes.*.label' => 'required|string',
            'nodes.*.type' => 'required|string',
            'edges' => 'nullable|array',
        ]);

        $result = $this->reconService->linkToInvestigation(
            (int) $validated['investigation_id'],
            $validated['nodes'],
            $validated['edges'] ?? []
        );

        return response()->json($result);
    }
}

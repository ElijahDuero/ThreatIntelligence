<?php

namespace App\Http\Controllers;

use App\Http\Responses\ServerSentEventStream;
use App\Models\Investigation;
use App\Services\Osint\DomainLookupService;
use App\Services\Osint\EmailLookupService;
use App\Services\Osint\IpLookupService;
use App\Services\Osint\PlatformEnumerationService;
use App\Services\Osint\UsernameLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OsintController extends Controller
{
    /**
     * Display the OSINT Framework Hub.
     */
    public function index(PlatformEnumerationService $enumerationService): Response
    {
        $investigations = Investigation::latest()->select('id', 'title', 'priority', 'status')->get();
        $totalCatalogPlatforms = count($enumerationService->getPlatforms());

        $frameworkCategories = [
            [
                'id' => 'username',
                'title' => 'Username Lookup',
                'description' => 'Cross-platform alias discovery, search engines, and real-time SSE profile probing.',
                'url' => '/osint/username',
                'icon' => 'UserCheck',
                'badge' => 'Active',
                'badge_color' => 'teal',
                'branches' => ['Live Discovered Accounts', 'Search Engines (12)', 'Specific Sites (7)'],
            ],
            [
                'id' => 'email',
                'title' => 'Email Addresses',
                'description' => 'Email verification, header analysis, breach discovery, and spam reputations.',
                'url' => '/osint/email',
                'icon' => 'Mail',
                'badge' => 'Active',
                'badge_color' => 'teal',
                'branches' => ['Email Search (13)', 'Common Formats (2)', 'Verification (10)', 'Breach Data (4)', 'Mail Blacklists (1)'],
            ],
            [
                'id' => 'ip',
                'title' => 'IP & MAC Address',
                'description' => 'Target telemetry, Geolocation, BGP/ASN routing, Threat scoring, and IEEE MAC OUI forensics.',
                'url' => '/osint/ip',
                'icon' => 'Network',
                'badge' => 'Active',
                'badge_color' => 'teal',
                'branches' => ['Geolocation (8)', 'Host/Port Discovery (13)', 'IPv4 Network (8)', 'BGP & Routing (4)', 'Threat Reputation (3)', 'Blacklists (4)', 'Protected Services (2)', 'Wireless Info (2)', 'MAC OUI & Hardware (1)'],
            ],
            [
                'id' => 'domain',
                'title' => 'Domain Name',
                'description' => 'Domains lookup and discovery via Shodan, urlscan.io, ZoomEye.ai, crt.sh, and DNS zone infrastructure.',
                'url' => '/osint/domain',
                'icon' => 'Globe',
                'badge' => 'Active',
                'badge_color' => 'teal',
                'branches' => ['Subdomains & Certs (4)', 'Host & Ports [Shodan] (4)', 'Web & DOM [urlscan.io] (4)', 'Threat & Risk [ZoomEye] (4)', 'DNS Infrastructure (4)'],
            ],
        ];

        return Inertia::render('Osint/Index', [
            'categories' => $frameworkCategories,
            'investigations' => $investigations,
            'metrics' => [
                'total_targets' => $totalCatalogPlatforms + 19 + 30 + 52 + 20,
                'enumeration_platforms' => $totalCatalogPlatforms,
                'framework_engines' => 19 + 30 + 52 + 20,
                'active_cases' => $investigations->count(),
            ],
        ]);
    }

    /**
     * Display the Username Lookup module under the OSINT Framework.
     */
    public function username(
        Request $request,
        UsernameLookupService $lookupService,
        PlatformEnumerationService $enumerationService
    ): Response {
        $rawUsername = $request->query('username', '');
        $sanitizedUsername = ltrim(trim((string) $rawUsername), '@');

        // Verify format if provided
        if (! preg_match('/^[a-zA-Z0-9_\-\.]+$/', $sanitizedUsername)) {
            $sanitizedUsername = '';
        }

        $investigations = Investigation::latest()->select('id', 'title', 'priority')->get();

        $initialEngines = $lookupService->getSearchEngines($sanitizedUsername);
        $initialSites = [];

        if (! empty($sanitizedUsername)) {
            $initialSites = $lookupService->getSpecificSites($sanitizedUsername);
        }

        return Inertia::render('Osint/UsernameLookup', [
            'initialUsername' => $sanitizedUsername,
            'initialEngines' => $initialEngines,
            'initialSites' => $initialSites,
            'investigations' => $investigations,
            'platformCategories' => $enumerationService->getCategories(),
            'totalCatalogPlatforms' => count($enumerationService->getPlatforms()),
        ]);
    }

    /**
     * Probe username against OSINT Framework Search Engines and Specific Sites.
     */
    public function probeUsername(Request $request, UsernameLookupService $lookupService): JsonResponse
    {
        // Sanitize leading @ symbol before validation
        if ($request->has('username')) {
            $request->merge([
                'username' => ltrim(trim((string) $request->input('username')), '@'),
            ]);
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[a-zA-Z0-9_\-\.]+$/',
            ],
            'probe_live' => 'nullable|boolean',
        ], [
            'username.regex' => 'The target alias may only contain letters, numbers, underscores, hyphens, and periods.',
        ]);

        $username = $validated['username'];
        $probeLive = $request->boolean('probe_live', true);

        $engines = $lookupService->getSearchEngines($username);

        $sites = $probeLive
            ? $lookupService->probeAllSpecificSites($username)
            : $lookupService->getSpecificSites($username);

        return response()->json([
            'success' => true,
            'username' => $username,
            'timestamp' => now()->toIso8601String(),
            'counts' => [
                'search_engines' => count($engines),
                'specific_sites' => count($sites),
                'live_found' => count(array_filter($sites, fn ($s) => ($s['status'] ?? null) === 'found')),
            ],
            'search_engines' => $engines,
            'specific_sites' => $sites,
        ]);
    }

    /**
     * Stream live multi-platform username enumeration discovery via Server-Sent Events (SSE).
     */
    public function streamEnumeration(Request $request, PlatformEnumerationService $enumerationService): StreamedResponse
    {
        $rawUsername = (string) $request->query('username', '');
        $username = ltrim(trim($rawUsername), '@');

        $categoriesRaw = (string) $request->query('categories', '');
        $categories = ! empty($categoriesRaw) ? array_filter(array_map('trim', explode(',', $categoriesRaw))) : null;

        return ServerSentEventStream::createStream(function (callable $sendEvent, callable $isAborted) use ($username, $categories, $enumerationService) {
            if (empty($username) || ! preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username) || strlen($username) < 2 || strlen($username) > 64) {
                $sendEvent('error', ['message' => 'The target alias is invalid or missing. Only alphanumeric characters, dashes, underscores, and dots are permitted.']);

                return;
            }

            $platforms = $enumerationService->getPlatforms($categories);
            $totalPlatforms = count($platforms);

            $sendEvent('start', [
                'username' => $username,
                'total_platforms' => $totalPlatforms,
                'categories' => $categories ?? ['all'],
                'started_at' => now()->toIso8601String(),
            ]);

            $onHit = fn (array $hit) => $sendEvent('hit', $hit);
            $onProgress = fn (array $progress) => $sendEvent('progress', $progress);

            $summary = $enumerationService->streamEnumeration(
                $username,
                $categories,
                $onHit,
                $onProgress,
                $isAborted
            );

            $sendEvent('done', [
                'username' => $username,
                'total_probed' => $summary['total_probed'],
                'total_found' => $summary['total_found'],
                'duration_ms' => $summary['duration_ms'],
                'completed_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Display the Email Addresses reconnaissance module under OSINT Framework.
     */
    public function email(Request $request, EmailLookupService $emailService): Response
    {
        $rawEmail = trim((string) $request->query('email', ''));
        $investigations = Investigation::latest()->select('id', 'title', 'priority')->get();

        $initialProbe = null;
        if (! empty($rawEmail)) {
            $initialProbe = $emailService->probeEmail($rawEmail, resolveMx: true);
        }

        $categorizedTools = $emailService->getCategorizedTools($rawEmail);

        return Inertia::render('Osint/EmailLookup', [
            'initialEmail' => $rawEmail,
            'initialProbe' => $initialProbe,
            'categorizedTools' => $categorizedTools,
            'investigations' => $investigations,
            'totalToolsCount' => count($emailService->getAllTools($rawEmail)),
        ]);
    }

    /**
     * Probe target email address against OSINT syntax, MX routing, hygiene, and tools.
     */
    public function probeEmail(Request $request, EmailLookupService $emailService): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/',
            ],
            'probe_live' => 'nullable|boolean',
        ], [
            'email.regex' => 'The target email must conform to standard email formatting (user@domain.tld).',
        ]);

        $email = trim($validated['email']);
        $probeLive = $request->boolean('probe_live', true);

        $result = $emailService->probeEmail($email, resolveMx: $probeLive);

        return response()->json($result);
    }

    /**
     * Stream real-time reconnaissance probing across all 30 OSINT Framework email tools via SSE.
     */
    public function streamEmail(Request $request, EmailLookupService $emailService): StreamedResponse
    {
        $rawEmail = trim((string) $request->query('email', ''));

        if (empty($rawEmail) || ! filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
            return ServerSentEventStream::createStream(function (callable $sendEvent) {
                $sendEvent('error', ['error' => 'Valid target email parameter is required for reconnaissance stream.']);
            }, 400);
        }

        $email = $rawEmail;

        return ServerSentEventStream::createStream(function (callable $sendEvent, callable $isAborted) use ($email, $emailService) {
            $allTools = $emailService->getAllTools($email);
            $totalTools = count($allTools);

            $sendEvent('start', [
                'email' => $email,
                'total_tools' => $totalTools,
                'branches' => ['email_search', 'common_formats', 'verification', 'breach_data', 'mail_blacklists'],
                'started_at' => now()->toIso8601String(),
            ]);

            $onResult = fn (array $result) => $sendEvent('result', $result);
            $onProgress = fn (array $progress) => $sendEvent('progress', $progress);

            $summary = $emailService->streamEmailProbes(
                $email,
                $onResult,
                $onProgress,
                $isAborted
            );

            $sendEvent('done', [
                'email' => $email,
                'total_probed' => $summary['total_probed'],
                'total_found' => $summary['total_found'],
                'duration_ms' => $summary['duration_ms'],
                'completed_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Display the IP & MAC Address reconnaissance module under OSINT Framework.
     */
    public function ip(Request $request, IpLookupService $ipService): Response
    {
        $rawTarget = trim((string) $request->query('target', $request->query('ip', '')));
        $investigations = Investigation::latest()->select('id', 'title', 'priority')->get();

        $initialClassification = ! empty($rawTarget) ? $ipService->classifyTarget($rawTarget) : null;
        $initialProbe = ! empty($rawTarget) ? $ipService->probeTarget($rawTarget) : null;
        $initialFindings = $initialProbe ? ($initialProbe['discovered_findings'] ?? []) : [];
        $categorizedTools = $ipService->getCategorizedTools($rawTarget);

        return Inertia::render('Osint/IpLookup', [
            'initialTarget' => $rawTarget,
            'initialClassification' => $initialClassification,
            'initialProbe' => $initialProbe,
            'initialFindings' => $initialFindings,
            'findingCategories' => $ipService->getFindingCategories(),
            'categorizedTools' => $categorizedTools,
            'investigations' => $investigations,
            'totalToolsCount' => count($ipService->getAllTools($rawTarget)),
        ]);
    }

    /**
     * Probe target IP, MAC, or domain hostname against OSINT classification and telemetry.
     */
    public function probeIp(Request $request, IpLookupService $ipService): JsonResponse
    {
        $validated = $request->validate([
            'target' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
        ]);

        $target = trim($validated['target']);
        $result = $ipService->probeTarget($target);

        return response()->json($result);
    }

    /**
     * Stream real-time reconnaissance probing across all 52 OSINT Framework IP & MAC tools via SSE.
     */
    public function streamIp(Request $request, IpLookupService $ipService): StreamedResponse
    {
        $rawTarget = trim((string) $request->query('target', $request->query('ip', '')));

        if (empty($rawTarget)) {
            return ServerSentEventStream::createStream(function (callable $sendEvent) {
                $sendEvent('error', ['error' => 'Target IP, MAC address, or hostname parameter is required for reconnaissance stream.']);
            }, 400);
        }

        $target = $rawTarget;

        return ServerSentEventStream::createStream(function (callable $sendEvent, callable $isAborted) use ($target, $ipService) {
            $allTools = $ipService->getAllTools($target);
            $totalTools = count($allTools);

            $sendEvent('start', [
                'target' => $target,
                'total_tools' => $totalTools,
                'branches' => array_keys($ipService->getCategorizedTools($target)),
                'started_at' => now()->toIso8601String(),
            ]);

            $onResult = fn (array $result) => $sendEvent('result', $result);
            $onProgress = fn (array $progress) => $sendEvent('progress', $progress);

            $summary = $ipService->streamIpProbes(
                $target,
                $onResult,
                $onProgress,
                $isAborted
            );

            $sendEvent('done', [
                'target' => $target,
                'total_probed' => $summary['total_probed'],
                'total_found' => $summary['total_found'],
                'duration_ms' => $summary['duration_ms'],
                'completed_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Display the Domain Name reconnaissance module under OSINT Framework.
     */
    public function domain(Request $request, DomainLookupService $domainService): Response
    {
        $rawTarget = trim((string) $request->query('target', $request->query('domain', '')));
        $cleanTarget = ! empty($rawTarget) ? $domainService->sanitizeDomain($rawTarget) : '';
        $investigations = Investigation::latest()->select('id', 'title', 'priority')->get();

        $initialProbe = ! empty($cleanTarget) ? $domainService->probeDomain($cleanTarget) : null;
        $initialFindings = $initialProbe ? ($initialProbe['initial_findings'] ?? []) : [];
        $categorizedTools = $domainService->getCategorizedTools($cleanTarget);

        return Inertia::render('Osint/DomainLookup', [
            'initialTarget' => $cleanTarget,
            'initialProbe' => $initialProbe,
            'initialFindings' => $initialFindings,
            'findingCategories' => $domainService->getFindingCategories(),
            'categorizedTools' => $categorizedTools,
            'investigations' => $investigations,
            'totalToolsCount' => count($domainService->getAllTools($cleanTarget)),
        ]);
    }

    /**
     * Probe target domain against OSINT classification and telemetry.
     */
    public function probeDomain(Request $request, DomainLookupService $domainService): JsonResponse
    {
        $validated = $request->validate([
            'target' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
        ]);

        $target = trim($validated['target']);
        $result = $domainService->probeDomain($target);

        return response()->json($result);
    }

    /**
     * Stream real-time reconnaissance probing across Shodan, urlscan.io, ZoomEye, crt.sh, and DNS via SSE.
     */
    public function streamDomain(Request $request, DomainLookupService $domainService): StreamedResponse
    {
        $rawTarget = trim((string) $request->query('target', $request->query('domain', '')));
        $cleanTarget = $domainService->sanitizeDomain($rawTarget);

        if (empty($cleanTarget)) {
            return ServerSentEventStream::createStream(function (callable $sendEvent) {
                $sendEvent('error', ['error' => 'Valid domain parameter is required for reconnaissance stream.']);
            }, 400);
        }

        $target = $cleanTarget;

        return ServerSentEventStream::createStream(function (callable $sendEvent) use ($target, $domainService) {
            $sendEvent('start', [
                'target' => $target,
                'total_tools' => 7,
                'branches' => array_keys($domainService->getCategorizedTools($target)),
                'started_at' => now()->toIso8601String(),
            ]);

            $emitter = function (string $event, array $payload) use ($sendEvent) {
                $sendEvent($event, $payload);
            };

            $domainService->streamDomainEnumeration($target, $emitter);
        });
    }
}

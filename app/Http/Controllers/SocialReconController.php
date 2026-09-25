<?php

namespace App\Http\Controllers;

use App\Http\Responses\ServerSentEventStream;
use App\Models\Investigation;
use App\Services\SocialRecon\SocialReconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialReconController extends Controller
{
    public function __construct(protected SocialReconService $socialReconService) {}

    /**
     * Display the Social Recon & Persona Footprint console.
     */
    public function index(): Response
    {
        $catalog = $this->socialReconService->getPlatformCatalog();
        $investigations = Investigation::select('id', 'title')
            ->latest()
            ->get();

        return Inertia::render('SocialRecon/Index', [
            'catalog' => $catalog,
            'investigations' => $investigations,
            'totalPlatforms' => count($catalog),
        ]);
    }

    /**
     * Concurrently probe a slice or batch of platforms for a target username.
     */
    public function probeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100|regex:/^[a-zA-Z0-9._-]+$/',
            'platform_ids' => 'nullable|array',
            'platform_ids.*' => 'string',
            'category' => 'nullable|string|in:all,developer,social,messaging,gaming,security',
        ]);

        $username = trim($validated['username']);
        $platformIds = $validated['platform_ids'] ?? [];
        $category = $validated['category'] ?? 'all';

        $results = $this->socialReconService->probeBatch($username, $platformIds, $category);

        return response()->json([
            'success' => true,
            'username' => $username,
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Search Google engine with hybrid API + live feed strategy for persona web mentions.
     */
    public function googleSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = trim($validated['query']);
        $limit = (int) ($validated['limit'] ?? 35);

        $data = $this->socialReconService->searchGoogle($query, $limit);

        return response()->json($data);
    }

    /**
     * Backward-compatible alias for general persona lookup.
     */
    public function generalPersonaSearch(Request $request): JsonResponse
    {
        return $this->googleSearch($request);
    }

    /**
     * Backward-compatible alias for exact persona footprint lookup.
     */
    public function exactPersonaSearch(Request $request): JsonResponse
    {
        return $this->googleSearch($request);
    }

    /**
     * Search Reddit for public posts and discussions matching an entity or phrase.
     */
    public function searchReddit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'subreddit' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:relevance,new,top,comments',
        ]);

        $query = trim($validated['query']);
        $subreddit = $validated['subreddit'] ?? null;
        $sort = $validated['sort'] ?? 'relevance';

        $data = $this->socialReconService->searchReddit($query, $subreddit, $sort);

        return response()->json([
            'success' => true,
            ...$data,
        ]);
    }

    /**
     * Scrape public messages from a Telegram channel via the engineered multi-page scraper.
     */
    public function scrapeTelegram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|max:100',
            'query' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:10|max:500',
            'before_id' => 'nullable|string|max:50',
            'extract_iocs' => 'nullable|boolean',
            'force_tor' => 'nullable|boolean',
        ]);

        $channel = trim($validated['channel']);
        $query = $validated['query'] ?? null;
        $limit = (int) ($validated['limit'] ?? 50);
        $beforeId = $validated['before_id'] ?? null;
        $extractIocs = (bool) ($validated['extract_iocs'] ?? true);
        $forceTor = (bool) ($validated['force_tor'] ?? true);

        $data = $this->socialReconService->scrapeTelegramChannel(
            channel: $channel,
            query: $query,
            limit: $limit,
            beforeId: $beforeId,
            extractIocs: $extractIocs,
            forceTor: $forceTor
        );

        return response()->json([
            'success' => true,
            ...$data,
        ]);
    }

    /**
     * Search public Telegram channels, groups, and bots by keyword.
     */
    public function searchTelegramChannels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:5|max:100',
            'force_tor' => 'nullable|boolean',
        ]);

        $keyword = trim($validated['keyword']);
        $limit = (int) ($validated['limit'] ?? 25);
        $forceTor = (bool) ($validated['force_tor'] ?? true);

        $data = $this->socialReconService->searchTelegramChannels($keyword, $limit, $forceTor);

        return response()->json($data);
    }

    /**
     * Generate precision search engine dorks for social media and leak targets.
     */
    public function generateDorks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|string|max:255',
            'keyword' => 'nullable|string|max:255',
        ]);

        $target = trim($validated['target']);
        $keyword = $validated['keyword'] ?? null;

        $dorks = $this->socialReconService->generateSocialDorks($target, $keyword);

        return response()->json([
            'success' => true,
            'target' => $target,
            'dorks' => $dorks,
            'count' => count($dorks),
        ]);
    }

    /**
     * Execute a precision search dork live and return discovered findings.
     */
    public function executeDork(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'engine' => 'nullable|string|in:duckduckgo,onionfind,vormweb,tordex,onionland,ahmia',
            'amount' => 'nullable|integer|min:1|max:30',
        ]);

        $query = trim($validated['query']);
        $engine = $validated['engine'] ?? 'duckduckgo';
        $amount = (int) ($validated['amount'] ?? 8);

        $data = $this->socialReconService->executeDork($query, $engine, $amount);

        return response()->json($data);
    }

    /**
     * Scout for user activities, comments, discussions, and code events across platforms.
     */
    public function userActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|string|max:255',
            'keyword' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $target = trim($validated['target']);
        $keyword = $validated['keyword'] ?? null;
        $limit = (int) ($validated['limit'] ?? 30);

        $data = $this->socialReconService->searchUserActivity($target, $keyword, $limit);

        return response()->json($data);
    }

    /**
     * Stream user activity results via SSE, yielding each platform's results as they complete.
     */
    public function streamActivity(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'target' => 'required|string|max:255',
            'keyword' => 'nullable|string|max:255',
        ]);

        $target = trim($validated['target']);
        $keyword = $validated['keyword'] ?? null;

        return ServerSentEventStream::createStream(function (callable $sendEvent, callable $isAborted) use ($target, $keyword) {
            foreach ($this->socialReconService->streamUserActivity($target, $keyword) as $event) {
                if ($isAborted()) {
                    break;
                }
                $sendEvent('', $event);
            }
        });
    }
}

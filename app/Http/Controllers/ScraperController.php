<?php

namespace App\Http\Controllers;

use App\Models\ScrapedTarget;
use App\Services\DarkWeb\DeepScraperService;
use App\Services\DarkWeb\ScreenshotService;
use App\Services\DarkWeb\SearchEngineManager;
use App\Services\DarkWeb\TorClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ScraperController extends Controller
{
    public function __construct(
        protected DeepScraperService $scraper,
        protected ScreenshotService $screenshotService,
    ) {}

    public function index(): Response
    {
        $recentScrapes = ScrapedTarget::latest()->take(12)->get();

        return Inertia::render('Scraper/Index', [
            'recentScrapes' => $recentScrapes,
        ]);
    }

    public function scrape(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string',
            'collect_images' => 'nullable|boolean',
            'use_tor' => 'nullable|boolean',
            'capture_screenshot' => 'nullable|boolean',
            'custom_keywords' => 'nullable|string',
            'crawl_subpages' => 'nullable|boolean',
        ]);

        $url = $request->input('url');
        $collectImages = (bool) $request->input('collect_images', false);
        $useTor = (bool) $request->input('use_tor', false);
        $captureScreenshot = (bool) $request->input('capture_screenshot', false);
        $customKeywords = $request->input('custom_keywords');
        $crawlSubpages = $request->has('crawl_subpages') ? (bool) $request->input('crawl_subpages') : true;

        $result = $this->scraper->scrape($url, $collectImages, $useTor, $customKeywords, $crawlSubpages);

        if ($result['status'] === 'success') {
            $screenshotData = null;
            if ($captureScreenshot) {
                $screenshotData = $this->screenshotService->capture($result['url'], $useTor);
            }

            $scrapedTarget = ScrapedTarget::create([
                'url' => $result['url'],
                'screenshot_path' => ($screenshotData && ($screenshotData['success'] ?? false)) ? $screenshotData['storage_path'] : null,
                'screenshot_metadata' => $screenshotData,
                'title' => $result['title'],
                'status_code' => $result['status_code'],
                'is_blacklisted' => $result['is_blacklisted'] ?? false,
                'response_time_seconds' => $result['response_time_seconds'],
                'server' => $result['server'],
                'headers' => $result['headers'],
                'metadata' => $result['metadata'] ?? [],
                'keywords' => $result['keywords'] ?? [],
                'custom_keywords' => $result['custom_keywords'] ?? [],
                'keyword_intel' => $result['keyword_intel'] ?? [],
                'sentiment' => $result['sentiment'] ?? [],
                'emails' => $result['emails'],
                'documents' => $result['documents'],
                'images' => $result['images'],
                'internal_links' => $result['internal_links'],
                'external_links' => $result['external_links'],
                'raw_text_sample' => $result['raw_text_sample'],
            ]);

            $result['target_id'] = $scrapedTarget->id;
            $result['screenshot'] = $screenshotData;
        }

        return response()->json($result);
    }

    /**
     * Capture on-demand forensic screenshot of a target URL
     */
    public function captureScreenshot(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string',
            'use_tor' => 'nullable|boolean',
            'target_id' => 'nullable|integer|exists:scraped_targets,id',
        ]);

        $url = $request->input('url');
        $useTor = (bool) $request->input('use_tor', false);
        $targetId = $request->input('target_id');

        $result = $this->screenshotService->capture($url, $useTor);

        if (($result['success'] ?? false) && $targetId) {
            $target = ScrapedTarget::find($targetId);
            if ($target) {
                $target->update([
                    'screenshot_path' => $result['storage_path'],
                    'screenshot_metadata' => $result,
                ]);
            }
        }

        return response()->json($result);
    }

    /**
     * Securely proxy and cache darknet images for live preview in the browser
     */
    public function proxyImage(Request $request, TorClient $torClient)
    {
        $url = $request->query('url');
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return response('Invalid image URL', 400);
        }

        $cacheKey = 'scraped_img_'.md5($url);

        $cached = Cache::remember($cacheKey, 3600, function () use ($url, $torClient) {
            $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $url);
            $torAvailable = $torClient->isTorAvailable();
            $targetUrl = ($isOnion && ! $torAvailable) ? $torClient->resolveOnionUrl($url) : $url;
            $useTor = $isOnion && $torAvailable;

            try {
                $client = $torClient->getHttpClient($useTor, 15);
                $res = $client->get($targetUrl);
                if ($res->getStatusCode() === 200) {
                    $contentType = $res->getHeaderLine('Content-Type') ?: 'image/jpeg';

                    return [
                        'content' => base64_encode((string) $res->getBody()),
                        'type' => $contentType,
                    ];
                }
            } catch (\Throwable $e) {
                // Return null if fetch fails
            }

            return null;
        });

        if (! $cached) {
            return response('Image unavailable', 404);
        }

        return response(base64_decode($cached['content']), 200)
            ->header('Content-Type', $cached['type'])
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Discover live .onion targets associated with a keyword
     */
    public function discoverTargets(Request $request, SearchEngineManager $searchEngine): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:200',
            'engine' => 'nullable|string',
            'use_tor' => 'nullable|boolean',
            'amount' => 'nullable|integer|min:3|max:30',
        ]);

        $query = $request->input('query');
        $engine = $request->input('engine', 'onionfind');
        $useTor = (bool) $request->input('use_tor', false);
        $amount = (int) $request->input('amount', 12);

        $results = $searchEngine->search($query, $amount, $engine, $useTor);

        // Filter and prioritize .onion sites
        $onionResults = [];
        $otherResults = [];
        foreach ($results as $res) {
            if ($res['is_onion'] ?? false) {
                $onionResults[] = $res;
            } else {
                $otherResults[] = $res;
            }
        }

        $allTargets = array_merge($onionResults, $otherResults);

        return response()->json([
            'status' => 'success',
            'query' => $query,
            'engine' => $engine,
            'total' => count($allTargets),
            'onion_count' => count($onionResults),
            'targets' => $allTargets,
        ]);
    }
}

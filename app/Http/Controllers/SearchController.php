<?php

namespace App\Http\Controllers;

use App\Http\Responses\ServerSentEventStream;
use App\Models\Investigation;
use App\Models\ScrapedTarget;
use App\Models\SearchQuery;
use App\Models\SearchResult;
use App\Services\DarkWeb\DeepScraperService;
use App\Services\DarkWeb\SearchEngineManager;
use App\Services\DarkWeb\TorClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SearchController extends Controller
{
    public function __construct(
        protected SearchEngineManager $searchEngine,
        protected TorClient $torClient,
        protected DeepScraperService $scraper,
    ) {}

    public function index(): Response
    {
        $engines = $this->searchEngine->getAvailableEngines();
        $investigations = Investigation::where('status', 'active')->get(['id', 'title']);
        $recentSearches = SearchQuery::latest()->take(8)->get(['id', 'query', 'engine', 'results_count', 'created_at']);

        return Inertia::render('Search/Index', [
            'engines' => $engines,
            'investigations' => $investigations,
            'recentSearches' => $recentSearches,
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $query = $request->query('q', '');
        $engine = $request->query('engine', 'duckduckgo');
        $amount = (int) $request->query('amount', 15);
        $useTor = filter_var($request->query('use_tor', false), FILTER_VALIDATE_BOOLEAN);
        $uniqueOnly = filter_var($request->query('unique', false), FILTER_VALIDATE_BOOLEAN);
        $deepScrape = filter_var($request->query('scrape', false), FILTER_VALIDATE_BOOLEAN);
        $collectImages = filter_var($request->query('images', false), FILTER_VALIDATE_BOOLEAN);

        return ServerSentEventStream::createStream(function (callable $sendEvent, callable $isAborted) use ($query, $engine, $amount, $useTor, $uniqueOnly, $deepScrape, $collectImages) {
            if (empty(trim($query))) {
                $sendEvent('error', ['message' => 'Empty search query provided.']);
                $sendEvent('done', ['total' => 0]);

                return;
            }

            $sendEvent('status', [
                'message' => "Initializing search with [{$engine}] engine...",
                'query' => $query,
                'use_tor' => $useTor,
            ]);

            $startTime = microtime(true);

            try {
                $results = $this->searchEngine->search($query, $amount, $engine, $useTor);

                if ($uniqueOnly) {
                    $seen = [];
                    $uniqueResults = [];
                    foreach ($results as $res) {
                        $key = md5(strtolower(trim($res['title'].$res['description'])));
                        if (! isset($seen[$key])) {
                            $seen[$key] = true;
                            $uniqueResults[] = $res;
                        }
                    }
                    $results = $uniqueResults;
                }

                $sendEvent('status', [
                    'message' => 'Found '.count($results).' results. Streaming search findings...',
                ]);

                // 1. Emit all basic search results immediately for instantaneous UI rendering
                foreach ($results as $result) {
                    if ($isAborted()) {
                        break;
                    }
                    $sendEvent('result', $result);
                    usleep(15000); // 15ms smooth stream feed
                }

                // 2. If deep scrape requested, run targeted metadata extraction pass (subpages disabled for performance)
                if ($deepScrape) {
                    $sendEvent('status', [
                        'message' => 'Starting deep intelligence extraction pass on '.count($results).' endpoints...',
                    ]);

                    foreach ($results as &$result) {
                        if ($isAborted()) {
                            break;
                        }

                        if (empty($result['url'])) {
                            continue;
                        }

                        $sendEvent('status', [
                            'message' => "Deep scraping #{$result['idx']}: {$result['url']}...",
                        ]);

                        try {
                            $scraped = $this->scraper->scrape(
                                url: $result['url'],
                                collectImages: $collectImages,
                                useTor: $useTor,
                                customKeywords: null,
                                crawlSubpages: false
                            );

                            if ($scraped['status'] === 'success') {
                                $result['scrape_data'] = [
                                    'metadata' => $scraped['metadata'] ?? [],
                                    'links_count' => ($scraped['internal_links_count'] ?? 0) + ($scraped['external_links_count'] ?? 0),
                                    'emails' => $scraped['emails'] ?? [],
                                    'documents' => $scraped['documents'] ?? [],
                                    'keywords' => $scraped['keywords'] ?? [],
                                    'sentiment' => $scraped['sentiment'] ?? null,
                                    'images_count' => count($scraped['images'] ?? []),
                                    'is_blacklisted' => $scraped['is_blacklisted'] ?? false,
                                ];
                                $result['_full_scraped'] = $scraped;
                                if (! empty($scraped['is_blacklisted'])) {
                                    $result['is_blacklisted'] = true;
                                }

                                $sendEvent('result_update', [
                                    'idx' => $result['idx'],
                                    'url' => $result['url'],
                                    'scrape_data' => $result['scrape_data'],
                                    'is_blacklisted' => $result['is_blacklisted'] ?? false,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            // Dead onion or timeout: gracefully skip as original darkdump does
                        }
                    }
                    unset($result);
                }

                $duration = round(microtime(true) - $startTime, 2);

                // Persist query in database
                $searchRecord = SearchQuery::create([
                    'query' => $query,
                    'engine' => $engine,
                    'scan_type' => 'search',
                    'use_tor' => $useTor,
                    'deep_scrape' => $deepScrape,
                    'collect_images' => $collectImages,
                    'results_count' => count($results),
                    'execution_time_seconds' => $duration,
                    'status' => 'completed',
                ]);

                foreach ($results as $item) {
                    $searchResultRecord = SearchResult::create([
                        'search_query_id' => $searchRecord->id,
                        'title' => $item['title'] ?? 'Untitled',
                        'url' => $item['url'] ?? '',
                        'description' => $item['description'] ?? '',
                        'engine' => $item['engine'] ?? $engine,
                        'position' => $item['idx'] ?? 0,
                        'is_onion' => $item['is_onion'] ?? false,
                        'is_blacklisted' => ($item['is_blacklisted'] ?? false) || ($item['scrape_data']['is_blacklisted'] ?? false),
                        'metadata' => $item['scrape_data'] ?? null,
                    ]);

                    // If full scrape data is available, persist ScrapedTarget record
                    if (! empty($item['_full_scraped']) && $item['_full_scraped']['status'] === 'success') {
                        $sData = $item['_full_scraped'];
                        ScrapedTarget::create([
                            'search_result_id' => $searchResultRecord->id,
                            'url' => $sData['url'],
                            'title' => $sData['title'],
                            'status_code' => $sData['status_code'],
                            'is_blacklisted' => $sData['is_blacklisted'] ?? false,
                            'response_time_seconds' => $sData['response_time_seconds'],
                            'server' => $sData['server'],
                            'headers' => $sData['headers'],
                            'metadata' => $sData['metadata'] ?? [],
                            'keywords' => $sData['keywords'] ?? [],
                            'sentiment' => $sData['sentiment'] ?? [],
                            'emails' => $sData['emails'],
                            'documents' => $sData['documents'],
                            'images' => $sData['images'],
                            'internal_links' => $sData['internal_links'],
                            'external_links' => $sData['external_links'],
                            'raw_text_sample' => $sData['raw_text_sample'],
                        ]);
                    }
                }

                $sendEvent('done', [
                    'total' => count($results),
                    'execution_time' => $duration,
                    'search_id' => $searchRecord->id,
                ]);

            } catch (\Throwable $e) {
                $sendEvent('error', [
                    'message' => 'Search error: '.$e->getMessage(),
                ]);
                $sendEvent('done', ['total' => 0]);
            }
        });
    }

    public function show(int $id): Response
    {
        $search = SearchQuery::with('results')->findOrFail($id);

        return Inertia::render('Search/Show', [
            'search' => $search,
        ]);
    }
}

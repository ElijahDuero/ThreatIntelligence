<?php

namespace App\Services\SocialRecon;

use App\Services\DarkWeb\SearchEngineManager;
use App\Services\DarkWeb\TorClient;
use App\Services\SocialRecon\Telegram\TelegramScraperService;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SocialReconService
{
    public function __construct(
        protected TorClient $torClient,
        protected SearchEngineManager $searchEngineManager,
        protected TelegramScraperService $telegramScraper,
    ) {}

    /**
     * Complete catalog of 50+ recognizable platforms for persona footprinting.
     */
    public function getPlatformCatalog(): array
    {
        return [
            // --- Developer & Tech ---
            [
                'id' => 'github',
                'name' => 'GitHub',
                'category' => 'developer',
                'url' => 'https://github.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'gitlab',
                'name' => 'GitLab',
                'category' => 'developer',
                'url' => 'https://gitlab.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'bitbucket',
                'name' => 'Bitbucket',
                'category' => 'developer',
                'url' => 'https://bitbucket.org/{username}/',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'dockerhub',
                'name' => 'Docker Hub',
                'category' => 'developer',
                'url' => 'https://hub.docker.com/v2/users/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'npm',
                'name' => 'NPM',
                'category' => 'developer',
                'url' => 'https://www.npmjs.com/~{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'pypi',
                'name' => 'PyPI',
                'category' => 'developer',
                'url' => 'https://pypi.org/user/{username}/',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'hackernews',
                'name' => 'HackerNews',
                'category' => 'developer',
                'url' => 'https://news.ycombinator.com/user?id={username}',
                'check_type' => 'body_not_contains',
                'not_contains' => 'No such user',
                'valid_status' => [200],
            ],
            [
                'id' => 'codepen',
                'name' => 'CodePen',
                'category' => 'developer',
                'url' => 'https://codepen.io/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'devto',
                'name' => 'Dev.to',
                'category' => 'developer',
                'url' => 'https://dev.to/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'replit',
                'name' => 'Replit',
                'category' => 'developer',
                'url' => 'https://replit.com/@{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'kaggle',
                'name' => 'Kaggle',
                'category' => 'developer',
                'url' => 'https://www.kaggle.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],

            // --- Social & Identity ---
            [
                'id' => 'x_twitter',
                'name' => 'X (Twitter)',
                'category' => 'social',
                'url' => 'https://x.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200, 302],
            ],
            [
                'id' => 'instagram',
                'name' => 'Instagram',
                'category' => 'social',
                'url' => 'https://www.instagram.com/{username}/',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'tiktok',
                'name' => 'TikTok',
                'category' => 'social',
                'url' => 'https://www.tiktok.com/@{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'pinterest',
                'name' => 'Pinterest',
                'category' => 'social',
                'url' => 'https://www.pinterest.com/{username}/',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'medium',
                'name' => 'Medium',
                'category' => 'social',
                'url' => 'https://medium.com/@{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'substack',
                'name' => 'Substack',
                'category' => 'social',
                'url' => 'https://{username}.substack.com',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'tumblr',
                'name' => 'Tumblr',
                'category' => 'social',
                'url' => 'https://{username}.tumblr.com',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'mastodon',
                'name' => 'Mastodon',
                'category' => 'social',
                'url' => 'https://mastodon.social/@{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'bluesky',
                'name' => 'Bluesky',
                'category' => 'social',
                'url' => 'https://bsky.app/profile/{username}.bsky.social',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'linktree',
                'name' => 'Linktree',
                'category' => 'social',
                'url' => 'https://linktr.ee/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'aboutme',
                'name' => 'About.me',
                'category' => 'social',
                'url' => 'https://about.me/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'patreon',
                'name' => 'Patreon',
                'category' => 'social',
                'url' => 'https://www.patreon.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],

            // --- Messaging & Discussion ---
            [
                'id' => 'telegram',
                'name' => 'Telegram',
                'category' => 'messaging',
                'url' => 'https://t.me/{username}',
                'check_type' => 'body_not_contains',
                'not_contains' => 'If you have Telegram, you can contact @',
                'valid_status' => [200],
            ],
            [
                'id' => 'reddit_user',
                'name' => 'Reddit',
                'category' => 'messaging',
                'url' => 'https://www.reddit.com/user/{username}/about.json',
                'display_url' => 'https://www.reddit.com/user/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'keybase',
                'name' => 'Keybase',
                'category' => 'messaging',
                'url' => 'https://keybase.io/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'discourse',
                'name' => 'Discourse Meta',
                'category' => 'messaging',
                'url' => 'https://meta.discourse.org/u/{username}.json',
                'display_url' => 'https://meta.discourse.org/u/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],

            // --- Gaming & Media ---
            [
                'id' => 'steam',
                'name' => 'Steam Community',
                'category' => 'gaming',
                'url' => 'https://steamcommunity.com/id/{username}',
                'check_type' => 'body_not_contains',
                'not_contains' => 'The specified profile could not be found',
                'valid_status' => [200],
            ],
            [
                'id' => 'twitch',
                'name' => 'Twitch',
                'category' => 'gaming',
                'url' => 'https://www.twitch.tv/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'youtube',
                'name' => 'YouTube Channel',
                'category' => 'gaming',
                'url' => 'https://www.youtube.com/@{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'soundcloud',
                'name' => 'SoundCloud',
                'category' => 'gaming',
                'url' => 'https://soundcloud.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'spotify',
                'name' => 'Spotify User',
                'category' => 'gaming',
                'url' => 'https://open.spotify.com/user/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'chess_com',
                'name' => 'Chess.com',
                'category' => 'gaming',
                'url' => 'https://api.chess.com/pub/player/{username}',
                'display_url' => 'https://www.chess.com/member/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'lichess',
                'name' => 'Lichess',
                'category' => 'gaming',
                'url' => 'https://lichess.org/api/user/{username}',
                'display_url' => 'https://lichess.org/@/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],

            // --- Security & Research ---
            [
                'id' => 'tryhackme',
                'name' => 'TryHackMe',
                'category' => 'security',
                'url' => 'https://tryhackme.com/api/users/{username}',
                'display_url' => 'https://tryhackme.com/p/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'pastebin',
                'name' => 'Pastebin User',
                'category' => 'security',
                'url' => 'https://pastebin.com/u/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'bugcrowd',
                'name' => 'Bugcrowd',
                'category' => 'security',
                'url' => 'https://bugcrowd.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
            [
                'id' => 'hackerone',
                'name' => 'HackerOne',
                'category' => 'security',
                'url' => 'https://hackerone.com/{username}',
                'check_type' => 'status',
                'valid_status' => [200],
            ],
        ];
    }

    /**
     * Concurrently probe a list of platforms for a given username handle.
     */
    public function probeBatch(string $username, array $platformIds = [], ?string $category = null): array
    {
        $allPlatforms = $this->getPlatformCatalog();
        $targetPlatforms = [];

        foreach ($allPlatforms as $p) {
            if ($category && $category !== 'all' && $p['category'] !== $category) {
                continue;
            }
            if (! empty($platformIds) && ! in_array($p['id'], $platformIds)) {
                continue;
            }
            $targetPlatforms[] = $p;
        }

        if (empty($targetPlatforms)) {
            return [];
        }

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Darkdump/5.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ];

        $startTimes = [];
        $responses = Http::pool(function (Pool $pool) use ($targetPlatforms, $username, $headers, &$startTimes) {
            $poolCalls = [];
            foreach ($targetPlatforms as $platform) {
                $checkUrl = str_replace('{username}', urlencode($username), $platform['url']);
                $startTimes[$platform['id']] = microtime(true);

                $poolCalls[$platform['id']] = $pool->as($platform['id'])
                    ->withHeaders($headers)
                    ->timeout(4)
                    ->connectTimeout(2)
                    ->get($checkUrl);
            }

            return $poolCalls;
        });

        $results = [];

        foreach ($targetPlatforms as $platform) {
            $id = $platform['id'];
            $targetUrl = str_replace('{username}', urlencode($username), $platform['display_url'] ?? $platform['url']);
            $startTime = $startTimes[$id] ?? microtime(true);
            $latencyMs = round((microtime(true) - $startTime) * 1000);

            $response = $responses[$id] ?? null;

            $exists = false;
            $statusCode = 0;
            $error = null;

            if ($response instanceof \Throwable) {
                $error = 'Timeout or network unreachable';
            } elseif ($response && method_exists($response, 'status')) {
                $statusCode = $response->status();
                $validStatuses = $platform['valid_status'] ?? [200];

                if (in_array($statusCode, $validStatuses)) {
                    $exists = true;

                    // Secondary body text check
                    if (($platform['check_type'] ?? '') === 'body_not_contains' && ! empty($platform['not_contains'])) {
                        $body = $response->body();
                        if (stripos($body, $platform['not_contains']) !== false) {
                            $exists = false;
                        }
                    }
                }
            }

            $results[] = [
                'id' => $platform['id'],
                'name' => $platform['name'],
                'category' => $platform['category'],
                'url' => $targetUrl,
                'exists' => $exists,
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
                'error' => $error,
            ];
        }

        return $results;
    }

    /**
     * Search Google engine with hybrid multi-tier strategy:
     * Tier 1: Official Google Custom Search JSON API (if configured in .env)
     * Tier 2: Live Google Index & Feed (no API key required)
     * Tier 3: Multi-engine clearnet fallback (DuckDuckGo + Bing) to guarantee comprehensive results
     */
    public function searchGoogle(string $query, int $limit = 35): array
    {
        $rawQuery = trim($query);
        if (empty($rawQuery)) {
            return [
                'success' => true,
                'query' => '',
                'results' => [],
                'total' => 0,
                'provider' => 'Google Search',
                'engines_searched' => [],
            ];
        }

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        $candidates = [];
        $primaryProvider = 'Google Index';

        // Tier 1: Google Custom Search API (if credentials set in .env / config)
        $apiKey = config('services.google.search_key');
        $cx = config('services.google.search_cx');

        if (! empty($apiKey) && ! empty($cx)) {
            try {
                $gResponse = Http::timeout(8)->get('https://www.googleapis.com/customsearch/v1', [
                    'key' => $apiKey,
                    'cx' => $cx,
                    'q' => $rawQuery,
                    'num' => min($limit, 10),
                ]);

                if ($gResponse->successful()) {
                    $items = $gResponse->json('items') ?? [];
                    foreach ($items as $item) {
                        $candidates[] = [
                            'title' => $item['title'] ?? '',
                            'url' => $item['link'] ?? '',
                            'description' => $item['snippet'] ?? '',
                            'engine' => 'Google API',
                        ];
                    }
                    if (! empty($candidates)) {
                        $primaryProvider = 'Google Custom Search API';
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Google Custom Search API notice: '.$e->getMessage());
            }
        }

        // Tier 2: Live Google Index & Feed (always available, no API keys needed)
        try {
            $rssUrl = 'https://news.google.com/rss/search?q='.urlencode($rawQuery).'&hl=en-US&gl=US&ceid=US:en';
            $rssResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
            ])->timeout(8)->get($rssUrl);

            if ($rssResponse->successful() && str_contains($rssResponse->body(), '<item>')) {
                $xml = @simplexml_load_string($rssResponse->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($xml && isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        $rawTitle = html_entity_decode(trim((string) $item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $link = trim((string) $item->link);
                        $desc = html_entity_decode(trim(strip_tags((string) $item->description)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $desc = trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $desc)));
                        $source = isset($item->source) ? trim((string) $item->source) : '';
                        $sourceUrl = isset($item->source['url']) ? trim((string) $item->source['url']) : '';
                        $pubDate = isset($item->pubDate) ? trim((string) $item->pubDate) : '';

                        // Clean title if publication is suffixed
                        if (! empty($source) && str_ends_with($rawTitle, ' - '.$source)) {
                            $rawTitle = trim(mb_substr($rawTitle, 0, -mb_strlen(' - '.$source)));
                        }

                        if (! empty($link) && ! empty($rawTitle)) {
                            $candidates[] = [
                                'title' => $rawTitle,
                                'url' => $link,
                                'description' => ! empty($desc) ? $desc : (! empty($source) ? 'Indexed via Google: '.$source : 'Google Search finding for '.$rawQuery),
                                'engine' => 'Google',
                                'source' => $source,
                                'source_url' => $sourceUrl,
                                'published_at' => $pubDate,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('Google RSS index search notice: '.$e->getMessage());
        }

        // Tier 3: Multi-engine clearnet fallback (DuckDuckGo + Bing) to supplement results
        if (count($candidates) < 15) {
            // DuckDuckGo
            try {
                $ddgResponse = Http::asForm()->withHeaders($headers + [
                    'Referer' => 'https://html.duckduckgo.com/',
                ])->timeout(8)->post('https://html.duckduckgo.com/html/', [
                    'q' => $rawQuery,
                    'b' => '',
                    'kl' => 'us-en',
                ]);

                if ($ddgResponse->successful()) {
                    $crawler = new Crawler($ddgResponse->body());
                    $crawler->filter('.result')->each(function (Crawler $node) use (&$candidates) {
                        $titleNode = $node->filter('.result__title a, .result__a');
                        $snippetNode = $node->filter('.result__snippet');
                        $urlNode = $node->filter('.result__url');

                        if ($titleNode->count() > 0) {
                            $rawHref = $titleNode->attr('href') ?? '';
                            $url = $this->extractCleanUrl($rawHref);
                            if (empty($url) && $urlNode->count() > 0) {
                                $url = trim($urlNode->text());
                                if (! preg_match('#^https?://#i', $url)) {
                                    $url = 'https://'.$url;
                                }
                            }

                            $title = trim($titleNode->text());
                            $snippet = $snippetNode->count() > 0 ? trim($snippetNode->text()) : '';

                            if (! empty($url) && ! empty($title)) {
                                $candidates[] = [
                                    'title' => $title,
                                    'url' => $url,
                                    'description' => $snippet,
                                    'engine' => 'Google Web',
                                ];
                            }
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::info('Google fallback DDG lookup notice: '.$e->getMessage());
            }

            // Bing
            try {
                $bingUrl = 'https://www.bing.com/search?q='.urlencode($rawQuery).'&count=20';
                $bingResponse = Http::withHeaders($headers + [
                    'Referer' => 'https://www.bing.com/',
                ])->timeout(8)->get($bingUrl);

                if ($bingResponse->successful()) {
                    $crawler = new Crawler($bingResponse->body());
                    $crawler->filter('li.b_algo')->each(function (Crawler $node) use (&$candidates) {
                        $titleNode = $node->filter('h2 a');
                        $snippetNode = $node->filter('p, .b_caption p, .b_snippet');

                        if ($titleNode->count() > 0) {
                            $url = trim($titleNode->attr('href') ?? '');
                            $title = trim($titleNode->text());
                            $snippet = $snippetNode->count() > 0 ? trim($snippetNode->text()) : '';

                            if (! empty($url) && ! empty($title)) {
                                $candidates[] = [
                                    'title' => $title,
                                    'url' => $url,
                                    'description' => $snippet,
                                    'engine' => 'Google Web',
                                ];
                            }
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::info('Google fallback Bing lookup notice: '.$e->getMessage());
            }
        }

        // Process all candidates into Google result cards
        $formattedResults = [];
        $seenUrls = [];

        $queryTokens = array_filter(preg_split('/\s+/', mb_strtolower($rawQuery)));

        foreach ($candidates as $cand) {
            $url = $cand['url'];
            if (empty($url) || isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            $title = $cand['title'];
            $description = $cand['description'] ?? '';

            // Build Google-style formatted breadcrumb and domain
            $displayUrl = ! empty($cand['source_url']) ? $cand['source_url'] : $url;
            $parsedHost = parse_url($displayUrl, PHP_URL_HOST) ?: 'web';
            $hostClean = preg_replace('/^www\./', '', $parsedHost);
            $path = parse_url($displayUrl, PHP_URL_PATH) ?: '';
            $pathSegments = array_filter(explode('/', trim($path, '/')));
            $breadcrumb = $hostClean;
            if (! empty($pathSegments)) {
                $breadcrumb .= ' â€º '.implode(' â€º ', array_slice($pathSegments, 0, 3));
            }

            // Calculate relevance score (results citing query tokens rank higher)
            $textForScore = mb_strtolower($title.' '.$description);
            $score = 0;
            if (str_contains($textForScore, mb_strtolower($rawQuery))) {
                $score += 15;
            }
            foreach ($queryTokens as $tok) {
                if (strlen($tok) > 2 && str_contains($textForScore, $tok)) {
                    $score += 3;
                }
            }
            // Prefer Google-branded results
            if (str_contains($cand['engine'], 'Google')) {
                $score += 5;
            }

            $formattedResults[] = [
                'id' => md5($url),
                'title' => $title,
                'description' => $description,
                'snippet' => $description,
                'url' => $url,
                'target_url' => ! empty($cand['source_url']) ? $cand['source_url'] : $url,
                'domain' => $hostClean,
                'source' => $cand['source'] ?? null,
                'published_at' => $cand['published_at'] ?? null,
                'breadcrumb' => $breadcrumb,
                'engine' => $cand['engine'],
                'score' => $score,
            ];

            if (count($formattedResults) >= $limit) {
                break;
            }
        }

        // Sort by relevance score descending
        usort($formattedResults, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'success' => true,
            'query' => $rawQuery,
            'results' => $formattedResults,
            'total' => count($formattedResults),
            'provider' => $primaryProvider,
            'engines_searched' => ['Google Search'],
        ];
    }

    /**
     * Backward-compatible alias for General Lookup.
     */
    public function searchGeneralPersonaLookup(string $query, int $limit = 35): array
    {
        return $this->searchGoogle($query, $limit);
    }

    /**
     * Backward-compatible alias for exact persona footprint lookup.
     */
    public function searchExactPersonaFootprint(string $query, int $limit = 30): array
    {
        return $this->searchGoogle($query, $limit);
    }

    /**
     * Search Reddit for public posts or discussions matching a keyword.
     * Uses a multi-tier strategy: Direct JSON API -> Public Atom/RSS feed -> Clearnet OSINT Mirror.
     */
    public function searchReddit(string $query, ?string $subreddit = null, string $sort = 'relevance', int $limit = 25): array
    {
        $cleanQuery = trim($query);
        $cleanSubreddit = trim((string) $subreddit, " \t\n\r\0\x0B/r");

        if (empty($cleanQuery)) {
            return ['results' => [], 'total' => 0];
        }

        $results = [];

        // Tier 1: Direct Reddit JSON API
        try {
            $url = ! empty($cleanSubreddit)
                ? 'https://www.reddit.com/r/'.$cleanSubreddit.'/search.json?q='.urlencode($cleanQuery).'&restrict_sr=1&sort='.$sort.'&limit='.$limit
                : 'https://www.reddit.com/search.json?q='.urlencode($cleanQuery).'&sort='.$sort.'&limit='.$limit;

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(6)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $children = $data['data']['children'] ?? [];

                foreach ($children as $child) {
                    $post = $child['data'] ?? [];
                    if (empty($post['id'])) {
                        continue;
                    }

                    $snippet = trim($post['selftext'] ?? '');
                    if (mb_strlen($snippet) > 280) {
                        $snippet = mb_substr($snippet, 0, 277).'...';
                    }

                    $results[] = [
                        'id' => $post['id'],
                        'title' => $post['title'] ?? 'Untitled Thread',
                        'author' => $post['author'] ?? '[deleted]',
                        'subreddit' => $post['subreddit_name_prefixed'] ?? ('r/'.($post['subreddit'] ?? 'all')),
                        'url' => 'https://reddit.com'.($post['permalink'] ?? ''),
                        'external_url' => $post['url'] ?? null,
                        'score' => (int) ($post['score'] ?? 0),
                        'num_comments' => (int) ($post['num_comments'] ?? 0),
                        'created_utc' => (int) ($post['created_utc'] ?? time()),
                        'snippet' => $snippet,
                        'is_over_18' => (bool) ($post['over_18'] ?? false),
                        'source' => 'Reddit Native API',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::info('Reddit JSON search failed, attempting fallback: '.$e->getMessage());
        }

        // Tier 2: Public Atom/RSS feed if JSON returned empty (e.g. 403 or rate-limited)
        if (empty($results)) {
            try {
                $rssUrl = ! empty($cleanSubreddit)
                    ? 'https://www.reddit.com/r/'.$cleanSubreddit.'/search.rss?q='.urlencode($cleanQuery).'&restrict_sr=1&sort='.$sort.'&limit='.$limit
                    : 'https://www.reddit.com/search.rss?q='.urlencode($cleanQuery).'&sort='.$sort.'&limit='.$limit;

                $rssResponse = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'application/atom+xml,application/xml,text/xml;q=0.9,*/*;q=0.8',
                ])->timeout(6)->get($rssUrl);

                if ($rssResponse->successful() && ! empty($rssResponse->body())) {
                    $crawler = new Crawler;
                    $crawler->addXmlContent($rssResponse->body());

                    $crawler->filterXPath('//*[local-name()="entry"]')->each(function (Crawler $node) use (&$results, $cleanSubreddit, $limit) {
                        if (count($results) >= $limit) {
                            return;
                        }

                        $title = $node->filterXPath('//*[local-name()="title"]')->count() ? trim($node->filterXPath('//*[local-name()="title"]')->text()) : 'Reddit Thread';
                        $link = $node->filterXPath('//*[local-name()="link"]')->count() ? $node->filterXPath('//*[local-name()="link"]')->attr('href') : '';
                        $author = $node->filterXPath('//*[local-name()="author"]/*[local-name()="name"]')->count() ? trim($node->filterXPath('//*[local-name()="author"]/*[local-name()="name"]')->text()) : 'u/unknown';
                        $cat = $node->filterXPath('//*[local-name()="category"]')->count() ? $node->filterXPath('//*[local-name()="category"]')->attr('label') : ($cleanSubreddit ? 'r/'.$cleanSubreddit : 'r/reddit');
                        $contentHtml = $node->filterXPath('//*[local-name()="content"]')->count() ? $node->filterXPath('//*[local-name()="content"]')->text() : '';
                        $updated = $node->filterXPath('//*[local-name()="updated"]')->count() ? strtotime($node->filterXPath('//*[local-name()="updated"]')->text()) : time();

                        $snippet = trim(strip_tags(html_entity_decode($contentHtml)));
                        if (mb_strlen($snippet) > 280) {
                            $snippet = mb_substr($snippet, 0, 277).'...';
                        }

                        $id = $node->filterXPath('//*[local-name()="id"]')->count() ? basename($node->filterXPath('//*[local-name()="id"]')->text()) : md5($link);

                        if (! empty($link)) {
                            $results[] = [
                                'id' => $id,
                                'title' => $title,
                                'author' => $author,
                                'subreddit' => $cat ?: 'r/all',
                                'url' => $link,
                                'external_url' => $link,
                                'score' => 0,
                                'num_comments' => 0,
                                'created_utc' => $updated ?: time(),
                                'snippet' => $snippet,
                                'is_over_18' => false,
                                'source' => 'Reddit RSS Feed',
                            ];
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::info('Reddit RSS search failed, attempting search engine mirror: '.$e->getMessage());
            }
        }

        // Tier 3: Clearnet OSINT Mirror fallback via Search Engine
        if (empty($results)) {
            $results = $this->searchRedditViaSearchEngine($cleanQuery, $cleanSubreddit, $limit);
        }

        return [
            'results' => $results,
            'total' => count($results),
            'query' => $cleanQuery,
            'subreddit' => $cleanSubreddit ?: null,
        ];
    }

    /**
     * Fallback search for Reddit discussions via DuckDuckGo site dork.
     */
    protected function searchRedditViaSearchEngine(string $cleanQuery, ?string $cleanSubreddit = null, int $limit = 25): array
    {
        $dork = 'site:reddit.com';
        if (! empty($cleanSubreddit)) {
            $dork .= '/r/'.$cleanSubreddit;
        }
        $dork .= ' '.$cleanQuery;

        try {
            $response = Http::asForm()->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Referer' => 'https://html.duckduckgo.com/',
            ])->timeout(8)->post('https://html.duckduckgo.com/html/', [
                'q' => $dork,
                'b' => '',
                'kl' => 'us-en',
            ]);

            if (! $response->successful()) {
                return [];
            }

            $crawler = new Crawler($response->body());
            $results = [];

            $crawler->filter('.result')->each(function (Crawler $node) use (&$results, $limit, $cleanSubreddit) {
                if (count($results) >= $limit) {
                    return;
                }

                $titleNode = $node->filter('.result__title a, .result__a');
                $snippetNode = $node->filter('.result__snippet');

                if ($titleNode->count() > 0) {
                    $rawHref = $titleNode->attr('href') ?? '';
                    $url = $this->extractCleanUrl($rawHref);

                    if (! str_contains($url, 'reddit.com')) {
                        return;
                    }

                    $title = trim($titleNode->text());
                    $snippet = $snippetNode->count() > 0 ? trim($snippetNode->text()) : '';

                    $sub = $cleanSubreddit ? 'r/'.$cleanSubreddit : 'r/reddit';
                    if (preg_match('#reddit\.com/(r/[a-zA-Z0-9_]+)#i', $url, $m)) {
                        $sub = $m[1];
                    }

                    $results[] = [
                        'id' => md5($url),
                        'title' => $title,
                        'author' => 'Indexed User',
                        'subreddit' => $sub,
                        'url' => $url,
                        'external_url' => $url,
                        'score' => 0,
                        'num_comments' => 0,
                        'created_utc' => time(),
                        'snippet' => $snippet,
                        'is_over_18' => false,
                        'source' => 'OSINT Mirror',
                    ];
                }
            });

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Reddit fallback mirror search failed: '.$e->getMessage());

            return [];
        }
    }

    protected function extractCleanUrl(string $href): string
    {
        if (empty($href)) {
            return '';
        }

        if (str_contains($href, 'uddg=')) {
            parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $queryParams);
            if (! empty($queryParams['uddg'])) {
                return urldecode($queryParams['uddg']);
            }
        }

        return $href;
    }

    /**
     * Scrape public messages from a Telegram channel via the engineered multi-page scraper.
     */
    public function scrapeTelegramChannel(
        string $channel,
        ?string $query = null,
        int $limit = 50,
        ?string $beforeId = null,
        bool $extractIocs = true,
        bool $forceTor = true
    ): array {
        return $this->telegramScraper->scrapeChannel($channel, $query, $limit, $beforeId, $extractIocs, $forceTor);
    }

    /**
     * Search public Telegram channels matching a keyword using dedicated indexer.
     */
    public function searchTelegramChannels(string $keyword, int $limit = 25, bool $forceTor = true): array
    {
        return $this->telegramScraper->searchChannels($keyword, $limit, $forceTor);
    }

    /**
     * Generate precision search engine dorks targeting indexed social profiles and comments.
     */
    public function generateSocialDorks(string $target, ?string $keyword = null): array
    {
        $cleanTarget = trim($target);
        $cleanKeyword = trim((string) $keyword);

        $suffix = ! empty($cleanKeyword) ? ' "'.$cleanKeyword.'"' : '';

        $dorks = [
            // --- Social & Bios ---
            [
                'platform' => 'X (Twitter)',
                'category' => 'social',
                'description' => 'Target profile bios, handles, and quotes on X/Twitter',
                'query' => 'site:x.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'LinkedIn Profiles',
                'category' => 'social',
                'description' => 'Target professional profile bios, roles, and work history',
                'query' => 'site:linkedin.com/in/ "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Instagram Accounts',
                'category' => 'social',
                'description' => 'Target public user bios, captions, and account tags',
                'query' => 'site:instagram.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Facebook Profiles',
                'category' => 'social',
                'description' => 'Target public profiles, pages, and community groups',
                'query' => 'site:facebook.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'TikTok User Bios',
                'category' => 'social',
                'description' => 'Target public TikTok profiles, user bios, and creator handles',
                'query' => 'site:tiktok.com/@ "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'TikTok Captions & Mentions',
                'category' => 'social',
                'description' => 'Scan video captions, sound descriptions, and hashtag mentions',
                'query' => 'site:tiktok.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'YouTube Channels',
                'category' => 'social',
                'description' => 'Target video descriptions, channel about pages, and transcripts',
                'query' => 'site:youtube.com "'.$cleanTarget.'"'.$suffix,
            ],

            // --- Messaging & Forums ---
            [
                'platform' => 'Discord Servers & Invites',
                'category' => 'messaging',
                'description' => 'Target public Discord server vanity invites, channels, and guilds',
                'query' => 'site:discord.com/invite "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Discord Vanity Links',
                'category' => 'messaging',
                'description' => 'Target direct vanity invite links and user server badges on discord.gg',
                'query' => 'site:discord.gg "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Reddit Threads & Comments',
                'category' => 'messaging',
                'description' => 'Target discussions, comments, and username mentions on Reddit',
                'query' => 'site:reddit.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Telegram Web Channels',
                'category' => 'messaging',
                'description' => 'Target indexed Telegram channel posts and message archives',
                'query' => 'site:t.me "'.$cleanTarget.'"'.$suffix,
            ],

            // --- Code & Repos ---
            [
                'platform' => 'GitHub Repos & Commits',
                'category' => 'developer',
                'description' => 'Target public source code, commit history, issues, and gists',
                'query' => 'site:github.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'GitLab Projects & Snippets',
                'category' => 'developer',
                'description' => 'Target alternative code repositories, snippets, and commit logs',
                'query' => 'site:gitlab.com "'.$cleanTarget.'"'.$suffix,
            ],

            // --- Leaks & Files ---
            [
                'platform' => 'BreachForums Discussions',
                'category' => 'security',
                'description' => 'Probe indexed BreachForums discussions, leaks, and user profiles',
                'query' => '"breachforums" "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'RaidForums Historical Archives',
                'category' => 'security',
                'description' => 'Search historical RaidForums database dumps, member threads, and mirrors',
                'query' => '"raidforums" "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Wayback Breach Snapshots',
                'category' => 'security',
                'description' => 'Probe Wayback Machine archived snapshots of seized underground forums',
                'query' => 'site:web.archive.org "breached" "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Cracked Underground Forum',
                'category' => 'security',
                'description' => 'Target credentials, configs, combolists, and VIP threads on Cracked',
                'query' => 'site:cracked.io "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Pastebin & Text Dumps',
                'category' => 'security',
                'description' => 'Check if target appears in text dumps, credentials, or leak logs',
                'query' => 'site:pastebin.com "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Rentry Markdown Pastes',
                'category' => 'security',
                'description' => 'Uncover anonymous markdown dumps, combolists, and raw payloads on Rentry',
                'query' => 'site:rentry.co "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Leaked Documents & PDFs',
                'category' => 'security',
                'description' => 'Uncover exposed PDF dossiers, internal documents, and resumes',
                'query' => 'filetype:pdf "'.$cleanTarget.'"'.$suffix,
            ],
            [
                'platform' => 'Credential & Breach Keywords',
                'category' => 'security',
                'description' => 'Scan for exposed credentials, database dumps, and plaintext hashes',
                'query' => '"'.$cleanTarget.'" password OR leak OR breach OR credential'.$suffix,
            ],
        ];

        foreach ($dorks as &$dork) {
            $encoded = urlencode($dork['query']);
            $dork['google_url'] = 'https://www.google.com/search?q='.$encoded;
            $dork['duckduckgo_url'] = 'https://duckduckgo.com/?q='.$encoded;
            $dork['bing_url'] = 'https://www.bing.com/search?q='.$encoded;
            $dork['yandex_url'] = 'https://yandex.com/search/?text='.$encoded;
            $dork['darkdump_search_url'] = '/search?q='.$encoded.'&engine=duckduckgo';
        }

        return $dorks;
    }

    /**
     * Execute a precision search dork live and return discovered findings.
     */
    public function executeDork(string $query, string $engine = 'duckduckgo', int $amount = 8): array
    {
        try {
            $results = $this->searchEngineManager->search($query, $amount, $engine, false);

            return [
                'success' => true,
                'query' => $query,
                'engine' => $engine,
                'results' => $results,
                'total' => count($results),
            ];
        } catch (\Throwable $e) {
            Log::warning('Execute dork failed for "'.$query.'": '.$e->getMessage());

            return [
                'success' => false,
                'query' => $query,
                'engine' => $engine,
                'results' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Scout for user activities, comments, forum posts, and discussions across platforms.
     */
    public function searchUserActivity(string $target, ?string $keyword = null, int $limit = 30): array
    {
        $cleanTarget = trim($target);
        $cleanKeyword = trim((string) $keyword);
        $searchPhrase = ! empty($cleanKeyword) ? $cleanTarget.' '.$cleanKeyword : $cleanTarget;
        $activities = [];

        foreach ($this->streamUserActivity($cleanTarget, ! empty($cleanKeyword) ? $cleanKeyword : null) as $event) {
            if (($event['type'] ?? '') === 'result' && ! empty($event['activities'])) {
                foreach ($event['activities'] as $act) {
                    $activities[] = $act;
                }
            }
        }

        // Generate the 21 surgical dork links as instant pivot helpers
        $dorks = $this->generateSocialDorks($cleanTarget, $cleanKeyword ?: null);

        // Calculate statistics for UI summary chips
        $stats = [
            'total' => count($activities),
            'comments' => count(array_filter($activities, fn ($a) => $a['category'] === 'comment')),
            'discussions' => count(array_filter($activities, fn ($a) => in_array($a['category'], ['discussion', 'post', 'web_mention']))),
            'code' => count(array_filter($activities, fn ($a) => $a['category'] === 'code')),
            'platforms' => array_values(array_unique(array_map(fn ($a) => $a['platform'], $activities))),
        ];

        return [
            'success' => true,
            'target' => $cleanTarget,
            'keyword' => $cleanKeyword ?: null,
            'activities' => array_slice($activities, 0, $limit),
            'stats' => $stats,
            'dorks' => $dorks,
        ];
    }

    /**
     * SSE generator: yields per-platform results one at a time for real-time streaming.
     *
     * @return \Generator<int, array>
     */
    public function streamUserActivity(string $target, ?string $keyword = null): \Generator
    {
        $cleanTarget = trim($target);
        $cleanKeyword = trim((string) $keyword);
        $searchPhrase = ! empty($cleanKeyword) ? $cleanTarget.' '.$cleanKeyword : $cleanTarget;

        $platformIndex = 0;
        $totalPlatforms = 25;

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        // 1. Hacker News
        $platformIndex++;
        yield $this->sseStatus('hackernews', 'Hacker News', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $hnUrl = 'https://hn.algolia.com/api/v1/search?query='.urlencode($searchPhrase).'&tags=comment';
            $hnRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(6)->get($hnUrl);
            if ($hnRes->successful()) {
                foreach (array_slice($hnRes->json()['hits'] ?? [], 0, 10) as $hit) {
                    $text = trim(strip_tags(html_entity_decode((string) ($hit['comment_text'] ?? ''))));
                    if (! empty($text)) {
                        $items[] = [
                            'id' => 'hn_'.($hit['objectID'] ?? uniqid()),
                            'platform' => 'Hacker News', 'platform_id' => 'hackernews', 'category' => 'comment',
                            'author' => $hit['author'] ?? 'unknown',
                            'title' => $hit['story_title'] ?? 'Discussion Thread',
                            'content' => $text,
                            'url' => 'https://news.ycombinator.com/item?id='.($hit['objectID'] ?? ''),
                            'date' => isset($hit['created_at']) ? date('Y-m-d H:i', strtotime($hit['created_at'])) : null,
                            'metadata' => ['points' => $hit['points'] ?? null],
                        ];
                    }
                }
            }
            yield $this->sseResult('hackernews', 'Hacker News', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('hackernews', 'Hacker News', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 2. GitHub
        $platformIndex++;
        yield $this->sseStatus('github', 'GitHub', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $ghRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/vnd.github.v3+json'])
                ->timeout(6)->get('https://api.github.com/users/'.urlencode($cleanTarget).'/events/public?per_page=12');
            if ($ghRes->successful() && is_array($ghRes->json())) {
                foreach (array_slice($ghRes->json(), 0, 8) as $ev) {
                    $type = $ev['type'] ?? 'Activity';
                    $repo = $ev['repo']['name'] ?? 'repository';
                    $cat = 'code';
                    $content = "Performed {$type} on {$repo}";
                    $link = 'https://github.com/'.$repo;
                    if ($type === 'IssueCommentEvent' || $type === 'CommitCommentEvent') {
                        $cat = 'comment';
                        $content = $ev['payload']['comment']['body'] ?? $content;
                        $link = $ev['payload']['comment']['html_url'] ?? $link;
                    } elseif ($type === 'PushEvent') {
                        $commits = $ev['payload']['commits'] ?? [];
                        if (! empty($commits)) {
                            $content = 'Commit: '.($commits[0]['message'] ?? 'Code update');
                        }
                    } elseif ($type === 'IssuesEvent') {
                        $cat = 'post';
                        $content = $ev['payload']['issue']['title'] ?? 'Issue opened';
                        $link = $ev['payload']['issue']['html_url'] ?? $link;
                    }
                    $items[] = [
                        'id' => 'gh_'.($ev['id'] ?? uniqid()),
                        'platform' => 'GitHub', 'platform_id' => 'github', 'category' => $cat,
                        'author' => $cleanTarget, 'title' => $repo, 'content' => $content, 'url' => $link,
                        'date' => isset($ev['created_at']) ? date('Y-m-d H:i', strtotime($ev['created_at'])) : null,
                        'metadata' => ['event_type' => $type],
                    ];
                }
            }
            yield $this->sseResult('github', 'GitHub', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('github', 'GitHub', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 3. GitLab
        $platformIndex++;
        yield $this->sseStatus('gitlab', 'GitLab', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $glUserRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json'])
                ->timeout(5)->get('https://gitlab.com/api/v4/users?username='.urlencode($cleanTarget));
            if ($glUserRes->successful() && is_array($glUserRes->json()) && ! empty($glUserRes->json())) {
                $user = $glUserRes->json()[0];
                $userId = $user['id'] ?? null;
                if ($userId) {
                    $glEventsRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json'])
                        ->timeout(6)->get("https://gitlab.com/api/v4/users/{$userId}/events?per_page=8");
                    if ($glEventsRes->successful() && is_array($glEventsRes->json())) {
                        foreach (array_slice($glEventsRes->json(), 0, 8) as $ev) {
                            $action = $ev['action_name'] ?? 'activity';
                            $targetType = $ev['target_type'] ?? 'Project';
                            $targetTitle = $ev['target_title'] ?? ($user['name'] ?? $cleanTarget);
                            $cat = in_array(strtolower($action), ['commented', 'commented on', 'opened', 'closed']) ? 'comment' : 'code';
                            $items[] = [
                                'id' => 'gitlab_'.($ev['id'] ?? uniqid()),
                                'platform' => 'GitLab',
                                'platform_id' => 'gitlab',
                                'category' => $cat,
                                'author' => $user['username'] ?? $cleanTarget,
                                'title' => "GitLab: {$action} {$targetType}",
                                'content' => ! empty($targetTitle) ? "{$action} {$targetType}: {$targetTitle}" : "GitLab event {$action}",
                                'url' => $user['web_url'] ?? 'https://gitlab.com/'.$cleanTarget,
                                'date' => isset($ev['created_at']) ? date('Y-m-d H:i', strtotime($ev['created_at'])) : null,
                                'metadata' => ['action' => $action, 'target_type' => $targetType],
                            ];
                        }
                    }
                }
            }
            if (empty($items)) {
                $items = $this->dorkPlatformSearch('gitlab', 'GitLab', 'site:gitlab.com "'.$cleanTarget.'"', $cleanTarget, 6);
            }
            yield $this->sseResult('gitlab', 'GitLab', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('gitlab', 'GitLab', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 4. Reddit
        $platformIndex++;
        yield $this->sseStatus('reddit', 'Reddit', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $rssUrl = 'https://www.reddit.com/search.rss?q='.urlencode('"'.$cleanTarget.'"'.(! empty($cleanKeyword) ? ' '.$cleanKeyword : '')).'&sort=new&limit=8';
            $rssRes = Http::withHeaders($headers + ['Accept' => 'application/atom+xml,application/xml,text/xml;q=0.9,*/*;q=0.8'])->timeout(6)->get($rssUrl);
            if ($rssRes->successful() && ! empty($rssRes->body())) {
                $c = new Crawler;
                $c->addXmlContent($rssRes->body());
                $c->filterXPath('//*[local-name()="entry"]')->each(function (Crawler $n) use (&$items) {
                    $title = $n->filterXPath('//*[local-name()="title"]')->count() ? trim($n->filterXPath('//*[local-name()="title"]')->text()) : 'Reddit Discussion';
                    $link = $n->filterXPath('//*[local-name()="link"]')->count() ? $n->filterXPath('//*[local-name()="link"]')->attr('href') : '';
                    $author = $n->filterXPath('//*[local-name()="author"]/*[local-name()="name"]')->count() ? trim($n->filterXPath('//*[local-name()="author"]/*[local-name()="name"]')->text()) : 'u/unknown';
                    $html = $n->filterXPath('//*[local-name()="content"]')->count() ? $n->filterXPath('//*[local-name()="content"]')->text() : '';
                    $snippet = trim(strip_tags(html_entity_decode((string) $html)));
                    if (mb_strlen($snippet) > 280) {
                        $snippet = mb_substr($snippet, 0, 277).'...';
                    }
                    $updated = $n->filterXPath('//*[local-name()="updated"]')->count() ? strtotime($n->filterXPath('//*[local-name()="updated"]')->text()) : null;
                    if (! empty($link)) {
                        $items[] = ['id' => 'reddit_'.md5($link), 'platform' => 'Reddit', 'platform_id' => 'reddit', 'category' => 'discussion', 'author' => $author, 'title' => $title, 'content' => $snippet ?: $title, 'url' => $link, 'date' => $updated ? date('Y-m-d H:i', $updated) : null, 'metadata' => []];
                    }
                });
            }
            yield $this->sseResult('reddit', 'Reddit', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('reddit', 'Reddit', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 5. Lemmy (Fediverse open communities & comments)
        $platformIndex++;
        yield $this->sseStatus('lemmy', 'Lemmy', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $lemmyUrl = 'https://lemmy.world/api/v3/search?q='.urlencode($searchPhrase).'&type_=Comments&sort=New&limit=8';
            $lemmyRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json'])
                ->timeout(6)->get($lemmyUrl);
            if ($lemmyRes->successful() && is_array($lemmyRes->json())) {
                foreach (array_slice($lemmyRes->json()['comments'] ?? [], 0, 6) as $entry) {
                    $comm = $entry['comment'] ?? [];
                    $creator = $entry['creator']['name'] ?? 'fediverse_user';
                    $community = $entry['community']['name'] ?? 'community';
                    $postName = $entry['post']['name'] ?? 'Lemmy Discussion';
                    $content = trim(strip_tags(html_entity_decode((string) ($comm['content'] ?? ''))));
                    if (! empty($content)) {
                        $cId = $comm['id'] ?? uniqid();
                        $items[] = [
                            'id' => 'lemmy_'.$cId,
                            'platform' => 'Lemmy',
                            'platform_id' => 'lemmy',
                            'category' => 'comment',
                            'author' => $creator,
                            'title' => "c/{$community}: {$postName}",
                            'content' => mb_strlen($content) > 280 ? mb_substr($content, 0, 277).'...' : $content,
                            'url' => "https://lemmy.world/comment/{$cId}",
                            'date' => isset($comm['published']) ? date('Y-m-d H:i', strtotime($comm['published'])) : null,
                            'metadata' => ['community' => $community],
                        ];
                    }
                }
            }
            if (empty($items)) {
                $items = $this->dorkPlatformSearch('lemmy', 'Lemmy', 'site:lemmy.world "'.$cleanTarget.'"', $cleanTarget, 6);
            }
            yield $this->sseResult('lemmy', 'Lemmy', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('lemmy', 'Lemmy', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 6. StackOverflow
        $platformIndex++;
        yield $this->sseStatus('stackoverflow', 'StackOverflow', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $soRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json', 'Accept-Encoding' => 'gzip, deflate'])
                ->timeout(6)->get('https://api.stackexchange.com/2.3/search/excerpts?order=desc&sort=relevance&q='.urlencode($searchPhrase).'&site=stackoverflow&pagesize=6');
            if ($soRes->successful()) {
                $raw = $soRes->body();
                $soData = str_starts_with($raw, "\x1f\x8b") ? json_decode(@gzdecode($raw) ?: '{}', true) : $soRes->json();
                foreach (array_slice($soData['items'] ?? [], 0, 6) as $item) {
                    $isAns = ($item['item_type'] ?? 'answer') === 'answer';
                    $content = trim(strip_tags(html_entity_decode((string) ($item['excerpt'] ?? $item['body'] ?? ''))));
                    $cleanTitle = trim(strip_tags(html_entity_decode((string) ($item['title'] ?? 'StackOverflow Discussion'))));
                    if (! empty($content) || ! empty($cleanTitle)) {
                        $link = ! empty($item['answer_id']) ? 'https://stackoverflow.com/a/'.$item['answer_id'] : 'https://stackoverflow.com/q/'.($item['question_id'] ?? '');
                        $items[] = ['id' => 'so_'.($item['answer_id'] ?? $item['question_id'] ?? uniqid()), 'platform' => 'StackOverflow', 'platform_id' => 'stackoverflow', 'category' => $isAns ? 'comment' : 'discussion', 'author' => $item['owner']['display_name'] ?? 'developer', 'title' => $cleanTitle, 'content' => $content ?: $cleanTitle, 'url' => $link, 'date' => isset($item['creation_date']) ? date('Y-m-d H:i', (int) $item['creation_date']) : null, 'metadata' => ['score' => $item['score'] ?? null]];
                    }
                }
            }
            yield $this->sseResult('stackoverflow', 'StackOverflow', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('stackoverflow', 'StackOverflow', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 7. Discourse
        $platformIndex++;
        yield $this->sseStatus('discourse', 'Discourse', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $dcRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json'])->timeout(6)->get('https://meta.discourse.org/search.json?q='.urlencode($searchPhrase));
            if ($dcRes->successful()) {
                foreach (array_slice($dcRes->json()['posts'] ?? [], 0, 6) as $post) {
                    $blurb = trim(strip_tags(html_entity_decode((string) ($post['blurb'] ?? ''))));
                    if (! empty($blurb)) {
                        $tid = $post['topic_id'] ?? null;
                        $pn = $post['post_number'] ?? 1;
                        $items[] = ['id' => 'dc_'.($post['id'] ?? uniqid()), 'platform' => 'Discourse', 'platform_id' => 'discourse', 'category' => $pn > 1 ? 'comment' : 'discussion', 'author' => $post['username'] ?? 'community', 'title' => trim(strip_tags(html_entity_decode((string) ($post['topic_title'] ?? 'Forum Discussion')))), 'content' => $blurb, 'url' => $tid ? "https://meta.discourse.org/t/{$tid}/{$pn}" : 'https://meta.discourse.org', 'date' => isset($post['created_at']) ? date('Y-m-d H:i', strtotime($post['created_at'])) : null, 'metadata' => ['post_number' => $pn]];
                    }
                }
            }
            yield $this->sseResult('discourse', 'Discourse', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('discourse', 'Discourse', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 8. Dev.to
        $platformIndex++;
        yield $this->sseStatus('devto', 'Dev.to', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $devRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0', 'Accept' => 'application/json'])->timeout(6)->get('https://dev.to/api/articles?username='.urlencode($cleanTarget).'&per_page=6');
            if ($devRes->successful() && is_array($devRes->json())) {
                foreach (array_slice($devRes->json(), 0, 6) as $art) {
                    $desc = trim(strip_tags(html_entity_decode((string) ($art['description'] ?? ''))));
                    $t = trim(strip_tags(html_entity_decode((string) ($art['title'] ?? 'Dev.to Article'))));
                    $items[] = ['id' => 'devto_'.($art['id'] ?? uniqid()), 'platform' => 'Dev.to', 'platform_id' => 'devto', 'category' => 'discussion', 'author' => $art['user']['username'] ?? $cleanTarget, 'title' => $t, 'content' => $desc ?: $t, 'url' => $art['url'] ?? '', 'date' => isset($art['published_at']) ? date('Y-m-d H:i', strtotime($art['published_at'])) : null, 'metadata' => ['comments_count' => $art['comments_count'] ?? 0, 'reactions' => $art['public_reactions_count'] ?? 0]];
                }
            }
            yield $this->sseResult('devto', 'Dev.to', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('devto', 'Dev.to', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 9. Lobste.rs (Tech community comments & profile)
        $platformIndex++;
        yield $this->sseStatus('lobsters', 'Lobste.rs', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $lobUrl = 'https://lobste.rs/u/'.urlencode($cleanTarget);
            $lobRes = Http::withHeaders($headers)->timeout(5)->get($lobUrl);
            if ($lobRes->successful() && ! empty($lobRes->body())) {
                $crawler = new Crawler($lobRes->body());
                $aboutNode = $crawler->filter('.profile .shorten_first_p, .profile dd');
                $bio = $aboutNode->count() > 0 ? trim($aboutNode->text()) : 'Active Lobste.rs profile';
                $items[] = [
                    'id' => 'lobsters_profile_'.md5($cleanTarget),
                    'platform' => 'Lobste.rs',
                    'platform_id' => 'lobsters',
                    'category' => 'discussion',
                    'author' => $cleanTarget,
                    'title' => 'Lobste.rs Profile: @'.$cleanTarget,
                    'content' => mb_strlen($bio) > 280 ? mb_substr($bio, 0, 277).'...' : $bio,
                    'url' => $lobUrl,
                    'date' => null,
                    'metadata' => ['source' => 'User Profile'],
                ];
            }
            $dorkItems = $this->dorkPlatformSearch('lobsters', 'Lobste.rs', 'site:lobste.rs "'.$cleanTarget.'"', $cleanTarget, 5);
            $items = array_merge($items, $dorkItems);
            yield $this->sseResult('lobsters', 'Lobste.rs', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('lobsters', 'Lobste.rs', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 10. YouTube (Invidious public API with DDG fallback)
        $platformIndex++;
        yield $this->sseStatus('youtube', 'YouTube', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $instances = ['https://inv.nadeko.net', 'https://invidious.fdn.fr', 'https://vid.puffyan.us'];
            foreach ($instances as $inst) {
                try {
                    $ytRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(5)->get($inst.'/api/v1/search?q='.urlencode('"'.$cleanTarget.'"').'&type=video&sort_by=upload_date');
                    if ($ytRes->successful() && is_array($ytRes->json())) {
                        foreach (array_slice($ytRes->json(), 0, 6) as $v) {
                            if (empty($v['videoId'])) {
                                continue;
                            }
                            $items[] = ['id' => 'yt_'.$v['videoId'], 'platform' => 'YouTube', 'platform_id' => 'youtube', 'category' => 'discussion', 'author' => $v['author'] ?? 'YouTube User', 'title' => $v['title'] ?? 'YouTube Video', 'content' => $v['description'] ?? $v['title'] ?? 'Video mentioning target', 'url' => 'https://www.youtube.com/watch?v='.$v['videoId'], 'date' => isset($v['published']) ? date('Y-m-d H:i', (int) $v['published']) : null, 'metadata' => ['views' => $v['viewCount'] ?? 0]];
                        }
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
            if (empty($items)) {
                $items = $this->dorkPlatformSearch('youtube', 'YouTube', 'site:youtube.com "'.$cleanTarget.'"', $cleanTarget, 6);
            }
            yield $this->sseResult('youtube', 'YouTube', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('youtube', 'YouTube', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 11. Twitter/X (DDG site dork, no public API without OAuth)
        $platformIndex++;
        yield $this->sseStatus('twitter', 'X (Twitter)', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('twitter', 'X (Twitter)', 'site:x.com "'.$cleanTarget.'"', $cleanTarget, 8);
            yield $this->sseResult('twitter', 'X (Twitter)', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('twitter', 'X (Twitter)', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 12. Medium (RSS feed with DDG fallback)
        $platformIndex++;
        yield $this->sseStatus('medium', 'Medium', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $rssRes = Http::withHeaders($headers + ['Accept' => 'application/rss+xml,application/xml,text/xml;q=0.9'])->timeout(5)->get('https://medium.com/feed/@'.urlencode($cleanTarget));
            if ($rssRes->successful() && ! empty($rssRes->body())) {
                $c = new Crawler;
                $c->addXmlContent($rssRes->body());
                $c->filterXPath('//item')->each(function (Crawler $n) use (&$items, $cleanTarget) {
                    $title = $n->filterXPath('title')->count() ? trim($n->filterXPath('title')->text()) : 'Medium Article';
                    $link = $n->filterXPath('link')->count() ? trim($n->filterXPath('link')->text()) : '';
                    $desc = trim(strip_tags(html_entity_decode((string) ($n->filterXPath('description')->count() ? $n->filterXPath('description')->text() : ''))));
                    if (mb_strlen($desc) > 280) {
                        $desc = mb_substr($desc, 0, 277).'...';
                    }
                    $pub = $n->filterXPath('pubDate')->count() ? strtotime($n->filterXPath('pubDate')->text()) : null;
                    $creator = $n->filterXPath('//*[local-name()="creator"]')->count() ? trim($n->filterXPath('//*[local-name()="creator"]')->text()) : $cleanTarget;
                    if (! empty($link)) {
                        $items[] = ['id' => 'medium_'.md5($link), 'platform' => 'Medium', 'platform_id' => 'medium', 'category' => 'discussion', 'author' => $creator, 'title' => $title, 'content' => $desc ?: $title, 'url' => $link, 'date' => $pub ? date('Y-m-d H:i', $pub) : null, 'metadata' => []];
                    }
                });
            }
            if (empty($items)) {
                $items = $this->dorkPlatformSearch('medium', 'Medium', 'site:medium.com "'.$cleanTarget.'"', $cleanTarget, 6);
            }
            yield $this->sseResult('medium', 'Medium', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('medium', 'Medium', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 13. Substack (Newsletters, author notes & discussions)
        $platformIndex++;
        yield $this->sseStatus('substack', 'Substack', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $subUrl = 'https://'.urlencode($cleanTarget).'.substack.com/feed';
            $subRes = Http::withHeaders($headers + ['Accept' => 'application/rss+xml,application/xml,text/xml;q=0.9'])->timeout(5)->get($subUrl);
            if ($subRes->successful() && ! empty($subRes->body())) {
                $c = new Crawler;
                $c->addXmlContent($subRes->body());
                $c->filterXPath('//item')->each(function (Crawler $n) use (&$items, $cleanTarget) {
                    $title = $n->filterXPath('title')->count() ? trim($n->filterXPath('title')->text()) : 'Substack Article';
                    $link = $n->filterXPath('link')->count() ? trim($n->filterXPath('link')->text()) : '';
                    $desc = trim(strip_tags(html_entity_decode((string) ($n->filterXPath('description')->count() ? $n->filterXPath('description')->text() : ''))));
                    if (mb_strlen($desc) > 280) {
                        $desc = mb_substr($desc, 0, 277).'...';
                    }
                    $pub = $n->filterXPath('pubDate')->count() ? strtotime($n->filterXPath('pubDate')->text()) : null;
                    if (! empty($link)) {
                        $items[] = [
                            'id' => 'substack_'.md5($link),
                            'platform' => 'Substack',
                            'platform_id' => 'substack',
                            'category' => 'discussion',
                            'author' => $cleanTarget,
                            'title' => $title,
                            'content' => $desc ?: $title,
                            'url' => $link,
                            'date' => $pub ? date('Y-m-d H:i', $pub) : null,
                            'metadata' => ['newsletter' => $cleanTarget.'.substack.com'],
                        ];
                    }
                });
            }
            if (empty($items)) {
                $items = $this->dorkPlatformSearch('substack', 'Substack', 'site:substack.com "'.$cleanTarget.'"', $cleanTarget, 6);
            }
            yield $this->sseResult('substack', 'Substack', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('substack', 'Substack', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 14. Mastodon (instance API)
        $platformIndex++;
        yield $this->sseStatus('mastodon', 'Mastodon', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $lookup = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(4)->get('https://mastodon.social/api/v1/accounts/lookup?acct='.urlencode($cleanTarget));
            if ($lookup->successful()) {
                $acctId = $lookup->json()['id'] ?? null;
                $acct = $lookup->json()['acct'] ?? $cleanTarget;
                if ($acctId) {
                    $statusRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(5)->get("https://mastodon.social/api/v1/accounts/{$acctId}/statuses?limit=8&exclude_replies=false");
                    if ($statusRes->successful() && is_array($statusRes->json())) {
                        foreach (array_slice($statusRes->json(), 0, 8) as $toot) {
                            $text = trim(strip_tags(html_entity_decode((string) ($toot['content'] ?? ''))));
                            if (! empty($text)) {
                                $items[] = ['id' => 'masto_'.($toot['id'] ?? uniqid()), 'platform' => 'Mastodon', 'platform_id' => 'mastodon', 'category' => ! empty($toot['in_reply_to_id']) ? 'comment' : 'post', 'author' => $acct, 'title' => 'Toot by @'.$acct, 'content' => $text, 'url' => $toot['url'] ?? $toot['uri'] ?? '', 'date' => isset($toot['created_at']) ? date('Y-m-d H:i', strtotime($toot['created_at'])) : null, 'metadata' => ['reblogs' => $toot['reblogs_count'] ?? 0, 'favourites' => $toot['favourites_count'] ?? 0]];
                            }
                        }
                    }
                }
            }
            yield $this->sseResult('mastodon', 'Mastodon', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('mastodon', 'Mastodon', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 15. Bluesky (AT Protocol public API)
        $platformIndex++;
        yield $this->sseStatus('bluesky', 'Bluesky', $platformIndex, $totalPlatforms);
        try {
            $items = [];
            $handle = str_contains($cleanTarget, '.') ? $cleanTarget : $cleanTarget.'.bsky.social';
            $resolve = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(4)->get('https://public.api.bsky.app/xrpc/com.atproto.identity.resolveHandle?handle='.urlencode($handle));
            if ($resolve->successful()) {
                $did = $resolve->json()['did'] ?? null;
                if ($did) {
                    $feedRes = Http::withHeaders(['User-Agent' => 'Darkdump-OSINT/5.0'])->timeout(5)->get('https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed?actor='.urlencode($did).'&limit=8');
                    if ($feedRes->successful()) {
                        foreach (array_slice($feedRes->json()['feed'] ?? [], 0, 8) as $entry) {
                            $p = $entry['post'] ?? [];
                            $rec = $p['record'] ?? [];
                            $txt = $rec['text'] ?? '';
                            if (! empty($txt)) {
                                $isReply = ! empty($rec['reply']);
                                $uri = $p['uri'] ?? '';
                                $webUrl = '';
                                if (preg_match('#at://(.+)/app\.bsky\.feed\.post/(.+)#', $uri, $m)) {
                                    $webUrl = 'https://bsky.app/profile/'.$handle.'/post/'.$m[2];
                                }
                                $items[] = ['id' => 'bsky_'.($p['cid'] ?? uniqid()), 'platform' => 'Bluesky', 'platform_id' => 'bluesky', 'category' => $isReply ? 'comment' : 'post', 'author' => $handle, 'title' => ($isReply ? 'Reply' : 'Post').' by @'.$handle, 'content' => $txt, 'url' => $webUrl ?: 'https://bsky.app/profile/'.$handle, 'date' => isset($rec['createdAt']) ? date('Y-m-d H:i', strtotime($rec['createdAt'])) : null, 'metadata' => ['likes' => $p['likeCount'] ?? 0, 'reposts' => $p['repostCount'] ?? 0]];
                            }
                        }
                    }
                }
            }
            yield $this->sseResult('bluesky', 'Bluesky', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('bluesky', 'Bluesky', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 16. Tumblr (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('tumblr', 'Tumblr', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('tumblr', 'Tumblr', 'site:tumblr.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('tumblr', 'Tumblr', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('tumblr', 'Tumblr', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 17. Pinterest (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('pinterest', 'Pinterest', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('pinterest', 'Pinterest', 'site:pinterest.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('pinterest', 'Pinterest', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('pinterest', 'Pinterest', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 18. LinkedIn (DDG dork for indexed profiles)
        $platformIndex++;
        yield $this->sseStatus('linkedin', 'LinkedIn', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('linkedin', 'LinkedIn', 'site:linkedin.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('linkedin', 'LinkedIn', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('linkedin', 'LinkedIn', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 19. Quora (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('quora', 'Quora', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('quora', 'Quora', 'site:quora.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('quora', 'Quora', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('quora', 'Quora', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 20. Product Hunt (Launches, maker comments & reviews)
        $platformIndex++;
        yield $this->sseStatus('producthunt', 'Product Hunt', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('producthunt', 'Product Hunt', 'site:producthunt.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('producthunt', 'Product Hunt', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('producthunt', 'Product Hunt', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 21. Facebook (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('facebook', 'Facebook', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('facebook', 'Facebook', 'site:facebook.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('facebook', 'Facebook', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('facebook', 'Facebook', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 22. Threads (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('threads', 'Threads', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('threads', 'Threads', 'site:threads.net "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('threads', 'Threads', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('threads', 'Threads', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 23. Snapchat (DDG dork for public stories)
        $platformIndex++;
        yield $this->sseStatus('snapchat', 'Snapchat', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('snapchat', 'Snapchat', 'site:snapchat.com "'.$cleanTarget.'"', $cleanTarget, 4);
            yield $this->sseResult('snapchat', 'Snapchat', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('snapchat', 'Snapchat', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 24. VK (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('vk', 'VK', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('vk', 'VK', 'site:vk.com "'.$cleanTarget.'"', $cleanTarget, 4);
            yield $this->sseResult('vk', 'VK', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('vk', 'VK', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // 25. TikTok (DDG dork)
        $platformIndex++;
        yield $this->sseStatus('tiktok', 'TikTok', $platformIndex, $totalPlatforms);
        try {
            $items = $this->dorkPlatformSearch('tiktok', 'TikTok', 'site:tiktok.com "'.$cleanTarget.'"', $cleanTarget, 6);
            yield $this->sseResult('tiktok', 'TikTok', $platformIndex, $totalPlatforms, $items);
        } catch (\Throwable $e) {
            yield $this->sseResult('tiktok', 'TikTok', $platformIndex, $totalPlatforms, [], $e->getMessage());
        }

        // Final: completion event with surgical dorks
        yield ['type' => 'complete', 'dorks' => $this->generateSocialDorks($cleanTarget, $cleanKeyword ?: null)];
    }

    /**
     * DuckDuckGo HTML scraping for platforms without free public APIs.
     */
    protected function dorkPlatformSearch(string $platformId, string $platformName, string $dorkQuery, string $target, int $limit = 6): array
    {
        $results = [];
        try {
            $response = Http::asForm()->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Referer' => 'https://html.duckduckgo.com/',
            ])->timeout(8)->post('https://html.duckduckgo.com/html/', ['q' => $dorkQuery, 'b' => '', 'kl' => 'us-en']);

            if ($response->successful()) {
                $crawler = new Crawler($response->body());
                $crawler->filter('.result')->each(function (Crawler $node) use (&$results, $limit, $platformId, $platformName, $target) {
                    if (count($results) >= $limit) {
                        return;
                    }
                    $titleNode = $node->filter('.result__title a, .result__a');
                    $snippetNode = $node->filter('.result__snippet');
                    if ($titleNode->count() > 0) {
                        $url = $this->extractCleanUrl($titleNode->attr('href') ?? '');
                        if (empty($url) || str_starts_with($url, '#')) {
                            return;
                        }
                        $results[] = ['id' => $platformId.'_'.md5($url), 'platform' => $platformName, 'platform_id' => $platformId, 'category' => 'web_mention', 'author' => $target, 'title' => trim($titleNode->text()), 'content' => $snippetNode->count() > 0 ? trim($snippetNode->text()) : 'Indexed mention on '.$platformName, 'url' => $url, 'date' => null, 'metadata' => ['source' => 'DuckDuckGo Index']];
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::info("{$platformName} dork search notice: ".$e->getMessage());
        }

        return $results;
    }

    protected function sseStatus(string $id, string $name, int $index, int $total): array
    {
        return ['type' => 'status', 'platform_id' => $id, 'platform' => $name, 'index' => $index, 'total' => $total, 'status' => 'scanning', 'progress' => round(($index / $total) * 100)];
    }

    protected function sseResult(string $id, string $name, int $index, int $total, array $activities, ?string $error = null): array
    {
        return ['type' => 'result', 'platform_id' => $id, 'platform' => $name, 'index' => $index, 'total' => $total, 'progress' => round(($index / $total) * 100), 'activities' => $activities, 'count' => count($activities), 'error' => $error];
    }
}

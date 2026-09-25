<?php

namespace App\Services\Osint;

use App\Services\Security\SafeUrlValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Log;

class PlatformEnumerationService
{
    /**
     * Outbound concurrency limit for asynchronous probe pool.
     */
    public const CONCURRENCY_LIMIT = 12;

    /**
     * Socket and transfer timeout per probe (in seconds).
     */
    public const TIMEOUT_SECONDS = 3.5;

    /**
     * Connect timeout per probe (in seconds).
     */
    public const CONNECT_TIMEOUT_SECONDS = 2.0;

    public function __construct(
        protected SafeUrlValidator $safeUrlValidator
    ) {}

    /**
     * Get platform category metadata for UI filters.
     *
     * @return array<string, array{id: string, label: string, icon: string}>
     */
    public function getCategories(): array
    {
        return [
            'all' => ['id' => 'all', 'label' => 'All Platforms', 'icon' => 'Layers'],
            'code' => ['id' => 'code', 'label' => 'Code & Dev', 'icon' => 'FolderGit2'],
            'social' => ['id' => 'social', 'label' => 'Social Networks', 'icon' => 'Share2'],
            'gaming' => ['id' => 'gaming', 'label' => 'Gaming', 'icon' => 'Gamepad2'],
            'tech' => ['id' => 'tech', 'label' => 'Tech & Blogs', 'icon' => 'Terminal'],
            'forums' => ['id' => 'forums', 'label' => 'Forums & Discussion', 'icon' => 'MessageSquare'],
            'media' => ['id' => 'media', 'label' => 'Media & Audio', 'icon' => 'Music'],
        ];
    }

    /**
     * Retrieve target platforms, optionally filtered by category.
     *
     * @param  array<string>|null  $categoryFilters
     * @return array<int, array<string, mixed>>
     */
    public function getPlatforms(?array $categoryFilters = null): array
    {
        $all = $this->platformCatalog();

        if (empty($categoryFilters) || in_array('all', $categoryFilters, true)) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn (array $p) => in_array($p['category'], $categoryFilters, true)
        ));
    }

    /**
     * Build the human-facing profile URL.
     */
    public function buildProfileUrl(array $platform, string $username): string
    {
        return str_replace('{username}', rawurlencode($username), $platform['url_template']);
    }

    /**
     * Build the network probe URL (may be a dedicated API endpoint or HTML page).
     */
    public function buildCheckUrl(array $platform, string $username): string
    {
        $template = $platform['check_url_template'] ?? $platform['url_template'];

        return str_replace('{username}', rawurlencode($username), $template);
    }

    /**
     * Execute live asynchronous probing of platforms with SSE callbacks.
     *
     * @param  array<string>|null  $categories
     * @param  callable(array<string, mixed>): void  $onHit
     * @param  callable(array<string, mixed>): void  $onProgress
     * @param  callable(): bool|null  $shouldAbort
     * @return array{total_probed: int, total_found: int, duration_ms: int, hits: array<int, array<string, mixed>>}
     */
    public function streamEnumeration(
        string $username,
        ?array $categories,
        callable $onHit,
        callable $onProgress,
        ?callable $shouldAbort = null
    ): array {
        $startTime = microtime(true);
        $platforms = $this->getPlatforms($categories);
        $totalPlatforms = count($platforms);

        $hits = [];
        $probedCount = 0;
        $foundCount = 0;

        $client = new Client([
            'timeout' => self::TIMEOUT_SECONDS,
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 3,
                'strict' => false,
                'referer' => true,
                'protocols' => ['https', 'http'],
            ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.7',
            ],
        ]);

        $requests = function () use ($platforms, $username) {
            foreach ($platforms as $index => $platform) {
                $checkUrl = $this->buildCheckUrl($platform, $username);

                // SSRF Protection Gate
                if (! $this->safeUrlValidator->isSafeUrl($checkUrl, requireHttps: false)) {
                    continue;
                }

                $method = $platform['check_method'] ?? 'GET';
                yield $index => new GuzzleRequest($method, $checkUrl);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => self::CONCURRENCY_LIMIT,
            'fulfilled' => function (GuzzleResponse $response, $index) use (
                $platforms,
                $username,
                $startTime,
                &$hits,
                &$probedCount,
                &$foundCount,
                $totalPlatforms,
                $onHit,
                $onProgress,
                $shouldAbort
            ) {
                if ($shouldAbort && $shouldAbort()) {
                    return;
                }

                $platform = $platforms[$index] ?? null;
                if (! $platform) {
                    return;
                }

                $probedCount++;
                $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
                $isFound = $this->evaluateResponse($platform, $response);

                if ($isFound) {
                    $foundCount++;
                    $profileUrl = $this->buildProfileUrl($platform, $username);

                    $hitPayload = [
                        'platform_id' => $platform['id'],
                        'platform' => $platform['name'],
                        'category' => $platform['category'],
                        'url' => $profileUrl,
                        'status' => 'found',
                        'response_time_ms' => $elapsedMs,
                        'confidence' => 1.0,
                        'icon' => $platform['icon'] ?? 'Globe',
                        'badge_color' => $platform['badge_color'] ?? 'teal',
                    ];

                    $hits[] = $hitPayload;
                    $onHit($hitPayload);
                }

                $onProgress([
                    'probed' => $probedCount,
                    'total' => $totalPlatforms,
                    'found' => $foundCount,
                    'percent' => $totalPlatforms > 0 ? (int) round(($probedCount / $totalPlatforms) * 100) : 100,
                    'last_platform' => $platform['name'],
                    'last_status' => $isFound ? 'found' : 'not_found',
                ]);
            },
            'rejected' => function (RequestException|\Throwable $reason, $index) use (
                $platforms,
                &$probedCount,
                &$foundCount,
                $totalPlatforms,
                $onProgress,
                $shouldAbort
            ) {
                if ($shouldAbort && $shouldAbort()) {
                    return;
                }

                $platform = $platforms[$index] ?? null;
                $probedCount++;

                $onProgress([
                    'probed' => $probedCount,
                    'total' => $totalPlatforms,
                    'found' => $foundCount,
                    'percent' => $totalPlatforms > 0 ? (int) round(($probedCount / $totalPlatforms) * 100) : 100,
                    'last_platform' => $platform ? $platform['name'] : 'Unknown',
                    'last_status' => 'error',
                ]);
            },
        ]);

        try {
            $promise = $pool->promise();
            $promise->wait();
        } catch (\Throwable $e) {
            Log::warning("Platform enumeration pool terminated with exception: {$e->getMessage()}");
        }

        $totalDurationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'total_probed' => $probedCount,
            'total_found' => $foundCount,
            'duration_ms' => $totalDurationMs,
            'hits' => $hits,
        ];
    }

    /**
     * Evaluate if the HTTP response confirms a positive profile match.
     */
    protected function evaluateResponse(array $platform, GuzzleResponse $response): bool
    {
        $statusCode = $response->getStatusCode();
        $expectedStatus = $platform['expected_status'] ?? 200;

        // Immediate status code mismatch
        if ($statusCode !== $expectedStatus) {
            return false;
        }

        // Check if an error string is present in body (soft-404)
        if (! empty($platform['error_string'])) {
            $body = (string) $response->getBody();
            if (stripos($body, $platform['error_string']) !== false) {
                return false;
            }
        }

        // Check if a required confirmation string must be present
        if (! empty($platform['must_contain'])) {
            $body = (string) $response->getBody();
            if (stripos($body, $platform['must_contain']) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Curated catalog of ~75 global services with deterministic detection heuristics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function platformCatalog(): array
    {
        return [
            // ==================== CODE & DEVELOPER (14) ====================
            [
                'id' => 'github',
                'name' => 'GitHub',
                'category' => 'code',
                'url_template' => 'https://github.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Not Found',
                'icon' => 'FolderGit2',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'gitlab',
                'name' => 'GitLab',
                'category' => 'code',
                'url_template' => 'https://gitlab.com/{username}',
                'expected_status' => 200,
                'error_string' => 'The page you\'re looking for could not be found',
                'icon' => 'FolderGit2',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'bitbucket',
                'name' => 'Bitbucket',
                'category' => 'code',
                'url_template' => 'https://bitbucket.org/{username}/',
                'expected_status' => 200,
                'error_string' => 'Resource not found',
                'icon' => 'FolderGit2',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'dockerhub',
                'name' => 'Docker Hub',
                'category' => 'code',
                'url_template' => 'https://hub.docker.com/u/{username}',
                'check_url_template' => 'https://hub.docker.com/v2/users/{username}/',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'npm',
                'name' => 'NPM',
                'category' => 'code',
                'url_template' => 'https://www.npmjs.com/~{username}',
                'expected_status' => 200,
                'error_string' => 'npm notice 404',
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'pypi',
                'name' => 'PyPI',
                'category' => 'code',
                'url_template' => 'https://pypi.org/user/{username}/',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'replit',
                'name' => 'Replit',
                'category' => 'code',
                'url_template' => 'https://replit.com/@{username}',
                'expected_status' => 200,
                'error_string' => '404 - Page not found',
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'kaggle',
                'name' => 'Kaggle',
                'category' => 'code',
                'url_template' => 'https://www.kaggle.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Page Not Found',
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'codeforces',
                'name' => 'Codeforces',
                'category' => 'code',
                'url_template' => 'https://codeforces.com/profile/{username}',
                'expected_status' => 200,
                'error_string' => 'No such user',
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'hackerrank',
                'name' => 'HackerRank',
                'category' => 'code',
                'url_template' => 'https://www.hackerrank.com/profile/{username}',
                'check_url_template' => 'https://www.hackerrank.com/rest/contests/master/hackers/{username}/profile',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'leetcode',
                'name' => 'LeetCode',
                'category' => 'code',
                'url_template' => 'https://leetcode.com/u/{username}/',
                'expected_status' => 200,
                'error_string' => 'User not found',
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'codecademy',
                'name' => 'Codecademy',
                'category' => 'code',
                'url_template' => 'https://www.codecademy.com/profiles/{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'sourceforge',
                'name' => 'SourceForge',
                'category' => 'code',
                'url_template' => 'https://sourceforge.net/u/{username}/',
                'expected_status' => 200,
                'icon' => 'FolderGit2',
                'badge_color' => 'teal',
            ],
            [
                'id' => 'packagist',
                'name' => 'Packagist',
                'category' => 'code',
                'url_template' => 'https://packagist.org/users/{username}/',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'teal',
            ],

            // ==================== SOCIAL NETWORKS (15) ====================
            [
                'id' => 'reddit',
                'name' => 'Reddit',
                'category' => 'social',
                'url_template' => 'https://www.reddit.com/user/{username}',
                'check_url_template' => 'https://www.reddit.com/user/{username}/about.json',
                'expected_status' => 200,
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'pinterest',
                'name' => 'Pinterest',
                'category' => 'social',
                'url_template' => 'https://www.pinterest.com/{username}/',
                'expected_status' => 200,
                'error_string' => 'User not found',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'mastodon',
                'name' => 'Mastodon (Social)',
                'category' => 'social',
                'url_template' => 'https://mastodon.social/@{username}',
                'expected_status' => 200,
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'bluesky',
                'name' => 'Bluesky',
                'category' => 'social',
                'url_template' => 'https://bsky.app/profile/{username}.bsky.social',
                'check_url_template' => 'https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor={username}.bsky.social',
                'expected_status' => 200,
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'telegram',
                'name' => 'Telegram',
                'category' => 'social',
                'url_template' => 'https://t.me/{username}',
                'expected_status' => 200,
                'error_string' => 'If you have Telegram, you can contact',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'vk',
                'name' => 'VK',
                'category' => 'social',
                'url_template' => 'https://vk.com/{username}',
                'expected_status' => 200,
                'error_string' => 'This page has been deleted or has not been created yet',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'tumblr',
                'name' => 'Tumblr',
                'category' => 'social',
                'url_template' => 'https://{username}.tumblr.com',
                'expected_status' => 200,
                'error_string' => 'There\'s nothing here.',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'deviantart',
                'name' => 'DeviantArt',
                'category' => 'social',
                'url_template' => 'https://www.deviantart.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Deactivated Account',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'flickr',
                'name' => 'Flickr',
                'category' => 'social',
                'url_template' => 'https://www.flickr.com/people/{username}',
                'expected_status' => 200,
                'error_string' => 'Page Not Found',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'linktree',
                'name' => 'Linktree',
                'category' => 'social',
                'url_template' => 'https://linktr.ee/{username}',
                'expected_status' => 200,
                'error_string' => 'The page you’re looking for doesn’t exist',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'aboutme',
                'name' => 'About.me',
                'category' => 'social',
                'url_template' => 'https://about.me/{username}',
                'expected_status' => 200,
                'error_string' => 'Page not found',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'behance',
                'name' => 'Behance',
                'category' => 'social',
                'url_template' => 'https://www.behance.net/{username}',
                'expected_status' => 200,
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'dribbble',
                'name' => 'Dribbble',
                'category' => 'social',
                'url_template' => 'https://dribbble.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Whoops, that page is gone',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'keybase',
                'name' => 'Keybase',
                'category' => 'social',
                'url_template' => 'https://keybase.io/{username}',
                'expected_status' => 200,
                'error_string' => '404 - Not Found',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],
            [
                'id' => 'gravatar',
                'name' => 'Gravatar',
                'category' => 'social',
                'url_template' => 'https://en.gravatar.com/{username}',
                'expected_status' => 200,
                'error_string' => 'User not found',
                'icon' => 'Share2',
                'badge_color' => 'cyan',
            ],

            // ==================== TECH & BLOGS (12) ====================
            [
                'id' => 'hackernews',
                'name' => 'HackerNews',
                'category' => 'tech',
                'url_template' => 'https://news.ycombinator.com/user?id={username}',
                'expected_status' => 200,
                'error_string' => 'No such user',
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'devto',
                'name' => 'DEV Community',
                'category' => 'tech',
                'url_template' => 'https://dev.to/{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'medium',
                'name' => 'Medium',
                'category' => 'tech',
                'url_template' => 'https://medium.com/@{username}',
                'expected_status' => 200,
                'error_string' => 'PAGE NOT FOUND',
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'substack',
                'name' => 'Substack',
                'category' => 'tech',
                'url_template' => 'https://{username}.substack.com',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'producthunt',
                'name' => 'ProductHunt',
                'category' => 'tech',
                'url_template' => 'https://www.producthunt.com/@{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'hashnode',
                'name' => 'Hashnode',
                'category' => 'tech',
                'url_template' => 'https://hashnode.com/@{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'buymeacoffee',
                'name' => 'BuyMeACoffee',
                'category' => 'tech',
                'url_template' => 'https://www.buymeacoffee.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Page not found',
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'kofi',
                'name' => 'Ko-fi',
                'category' => 'tech',
                'url_template' => 'https://ko-fi.com/{username}',
                'expected_status' => 200,
                'error_string' => 'Page Not Found',
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'patreon',
                'name' => 'Patreon',
                'category' => 'tech',
                'url_template' => 'https://www.patreon.com/{username}',
                'expected_status' => 200,
                'error_string' => 'This page is unavailable',
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'disqus',
                'name' => 'Disqus',
                'category' => 'tech',
                'url_template' => 'https://disqus.com/by/{username}/',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'freelancer',
                'name' => 'Freelancer',
                'category' => 'tech',
                'url_template' => 'https://www.freelancer.com/u/{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],
            [
                'id' => 'fiverr',
                'name' => 'Fiverr',
                'category' => 'tech',
                'url_template' => 'https://www.fiverr.com/{username}',
                'expected_status' => 200,
                'icon' => 'Terminal',
                'badge_color' => 'purple',
            ],

            // ==================== GAMING (12) ====================
            [
                'id' => 'steam',
                'name' => 'Steam Community',
                'category' => 'gaming',
                'url_template' => 'https://steamcommunity.com/id/{username}',
                'expected_status' => 200,
                'error_string' => 'The specified profile could not be found',
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'chess_com',
                'name' => 'Chess.com',
                'category' => 'gaming',
                'url_template' => 'https://www.chess.com/member/{username}',
                'check_url_template' => 'https://api.chess.com/pub/player/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'lichess',
                'name' => 'Lichess',
                'category' => 'gaming',
                'url_template' => 'https://lichess.org/@/{username}',
                'check_url_template' => 'https://lichess.org/api/user/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'roblox',
                'name' => 'Roblox',
                'category' => 'gaming',
                'url_template' => 'https://www.roblox.com/user.aspx?username={username}',
                'expected_status' => 200,
                'error_string' => 'Page cannot be found or no longer exists',
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'twitch',
                'name' => 'Twitch',
                'category' => 'gaming',
                'url_template' => 'https://www.twitch.tv/{username}',
                'expected_status' => 200,
                'error_string' => 'Unless you’ve got a time machine, that content is unavailable',
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'speedrun',
                'name' => 'Speedrun.com',
                'category' => 'gaming',
                'url_template' => 'https://www.speedrun.com/user/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'osu',
                'name' => 'osu!',
                'category' => 'gaming',
                'url_template' => 'https://osu.ppy.sh/users/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'itchio',
                'name' => 'Itch.io',
                'category' => 'gaming',
                'url_template' => 'https://{username}.itch.io',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'newgrounds',
                'name' => 'Newgrounds',
                'category' => 'gaming',
                'url_template' => 'https://{username}.newgrounds.com',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'kongregate',
                'name' => 'Kongregate',
                'category' => 'gaming',
                'url_template' => 'https://www.kongregate.com/accounts/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'geocaching',
                'name' => 'Geocaching',
                'category' => 'gaming',
                'url_template' => 'https://www.geocaching.com/p/default.aspx?u={username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],
            [
                'id' => 'minecraft',
                'name' => 'Minecraft (Mojang)',
                'category' => 'gaming',
                'url_template' => 'https://namemc.com/profile/{username}',
                'check_url_template' => 'https://api.mojang.com/users/profiles/minecraft/{username}',
                'expected_status' => 200,
                'icon' => 'Gamepad2',
                'badge_color' => 'emerald',
            ],

            // ==================== FORUMS & DISCUSSION (11) ====================
            [
                'id' => 'pastebin',
                'name' => 'Pastebin',
                'category' => 'forums',
                'url_template' => 'https://pastebin.com/u/{username}',
                'expected_status' => 200,
                'error_string' => 'Not Found',
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'wikipedia',
                'name' => 'Wikipedia User',
                'category' => 'forums',
                'url_template' => 'https://en.wikipedia.org/wiki/User:{username}',
                'expected_status' => 200,
                'error_string' => 'Wikipedia does not have a user page with this exact title',
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'archiveorg',
                'name' => 'Archive.org',
                'category' => 'forums',
                'url_template' => 'https://archive.org/details/@{username}',
                'expected_status' => 200,
                'error_string' => 'cannot find account',
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'tripadvisor',
                'name' => 'Tripadvisor',
                'category' => 'forums',
                'url_template' => 'https://www.tripadvisor.com/Profile/{username}',
                'expected_status' => 200,
                'error_string' => 'This page could not be found',
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'goodreads',
                'name' => 'Goodreads',
                'category' => 'forums',
                'url_template' => 'https://www.goodreads.com/{username}',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'quora',
                'name' => 'Quora',
                'category' => 'forums',
                'url_template' => 'https://www.quora.com/profile/{username}',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'hackthebox',
                'name' => 'Hack The Box',
                'category' => 'forums',
                'url_template' => 'https://app.hackthebox.com/users/{username}',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'tryhackme',
                'name' => 'TryHackMe',
                'category' => 'forums',
                'url_template' => 'https://tryhackme.com/p/{username}',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'slashdot',
                'name' => 'Slashdot',
                'category' => 'forums',
                'url_template' => 'https://slashdot.org/~{username}',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'instructables',
                'name' => 'Instructables',
                'category' => 'forums',
                'url_template' => 'https://www.instructables.com/member/{username}/',
                'expected_status' => 200,
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],
            [
                'id' => 'pgp_mit',
                'name' => 'MIT PGP Keyserver',
                'category' => 'forums',
                'url_template' => 'https://pgp.mit.edu/pks/lookup?search={username}&op=index',
                'expected_status' => 200,
                'error_string' => 'No keys found',
                'icon' => 'MessageSquare',
                'badge_color' => 'amber',
            ],

            // ==================== MEDIA & AUDIO (11) ====================
            [
                'id' => 'spotify',
                'name' => 'Spotify',
                'category' => 'media',
                'url_template' => 'https://open.spotify.com/user/{username}',
                'expected_status' => 200,
                'error_string' => 'Page not found',
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'soundcloud',
                'name' => 'SoundCloud',
                'category' => 'media',
                'url_template' => 'https://soundcloud.com/{username}',
                'expected_status' => 200,
                'error_string' => 'We can’t find that user',
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'vimeo',
                'name' => 'Vimeo',
                'category' => 'media',
                'url_template' => 'https://vimeo.com/{username}',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'bandcamp',
                'name' => 'Bandcamp',
                'category' => 'media',
                'url_template' => 'https://{username}.bandcamp.com',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'mixcloud',
                'name' => 'Mixcloud',
                'category' => 'media',
                'url_template' => 'https://www.mixcloud.com/{username}/',
                'expected_status' => 200,
                'error_string' => 'Page Not Found',
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'lastfm',
                'name' => 'Last.fm',
                'category' => 'media',
                'url_template' => 'https://www.last.fm/user/{username}',
                'expected_status' => 200,
                'error_string' => 'User not found',
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'letterboxd',
                'name' => 'Letterboxd',
                'category' => 'media',
                'url_template' => 'https://letterboxd.com/{username}/',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'trakttv',
                'name' => 'Trakt.tv',
                'category' => 'media',
                'url_template' => 'https://trakt.tv/users/{username}',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => '500px',
                'name' => '500px',
                'category' => 'media',
                'url_template' => 'https://500px.com/p/{username}',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'dailymotion',
                'name' => 'DailyMotion',
                'category' => 'media',
                'url_template' => 'https://www.dailymotion.com/{username}',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
            [
                'id' => 'youtube',
                'name' => 'YouTube',
                'category' => 'media',
                'url_template' => 'https://www.youtube.com/@{username}',
                'expected_status' => 200,
                'icon' => 'Music',
                'badge_color' => 'rose',
            ],
        ];
    }
}

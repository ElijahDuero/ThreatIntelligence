<?php

namespace App\Services\DarkWeb;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SearchEngineManager
{
    public function __construct(
        protected TorClient $torClient,
        protected AhmiaBlacklistService $blacklistService,
    ) {}

    public function getAvailableEngines(): array
    {
        return [
            'onionfind' => [
                'name' => 'OnionFind',
                'description' => 'Unfiltered dark web search engine (fast clearnet gateway + onion mirror)',
                'requires_tor' => false,
                'filtered' => false,
                'default' => false,
            ],
            'vormweb' => [
                'name' => 'VormWeb',
                'description' => 'Unfiltered European & global dark web crawler (clearnet gateway + onion mirror)',
                'requires_tor' => false,
                'filtered' => false,
                'default' => false,
            ],
            'tordex' => [
                'name' => 'TorDex',
                'description' => 'Unfiltered deep web index & directory (clearnet mirror + onion)',
                'requires_tor' => false,
                'filtered' => false,
                'default' => false,
            ],
            'onionland' => [
                'name' => 'OnionLand',
                'description' => 'Comprehensive dark web crawler with minimal filtering (Tor + I2P)',
                'requires_tor' => false,
                'filtered' => false,
                'default' => false,
            ],
            'ahmia' => [
                'name' => 'Ahmia',
                'description' => 'Filtered dark web index with automated CSAM/abuse blacklisting',
                'requires_tor' => false,
                'filtered' => true,
                'default' => false,
            ],
            'duckduckgo' => [
                'name' => 'DuckDuckGo',
                'description' => 'Clearnet, filtered, ultra-fast index (no Tor needed)',
                'requires_tor' => false,
                'filtered' => true,
                'default' => true,
            ],
        ];
    }

    public function search(string $query, int $amount = 15, string $engine = 'duckduckgo', bool $useTor = false): array
    {
        $engine = strtolower($engine);
        $results = [];

        // Check if user requested Tor but Tor socket is not active
        $torActive = $this->torClient->isTorAvailable();
        $effectiveTor = $useTor && $torActive;

        try {
            switch ($engine) {
                case 'onionfind':
                    $results = $this->searchOnionFind($query, $amount, $effectiveTor);
                    break;
                case 'vormweb':
                    $results = $this->searchVormWeb($query, $amount, $effectiveTor);
                    break;
                case 'tordex':
                    $results = $this->searchTorDex($query, $amount, $effectiveTor);
                    break;
                case 'onionland':
                    $results = $this->searchOnionLand($query, $amount, $effectiveTor);
                    break;
                case 'ahmia':
                    $results = $this->searchAhmia($query, $amount, $effectiveTor);
                    break;
                case 'duckduckgo':
                default:
                    $results = $this->searchDuckDuckGo($query, $amount);
                    break;
            }
        } catch (\Throwable $e) {
            Log::warning("Search attempt failed for [{$engine}]: ".$e->getMessage());

            // If an unfiltered engine failed, fall back to another unfiltered engine first
            if (in_array($engine, ['onionfind', 'vormweb', 'tordex', 'onionland'])) {
                try {
                    $fallback = ($engine === 'onionfind') ? 'tordex' : 'onionfind';
                    $results = ($fallback === 'tordex')
                        ? $this->searchTorDex($query, $amount, false)
                        : $this->searchOnionFind($query, $amount, false);
                } catch (\Throwable $fbErr) {
                    Log::warning('Secondary unfiltered fallback failed: '.$fbErr->getMessage());
                }
            }

            // Ultimate fallback to DuckDuckGo if still empty
            if (empty($results) && $engine !== 'duckduckgo') {
                try {
                    $results = $this->searchDuckDuckGo($query, $amount);
                } catch (\Throwable $ddgErr) {
                    Log::error('Ultimate fallback failed: '.$ddgErr->getMessage());
                }
            }
        }

        // Post-process results: Blacklist check & onion detection
        foreach ($results as &$res) {
            $url = $res['url'] ?? '';
            $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $url);
            $res['is_onion'] = $isOnion;
            $res['is_blacklisted'] = $isOnion ? $this->blacklistService->isBlacklisted($url) : false;
        }

        return array_slice($results, 0, $amount);
    }

    /**
     * Search OnionFind (Unfiltered)
     */
    protected function searchOnionFind(string $query, int $amount, bool $useTor = false): array
    {
        $url = $useTor
            ? 'http://ofinde3b67voi7xiq3qflof2mwriwngicd7glwvf3bclgdgcjfozlzqd.onion/search?q='.urlencode($query)
            : 'https://onionfind.com/search?q='.urlencode($query);

        $client = $this->torClient->getHttpClient($useTor, 20);
        $response = $client->get($url);
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.result-item, .result, div[class*="result"]')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

            $titleNode = $node->filter('.result-title, h4 a, h3 a, a[href*=".onion"]');
            $descNode = $node->filter('.result-description, p, .snippet');

            if ($titleNode->count() > 0) {
                $rawUrl = trim($titleNode->attr('href') ?? '');
                $title = trim($titleNode->text());
                $description = $descNode->count() > 0 ? trim($descNode->text()) : '';

                if (! empty($rawUrl)) {
                    if (! preg_match('#^https?://#i', $rawUrl)) {
                        $rawUrl = 'http://'.$rawUrl;
                    }
                    $results[] = [
                        'idx' => count($results) + 1,
                        'title' => $title ?: 'OnionFind Indexed Target',
                        'url' => $rawUrl,
                        'description' => $description,
                        'engine' => 'OnionFind',
                    ];
                }
            }
        });

        // Fallback: search all .onion links if container parser missed
        if (empty($results)) {
            $crawler->filter('a')->each(function (Crawler $node) use (&$results, $amount) {
                if (count($results) >= $amount) {
                    return;
                }
                $href = $node->attr('href') ?? '';
                if (str_contains($href, '.onion') && ! str_contains($href, 'onionfind')) {
                    $results[] = [
                        'idx' => count($results) + 1,
                        'title' => trim($node->text()) ?: 'Onion Node',
                        'url' => str_starts_with($href, 'http') ? $href : 'http://'.$href,
                        'description' => 'Indexed via OnionFind Dark Web Crawler',
                        'engine' => 'OnionFind',
                    ];
                }
            });
        }

        return $results;
    }

    /**
     * Search VormWeb (Unfiltered)
     */
    protected function searchVormWeb(string $query, int $amount, bool $useTor = false): array
    {
        $url = $useTor
            ? 'http://volkancfgpi4c7ghph6id2t7vcntenuly66qjt6oedwtjmyj4tkk5oqd.onion/en/search?q='.urlencode($query)
            : 'https://vormweb.de/en/search?q='.urlencode($query);

        $client = $this->torClient->getHttpClient($useTor, 20);
        $response = $client->get($url);
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.query-box, .result, .item')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

            $linkNode = $node->filter('a#urllink, h4 a, h3 a, a');
            $descNode = $node->filter('li i, p, .snippet');

            if ($linkNode->count() > 0) {
                $rawUrl = trim($linkNode->attr('href') ?? '');
                $title = trim($linkNode->text());
                $description = $descNode->count() > 0 ? trim($descNode->text()) : '';

                if (! empty($rawUrl)) {
                    if (! preg_match('#^https?://#i', $rawUrl)) {
                        $rawUrl = 'http://'.$rawUrl;
                    }
                    $results[] = [
                        'idx' => count($results) + 1,
                        'title' => $title ?: 'VormWeb Darknet Target',
                        'url' => $rawUrl,
                        'description' => $description,
                        'engine' => 'VormWeb',
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Search TorDex (Unfiltered)
     */
    protected function searchTorDex(string $query, int $amount, bool $useTor = false): array
    {
        // TorDex uses ?query={query}
        $url = $useTor
            ? 'http://tordexpmg4xy32rfp4ovnz7zq5ujoejwq2u26uxxtkscgo5u3losmeid.onion/search?query='.urlencode($query)
            : 'https://tordex.cc/search?query='.urlencode($query);

        $client = $this->torClient->getHttpClient($useTor, 20);
        $response = $client->get($url);
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);
        $results = [];

        // Primary TorDex selector: .container h5 a or .search-result
        $crawler->filter('.container h5 a, .search-result a, a.title, h3 a')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

            $rawUrl = trim($node->attr('href') ?? '');
            $title = trim($node->text());

            if (! empty($rawUrl)) {
                if (! preg_match('#^https?://#i', $rawUrl)) {
                    $rawUrl = 'http://'.$rawUrl;
                }

                $results[] = [
                    'idx' => count($results) + 1,
                    'title' => $title ?: 'TorDex Indexed Finding',
                    'url' => $rawUrl,
                    'description' => 'Unfiltered dark web finding via TorDex index.',
                    'engine' => 'TorDex',
                ];
            }
        });

        return $results;
    }

    /**
     * Search OnionLand (Unfiltered)
     */
    protected function searchOnionLand(string $query, int $amount, bool $useTor = false): array
    {
        $url = $useTor
            ? 'http://3bbad7fauom4d6sgppalyqddsqbf5u5p56b5k5uk2zxsy3d6ey2jobad.onion/search?q='.urlencode($query)
            : 'https://onionlandsearchengine.com/search?q='.urlencode($query);

        $client = $this->torClient->getHttpClient($useTor, 20);
        $response = $client->get($url);
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.result-block, .result, .search-result')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

            $titleNode = $node->filter('.title a, h3 a, a');
            $descNode = $node->filter('p, .snippet');

            if ($titleNode->count() > 0) {
                $rawHref = $titleNode->attr('href') ?? '';
                $cleanUrl = $this->extractCleanUrl($rawHref);

                $results[] = [
                    'idx' => count($results) + 1,
                    'title' => trim($titleNode->text()) ?: 'OnionLand Result',
                    'url' => $cleanUrl ?: $rawHref,
                    'description' => $descNode->count() > 0 ? trim($descNode->text()) : '',
                    'engine' => 'OnionLand',
                ];
            }
        });

        return $results;
    }

    /**
     * Search DuckDuckGo (Filtered)
     */
    protected function searchDuckDuckGo(string $query, int $amount): array
    {
        $client = $this->torClient->getHttpClient(false, 15);
        $response = $client->post('https://html.duckduckgo.com/html/', [
            'form_params' => [
                'q' => $query,
                'b' => '',
                'kl' => 'us-en',
            ],
            'headers' => [
                'Referer' => 'https://html.duckduckgo.com/',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $html = (string) $response->getBody();
        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.result')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

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
                $description = $snippetNode->count() > 0 ? trim($snippetNode->text()) : '';

                if (! empty($url) && ! empty($title)) {
                    $results[] = [
                        'idx' => count($results) + 1,
                        'title' => $title,
                        'url' => $url,
                        'description' => $description,
                        'engine' => 'DuckDuckGo',
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Search Ahmia (Filtered)
     */
    protected function searchAhmia(string $query, int $amount, bool $useTor = false): array
    {
        $url = $useTor
            ? 'http://juhanurmihxlp77nkq76byazcldy2hlmovfu2epvl5ankdibsot4csyd.onion/search/?q='.urlencode($query)
            : 'https://ahmia.fi/search/?q='.urlencode($query);

        $client = $this->torClient->getHttpClient($useTor, 25);
        $response = $client->get($url);
        $html = (string) $response->getBody();

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('li.result')->each(function (Crawler $node) use (&$results, $amount) {
            if (count($results) >= $amount) {
                return;
            }

            $linkNode = $node->filter('h4 a');
            $descNode = $node->filter('p');

            if ($linkNode->count() > 0) {
                $href = $linkNode->attr('href');
                $title = trim($linkNode->text());
                $desc = $descNode->count() > 0 ? trim($descNode->text()) : '';

                $parsedUrl = $this->extractAhmiaRedirect($href);

                $results[] = [
                    'idx' => count($results) + 1,
                    'title' => $title ?: 'Untitled Onion Site',
                    'url' => $parsedUrl ?: $href,
                    'description' => $desc,
                    'engine' => 'Ahmia',
                ];
            }
        });

        return $results;
    }

    protected function extractQueryParam(string $href, string $paramName): ?string
    {
        if (str_contains($href, $paramName.'=')) {
            parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $queryParams);
            if (! empty($queryParams[$paramName])) {
                return urldecode((string) $queryParams[$paramName]);
            }
        }

        return null;
    }

    protected function extractCleanUrl(string $href): string
    {
        if (empty($href)) {
            return '';
        }

        return $this->extractQueryParam($href, 'uddg') ?? $href;
    }

    protected function extractAhmiaRedirect(string $href): string
    {
        return $this->extractQueryParam($href, 'redirect_url') ?? $href;
    }
}

<?php

namespace App\Services\DarkWeb;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class DeepScraperService
{
    protected TorClient $torClient;

    protected AhmiaBlacklistService $blacklistService;

    /**
     * Complete list of document and intelligence file extensions from original darkdump.py
     */
    protected array $documentExtensions = [
        // Office Documents & Text
        'pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx',
        'txt', 'csv', 'rtf', 'odt', 'ods', 'odp', 'epub',
        'mobi', 'log', 'msg', 'wpd', 'wps', 'tex', 'vsd',
        'xml', 'json', 'xps', 'md', 'code',
        // Databases & Keystores
        'sql', 'db', 'sqlite', 'kdbx',
        // Executables & Binaries
        'dll', 'exe', 'apk', 'bin', 'dmg', 'iso', 'vmdk', 'img',
        // Compressed Archives
        'zip', 'tar', 'gz', 'rar', '7z', 'bz2',
        // Media Audio & Video Artifacts
        'mp3', 'wav', 'mp4', 'avi', 'mov', 'flv', 'wma', 'aac',
    ];

    /**
     * Standard 179 English stopwords matching NLTK corpus used in original darkdump.py
     */
    protected array $stopWords = [
        'i' => true, 'me' => true, 'my' => true, 'myself' => true, 'we' => true,
        'our' => true, 'ours' => true, 'ourselves' => true, 'you' => true, 'your' => true,
        'yours' => true, 'yourself' => true, 'yourselves' => true, 'he' => true, 'him' => true,
        'his' => true, 'himself' => true, 'she' => true, 'her' => true, 'hers' => true,
        'herself' => true, 'it' => true, 'its' => true, 'itself' => true, 'they' => true,
        'them' => true, 'their' => true, 'theirs' => true, 'themselves' => true, 'what' => true,
        'which' => true, 'who' => true, 'whom' => true, 'this' => true, 'that' => true,
        'these' => true, 'those' => true, 'am' => true, 'is' => true, 'are' => true,
        'was' => true, 'were' => true, 'be' => true, 'been' => true, 'being' => true,
        'have' => true, 'has' => true, 'had' => true, 'having' => true, 'do' => true,
        'does' => true, 'did' => true, 'doing' => true, 'a' => true, 'an' => true,
        'the' => true, 'and' => true, 'but' => true, 'if' => true, 'or' => true,
        'because' => true, 'as' => true, 'until' => true, 'while' => true, 'of' => true,
        'at' => true, 'by' => true, 'for' => true, 'with' => true, 'about' => true,
        'against' => true, 'between' => true, 'into' => true, 'through' => true, 'during' => true,
        'before' => true, 'after' => true, 'above' => true, 'below' => true, 'to' => true,
        'from' => true, 'up' => true, 'down' => true, 'in' => true, 'out' => true,
        'on' => true, 'off' => true, 'over' => true, 'under' => true, 'again' => true,
        'further' => true, 'then' => true, 'once' => true, 'here' => true, 'there' => true,
        'when' => true, 'where' => true, 'why' => true, 'how' => true, 'all' => true,
        'any' => true, 'both' => true, 'each' => true, 'few' => true, 'more' => true,
        'most' => true, 'other' => true, 'some' => true, 'such' => true, 'no' => true,
        'nor' => true, 'not' => true, 'only' => true, 'own' => true, 'same' => true,
        'so' => true, 'than' => true, 'too' => true, 'very' => true, 's' => true,
        't' => true, 'can' => true, 'will' => true, 'just' => true, 'don' => true,
        'should' => true, 'now' => true,
    ];

    public function __construct(TorClient $torClient, AhmiaBlacklistService $blacklistService)
    {
        $this->torClient = $torClient;
        $this->blacklistService = $blacklistService;
    }

    /**
     * Core Deep Scrape method faithfully adapted from original darkdump.py
     */
    public function scrape(
        string $url,
        bool $collectImages = false,
        bool $useTor = false,
        string|array|null $customKeywords = null,
        bool $crawlSubpages = true
    ): array {
        $trimmed = trim($url);
        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'http://'.$trimmed;
        }

        if (! filter_var($trimmed, FILTER_VALIDATE_URL) && ! preg_match('#^https?://[a-z0-9-]+\.onion(\S*)?$#i', $trimmed)) {
            return [
                'status' => 'error',
                'url' => $url,
                'error' => 'Invalid target URL format. Please provide a valid HTTP/HTTPS or .onion web address.',
                'diagnostic' => [
                    'summary' => 'Invalid target URL format',
                    'detail' => 'The provided target "'.$url.'" is not a valid absolute web URL or onion address.',
                    'suggestion' => 'Enter a full URL including domain (e.g. http://example.onion or https://example.com).',
                ],
                'raw_error' => 'Invalid URL syntax',
                'response_time_seconds' => 0,
            ];
        }

        $url = $trimmed;
        $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $url);
        $torAvailable = $this->torClient->isTorAvailable();
        $routeViaTor = ($useTor || $isOnion) && $torAvailable;

        // Check Ahmia abuse blacklist
        $isBlacklisted = $this->blacklistService->isBlacklisted($url);

        // If it's an onion URL but Tor socket is unreachable, resolve via Tor2web gateway
        $targetUrl = ($isOnion && ! $torAvailable)
            ? $this->torClient->resolveOnionUrl($url)
            : $url;

        $startTime = microtime(true);
        $client = $this->torClient->getHttpClient($routeViaTor, 30);

        try {
            $response = $client->get($targetUrl);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $html = (string) $response->getBody();
            $duration = round(microtime(true) - $startTime, 2);

            $serverHeader = $headers['Server'][0] ?? $headers['server'][0] ?? 'Hidden/Cloud';

            $crawler = new Crawler($html, $url);

            // Title
            $titleNode = $crawler->filter('title');
            $title = $titleNode->count() > 0 ? trim($titleNode->text()) : 'Untitled Web Page';

            // 1. Extract Metadata (<meta> tags dictionary matching darkdump.py extract_metadata)
            $metadata = $this->extractMetadata($crawler);

            // 2. Clean Text & NLTK-style Keyword Extraction (matching darkdump.py extract_keywords)
            $cleanText = $this->cleanText($html);
            $keywords = $this->extractKeywords($cleanText);

            // 3. Text Analysis & Sentiment (matching darkdump.py analyze_text)
            $sentiment = $this->analyzeText($cleanText, $html);

            // 4. Extract Emails (matching darkdump.py extract_emails)
            $emails = $this->extractEmails($crawler, $html);

            // 5. Extract Documents (matching darkdump.py extract_document_links across 45+ extensions)
            $documents = $this->extractDocumentLinks($crawler, $url);

            // 6. Extract Link Topology (internal vs external links)
            $internalLinks = [];
            $externalLinks = [];
            $host = parse_url($url, PHP_URL_HOST);

            $crawler->filter('a[href]')->each(function (Crawler $link) use ($url, $host, &$internalLinks, &$externalLinks) {
                $href = trim($link->attr('href') ?? '');
                if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                    return;
                }

                $absUrl = $this->resolveUrl($url, $href);
                if (empty($absUrl)) {
                    return;
                }

                $linkHost = parse_url($absUrl, PHP_URL_HOST);
                if ($linkHost === $host) {
                    $internalLinks[] = $absUrl;
                } else {
                    $externalLinks[] = $absUrl;
                }
            });

            // 7. Visual Assets & Image Scraping (matching darkdump.py with high-res & onion preview proxy)
            $images = $collectImages ? $this->extractImages($crawler, $url) : [];

            // 8. Custom Keywords Intelligence & Targeted Subpage Crawling
            $parsedCustomKeywords = $this->parseCustomKeywords($customKeywords);
            $keywordIntel = $this->analyzeKeywordIntel(
                crawler: $crawler,
                html: $html,
                keywords: $parsedCustomKeywords,
                internalLinks: array_unique($internalLinks),
                documents: $documents,
                emails: $emails,
                crawlSubpages: $crawlSubpages,
                url: $url,
                routeViaTor: $routeViaTor
            );

            if (! empty($keywordIntel['additional_documents'])) {
                $documents = array_merge($documents, $keywordIntel['additional_documents']);
            }

            // Raw text sample for intelligence inspection
            $bodyText = '';
            if ($crawler->filter('body')->count() > 0) {
                $bodyText = preg_replace('/\s+/', ' ', trim($crawler->filter('body')->text()));
                $bodyText = substr($bodyText, 0, 1500);
            }

            return [
                'status' => 'success',
                'url' => $url,
                'title' => $title,
                'status_code' => $statusCode,
                'is_blacklisted' => $isBlacklisted,
                'response_time_seconds' => $duration,
                'server' => $serverHeader,
                'headers' => array_map(fn ($v) => implode(', ', $v), array_slice($headers, 0, 15)),
                'metadata' => $metadata,
                'keywords' => $keywords,
                'custom_keywords' => $parsedCustomKeywords,
                'keyword_intel' => $keywordIntel,
                'sentiment' => $sentiment,
                'emails' => array_slice(array_unique($emails), 0, 50),
                'documents' => array_slice(array_unique($documents, SORT_REGULAR), 0, 50),
                'images' => array_slice($images, 0, 80),
                'internal_links_count' => count(array_unique($internalLinks)),
                'external_links_count' => count(array_unique($externalLinks)),
                'internal_links' => array_slice(array_unique($internalLinks), 0, 25),
                'external_links' => array_slice(array_unique($externalLinks), 0, 25),
                'raw_text_sample' => $bodyText,
            ];
        } catch (\Throwable $e) {
            Log::warning("Deep scrape failed for [{$url}]: ".$e->getMessage());
            $diagnostic = $this->diagnoseError($e, $url, $routeViaTor);

            return [
                'status' => 'error',
                'url' => $url,
                'error' => $diagnostic['summary'],
                'diagnostic' => $diagnostic,
                'raw_error' => $e->getMessage(),
                'response_time_seconds' => round(microtime(true) - $startTime, 2),
            ];
        }
    }

    /**
     * Port of clean_text from original darkdump.py
     */
    public function cleanText(string $html): string
    {
        $stripped = preg_replace('/<(script|style)[^>]*?>.*?<\/\\1>/si', ' ', $html);
        $text = strip_tags($stripped);
        $text = preg_replace('/[\r\n]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);

        return trim($text);
    }

    /**
     * Port of extract_keywords from original darkdump.py
     * Removes stop words and returns top 18 keywords by frequency distribution
     */
    public function extractKeywords(string $cleanText): array
    {
        if (empty($cleanText)) {
            return [];
        }

        $tokens = preg_split('/\s+/', strtolower($cleanText));
        $freqDist = [];

        foreach ($tokens as $token) {
            if (strlen($token) >= 3 && ctype_alnum($token) && ! isset($this->stopWords[$token])) {
                $freqDist[$token] = ($freqDist[$token] ?? 0) + 1;
            }
        }

        arsort($freqDist);

        return array_slice(array_keys($freqDist), 0, 18);
    }

    /**
     * Port of analyze_text from original darkdump.py
     * Provides top 10 words, polarity score, subjectivity score, and sentiment label
     */
    public function analyzeText(string $cleanText, string $rawText): array
    {
        $tokens = preg_split('/\s+/', strtolower($cleanText));
        $freqDist = [];

        foreach ($tokens as $token) {
            if (strlen($token) >= 3 && ctype_alnum($token) && ! isset($this->stopWords[$token])) {
                $freqDist[$token] = ($freqDist[$token] ?? 0) + 1;
            }
        }

        arsort($freqDist);
        $topWords = [];
        foreach (array_slice($freqDist, 0, 10, true) as $w => $count) {
            $topWords[] = ['word' => $w, 'count' => $count];
        }

        // Lexical sentiment scoring
        $posLexicon = ['secure', 'trust', 'privacy', 'freedom', 'verified', 'official', 'safe', 'good', 'best', 'community', 'support', 'protect', 'success', 'open', 'free'];
        $negLexicon = ['leak', 'hack', 'dump', 'breach', 'scam', 'fraud', 'exploit', 'bypass', 'malware', 'ransom', 'stolen', 'threat', 'counterfeit', 'illegal', 'attack', 'phishing', 'bulletproof', 'carding'];
        $subjLexicon = ['guarantee', 'unlimited', 'secret', 'exclusive', 'hidden', 'danger', 'critical', 'urgent', 'instant', 'cheap', 'best', 'worst'];

        $posMatches = 0;
        $negMatches = 0;
        $subjMatches = 0;

        foreach ($tokens as $t) {
            if (in_array($t, $posLexicon, true)) {
                $posMatches++;
            }
            if (in_array($t, $negLexicon, true)) {
                $negMatches++;
            }
            if (in_array($t, $subjLexicon, true)) {
                $subjMatches++;
            }
        }

        $totalValence = $posMatches + $negMatches;
        $polarity = $totalValence > 0 ? round(($posMatches - $negMatches) / $totalValence, 2) : 0.0;
        $subjectivity = min(1.0, round(($subjMatches * 3) / max(1, count($tokens) / 10), 2));

        $label = 'Neutral';
        if ($negMatches >= 3 || str_contains(strtolower($rawText), 'dump') || str_contains(strtolower($rawText), 'leak')) {
            $label = 'High Risk / Threat Intel';
        } elseif ($polarity > 0.15) {
            $label = 'Positive';
        } elseif ($polarity < -0.15) {
            $label = 'Negative';
        }

        return [
            'top_words' => $topWords,
            'polarity' => $polarity,
            'subjectivity' => $subjectivity,
            'label' => $label,
            'threat_detected' => $negMatches >= 2,
        ];
    }

    /**
     * Port of extract_metadata from original darkdump.py
     * Extracts all <meta name/property="..." content="..."> tags into a dictionary
     */
    public function extractMetadata(Crawler $crawler): array
    {
        $metadata = [];
        $crawler->filter('meta')->each(function (Crawler $meta) use (&$metadata) {
            $key = $meta->attr('name') ?: $meta->attr('property') ?: $meta->attr('http-equiv');
            $content = $meta->attr('content');
            if ($key && $content !== null && trim($content) !== '') {
                $metadata[trim($key)] = trim($content);
            }
        });

        return $metadata;
    }

    /**
     * Port of extract_emails from original darkdump.py
     */
    public function extractEmails(Crawler $crawler, string $html): array
    {
        // Regex from original darkdump.py
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}/i', $html, $matches);
        $emails = $matches[0] ?? [];

        // Check mailto: links
        $crawler->filter('a[href^="mailto:"]')->each(function (Crawler $link) use (&$emails) {
            $href = $link->attr('href');
            $parsed = str_replace('mailto:', '', $href);
            $parsed = explode('?', $parsed)[0];
            if (! empty($parsed) && filter_var($parsed, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $parsed;
            }
        });

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Port of extract_document_links from original darkdump.py
     * Matches all 45+ extensions and classifies document types
     */
    public function extractDocumentLinks(Crawler $crawler, string $baseUrl): array
    {
        $documents = [];
        $crawler->filter('a[href]')->each(function (Crawler $link) use ($baseUrl, &$documents) {
            $href = trim($link->attr('href') ?? '');
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                return;
            }

            $absUrl = $this->resolveUrl($baseUrl, $href);
            if (empty($absUrl)) {
                return;
            }

            $path = parse_url($absUrl, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($ext, $this->documentExtensions, true)) {
                $text = trim($link->text());
                $filename = basename($path) ?: ('document.'.$ext);
                $documents[] = [
                    'url' => $absUrl,
                    'name' => $filename,
                    'title' => $text ?: $filename,
                    'extension' => $ext,
                    'category' => $this->classifyDocument($ext),
                ];
            }
        });

        return array_values(array_unique($documents, SORT_REGULAR));
    }

    /**
     * Classify extracted documents into intelligence buckets
     */
    protected function classifyDocument(string $ext): string
    {
        return match ($ext) {
            'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages', 'md' => 'Document',
            'xlsx', 'xls', 'csv', 'ods' => 'Spreadsheet',
            'sql', 'db', 'sqlite', 'kdbx', 'log', 'json', 'xml' => 'Database / Keystore',
            'zip', 'tar', 'gz', 'rar', '7z', 'bz2' => 'Archive',
            'dll', 'exe', 'apk', 'bin' => 'Executable / Binary',
            'vmdk', 'iso', 'img', 'dmg' => 'Virtual Disk / ISO',
            'mp3', 'wav', 'mp4', 'avi', 'mov', 'flv', 'wma', 'aac' => 'Media Asset',
            default => 'Other File',
        };
    }

    /**
     * Advanced Multi-Source Image Asset Extraction
     */
    public function extractImages(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $seenUrls = [];

        $addImage = function (?string $rawUrl, string $sourceType, ?string $alt = null, ?int $w = null, ?int $h = null) use (&$images, &$seenUrls, $baseUrl) {
            if (empty($rawUrl)) {
                return;
            }
            $cleanUrl = trim($rawUrl);
            $absUrl = $this->resolveUrl($baseUrl, $cleanUrl);
            if (empty($absUrl) || isset($seenUrls[$absUrl])) {
                return;
            }

            // Exclude noise / tracking pixels
            if ($this->isTrackingPixel($absUrl, $w, $h)) {
                return;
            }
            $seenUrls[$absUrl] = true;

            $path = parse_url($absUrl, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'img';
            $filename = basename($path) ?: 'asset.'.$ext;

            $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $absUrl);
            $previewUrl = $isOnion
                ? url('/api/scraper/proxy-image?url='.urlencode($absUrl))
                : $absUrl;

            $images[] = [
                'url' => $absUrl,
                'preview_url' => $previewUrl,
                'alt' => $alt ?: $filename,
                'filename' => $filename,
                'extension' => $ext,
                'source_type' => $sourceType,
                'is_onion' => $isOnion,
                'width' => $w,
                'height' => $h,
            ];
        };

        // 1. Standard <img> tags & lazy load attributes
        $crawler->filter('img')->each(function (Crawler $img) use ($addImage) {
            $attrs = ['src', 'data-src', 'data-original', 'data-lazy-src', 'data-url', 'data-hi-res-src', 'data-zoom-image'];
            $alt = $img->attr('alt') ?: $img->attr('title');
            $w = is_numeric($img->attr('width')) ? (int) $img->attr('width') : null;
            $h = is_numeric($img->attr('height')) ? (int) $img->attr('height') : null;

            foreach ($attrs as $attr) {
                $val = $img->attr($attr);
                if (! empty($val)) {
                    $addImage($val, 'img_tag', $alt, $w, $h);
                }
            }

            // Parse srcset on img
            $srcset = $img->attr('srcset') ?: $img->attr('data-srcset');
            if (! empty($srcset)) {
                $parts = explode(',', $srcset);
                foreach ($parts as $part) {
                    $tokens = preg_split('/\s+/', trim($part));
                    if (! empty($tokens[0])) {
                        $addImage($tokens[0], 'srcset', $alt, $w, $h);
                    }
                }
            }
        });

        // 2. <picture> and <source> elements
        $crawler->filter('picture source, video[poster]')->each(function (Crawler $el) use ($addImage) {
            $poster = $el->attr('poster');
            if (! empty($poster)) {
                $addImage($poster, 'video_poster', 'Video Poster');
            }
            $srcset = $el->attr('srcset') ?: $el->attr('data-srcset');
            if (! empty($srcset)) {
                $parts = explode(',', $srcset);
                foreach ($parts as $part) {
                    $tokens = preg_split('/\s+/', trim($part));
                    if (! empty($tokens[0])) {
                        $addImage($tokens[0], 'picture_source', 'Responsive Source');
                    }
                }
            }
        });

        // 3. OpenGraph & Twitter Meta Tags
        $crawler->filter('meta[property^="og:image"], meta[name^="twitter:image"], link[rel="image_src"]')->each(function (Crawler $meta) use ($addImage) {
            $content = $meta->attr('content') ?: $meta->attr('href');
            if (! empty($content)) {
                $addImage($content, 'meta_og', 'OpenGraph Media');
            }
        });

        // 4. Favicons & Icons
        $crawler->filter('link[rel*="icon"]')->each(function (Crawler $link) use ($addImage) {
            $href = $link->attr('href');
            if (! empty($href)) {
                $addImage($href, 'favicon', 'Site Favicon');
            }
        });

        // 5. CSS inline background-image
        $crawler->filter('[style*="url("]')->each(function (Crawler $el) use ($addImage) {
            $style = $el->attr('style') ?? '';
            if (preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $matches)) {
                foreach ($matches[1] as $bgUrl) {
                    $addImage($bgUrl, 'background_css', 'Background Asset');
                }
            }
        });

        // 6. Direct Anchor Links pointing to images
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'];
        $crawler->filter('a[href]')->each(function (Crawler $a) use ($addImage, $imageExts) {
            $href = $a->attr('href') ?? '';
            $path = parse_url($href, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, $imageExts, true)) {
                $text = trim($a->text()) ?: basename($path);
                $addImage($href, 'linked_media', $text);
            }
        });

        return $images;
    }

    /**
     * Filter out tracking pixels / blank gifs
     */
    protected function isTrackingPixel(string $url, ?int $width = null, ?int $height = null): bool
    {
        if ($width === 1 && $height === 1) {
            return true;
        }
        $lower = strtolower($url);
        if (str_contains($lower, 'spacer.gif') || str_contains($lower, 'pixel.gif') || str_contains($lower, 'blank.gif') || str_contains($lower, '1x1.')) {
            return true;
        }
        if (str_starts_with($lower, 'data:image/gif;base64,r0lgodlhaqabaia')) {
            return true;
        }

        return false;
    }

    /**
     * Normalize and resolve relative URLs into full canonical absolute URLs
     */
    protected function resolveUrl(string $base, string $rel): string
    {
        if (empty($rel)) {
            return '';
        }
        if (parse_url($rel, PHP_URL_SCHEME) !== null) {
            return $rel;
        }
        if (str_starts_with($rel, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?? 'http';

            return $scheme.':'.$rel;
        }

        $baseParsed = parse_url($base);
        $scheme = $baseParsed['scheme'] ?? 'http';
        $host = $baseParsed['host'] ?? '';
        $port = isset($baseParsed['port']) ? ':'.$baseParsed['port'] : '';

        if (str_starts_with($rel, '/')) {
            return "{$scheme}://{$host}{$port}{$rel}";
        }

        $path = $baseParsed['path'] ?? '/';
        $dir = dirname($path);
        if ($dir === '\\' || $dir === '.') {
            $dir = '';
        }

        return "{$scheme}://{$host}{$port}".rtrim($dir, '/').'/'.ltrim($rel, '/');
    }

    /**
     * Categorize and formulate user-friendly forensic diagnostics from exceptions (Dead onion handler)
     */
    protected function diagnoseError(\Throwable $e, string $url, bool $routeViaTor): array
    {
        $msg = $e->getMessage();
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $url);

        $type = 'CONNECTION_FAILED';
        $summary = "Connection to [{$host}] failed.";
        $suggestion = 'Verify if the site is still online, or try again in a few moments.';

        if (str_contains($msg, 'cURL error 28') || str_contains($msg, 'timed out')) {
            $type = 'TARGET_TIMEOUT';
            $summary = "Target server at [{$host}] timed out and did not respond.";
            $suggestion = $isOnion
                ? 'Dark web hidden services have high churn rates and frequently go offline. If the service is running, it may be experiencing high latency negotiating Tor rendezvous circuits.'
                : 'The server is unreachable, dropping connection probes, or the domain has been abandoned/taken offline.';
        } elseif (str_contains($msg, 'cURL error 6') || str_contains($msg, 'Could not resolve host')) {
            $type = 'DNS_NOT_FOUND';
            $summary = "Domain [{$host}] does not exist (NXDOMAIN).";
            $suggestion = 'The website domain has expired, has no active DNS records, or was seized/de-registered.';
        } elseif (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Failed to connect') || str_contains($msg, 'Connection refused')) {
            $type = 'CONNECTION_REFUSED';
            $summary = "Target server at [{$host}] actively refused the connection.";
            $suggestion = 'The web server port (80/443) is closed or a firewall is rejecting crawler probes.';
        } elseif (str_contains($msg, 'cURL error 97') || str_contains($msg, 'SOCKS5')) {
            $type = 'TOR_SOCKS_FAILED';
            $summary = "Tor network unable to establish circuit to [{$host}].";
            $suggestion = $isOnion
                ? 'The hidden service descriptor is not currently published on Tor directory (HSDir) relays. The onion site is offline.'
                : 'Tor exit node was unable to reach the destination clearnet host.';
        } elseif (str_contains($msg, 'cURL error 35') || str_contains($msg, 'SSL') || str_contains($msg, 'certificate')) {
            $type = 'SSL_HANDSHAKE_ERROR';
            $summary = "SSL/TLS handshake failed with [{$host}].";
            $suggestion = 'The target server has an incompatible or misconfigured SSL cipher suite.';
        }

        return [
            'type' => $type,
            'summary' => $summary,
            'detail' => $msg,
            'suggestion' => $suggestion,
            'host' => $host,
            'is_onion' => $isOnion,
            'route_via_tor' => $routeViaTor,
        ];
    }

    /**
     * Parse and sanitize custom keywords from user input
     *
     * @return array<string>
     */
    public function parseCustomKeywords(string|array|null $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $items = is_array($raw) ? $raw : preg_split('/[,;\n\r]+/', (string) $raw);
        $clean = [];

        foreach ($items as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed !== '' && mb_strlen($trimmed) >= 2) {
                $lower = mb_strtolower($trimmed);
                if (! isset($clean[$lower])) {
                    $clean[$lower] = $trimmed;
                }
            }
        }

        return array_values($clean);
    }

    /**
     * Extract keyword occurrences and context snippets across page elements
     *
     * @param  array<string>  $keywords
     * @param  array<string>  $internalLinks
     * @param  array<array>  $documents
     * @param  array<string>  $emails
     */
    public function analyzeKeywordIntel(
        Crawler $crawler,
        string $html,
        array $keywords,
        array $internalLinks,
        array $documents,
        array $emails,
        bool $crawlSubpages,
        string $url,
        bool $routeViaTor
    ): array {
        if (empty($keywords)) {
            return [
                'query_keywords' => [],
                'total_matches_count' => 0,
                'crawl_subpages_enabled' => $crawlSubpages,
                'crawled_pages_count' => 0,
                'summary' => 'No custom keywords specified for this scrape.',
                'keyword_matches' => [],
                'crawled_subpages' => [],
                'additional_documents' => [],
            ];
        }

        $titleNode = $crawler->filter('title');
        $title = $titleNode->count() > 0 ? trim($titleNode->text()) : '';
        $bodyNode = $crawler->filter('body');
        $bodyText = $bodyNode->count() > 0 ? trim($bodyNode->text()) : strip_tags($html);
        $bodyNormalized = preg_replace('/\s+/', ' ', $bodyText);

        // Extract metadata text
        $metaValues = [];
        $crawler->filter('meta')->each(function (Crawler $node) use (&$metaValues) {
            $content = trim($node->attr('content') ?? '');
            if (! empty($content)) {
                $metaValues[] = $content;
            }
        });
        $metaText = implode(' | ', $metaValues);

        $keywordMatches = [];
        $totalMatches = 0;

        foreach ($keywords as $kw) {
            $escaped = preg_quote($kw, '/');
            $pattern = '/('.$escaped.')/iu';

            $primaryHits = 0;
            $sources = [];
            $snippets = [];

            // 1. Check title
            if (preg_match($pattern, $title)) {
                $primaryHits++;
                $sources[] = 'title';
                $snippets[] = 'Page Title: "'.$title.'"';
            }

            // 2. Check metadata
            if (preg_match_all($pattern, $metaText, $metaMatches)) {
                $count = count($metaMatches[0]);
                $primaryHits += $count;
                $sources[] = 'meta';
                $metaSnippets = $this->extractSnippets($metaText, $kw, 2);
                foreach ($metaSnippets as $ms) {
                    $snippets[] = 'Meta: '.$ms;
                }
            }

            // 3. Check page body text
            if (preg_match_all($pattern, $bodyNormalized, $bodyMatches)) {
                $count = count($bodyMatches[0]);
                $primaryHits += $count;
                $sources[] = 'body';
                $bodySnippets = $this->extractSnippets($bodyNormalized, $kw, 5);
                foreach ($bodySnippets as $bs) {
                    $snippets[] = $bs;
                }
            }

            // 4. Check discovered documents
            $docHits = 0;
            foreach ($documents as $doc) {
                $docName = $doc['name'] ?? '';
                $docUrl = $doc['url'] ?? '';
                if (preg_match($pattern, $docName) || preg_match($pattern, $docUrl)) {
                    $docHits++;
                    $primaryHits++;
                    $snippets[] = 'Discovered Document: '.($docName ?: $docUrl).' ('.($doc['extension'] ?? 'file').')';
                }
            }
            if ($docHits > 0) {
                $sources[] = 'documents';
            }

            // 5. Check discovered emails
            $emailHits = 0;
            foreach ($emails as $email) {
                if (preg_match($pattern, $email)) {
                    $emailHits++;
                    $primaryHits++;
                    $snippets[] = 'Discovered Email: '.$email;
                }
            }
            if ($emailHits > 0) {
                $sources[] = 'emails';
            }

            // 6. Check internal links
            $linkHits = 0;
            foreach ($internalLinks as $link) {
                if (preg_match($pattern, $link)) {
                    $linkHits++;
                }
            }
            if ($linkHits > 0) {
                $sources[] = 'links';
            }

            $totalMatches += $primaryHits;

            $keywordMatches[$kw] = [
                'keyword' => $kw,
                'hit_count' => $primaryHits,
                'primary_hits' => $primaryHits,
                'subpage_hits' => 0,
                'sources' => array_values(array_unique($sources)),
                'snippets' => array_values(array_unique($snippets)),
            ];
        }

        $crawledSubpages = [];
        $additionalDocuments = [];

        // Targeted Subpage Crawl
        if ($crawlSubpages && ! empty($internalLinks)) {
            $crawlResults = $this->crawlMatchingSubpages(
                internalLinks: $internalLinks,
                keywords: $keywords,
                baseUrl: $url,
                routeViaTor: $routeViaTor,
                maxPages: 3
            );

            $crawledSubpages = $crawlResults['crawled_subpages'];
            $additionalDocuments = $crawlResults['additional_documents'];

            // Merge subpage findings into keywordMatches
            foreach ($crawlResults['keyword_subpage_hits'] as $kw => $subData) {
                if (isset($keywordMatches[$kw])) {
                    $subHits = $subData['hits'];
                    $keywordMatches[$kw]['subpage_hits'] += $subHits;
                    $keywordMatches[$kw]['hit_count'] += $subHits;
                    $totalMatches += $subHits;

                    if ($subHits > 0 && ! in_array('subpages', $keywordMatches[$kw]['sources'])) {
                        $keywordMatches[$kw]['sources'][] = 'subpages';
                    }

                    foreach ($subData['snippets'] as $subSnippet) {
                        if (count($keywordMatches[$kw]['snippets']) < 10) {
                            $keywordMatches[$kw]['snippets'][] = $subSnippet;
                        }
                    }
                    $keywordMatches[$kw]['snippets'] = array_values(array_unique($keywordMatches[$kw]['snippets']));
                }
            }
        }

        $crawledCount = count($crawledSubpages);
        $kwCount = count($keywords);
        $summary = "Indexed {$kwCount} target keywords: found {$totalMatches} total match occurrences across target site and {$crawledCount} crawled subpages.";

        return [
            'query_keywords' => $keywords,
            'total_matches_count' => $totalMatches,
            'crawl_subpages_enabled' => $crawlSubpages,
            'crawled_pages_count' => $crawledCount,
            'summary' => $summary,
            'keyword_matches' => array_values($keywordMatches),
            'crawled_subpages' => $crawledSubpages,
            'additional_documents' => $additionalDocuments,
        ];
    }

    /**
     * Crawl matching child internal links for keyword intelligence
     *
     * @param  array<string>  $internalLinks
     * @param  array<string>  $keywords
     */
    protected function crawlMatchingSubpages(
        array $internalLinks,
        array $keywords,
        string $baseUrl,
        bool $routeViaTor,
        int $maxPages = 3
    ): array {
        $crawledSubpages = [];
        $additionalDocuments = [];
        $keywordSubpageHits = [];

        foreach ($keywords as $kw) {
            $keywordSubpageHits[$kw] = [
                'hits' => 0,
                'snippets' => [],
            ];
        }

        // Filter out baseUrl and normalize
        $normalizedBase = rtrim($baseUrl, '/');
        $candidates = [];
        $keywordMatchedCandidates = [];

        foreach ($internalLinks as $link) {
            $linkTrimmed = rtrim($link, '/');
            if (empty($linkTrimmed) || $linkTrimmed === $normalizedBase) {
                continue;
            }

            // Check if link matches any keyword
            $matchesKw = false;
            foreach ($keywords as $kw) {
                if (stripos($linkTrimmed, $kw) !== false) {
                    $matchesKw = true;
                    break;
                }
            }

            if ($matchesKw) {
                $keywordMatchedCandidates[] = $link;
            } else {
                $candidates[] = $link;
            }
        }

        // Prioritize links matching keywords, followed by general internal links
        $linksToCrawl = array_unique(array_merge($keywordMatchedCandidates, $candidates));
        $selectedLinks = array_slice($linksToCrawl, 0, $maxPages);

        if (empty($selectedLinks)) {
            return [
                'crawled_subpages' => [],
                'additional_documents' => [],
                'keyword_subpage_hits' => $keywordSubpageHits,
            ];
        }

        $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $baseUrl);
        $torAvailable = $this->torClient->isTorAvailable();

        foreach ($selectedLinks as $subUrl) {
            $targetUrl = ($isOnion && ! $torAvailable)
                ? $this->torClient->resolveOnionUrl($subUrl)
                : $subUrl;

            $subClient = $this->torClient->getHttpClient($routeViaTor, 8);

            try {
                $subRes = $subClient->get($targetUrl);
                $subStatus = $subRes->getStatusCode();
                $subHtml = (string) $subRes->getBody();
                $subCrawler = new Crawler($subHtml, $subUrl);

                $subTitleNode = $subCrawler->filter('title');
                $subTitle = $subTitleNode->count() > 0 ? trim($subTitleNode->text()) : 'Untitled Subpage';
                $subBodyNode = $subCrawler->filter('body');
                $subBodyText = $subBodyNode->count() > 0 ? trim($subBodyNode->text()) : strip_tags($subHtml);
                $subBodyNormalized = preg_replace('/\s+/', ' ', $subBodyText);

                $subTotalHits = 0;
                $subMatchedKws = [];
                $subSnippets = [];

                foreach ($keywords as $kw) {
                    $escaped = preg_quote($kw, '/');
                    $pattern = '/('.$escaped.')/iu';

                    if (preg_match_all($pattern, $subBodyNormalized, $subMatches)) {
                        $kwHits = count($subMatches[0]);
                        $subTotalHits += $kwHits;
                        $subMatchedKws[] = $kw;

                        $keywordSubpageHits[$kw]['hits'] += $kwHits;

                        $extracted = $this->extractSnippets($subBodyNormalized, $kw, 3);
                        foreach ($extracted as $snip) {
                            $subSnippets[] = $snip;
                            $keywordSubpageHits[$kw]['snippets'][] = "[{$subTitle}] {$snip}";
                        }
                    }
                }

                // Discover additional documents on subpage
                $subDocs = $this->extractDocumentLinks($subCrawler, $subUrl);
                foreach ($subDocs as $sd) {
                    $additionalDocuments[] = $sd;
                }

                $crawledSubpages[] = [
                    'url' => $subUrl,
                    'title' => $subTitle,
                    'status_code' => $subStatus,
                    'hit_count' => $subTotalHits,
                    'matched_keywords' => array_values(array_unique($subMatchedKws)),
                    'snippets' => array_slice(array_unique($subSnippets), 0, 4),
                ];
            } catch (\Throwable $subException) {
                $crawledSubpages[] = [
                    'url' => $subUrl,
                    'title' => 'Crawl Connection Failed',
                    'status_code' => 0,
                    'hit_count' => 0,
                    'matched_keywords' => [],
                    'snippets' => [],
                    'error' => $subException->getMessage(),
                ];
            }
        }

        return [
            'crawled_subpages' => $crawledSubpages,
            'additional_documents' => $additionalDocuments,
            'keyword_subpage_hits' => $keywordSubpageHits,
        ];
    }

    /**
     * Extract context snippets around matched keywords
     *
     * @return array<string>
     */
    public function extractSnippets(string $text, string $keyword, int $maxSnippets = 5, int $contextLength = 70): array
    {
        if (empty($text) || empty($keyword)) {
            return [];
        }

        $snippets = [];
        $pattern = '/('.preg_quote($keyword, '/').')/iu';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                if (count($snippets) >= $maxSnippets) {
                    break;
                }

                $matchedWord = $match[0];
                $offset = $match[1];

                $start = max(0, $offset - $contextLength);
                $length = strlen($matchedWord) + ($contextLength * 2);
                $rawSnippet = substr($text, $start, $length);

                $cleanSnippet = trim(preg_replace('/\s+/', ' ', $rawSnippet));
                $prefix = $start > 0 ? '...' : '';
                $suffix = ($start + $length < strlen($text)) ? '...' : '';

                $snippets[] = $prefix.$cleanSnippet.$suffix;
            }
        }

        return array_values(array_unique($snippets));
    }
}

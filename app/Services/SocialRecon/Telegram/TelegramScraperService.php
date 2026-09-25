<?php

namespace App\Services\SocialRecon\Telegram;

use App\Services\DarkWeb\TorClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class TelegramScraperService
{
    public function __construct(
        protected TorClient $torClient
    ) {}

    /**
     * Scrape messages from a public Telegram channel with deep backward pagination,
     * strict Tor proxy routing, and automated IOC / threat intelligence extraction.
     *
     * @return array{
     *     channel: array<string, mixed>|null,
     *     messages: array<int, array<string, mixed>>,
     *     iocs_summary: array<string, mixed>,
     *     pagination: array<string, mixed>,
     *     network: array<string, mixed>,
     *     error?: string
     * }
     */
    public function scrapeChannel(
        string $channel,
        ?string $query = null,
        int $limit = 50,
        ?string $beforeId = null,
        bool $extractIocs = true,
        bool $forceTor = true
    ): array {
        $cleanChannel = trim(str_replace(['https://t.me/s/', 'https://t.me/', '@', 't.me/s/', 't.me/'], '', $channel), '/ ');

        if (empty($cleanChannel)) {
            return [
                'channel' => null,
                'messages' => [],
                'iocs_summary' => $this->emptyIocSummary(),
                'pagination' => ['oldest_id' => null, 'newest_id' => null, 'has_more' => false, 'count' => 0],
                'network' => ['is_tor' => false, 'proxy' => null],
                'error' => 'Invalid channel identifier.',
            ];
        }

        // 1. Verify Tor routing availability
        $useTor = $this->torClient->isTorAvailable();
        if ($forceTor && ! $useTor) {
            // Attempt auto-start if binary exists
            $startRes = $this->torClient->startTor();
            $useTor = $this->torClient->isTorAvailable();

            if (! $useTor) {
                // If Tor is still offline, check if clearnet fallback is safe or return error
                Log::warning("Tor daemon is offline for Telegram scrape @{$cleanChannel}. Falling back to clearnet.");
            }
        }

        $timeout = $useTor ? 20 : 12;
        $httpOptions = [
            'verify' => false,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
        ];

        if ($useTor) {
            $httpOptions['proxy'] = $this->torClient->getProxyUrl();
        }

        $collectedMessages = [];
        $channelMetadata = null;
        $nextCursor = $beforeId;
        $maxIterations = (int) ceil(max(1, min($limit, 500)) / 20);
        $iterations = 0;
        $hasMore = false;

        try {
            // Initial fetch or cursor fetch
            while ($iterations < $maxIterations) {
                $iterations++;

                $url = 'https://t.me/s/'.$cleanChannel;
                $headers = [
                    'User-Agent' => $this->torClient->getRandomUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://t.me/s/'.$cleanChannel,
                ];

                $queryParams = [];
                if (! empty($query) && $iterations === 1 && empty($nextCursor)) {
                    $queryParams['q'] = trim($query);
                }

                if (! empty($nextCursor)) {
                    $queryParams['before'] = $nextCursor;
                    $headers['X-Requested-With'] = 'XMLHttpRequest';
                    $headers['Accept'] = 'application/json, text/javascript, */*; q=0.01';
                }

                if (! empty($queryParams)) {
                    $url .= '?'.http_build_query($queryParams);
                }

                $response = Http::withHeaders($headers)
                    ->withOptions($httpOptions)
                    ->timeout($timeout)
                    ->get($url);

                if (! $response->successful()) {
                    if (empty($collectedMessages)) {
                        return [
                            'channel' => ['username' => $cleanChannel],
                            'messages' => [],
                            'total' => 0,
                            'iocs_summary' => $this->emptyIocSummary(),
                            'pagination' => ['oldest_id' => null, 'newest_id' => null, 'has_more' => false, 'count' => 0],
                            'network' => ['is_tor' => $useTor, 'proxy' => $useTor ? $this->torClient->getProxyUrl() : null],
                            'error' => 'Telegram returned HTTP '.$response->status().' (channel may be private or restricted).',
                        ];
                    }
                    break;
                }

                $body = $response->body();

                // AJAX pagination returns a JSON-encoded HTML string
                if (! empty($nextCursor)) {
                    $decoded = json_decode($body);
                    $html = is_string($decoded) ? $decoded : $body;
                } else {
                    $html = $body;
                }

                $crawler = new Crawler($html);

                // Channel metadata extraction (from the first full page response)
                if ($channelMetadata === null && empty($nextCursor)) {
                    $channelMetadata = $this->extractChannelMetadata($crawler, $cleanChannel);
                }

                // Extract messages from this HTML segment
                $batchMessages = $this->parseMessagesFromCrawler($crawler, $cleanChannel, $extractIocs);

                if (empty($batchMessages)) {
                    $hasMore = false;
                    break;
                }

                // Deduplicate and append messages
                foreach ($batchMessages as $msg) {
                    $msgId = $msg['id'] ?? null;
                    if ($msgId && ! isset($collectedMessages[$msgId])) {
                        $collectedMessages[$msgId] = $msg;
                    }
                }

                // Check for next backward pagination link
                $moreLink = $crawler->filter('a.tme_messages_more');
                if ($moreLink->count() && $moreLink->attr('data-before')) {
                    $nextCursor = trim((string) $moreLink->attr('data-before'));
                    $hasMore = true;
                } else {
                    // Check oldest message ID as cursor fallback
                    $oldestInBatch = end($batchMessages);
                    if ($oldestInBatch && ! empty($oldestInBatch['id']) && $oldestInBatch['id'] !== $nextCursor) {
                        $nextCursor = (string) $oldestInBatch['id'];
                        $hasMore = true;
                    } else {
                        $hasMore = false;
                        break;
                    }
                }

                // Check if we reached the requested limit
                if (count($collectedMessages) >= $limit) {
                    break;
                }
            }

            // Fallback channel metadata if not extracted
            if ($channelMetadata === null) {
                $channelMetadata = [
                    'username' => $cleanChannel,
                    'title' => $cleanChannel,
                    'description' => '',
                    'subscribers' => 'Public',
                    'avatar' => null,
                    'verified' => false,
                    'url' => 'https://t.me/'.$cleanChannel,
                ];
            }

            // Sort messages newest first by numeric ID if possible
            $messagesList = array_values($collectedMessages);
            usort($messagesList, function ($a, $b) {
                $idA = is_numeric($a['id'] ?? null) ? (int) $a['id'] : 0;
                $idB = is_numeric($b['id'] ?? null) ? (int) $b['id'] : 0;

                return $idB <=> $idA;
            });

            // Slice to exact limit
            $finalMessages = array_slice($messagesList, 0, $limit);

            // Compute oldest and newest cursor IDs
            $oldestId = ! empty($finalMessages) ? end($finalMessages)['id'] : null;
            $newestId = ! empty($finalMessages) ? $finalMessages[0]['id'] : null;

            // Aggregate IOCs summary across all messages
            $iocsSummary = $extractIocs ? $this->aggregateIocs($finalMessages) : $this->emptyIocSummary();

            return [
                'channel' => $channelMetadata,
                'messages' => $finalMessages,
                'total' => count($finalMessages),
                'iocs_summary' => $iocsSummary,
                'pagination' => [
                    'oldest_id' => $oldestId,
                    'newest_id' => $newestId,
                    'next_cursor' => $nextCursor,
                    'has_more' => $hasMore,
                    'count' => count($finalMessages),
                    'limit' => $limit,
                ],
                'network' => [
                    'is_tor' => $useTor,
                    'proxy' => $useTor ? $this->torClient->getProxyUrl() : null,
                    'port' => $this->torClient->resolveActivePort(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Telegram scrape failed for @'.$cleanChannel.': '.$e->getMessage());

            return [
                'channel' => $channelMetadata ?? ['username' => $cleanChannel, 'title' => $cleanChannel, 'url' => 'https://t.me/'.$cleanChannel],
                'messages' => array_values($collectedMessages),
                'iocs_summary' => $this->emptyIocSummary(),
                'pagination' => ['oldest_id' => null, 'newest_id' => null, 'has_more' => false, 'count' => count($collectedMessages)],
                'network' => ['is_tor' => $useTor, 'proxy' => $useTor ? $this->torClient->getProxyUrl() : null],
                'error' => 'Scrape encountered an error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Extract channel header metadata.
     */
    protected function extractChannelMetadata(Crawler $crawler, string $cleanChannel): array
    {
        $titleEl = $crawler->filter('.tgme_channel_info_header_title');
        $channelTitle = $titleEl->count() ? trim($titleEl->text()) : $cleanChannel;

        $descEl = $crawler->filter('.tgme_channel_info_description');
        $channelDesc = $descEl->count() ? trim($descEl->text()) : '';

        $counterEl = $crawler->filter('.tgme_channel_info_counter .counter_value');
        $subscribers = $counterEl->count() ? trim($counterEl->first()->text()) : 'Public';

        $avatarEl = $crawler->filter('.tgme_page_photo_image img');
        $avatarUrl = $avatarEl->count() ? $avatarEl->attr('src') : null;

        $isVerified = $crawler->filter('.tgme_channel_info_header_title .verified-icon')->count() > 0;

        return [
            'username' => $cleanChannel,
            'title' => $channelTitle,
            'description' => $channelDesc,
            'subscribers' => $subscribers,
            'avatar' => $avatarUrl,
            'verified' => $isVerified,
            'url' => 'https://t.me/'.$cleanChannel,
        ];
    }

    /**
     * Parse message elements from a Symfony Crawler instance.
     */
    protected function parseMessagesFromCrawler(Crawler $crawler, string $cleanChannel, bool $extractIocs): array
    {
        $messages = [];

        $crawler->filter('.tgme_widget_message_wrap')->each(function (Crawler $wrap) use (&$messages, $cleanChannel, $extractIocs) {
            $msgNode = $wrap->filter('.tgme_widget_message');
            if (! $msgNode->count()) {
                return;
            }

            // Message ID from data-post attribute (format: channel/id)
            $dataPost = $msgNode->attr('data-post');
            $msgId = null;
            if ($dataPost) {
                $parts = explode('/', $dataPost);
                $msgId = end($parts);
            }

            // Text
            $textEl = $wrap->filter('.tgme_widget_message_text');
            $text = $textEl->count() ? trim($textEl->text()) : '';

            // Datetime
            $dateEl = $wrap->filter('.tgme_widget_message_date time');
            $datetime = $dateEl->count() ? $dateEl->attr('datetime') : null;

            // View count
            $viewsEl = $wrap->filter('.tgme_widget_message_views');
            $views = $viewsEl->count() ? trim($viewsEl->text()) : '0';

            // Message URL
            $linkEl = $wrap->filter('.tgme_widget_message_date');
            $msgUrl = $linkEl->count() ? $linkEl->attr('href') : null;
            if (empty($msgUrl) && $msgId) {
                $msgUrl = 'https://t.me/'.$cleanChannel.'/'.$msgId;
            }

            // Forwarded From channel/source
            $forwardedFrom = null;
            $forwardNode = $wrap->filter('.tgme_widget_message_forwarded_from_name');
            if ($forwardNode->count()) {
                $forwardHref = $forwardNode->attr('href') ?? '';
                $forwardName = trim($forwardNode->text());
                $forwardHandle = trim(str_replace(['https://t.me/', 'http://t.me/', '/'], '', $forwardHref), '@ ');

                $forwardedFrom = [
                    'name' => $forwardName,
                    'handle' => $forwardHandle ?: null,
                    'url' => $forwardHref ?: null,
                ];
            }

            // Media extraction (photos, video preview, document attachments)
            $media = [];

            // Photo preview
            $photoEl = $wrap->filter('.tgme_widget_message_photo_wrap');
            if ($photoEl->count()) {
                $style = $photoEl->attr('style') ?? '';
                if (preg_match('/background-image:\s*url\([\'"]?([^\'")]+)[\'"]?\)/i', $style, $matches)) {
                    $media[] = [
                        'type' => 'photo',
                        'thumbnail' => $matches[1],
                        'url' => $matches[1],
                    ];
                }
            }

            // Video preview
            $videoEl = $wrap->filter('.tgme_widget_message_video_thumb');
            if ($videoEl->count()) {
                $style = $videoEl->attr('style') ?? '';
                $duration = $wrap->filter('.tgme_widget_message_video_duration')->count()
                    ? trim($wrap->filter('.tgme_widget_message_video_duration')->text())
                    : null;

                if (preg_match('/background-image:\s*url\([\'"]?([^\'")]+)[\'"]?\)/i', $style, $matches)) {
                    $media[] = [
                        'type' => 'video',
                        'thumbnail' => $matches[1],
                        'duration' => $duration,
                    ];
                }
            }

            // Document / File attachment
            $docEl = $wrap->filter('.tgme_widget_message_document');
            if ($docEl->count()) {
                $docTitle = $wrap->filter('.tgme_widget_message_document_title')->count()
                    ? trim($wrap->filter('.tgme_widget_message_document_title')->text())
                    : 'Document Attachment';
                $docExtra = $wrap->filter('.tgme_widget_message_document_extra')->count()
                    ? trim($wrap->filter('.tgme_widget_message_document_extra')->text())
                    : null;

                $media[] = [
                    'type' => 'document',
                    'filename' => $docTitle,
                    'filesize' => $docExtra,
                ];
            }

            // Extract IOCs from message text
            $iocs = $extractIocs ? $this->extractIocs($text) : $this->emptyIocs();

            if (! empty($text) || ! empty($msgUrl) || ! empty($media)) {
                $messages[] = [
                    'id' => $msgId,
                    'text' => $text,
                    'datetime' => $datetime,
                    'views' => $views,
                    'url' => $msgUrl,
                    'channel' => $cleanChannel,
                    'forwarded_from' => $forwardedFrom,
                    'media' => $media,
                    'iocs' => $iocs,
                    'has_iocs' => $iocs['total'] > 0,
                ];
            }
        });

        return $messages;
    }

    /**
     * Extract threat intelligence indicators of compromise (IOCs) from post text.
     */
    public function extractIocs(string $text): array
    {
        if (empty($text)) {
            return $this->emptyIocs();
        }

        // 1. Email harvesting
        $emails = [];
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $emails = array_values(array_unique(array_filter($matches[0])));
        }

        // 2. Cryptocurrency addresses
        // Bitcoin (Legacy, SegWit, Bech32)
        $btc = [];
        if (preg_match_all('/\b(?:1[a-km-zA-HJ-NP-Z1-9]{25,34}|3[a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{25,39}|bc1[a-zA-HJ-NP-Z0-9]{59,62})\b/', $text, $matches)) {
            $btc = array_values(array_unique(array_filter($matches[0])));
        }

        // Ethereum & EVM
        $eth = [];
        if (preg_match_all('/\b0x[a-fA-F0-9]{40}\b/', $text, $matches)) {
            $eth = array_values(array_unique(array_filter($matches[0])));
        }

        // Monero (XMR)
        $xmr = [];
        if (preg_match_all('/\b4[0-9AB][1-9A-HJ-NP-Za-km-z]{93}\b/', $text, $matches)) {
            $xmr = array_values(array_unique(array_filter($matches[0])));
        }

        // TRON / USDT TRC20
        $usdt = [];
        if (preg_match_all('/\bT[A-Za-z1-9]{33}\b/', $text, $matches)) {
            $usdt = array_values(array_unique(array_filter($matches[0])));
        }

        // 3. Cryptographic Hashes (MD5, SHA1, SHA256)
        $hashes = [];
        // SHA256
        if (preg_match_all('/\b[a-fA-F0-9]{64}\b/', $text, $matches)) {
            foreach ($matches[0] as $h) {
                $hashes[] = ['type' => 'SHA-256', 'value' => strtolower($h)];
            }
        }
        // SHA1
        if (preg_match_all('/\b[a-fA-F0-9]{40}\b/', $text, $matches)) {
            foreach ($matches[0] as $h) {
                // Avoid matching ETH addresses (which start with 0x)
                if (! str_starts_with($h, '0x')) {
                    $hashes[] = ['type' => 'SHA-1', 'value' => strtolower($h)];
                }
            }
        }
        // MD5 (exclude pure decimal strings like phone numbers/IDs)
        if (preg_match_all('/\b[a-fA-F0-9]{32}\b/', $text, $matches)) {
            foreach ($matches[0] as $h) {
                if (! ctype_digit($h)) {
                    $hashes[] = ['type' => 'MD5', 'value' => strtolower($h)];
                }
            }
        }

        // 4. Leak & Credential Markers
        $leakPatterns = [];
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}:[^\s:]{4,}/', $text, $matches)) {
            $leakPatterns[] = 'Email:Password Combo ('.count($matches[0]).' records)';
        }
        if (preg_match('/\b(combo|combolist|stealer\s*log|db\s*dump|database\s*leak|breach|seed\s*phrase|private\s*key)\b/i', $text, $m)) {
            $leakPatterns[] = 'Breach Keyword: '.ucwords($m[1]);
        }

        $totalIocs = count($emails) + count($btc) + count($eth) + count($xmr) + count($usdt) + count($hashes) + count($leakPatterns);

        return [
            'total' => $totalIocs,
            'emails' => $emails,
            'crypto' => [
                'btc' => $btc,
                'eth' => $eth,
                'xmr' => $xmr,
                'usdt' => $usdt,
            ],
            'hashes' => $hashes,
            'leak_patterns' => $leakPatterns,
        ];
    }

    /**
     * Aggregate unique IOCs across a message collection.
     */
    protected function aggregateIocs(array $messages): array
    {
        $allEmails = [];
        $allBtc = [];
        $allEth = [];
        $allXmr = [];
        $allUsdt = [];
        $allHashes = [];
        $allPatterns = [];

        foreach ($messages as $msg) {
            $iocs = $msg['iocs'] ?? [];
            if (empty($iocs)) {
                continue;
            }

            foreach ($iocs['emails'] ?? [] as $email) {
                $allEmails[] = $email;
            }
            foreach ($iocs['crypto']['btc'] ?? [] as $addr) {
                $allBtc[] = $addr;
            }
            foreach ($iocs['crypto']['eth'] ?? [] as $addr) {
                $allEth[] = $addr;
            }
            foreach ($iocs['crypto']['xmr'] ?? [] as $addr) {
                $allXmr[] = $addr;
            }
            foreach ($iocs['crypto']['usdt'] ?? [] as $addr) {
                $allUsdt[] = $addr;
            }
            foreach ($iocs['hashes'] ?? [] as $h) {
                $allHashes[] = $h;
            }
            foreach ($iocs['leak_patterns'] ?? [] as $p) {
                $allPatterns[] = $p;
            }
        }

        $uniqueEmails = array_values(array_unique($allEmails));
        $uniqueBtc = array_values(array_unique($allBtc));
        $uniqueEth = array_values(array_unique($allEth));
        $uniqueXmr = array_values(array_unique($allXmr));
        $uniqueUsdt = array_values(array_unique($allUsdt));
        $uniquePatterns = array_values(array_unique($allPatterns));

        // Deduplicate hashes by value
        $hashMap = [];
        foreach ($allHashes as $h) {
            $hashVal = $h['value'] ?? '';
            if ($hashVal && ! isset($hashMap[$hashVal])) {
                $hashMap[$hashVal] = $h;
            }
        }

        $total = count($uniqueEmails) + count($uniqueBtc) + count($uniqueEth) + count($uniqueXmr) + count($uniqueUsdt) + count($hashMap) + count($uniquePatterns);

        return [
            'total' => $total,
            'emails' => $uniqueEmails,
            'crypto' => [
                'btc' => $uniqueBtc,
                'eth' => $uniqueEth,
                'xmr' => $uniqueXmr,
                'usdt' => $uniqueUsdt,
            ],
            'hashes' => array_values($hashMap),
            'leak_patterns' => $uniquePatterns,
        ];
    }

    protected function emptyIocs(): array
    {
        return [
            'total' => 0,
            'emails' => [],
            'crypto' => ['btc' => [], 'eth' => [], 'xmr' => [], 'usdt' => []],
            'hashes' => [],
            'leak_patterns' => [],
        ];
    }

    protected function emptyIocSummary(): array
    {
        return $this->emptyIocs();
    }

    /**
     * Search public Telegram channels, groups, and bots matching a keyword via public indexing directory.
     *
     * @return array{
     *     success: bool,
     *     keyword: string,
     *     channels: array<int, array<string, mixed>>,
     *     count: int,
     *     network: array<string, mixed>,
     *     error?: string
     * }
     */
    public function searchChannels(string $keyword, int $limit = 25, bool $forceTor = true): array
    {
        $cleanQuery = trim($keyword);
        if (empty($cleanQuery)) {
            return [
                'success' => false,
                'keyword' => '',
                'channels' => [],
                'count' => 0,
                'network' => ['is_tor' => false, 'proxy' => null],
                'error' => 'Please provide a search keyword or topic.',
            ];
        }

        // Verify Tor status
        $useTor = $this->torClient->isTorAvailable();
        if ($forceTor && ! $useTor) {
            $this->torClient->startTor();
            $useTor = $this->torClient->isTorAvailable();
        }

        $httpOptions = [
            'verify' => false,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
        ];

        if ($useTor) {
            $httpOptions['proxy'] = $this->torClient->getProxyUrl();
        }

        $timeout = $useTor ? 18 : 12;
        $url = 'https://lyzem.com/search?q='.urlencode($cleanQuery);

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->torClient->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->withOptions($httpOptions)->timeout($timeout)->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'keyword' => $cleanQuery,
                    'channels' => [],
                    'count' => 0,
                    'network' => ['is_tor' => $useTor, 'proxy' => $useTor ? $this->torClient->getProxyUrl() : null],
                    'error' => 'Telegram directory indexer returned HTTP '.$response->status().'.',
                ];
            }

            $crawler = new Crawler($response->body());
            $channels = [];
            $seenHandles = [];

            $crawler->filter('.search-result')->each(function (Crawler $node) use (&$channels, &$seenHandles, $limit) {
                if (count($channels) >= $limit) {
                    return;
                }

                $typeWrapper = $node->filter('.search-result-type-wrapper');
                $type = $typeWrapper->count() ? trim((string) $typeWrapper->attr('title')) : 'channel';

                $titleEl = $node->filter('.search-result-title a');
                $title = $titleEl->count() ? trim($titleEl->text()) : '';
                $href = $titleEl->count() ? trim((string) $titleEl->attr('href')) : '';

                $descEl = $node->filter('.search-result-descr');
                $desc = $descEl->count() ? trim($descEl->text()) : '';

                $handle = trim(str_replace(['https://t.me/', 'http://t.me/', '/'], '', $href), '@ ');

                if (! empty($handle) && ! empty($title) && ! isset($seenHandles[strtolower($handle)])) {
                    $seenHandles[strtolower($handle)] = true;
                    $channels[] = [
                        'handle' => $handle,
                        'title' => $title,
                        'description' => $desc,
                        'type' => in_array($type, ['channel', 'group', 'bot']) ? $type : 'channel',
                        'url' => 'https://t.me/'.$handle,
                        'web_url' => 'https://t.me/s/'.$handle,
                    ];
                }
            });

            return [
                'success' => true,
                'keyword' => $cleanQuery,
                'channels' => $channels,
                'count' => count($channels),
                'network' => [
                    'is_tor' => $useTor,
                    'proxy' => $useTor ? $this->torClient->getProxyUrl() : null,
                    'port' => $this->torClient->resolveActivePort(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Telegram channel search failed for "'.$cleanQuery.'": '.$e->getMessage());

            return [
                'success' => false,
                'keyword' => $cleanQuery,
                'channels' => [],
                'count' => 0,
                'network' => ['is_tor' => $useTor, 'proxy' => $useTor ? $this->torClient->getProxyUrl() : null],
                'error' => 'Channel discovery error: '.$e->getMessage(),
            ];
        }
    }
}

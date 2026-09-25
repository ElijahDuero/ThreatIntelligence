<?php

namespace App\Services\DarkWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AhmiaBlacklistService
{
    protected TorClient $torClient;

    protected string $cacheKey = 'ahmia_blacklist_hashes';

    protected int $cacheTtl = 86400; // 24 hours

    public function __construct(TorClient $torClient)
    {
        $this->torClient = $torClient;
    }

    public function isBlacklisted(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            $host = $url;
        }

        // Clean host to onion domain
        $host = strtolower(trim($host));
        $md5 = md5($host);

        $blacklist = $this->getBlacklist();

        return in_array($md5, $blacklist, true) || in_array($host, $blacklist, true);
    }

    public function getBlacklist(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
            try {
                $client = $this->torClient->getHttpClient(false, 10);
                $response = $client->get('https://ahmia.fi/blacklist/banned/');
                if ($response->getStatusCode() === 200) {
                    $body = (string) $response->getBody();
                    $lines = array_filter(array_map('trim', explode("\n", $body)));

                    return array_values($lines);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch live Ahmia blacklist: '.$e->getMessage());
            }

            return [];
        });
    }
}

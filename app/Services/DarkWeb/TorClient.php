<?php

namespace App\Services\DarkWeb;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TorClient
{
    protected string $socksHost;

    protected int $socksPort;

    protected int $timeout;

    protected ?bool $cachedAvailability = null;

    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; rv:128.0) Gecko/20100101 Firefox/128.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:128.0) Gecko/20100101 Firefox/128.0',
        'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
    ];

    public function __construct()
    {
        $this->socksHost = config('services.tor.socks_host', env('TOR_SOCKS_HOST', '127.0.0.1'));
        $this->socksPort = (int) config('services.tor.socks_port', env('TOR_SOCKS_PORT', 9050));
        $this->timeout = (int) config('services.tor.timeout', env('TOR_TIMEOUT', 25));
    }

    /**
     * Locate local tor.exe if installed
     */
    public function findTorBinary(): ?string
    {
        $candidates = [
            env('TOR_BINARY_PATH'),
            getenv('USERPROFILE').'\\Desktop\\Tor Browser\\Browser\\TorBrowser\\Tor\\tor.exe',
            'C:\\Program Files\\Tor Browser\\Browser\\TorBrowser\\Tor\\tor.exe',
            'C:\\Program Files (x86)\\Tor Browser\\Browser\\TorBrowser\\Tor\\tor.exe',
        ];

        foreach ($candidates as $path) {
            if (! empty($path) && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Non-blocking detection whether port 9050 or 9150 is open and listening.
     */
    public function resolveActivePort(): int
    {
        if ($this->cachedAvailability !== null) {
            return $this->socksPort;
        }

        // 1. Fast probe primary configured port (9050)
        if ($this->isPortOpen($this->socksHost, $this->socksPort, 0.05)) {
            $this->cachedAvailability = true;

            return $this->socksPort;
        }

        // 2. Fast probe Tor Browser default port (9150)
        if ($this->socksPort !== 9150 && $this->isPortOpen($this->socksHost, 9150, 0.05)) {
            $this->socksPort = 9150;
            $this->cachedAvailability = true;

            return 9150;
        }

        $this->cachedAvailability = false;

        return $this->socksPort;
    }

    /**
     * Start background Tor process with dedicated data directory
     */
    public function autoStartTor(string $binaryPath): bool
    {
        try {
            $dataDir = storage_path('tor_data');
            if (! is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }

            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf(
                    'start "" /B "%s" --SocksPort 9050 --DataDirectory "%s"',
                    $binaryPath,
                    $dataDir
                );
                pclose(popen($cmd, 'r'));
            } else {
                $cmd = sprintf(
                    '"%s" --SocksPort 9050 --DataDirectory "%s" > /dev/null 2>&1 &',
                    $binaryPath,
                    $dataDir
                );
                exec($cmd);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Tor auto-start failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Start the background Tor process and verify socket binding
     */
    public function startTor(): array
    {
        // 1. Check if already open on 9050
        if ($this->isPortOpen('127.0.0.1', 9050, 0.2)) {
            $this->cachedAvailability = true;
            $this->socksPort = 9050;
            Cache::forget('tor_status_live');

            return [
                'success' => true,
                'message' => 'Tor SOCKS5 proxy is already active on port 9050.',
                'status' => $this->checkTorStatus(),
            ];
        }

        // 2. Check if open on Tor Browser port 9150
        if ($this->isPortOpen('127.0.0.1', 9150, 0.2)) {
            $this->cachedAvailability = true;
            $this->socksPort = 9150;
            Cache::forget('tor_status_live');

            return [
                'success' => true,
                'message' => 'Tor Browser proxy is active on port 9150.',
                'status' => $this->checkTorStatus(),
            ];
        }

        $binary = $this->findTorBinary();
        if (! $binary) {
            return [
                'success' => false,
                'message' => 'Could not locate tor.exe. Ensure Tor Browser is installed.',
                'status' => $this->checkTorStatus(),
            ];
        }

        $started = $this->autoStartTor($binary);
        if (! $started) {
            return [
                'success' => false,
                'message' => 'Failed to spawn background Tor process.',
                'status' => $this->checkTorStatus(),
            ];
        }

        // Poll for up to 8 seconds for socket to open
        for ($i = 0; $i < 16; $i++) {
            usleep(500000); // 500ms
            if ($this->isPortOpen('127.0.0.1', 9050, 0.2)) {
                $this->cachedAvailability = true;
                $this->socksPort = 9050;
                Cache::forget('tor_status_live');

                return [
                    'success' => true,
                    'message' => 'Tor SOCKS5 daemon successfully started on port 9050!',
                    'status' => $this->checkTorStatus(),
                ];
            }
        }

        Cache::forget('tor_status_live');

        return [
            'success' => true,
            'message' => 'Tor process spawned (network circuit bootstrapping in progress).',
            'status' => $this->checkTorStatus(),
        ];
    }

    /**
     * Stop background Tor process
     */
    public function stopTor(): array
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                exec('taskkill /F /IM tor.exe /T 2>&1');
            } else {
                exec('pkill -f tor 2>&1');
            }
        } catch (\Throwable $e) {
            Log::warning('Error stopping Tor process: '.$e->getMessage());
        }

        $this->cachedAvailability = false;
        Cache::forget('tor_status_live');

        return [
            'success' => true,
            'message' => 'Tor SOCKS5 daemon stopped. Switched to Clearnet mode.',
            'status' => $this->checkTorStatus(),
        ];
    }

    /**
     * Non-blocking socket probe to test if a port is listening
     */
    public function isPortOpen(string $host, int $port, float $timeout = 0.3): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);

            return true;
        }

        return false;
    }

    public function isTorAvailable(): bool
    {
        if ($this->cachedAvailability !== null) {
            return $this->cachedAvailability;
        }

        $this->resolveActivePort();

        return $this->cachedAvailability ?? false;
    }

    public function getProxyUrl(): string
    {
        return sprintf('socks5h://%s:%d', $this->socksHost, $this->socksPort);
    }

    public function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * Check real-time Tor connectivity status with 30s cache
     */
    public function checkTorStatus(): array
    {
        return Cache::remember('tor_status_live', 60, function () {
            $this->resolveActivePort();
            $proxy = $this->getProxyUrl();

            // If neither 9050 nor 9150 is open, return offline instantly
            if (! $this->isTorAvailable()) {
                return [
                    'is_tor' => false,
                    'ip' => 'Tor Proxy Offline',
                    'proxy' => $proxy,
                    'status' => 'disconnected',
                    'hint' => 'Run "php artisan tor:start" to enable local SOCKS5 proxy routing.',
                ];
            }

            $client = new Client([
                RequestOptions::TIMEOUT => 3,
                RequestOptions::CONNECT_TIMEOUT => 2,
                RequestOptions::PROXY => $proxy,
                RequestOptions::VERIFY => false,
                RequestOptions::HEADERS => [
                    'User-Agent' => $this->getRandomUserAgent(),
                    'Accept' => 'application/json',
                ],
                'curl' => [
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_CONNECTTIMEOUT => 2,
                ],
            ]);

            try {
                $response = $client->get('https://check.torproject.org/api/ip');
                if ($response->getStatusCode() === 200) {
                    $data = json_decode((string) $response->getBody(), true);

                    return [
                        'is_tor' => (bool) ($data['IsTor'] ?? false),
                        'ip' => $data['IP'] ?? 'unknown',
                        'proxy' => $proxy,
                        'status' => 'connected',
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug('Tor status check warning: '.$e->getMessage());
            }

            // If port is listening but external exit check timed out, mark as ready/active
            return [
                'is_tor' => true,
                'ip' => 'SOCKS5 Active ('.$this->socksPort.')',
                'proxy' => $proxy,
                'status' => 'connected',
            ];
        });
    }

    /**
     * Resolves an .onion URL via Tor2web gateway if Tor socket is offline
     */
    public function resolveOnionUrl(string $url): string
    {
        if (! $this->isTorAvailable()) {
            // Replace .onion with .onion.pet or .onion.ws
            return preg_replace('/\.onion(\/|$)/i', '.onion.pet$1', $url);
        }

        return $url;
    }

    public function getHttpClient(bool $useTor = false, ?int $customTimeout = null): Client
    {
        $options = [
            RequestOptions::TIMEOUT => $customTimeout ?? $this->timeout,
            RequestOptions::CONNECT_TIMEOUT => $useTor ? 20 : 10,
            RequestOptions::VERIFY => false,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
            RequestOptions::HEADERS => [
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'close',
            ],
        ];

        if ($useTor && $this->isTorAvailable()) {
            $options[RequestOptions::PROXY] = $this->getProxyUrl();
        }

        return new Client($options);
    }
}

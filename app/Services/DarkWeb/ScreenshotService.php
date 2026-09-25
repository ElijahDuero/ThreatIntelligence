<?php

namespace App\Services\DarkWeb;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ScreenshotService
{
    protected TorClient $torClient;

    public function __construct(TorClient $torClient)
    {
        $this->torClient = $torClient;
    }

    /**
     * Locate Google Chrome or Microsoft Edge binary on the host system
     */
    public function findBrowserBinary(): ?string
    {
        $candidates = [
            env('CHROME_BINARY_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $path) {
            if (! empty($path) && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Capture a full headless browser screenshot of an onion or clearnet target.
     *
     * @param  string  $url  Target URL to capture
     * @param  bool  $useTor  Whether to route the headless browser through Tor SOCKS5
     * @param  int  $timeout  Maximum process execution timeout in seconds
     */
    public function capture(string $url, bool $useTor = false, int $timeout = 30): array
    {
        $binary = $this->findBrowserBinary();
        if (! $binary) {
            return [
                'success' => false,
                'error' => 'No headless browser engine (Chrome or Edge) detected on host system.',
            ];
        }

        $targetUrl = trim($url);
        if (! preg_match('#^https?://#i', $targetUrl)) {
            $targetUrl = 'https://'.$targetUrl;
        }

        $outputDir = storage_path('app/public/evidence_screenshots');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Ephemeral user data directory to prevent multi-instance lock collision on Windows
        $profileDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chrome_shot_'.bin2hex(random_bytes(6));
        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $cleanDomain = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', parse_url($targetUrl, PHP_URL_HOST) ?: 'target');
        $filename = 'evidence_'.substr($cleanDomain, 0, 32).'_'.time().'_'.substr(md5($targetUrl.microtime()), 0, 8).'.png';
        $fullPath = $outputDir.DIRECTORY_SEPARATOR.$filename;

        $args = [
            $binary,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--window-size=1280,800',
            '--hide-scrollbars',
            '--disable-notifications',
            '--ignore-certificate-errors',
            '--disable-breakpad',
            '--disable-crash-reporter',
            '--no-crash-upload',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-dev-shm-usage',
            '--enable-features=NetworkService,NetworkServiceInProcess',
            '--user-data-dir='.$profileDir,
            '--screenshot='.$fullPath,
        ];

        // Route through SOCKS5 proxy for Tor hidden services or when Tor is requested
        $isOnion = (bool) preg_match('/\.onion(\/|$)/i', $targetUrl);
        $shouldUseTor = $useTor || $isOnion;

        if ($shouldUseTor) {
            $torPort = $this->torClient->resolveActivePort();
            $args[] = "--proxy-server=socks5://127.0.0.1:{$torPort}";
            $args[] = '--host-resolver-rules=MAP * ~NOTFOUND , EXCLUDE 127.0.0.1';
        }

        $args[] = $targetUrl;

        // Ensure Windows child processes retain critical system and networking environment variables
        $processEnv = [
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\WINDOWS',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\WINDOWS',
            'PATH' => getenv('PATH') ?: (getenv('Path') ?: 'C:\\WINDOWS\\system32;C:\\WINDOWS'),
            'USERPROFILE' => getenv('USERPROFILE') ?: sys_get_temp_dir(),
            'APPDATA' => getenv('APPDATA') ?: (getenv('USERPROFILE') ? getenv('USERPROFILE').'\\AppData\\Roaming' : sys_get_temp_dir()),
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: (getenv('USERPROFILE') ? getenv('USERPROFILE').'\\AppData\\Local' : sys_get_temp_dir()),
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
            'ProgramData' => getenv('ProgramData') ?: 'C:\\ProgramData',
            'ProgramFiles' => getenv('ProgramFiles') ?: 'C:\\Program Files',
            'ProgramFiles(x86)' => getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)',
            'CommonProgramFiles' => getenv('CommonProgramFiles') ?: 'C:\\Program Files\\Common Files',
            'ComSpec' => getenv('ComSpec') ?: 'C:\\WINDOWS\\system32\\cmd.exe',
        ];

        try {
            $result = Process::timeout($timeout)->env($processEnv)->run($args);

            if (! file_exists($fullPath) || filesize($fullPath) === 0) {
                $err = $result->errorOutput() ?: $result->output();
                Log::warning('Headless screenshot failed to write: '.$err);

                return [
                    'success' => false,
                    'error' => 'Browser rendered but no screenshot image was captured.',
                    'diagnostic' => $err,
                ];
            }

            $sizeBytes = filesize($fullPath);
            $sha256 = hash_file('sha256', $fullPath);

            return [
                'success' => true,
                'url' => $targetUrl,
                'filename' => $filename,
                'storage_path' => 'evidence_screenshots/'.$filename,
                'public_url' => asset('storage/evidence_screenshots/'.$filename),
                'file_size' => $sizeBytes,
                'file_size_formatted' => $sizeBytes > 1048576
                    ? round($sizeBytes / 1048576, 2).' MB'
                    : round($sizeBytes / 1024, 1).' KB',
                'sha256' => $sha256,
                'width' => 1280,
                'height' => 800,
                'captured_at' => now()->toIso8601String(),
                'routed_tor' => $shouldUseTor,
            ];
        } catch (\Throwable $e) {
            Log::error('ScreenshotService exception: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Failed to capture screenshot: '.$e->getMessage(),
            ];
        } finally {
            if (is_dir($profileDir)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    @exec('rd /s /q "'.addslashes($profileDir).'"');
                }
            }
        }
    }
}

<?php

use App\Services\DarkWeb\TorClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tor:status', function (TorClient $torClient) {
    $this->info('--- Tor Network & SOCKS5 Socket Status ---');
    $status = $torClient->checkTorStatus();

    $this->table(
        ['Metric', 'Value'],
        [
            ['Socket Status', $status['status'] === 'connected' ? '<info>ONLINE / CONNECTED</info>' : '<error>OFFLINE / DISCONNECTED</error>'],
            ['SOCKS Proxy', $status['proxy'] ?? 'Unknown'],
            ['Tor Network Exit IP', $status['ip'] ?? 'Unknown'],
            ['Is Verified Tor Exit', ($status['is_tor'] ?? false) ? '<info>YES</info>' : '<comment>NO</comment>'],
            ['Detected Tor Binary', $torClient->findTorBinary() ?? '<comment>None found</comment>'],
        ]
    );

    if ($status['status'] !== 'connected') {
        $this->warn('Tip: Run "php artisan tor:start" to automatically launch the background Tor proxy socket.');
    }
})->purpose('Check real-time Tor SOCKS5 proxy socket and network connectivity');

Artisan::command('tor:start', function (TorClient $torClient) {
    $this->info('Initializing Tor SOCKS5 daemon...');

    if ($torClient->isPortOpen('127.0.0.1', 9050, 0.5)) {
        $this->info('Tor SOCKS5 proxy is already running and listening on 127.0.0.1:9050.');

        return 0;
    }

    if ($torClient->isPortOpen('127.0.0.1', 9150, 0.5)) {
        $this->info('Tor Browser SOCKS5 proxy is already running on 127.0.0.1:9150.');

        return 0;
    }

    $binary = $torClient->findTorBinary();
    if (! $binary) {
        $this->error('Could not locate tor.exe. Please ensure Tor Browser is installed on your Desktop or specify TOR_BINARY_PATH in .env');

        return 1;
    }

    $this->line("Found Tor executable: {$binary}");
    $this->line('Spawning background process on port 9050...');

    $torClient->autoStartTor($binary);

    for ($i = 1; $i <= 10; $i++) {
        sleep(1);
        if ($torClient->isPortOpen('127.0.0.1', 9050, 0.5)) {
            $this->info('Tor SOCKS5 proxy successfully started! Listening on 127.0.0.1:9050');

            return 0;
        }
    }

    $this->warn('Tor process spawned. It may take up to 20 seconds to finish network bootstrapping.');

    return 0;
})->purpose('Start the local Tor SOCKS5 proxy daemon');

Artisan::command('tor:stop', function (TorClient $torClient) {
    $this->info('Stopping Tor SOCKS5 daemon...');
    $res = $torClient->stopTor();
    $this->info($res['message']);
})->purpose('Stop the local Tor SOCKS5 proxy daemon');

Schedule::command('recon:poll-monitors')->everyFifteenMinutes();

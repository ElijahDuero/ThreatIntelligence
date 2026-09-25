<?php

namespace Tests\Unit;

use App\Services\DarkWeb\AhmiaBlacklistService;
use App\Services\DarkWeb\TorClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AhmiaBlacklistServiceTest extends TestCase
{
    public function test_blacklisted_domain_detected_by_md5_or_hostname(): void
    {
        $torClient = $this->createMock(TorClient::class);
        $service = new AhmiaBlacklistService($torClient);

        $bannedHost = 'maliciousdarknet777.onion';
        Cache::put('ahmia_blacklist_hashes', [md5($bannedHost)], 3600);

        $this->assertTrue($service->isBlacklisted("http://{$bannedHost}/index.html"));
        $this->assertFalse($service->isBlacklisted('http://clean-secure-source.onion/news'));
    }
}

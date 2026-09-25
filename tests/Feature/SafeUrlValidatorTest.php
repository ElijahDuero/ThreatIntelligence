<?php

namespace Tests\Feature;

use App\Services\Security\SafeUrlValidator;
use InvalidArgumentException;
use Tests\TestCase;

class SafeUrlValidatorTest extends TestCase
{
    protected SafeUrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SafeUrlValidator;
    }

    public function test_it_blocks_localhost_and_loopback(): void
    {
        $this->assertFalse($this->validator->isSafeUrl('http://localhost:8000'));
        $this->assertFalse($this->validator->isSafeUrl('https://127.0.0.1/admin'));
        $this->assertFalse($this->validator->isSafeUrl('http://127.0.0.2'));
        $this->assertFalse($this->validator->isSafeUrl('https://[::1]'));
    }

    public function test_it_blocks_cloud_metadata_imds(): void
    {
        // AWS / GCP / Azure IMDS endpoint
        $this->assertFalse($this->validator->isSafeUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($this->validator->isSafeUrl('https://169.254.169.254/'));
    }

    public function test_it_blocks_rfc1918_private_networks(): void
    {
        $this->assertFalse($this->validator->isSafeUrl('http://10.0.0.1/status'));
        $this->assertFalse($this->validator->isSafeUrl('https://172.16.0.1'));
        $this->assertFalse($this->validator->isSafeUrl('http://192.168.1.1'));
    }

    public function test_it_blocks_non_http_schemes(): void
    {
        $this->assertFalse($this->validator->isSafeUrl('file:///etc/passwd'));
        $this->assertFalse($this->validator->isSafeUrl('gopher://127.0.0.1:70/'));
        $this->assertFalse($this->validator->isSafeUrl('dict://127.0.0.1:11211/'));
        $this->assertFalse($this->validator->isSafeUrl('php://filter/read=convert.base64-encode/resource=index.php'));
    }

    public function test_it_enforces_https_when_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only HTTPS allowed');

        $this->validator->assertSafeUrl('http://example.com', requireHttps: true);
    }

    public function test_it_detects_onion_addresses_safely(): void
    {
        // Onion addresses destined for Tor proxying are safe from local SSRF
        $this->assertTrue($this->validator->isSafeUrl('https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion'));
    }

    public function test_it_validates_private_ip_helper(): void
    {
        $this->assertTrue($this->validator->isPrivateOrReservedIp('127.0.0.1'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('169.254.169.254'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('10.1.2.3'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('192.168.0.100'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('172.20.0.1'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('0.0.0.0'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('255.255.255.255'));
        $this->assertTrue($this->validator->isPrivateOrReservedIp('::1'));

        // Public DNS servers
        $this->assertFalse($this->validator->isPrivateOrReservedIp('8.8.8.8'));
        $this->assertFalse($this->validator->isPrivateOrReservedIp('1.1.1.1'));
    }
}

<?php

namespace Tests\Unit;

use App\Services\Osint\IpLookupService;
use App\Services\Osint\IpMacCorrelationService;
use App\Services\Security\SafeUrlValidator;
use Tests\TestCase;

class IpMacCorrelationServiceTest extends TestCase
{
    protected IpMacCorrelationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $safeUrlValidator = $this->createMock(SafeUrlValidator::class);
        $ipLookupService = $this->createMock(IpLookupService::class);
        $this->service = new IpMacCorrelationService($safeUrlValidator, $ipLookupService);
    }

    /**
     * Test RFC 4291 EUI-64 SLAAC IPv6 reversal to physical 48-bit MAC address.
     */
    public function test_reverses_eui64_slaac_ipv6_to_physical_mac(): void
    {
        // Vector 1: Standard EUI-64 with 00:1A:2B:3C:4D:5E -> bit 7 flipped is 02
        $ipv6 = '2001:0db8:85a3:0000:021a:2bff:fe3c:4d5e';
        $mac = $this->service->reverseEui64($ipv6);
        $this->assertSame('00:1A:2B:3C:4D:5E', $mac);

        // Vector 2: Compressed IPv6 notation with 50:7B:9D:11:22:33 -> 527b:9dff:fe11:2233
        $ipv6Compressed = 'fe80::527b:9dff:fe11:2233';
        $mac2 = $this->service->reverseEui64($ipv6Compressed);
        $this->assertSame('50:7B:9D:11:22:33', $mac2);

        // Vector 3: Non-SLAAC IPv6 (no ff:fe in middle of interface ID)
        $nonSlaac = '2001:db8::1';
        $this->assertNull($this->service->reverseEui64($nonSlaac));

        // Vector 4: Random privacy extension IPv6 (RFC 4941)
        $privacyIpv6 = '2001:db8::a1b2:c3d4:e5f6:7890';
        $this->assertNull($this->service->reverseEui64($privacyIpv6));
    }

    /**
     * Test parsing Windows arp -a tabular output for local target IP.
     */
    public function test_parses_windows_arp_tabular_output(): void
    {
        $windowsArpOutput = <<<'RAW'
Interface: 192.168.50.34 --- 0xa
  Internet Address      Physical Address      Type
  192.168.50.1          48-8f-5a-af-c0-c0     dynamic   
  192.168.50.255        ff-ff-ff-ff-ff-ff     static    
  224.0.0.22            01-00-5e-00-00-16     static    
RAW;

        $mac = $this->service->parseArpOutput($windowsArpOutput, '192.168.50.1');
        $this->assertSame('48:8F:5A:AF:C0:C0', $mac);

        // Not present in ARP table
        $missing = $this->service->parseArpOutput($windowsArpOutput, '192.168.50.99');
        $this->assertNull($missing);

        // Broadcast / Multicast addresses are ignored
        $broadcast = $this->service->parseArpOutput($windowsArpOutput, '192.168.50.255');
        $this->assertNull($broadcast);
    }

    /**
     * Test parsing Linux /proc/net/arp tabular output for local target IP.
     */
    public function test_parses_linux_proc_net_arp_output(): void
    {
        $linuxArpOutput = <<<'RAW'
IP address       HW type     Flags       HW address            Mask     Device
192.168.1.1      0x1         0x2         00:11:22:33:44:55     *        eth0
192.168.1.50     0x1         0x0         00:00:00:00:00:00     *        eth0
RAW;

        $mac = $this->service->parseArpOutput($linuxArpOutput, '192.168.1.1');
        $this->assertSame('00:11:22:33:44:55', $mac);

        // Incomplete / Zeroed MAC is ignored
        $incomplete = $this->service->parseArpOutput($linuxArpOutput, '192.168.1.50');
        $this->assertNull($incomplete);
    }

    /**
     * Test HTTP response header extraction for device/router MAC leaks.
     */
    public function test_extracts_mac_from_http_headers(): void
    {
        $headers = [
            'Server' => ['RouterOS v6.48'],
            'X-MAC-Address' => ['48:8F:5A:AF:C0:C0'],
            'Content-Type' => ['text/html'],
        ];

        $mac = $this->service->extractHttpMacHeaders($headers);
        $this->assertSame('48:8F:5A:AF:C0:C0', $mac);

        // Case-insensitive check with dashes
        $altHeaders = [
            'x-device-mac' => ['00-1a-2b-3c-4d-5e'],
        ];
        $mac2 = $this->service->extractHttpMacHeaders($altHeaders);
        $this->assertSame('00:1A:2B:3C:4D:5E', $mac2);

        // Clean headers with no leak
        $cleanHeaders = [
            'Server' => ['nginx/1.18.0'],
            'Content-Type' => ['text/html'],
        ];
        $this->assertNull($this->service->extractHttpMacHeaders($cleanHeaders));
    }

    /**
     * Test regex extraction of MAC addresses from unstructured CTI/Shodan banners.
     */
    public function test_extracts_mac_from_banner_text(): void
    {
        $banner = 'MikroTik RouterOS 6.49.2 (c) 1999-2021 http://www.mikrotik.com/ MAC: 48:8F:5A:AF:C0:C0 System: RB750Gr3';
        $mac = $this->service->extractBannerMac($banner);
        $this->assertSame('48:8F:5A:AF:C0:C0', $mac);

        $noMacBanner = 'SSH-2.0-OpenSSH_8.2p1 Ubuntu-4ubuntu0.5';
        $this->assertNull($this->service->extractBannerMac($noMacBanner));
    }

    /**
     * Test private RFC 1918 and loopback IP classification.
     */
    public function test_identifies_private_and_public_ipv4(): void
    {
        $this->assertTrue($this->service->isPrivateIp('127.0.0.1'));
        $this->assertTrue($this->service->isPrivateIp('192.168.1.1'));
        $this->assertTrue($this->service->isPrivateIp('10.0.0.1'));
        $this->assertTrue($this->service->isPrivateIp('172.16.5.10'));

        $this->assertFalse($this->service->isPrivateIp('8.8.8.8'));
        $this->assertFalse($this->service->isPrivateIp('1.1.1.1'));
        $this->assertFalse($this->service->isPrivateIp('142.250.190.46'));
    }

    /**
     * Test command injection security: invalid IP inputs must be rejected immediately.
     */
    public function test_rejects_command_injection_payloads(): void
    {
        $maliciousInputs = [
            '127.0.0.1; whoami',
            '192.168.1.1 | dir',
            '10.0.0.1 && calc.exe',
            '`reboot`',
            '$(whoami)',
        ];

        foreach ($maliciousInputs as $badInput) {
            $result = $this->service->resolveArpMac($badInput);
            $this->assertNull($result, "Expected null for malicious payload: {$badInput}");
        }
    }

    /**
     * Test correlation fallback for public WAN IP with no leak (L3 boundary reporting).
     */
    public function test_correlates_public_wan_ip_with_l3_boundary_explanation(): void
    {
        // 8.8.8.8 is a public WAN IP without local ARP or header leaks
        $result = $this->service->correlateIpToMac('8.8.8.8');

        $this->assertFalse($result['resolved']);
        $this->assertNull($result['mac']);
        $this->assertSame('l3_wan_boundary', $result['method']);
        $this->assertSame('Layer 3 WAN Boundary', $result['method_label']);
        $this->assertSame('none', $result['confidence']);
        $this->assertStringContainsString('gateway', strtolower($result['explanation']));
    }
}

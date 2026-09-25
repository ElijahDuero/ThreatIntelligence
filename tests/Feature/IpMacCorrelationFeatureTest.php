<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Osint\IpLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpMacCorrelationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_api_returns_associated_mac_structure_for_public_wan_ip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '8.8.8.8',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target' => '8.8.8.8',
                'telemetry' => [
                    'associated_mac' => [
                        'resolved' => false,
                        'mac' => null,
                        'method' => 'l3_wan_boundary',
                        'method_label' => 'Layer 3 WAN Boundary',
                        'confidence' => 'none',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'telemetry' => [
                    'associated_mac' => [
                        'resolved',
                        'mac',
                        'method',
                        'method_label',
                        'confidence',
                        'explanation',
                    ],
                ],
            ]);
    }

    public function test_probe_api_correlates_eui64_mac_for_slaac_ipv6_target(): void
    {
        $user = User::factory()->create();

        // Standard SLAAC IPv6 with embedded EUI-64 MAC (00:1A:2B:3C:4D:5E)
        $slaacIpv6 = '2001:0db8:85a3:0000:021a:2bff:fe3c:4d5e';

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => $slaacIpv6,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'classification' => [
                    'type' => 'ipv6',
                ],
                'telemetry' => [
                    'associated_mac' => [
                        'resolved' => true,
                        'mac' => '00:1A:2B:3C:4D:5E',
                        'method' => 'eui64_inversion',
                        'method_label' => 'SLAAC EUI-64 Inversion',
                        'confidence' => 'high',
                    ],
                ],
            ]);

        $macData = $response->json('telemetry.associated_mac');
        $this->assertTrue($macData['resolved']);
        $this->assertSame('00:1A:2B:3C:4D:5E', $macData['mac']);
        $this->assertNotNull($macData['vendor']);
    }

    public function test_discovered_findings_include_associated_mac_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '8.8.8.8',
        ]);

        $response->assertStatus(200);
        $findings = $response->json('discovered_findings');
        $this->assertIsArray($findings);

        $macFinding = collect($findings)->first(fn ($f) => in_array($f['id'], ['ip_associated_mac', 'ip_associated_mac_boundary']));

        $this->assertNotNull($macFinding);
        $this->assertSame('hardware', $macFinding['category']);
        $this->assertSame('Hardware & Wireless', $macFinding['category_label']);
    }

    public function test_stream_ip_probes_processes_associated_mac(): void
    {
        $ipLookupService = app(IpLookupService::class);

        $results = [];
        $progress = [];

        $summary = $ipLookupService->streamIpProbes(
            '2001:0db8:85a3:0000:021a:2bff:fe3c:4d5e',
            function (array $result) use (&$results) {
                $results[] = $result;
            },
            function (array $prog) use (&$progress) {
                $progress[] = $prog;
            }
        );

        $this->assertGreaterThan(0, $summary['total_probed']);

        // Check wireshark tool found the correlated MAC
        $wireshark = collect($results)->firstWhere('tool_id', 'wireshark');
        $this->assertNotNull($wireshark);
        $this->assertSame('found', $wireshark['status']);
        $this->assertStringContainsString('00:1A:2B:3C:4D:5E', $wireshark['summary']);
    }
}

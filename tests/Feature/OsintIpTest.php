<?php

namespace Tests\Feature;

use App\Models\Investigation;
use App\Models\User;
use App\Services\Osint\IpLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OsintIpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_ip_routes(): void
    {
        $this->get('/osint/ip')->assertRedirect('/login');
        $this->postJson('/api/osint/ip/probe', ['target' => '8.8.8.8'])->assertStatus(401);
        $this->get('/api/osint/ip/stream?target=8.8.8.8')->assertStatus(401);
    }

    public function test_authenticated_user_can_access_ip_lookup_view(): void
    {
        $user = User::factory()->create();

        Investigation::create([
            'title' => 'Operation Iron Tracer',
            'status' => 'active',
            'priority' => 'critical',
        ]);

        $response = $this->actingAs($user)->get('/osint/ip?target=8.8.8.8');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Osint/IpLookup')
                ->where('initialTarget', '8.8.8.8')
                ->has('categorizedTools.geolocation', 8)
                ->has('categorizedTools.host_port_discovery', 13)
                ->has('categorizedTools.ipv4', 8)
                ->has('categorizedTools.ipv6', 1)
                ->has('categorizedTools.bgp', 4)
                ->has('categorizedTools.reputation', 3)
                ->has('categorizedTools.blacklists', 4)
                ->has('categorizedTools.neighbor_domains', 4)
                ->has('categorizedTools.protected_by_cloud', 2)
                ->has('categorizedTools.wireless_network_info', 2)
                ->has('categorizedTools.network_analysis_tools', 4)
                ->has('categorizedTools.ip_loggers', 3)
                ->where('totalToolsCount', 56)
                ->has('investigations')
                ->has('initialClassification')
                ->has('initialProbe')
                ->has('initialFindings')
                ->has('findingCategories')
            );
    }

    public function test_probe_api_validates_target(): void
    {
        $user = User::factory()->create();

        // Empty target
        $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['target']);

        // Single character target (min 2)
        $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => 'a',
        ])->assertStatus(422)->assertJsonValidationErrors(['target']);
    }

    public function test_probe_api_classifies_and_probes_ipv4(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '8.8.8.8',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target' => '8.8.8.8',
                'classification' => [
                    'type' => 'ipv4',
                    'clean_target' => '8.8.8.8',
                    'is_valid' => true,
                    'resolved_ip' => '8.8.8.8',
                ],
                'tools_count' => 56,
            ])
            ->assertJsonStructure([
                'success',
                'target',
                'timestamp',
                'classification' => ['type', 'clean_target', 'is_valid', 'resolved_ip', 'notes'],
                'telemetry' => ['geoip', 'threat_summary'],
                'discovered_findings',
                'tools_count',
                'counts',
                'categorized_tools',
            ]);
    }

    public function test_probe_api_classifies_and_probes_ipv6(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '2001:4860:4860::8888',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'classification' => [
                    'type' => 'ipv6',
                    'clean_target' => '2001:4860:4860::8888',
                    'is_valid' => true,
                ],
            ]);
    }

    public function test_probe_api_classifies_and_probes_hostname(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => 'dns.google',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'classification' => [
                    'type' => 'hostname',
                    'clean_target' => 'dns.google',
                    'is_valid' => true,
                ],
            ]);
    }

    public function test_probe_api_classifies_and_probes_mac_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '00:1A:2B:3C:4D:5E',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'classification' => [
                    'type' => 'mac',
                    'clean_target' => '00:1A:2B:3C:4D:5E',
                    'is_valid' => true,
                ],
            ])
            ->assertJsonStructure([
                'telemetry' => [
                    'mac' => ['mac', 'oui_prefix', 'vendor', 'is_multicast', 'is_local', 'block_type'],
                ],
            ]);
    }

    public function test_sse_stream_rejects_missing_target(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/osint/ip/stream?target=');

        $response->assertStatus(400);
    }

    public function test_sse_stream_returns_event_stream_for_valid_target(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/osint/ip/stream?target=1.1.1.1');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_service_provides_all_framework_branches_and_tools(): void
    {
        $service = app(IpLookupService::class);
        $categorized = $service->getCategorizedTools('8.8.8.8');

        $this->assertCount(12, $categorized);
        $this->assertArrayHasKey('geolocation', $categorized);
        $this->assertArrayHasKey('host_port_discovery', $categorized);
        $this->assertArrayHasKey('ipv4', $categorized);
        $this->assertArrayHasKey('ipv6', $categorized);
        $this->assertArrayHasKey('bgp', $categorized);
        $this->assertArrayHasKey('reputation', $categorized);
        $this->assertArrayHasKey('blacklists', $categorized);
        $this->assertArrayHasKey('neighbor_domains', $categorized);
        $this->assertArrayHasKey('protected_by_cloud', $categorized);
        $this->assertArrayHasKey('wireless_network_info', $categorized);
        $this->assertArrayHasKey('network_analysis_tools', $categorized);
        $this->assertArrayHasKey('ip_loggers', $categorized);

        $allTools = $service->getAllTools('8.8.8.8');
        $this->assertEquals(56, count($allTools));
    }

    public function test_probe_api_returns_structured_discovered_findings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/ip/probe', [
            'target' => '8.8.8.8',
        ]);

        $response->assertStatus(200);
        $findings = $response->json('discovered_findings');
        $this->assertIsArray($findings);
        $this->assertNotEmpty($findings);

        // Verify each finding has the required Username Recon structure
        foreach ($findings as $finding) {
            $this->assertArrayHasKey('platform', $finding);
            $this->assertArrayHasKey('website_domain', $finding);
            $this->assertArrayHasKey('url', $finding);
            $this->assertArrayHasKey('category', $finding);
            $this->assertArrayHasKey('status', $finding);
            $this->assertArrayHasKey('summary', $finding);
        }
    }
}

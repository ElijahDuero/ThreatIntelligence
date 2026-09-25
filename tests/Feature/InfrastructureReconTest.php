<?php

namespace Tests\Feature;

use App\Models\Investigation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InfrastructureReconTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_infra_recon_redirects_unauthenticated_guests(): void
    {
        $response = $this->get(route('infra_recon.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_infra_recon_page_can_be_rendered_for_authenticated_operators(): void
    {
        Investigation::create([
            'title' => 'Project Ghost',
            'status' => 'active',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($this->user)->get(route('infra_recon.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Infrastructure/Index')
            ->has('investigations', 1)
        );
    }

    public function test_inspect_domain_validates_domain_input(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('infra_recon.domain'), [
            'domain' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_inspect_domain_executes_and_returns_structured_telemetry(): void
    {
        Http::fake([
            'https://crt.sh/*' => Http::response([
                [
                    'id' => 12345,
                    'name_value' => "api.example.com\nadmin.example.com",
                    'issuer_name' => 'C=US, O=Let\'s Encrypt, CN=R3',
                    'not_before' => '2026-01-01T00:00:00',
                    'not_after' => '2026-04-01T00:00:00',
                ],
            ], 200),
            'https://rdap.org/domain/*' => Http::response([
                'status' => ['active', 'clientTransferProhibited'],
                'entities' => [
                    [
                        'roles' => ['registrar'],
                        'vcardArray' => [
                            'vcard',
                            [
                                ['fn', [], 'text', 'MarkMonitor Inc.'],
                            ],
                        ],
                    ],
                ],
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '2010-01-15T00:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2030-01-15T00:00:00Z'],
                ],
                'nameservers' => [
                    ['ldhName' => 'ns1.example.com'],
                    ['ldhName' => 'ns2.example.com'],
                ],
            ], 200),
            'http://ip-api.com/json/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'California',
                'city' => 'San Francisco',
                'isp' => 'Cloudflare, Inc.',
                'org' => 'Cloudflare Network',
                'as' => 'AS13335 Cloudflare, Inc.',
            ], 200),
            'https://rdap.org/ip/*' => Http::response([
                'name' => 'CLOUDFLARE-NET',
                'handle' => 'NET-104-16-0-0-1',
                'startAddress' => '104.16.0.0',
                'endAddress' => '104.31.255.255',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('infra_recon.domain'), [
            'domain' => 'https://example.com/some/path',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target' => 'example.com',
                'type' => 'domain',
            ])
            ->assertJsonStructure([
                'success',
                'target',
                'type',
                'dns' => ['records', 'grouped', 'counts'],
                'subdomains' => ['total_found', 'subdomains'],
                'rdap' => ['domain', 'registrar', 'nameservers'],
                'summary',
            ]);

        $data = $response->json();
        $this->assertEquals('example.com', $data['target']);
        $this->assertEquals('MarkMonitor Inc.', $data['rdap']['registrar']);
        $this->assertCount(2, $data['subdomains']['subdomains']);
    }

    public function test_inspect_ip_validates_ip_format(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('infra_recon.ip'), [
            'ip' => 'not-an-ip-address',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid IPv4 or IPv6 address.',
            ]);
    }

    public function test_inspect_ip_returns_geoip_and_asn_intel(): void
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'California',
                'city' => 'San Jose',
                'isp' => 'Cloudflare, Inc.',
                'org' => 'Cloudflare, Inc.',
                'as' => 'AS13335 Cloudflare, Inc.',
                'lat' => 37.3382,
                'lon' => -121.8863,
                'timezone' => 'America/Los_Angeles',
            ], 200),
            'https://rdap.org/ip/*' => Http::response([
                'name' => 'CLOUDFLARE-NET',
                'handle' => 'NET-1-1-1-0-24',
                'startAddress' => '1.1.1.0',
                'endAddress' => '1.1.1.255',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('infra_recon.ip'), [
            'ip' => '1.1.1.1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ip' => '1.1.1.1',
                'country' => 'United States',
                'isp' => 'Cloudflare, Inc.',
                'as' => 'AS13335 Cloudflare, Inc.',
            ]);
    }

    public function test_link_to_investigation_injects_nodes_and_edges_into_case_graph(): void
    {
        $investigation = Investigation::create([
            'title' => 'Case Alpha',
            'status' => 'active',
            'priority' => 'critical',
            'graph_data' => [
                'nodes' => [
                    ['id' => 'case_1', 'label' => 'Existing Case Node', 'type' => 'case'],
                ],
                'edges' => [],
            ],
        ]);

        $nodes = [
            ['id' => 'dom_target_com', 'label' => 'target.com', 'type' => 'domain'],
            ['id' => 'ip_104_21_1_1', 'label' => '104.21.1.1', 'type' => 'ip'],
        ];

        $edges = [
            ['id' => 'edge_test_1', 'source' => 'dom_target_com', 'target' => 'ip_104_21_1_1', 'label' => 'resolves_to'],
        ];

        $response = $this->actingAs($this->user)->postJson(route('infra_recon.link_graph'), [
            'investigation_id' => $investigation->id,
            'nodes' => $nodes,
            'edges' => $edges,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'investigation_id' => $investigation->id,
                'added_nodes' => 2,
                'added_edges' => 1,
                'total_nodes' => 3,
                'total_edges' => 1,
            ]);

        $investigation->refresh();
        $this->assertCount(3, $investigation->graph_data['nodes']);
        $this->assertCount(1, $investigation->graph_data['edges']);
    }

    public function test_inspect_ports_returns_passive_ports_and_cves(): void
    {
        Http::fake([
            'https://internetdb.shodan.io/*' => Http::response([
                'ip' => '1.1.1.1',
                'ports' => [53, 80, 443],
                'vulns' => ['CVE-2021-41773'],
                'cpes' => ['cpe:/a:cloudflare:cloudflare'],
                'tags' => ['cloud'],
                'hostnames' => ['one.one.one.one'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('infra_recon.ports'), [
            'ip' => '1.1.1.1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ip' => '1.1.1.1',
                'intel' => [
                    'total_ports' => 3,
                    'total_cves' => 1,
                    'has_vulnerabilities' => true,
                ],
            ]);

        $data = $response->json();
        $this->assertEquals('DNS Name Server', $data['intel']['ports'][0]['service']);
        $this->assertEquals('CVE-2021-41773', $data['intel']['cves'][0]['id']);
    }
}

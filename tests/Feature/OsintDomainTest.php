<?php

namespace Tests\Feature;

use App\Models\Investigation;
use App\Models\User;
use App\Services\Osint\DomainLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OsintDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_domain_routes(): void
    {
        $this->get('/osint/domain')->assertRedirect('/login');
        $this->postJson('/api/osint/domain/probe', ['target' => 'github.com'])->assertStatus(401);
        $this->get('/api/osint/domain/stream?target=github.com')->assertStatus(401);
    }

    public function test_authenticated_user_can_access_domain_lookup_view(): void
    {
        $user = User::factory()->create();

        Investigation::create([
            'title' => 'Operation Deep Vector',
            'status' => 'active',
            'priority' => 'critical',
        ]);

        $response = $this->actingAs($user)->get('/osint/domain?target=github.com');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Osint/DomainLookup')
                ->where('initialTarget', 'github.com')
                ->has('categorizedTools.subdomains', 4)
                ->has('categorizedTools.host_port', 4)
                ->has('categorizedTools.web_dom', 4)
                ->has('categorizedTools.threat', 4)
                ->has('categorizedTools.dns', 4)
                ->where('totalToolsCount', 20)
                ->has('investigations')
                ->has('initialProbe')
                ->has('initialFindings')
                ->has('findingCategories', 6)
            );
    }

    public function test_probe_api_validates_domain_target(): void
    {
        $user = User::factory()->create();

        // Empty target
        $this->actingAs($user)->postJson('/api/osint/domain/probe', [
            'target' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['target']);

        // Single character target (min 2)
        $this->actingAs($user)->postJson('/api/osint/domain/probe', [
            'target' => 'a',
        ])->assertStatus(422)->assertJsonValidationErrors(['target']);
    }

    public function test_probe_api_returns_structured_telemetry_and_findings(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'California',
                'city' => 'San Francisco',
                'lat' => 37.77,
                'lon' => -122.41,
                'isp' => 'GitHub, Inc.',
                'as' => 'AS36459 GitHub, Inc.',
            ], 200),
            'https://internetdb.shodan.io/*' => Http::response([
                'ports' => [80, 443],
                'hostnames' => ['github.com'],
                'vulns' => [],
            ], 200),
            'https://urlscan.io/api/v1/search/*' => Http::response([
                'results' => [
                    [
                        '_id' => 'abc-123',
                        'page' => [
                            'server' => 'GitHub.com',
                            'title' => 'GitHub: Let\'s build from here',
                            'ip' => '140.82.121.4',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/api/osint/domain/probe', [
            'target' => 'github.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'target',
                'duration_ms',
                'telemetry' => [
                    'domain',
                    'primary_ip',
                    'dns_records',
                    'nameservers',
                    'mail_servers',
                    'txt_records',
                    'geoip',
                    'whois',
                ],
                'initial_findings',
            ]);

        $this->assertEquals('github.com', $response->json('target'));
    }

    public function test_stream_domain_returns_event_stream_with_probes(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://internetdb.shodan.io/*' => Http::response([
                'ports' => [80, 443],
                'hostnames' => ['github.com'],
            ], 200),
            'https://urlscan.io/api/v1/search/*' => Http::response([
                'results' => [],
            ], 200),
            'https://crt.sh/*' => Http::response([
                ['name_value' => "api.github.com\ngithub.com"],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/api/osint/domain/stream?target=github.com');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: start', $content);
        $this->assertStringContainsString('event: progress', $content);
        $this->assertStringContainsString('event: done', $content);
    }

    public function test_domain_lookup_service_sanitizes_urls_and_subdomains(): void
    {
        $service = app(DomainLookupService::class);

        $this->assertEquals('github.com', $service->sanitizeDomain('https://github.com/torvalds/linux'));
        $this->assertEquals('api.cloudflare.com', $service->sanitizeDomain('http://api.cloudflare.com:8080/v4/user'));
        $this->assertEquals('shodan.io', $service->sanitizeDomain('  SHODAN.IO  '));
        $this->assertEquals('', $service->sanitizeDomain('invalid-domain'));
    }
}

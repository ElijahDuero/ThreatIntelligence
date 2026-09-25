<?php

namespace Tests\Feature;

use App\Models\Investigation;
use App\Models\User;
use App\Services\Osint\EmailLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OsintEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_email_routes(): void
    {
        $this->get('/osint/email')->assertRedirect('/login');
        $this->postJson('/api/osint/email/probe', ['email' => 'analyst@domain.com'])->assertStatus(401);
    }

    public function test_authenticated_user_can_access_email_lookup_view(): void
    {
        $user = User::factory()->create();

        Investigation::create([
            'title' => 'Operation Deep Vector',
            'status' => 'active',
            'priority' => 'critical',
        ]);

        $response = $this->actingAs($user)->get('/osint/email?email=operator@proton.me');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Osint/EmailLookup')
                ->where('initialEmail', 'operator@proton.me')
                ->has('categorizedTools.email_search', 13)
                ->has('categorizedTools.common_formats', 2)
                ->has('categorizedTools.verification', 10)
                ->has('categorizedTools.breach_data', 4)
                ->has('categorizedTools.mail_blacklists', 1)
                ->where('totalToolsCount', 30)
                ->has('investigations')
                ->has('initialProbe')
            );
    }

    public function test_probe_api_validates_email(): void
    {
        $user = User::factory()->create();

        // Empty email
        $this->actingAs($user)->postJson('/api/osint/email/probe', [
            'email' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        // Missing domain/at
        $this->actingAs($user)->postJson('/api/osint/email/probe', [
            'email' => 'invalid-no-at-sign',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_probe_api_returns_structured_telemetry_for_target(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/email/probe', [
            'email' => 'target.analyst@example.com',
            'probe_live' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'email' => 'target.analyst@example.com',
                'tools_count' => 30,
                'counts' => [
                    'email_search' => 13,
                    'common_formats' => 2,
                    'verification' => 10,
                    'breach_data' => 4,
                    'mail_blacklists' => 1,
                    'total_tools' => 30,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'email',
                'timestamp',
                'syntax' => ['is_valid', 'local_part', 'domain', 'has_plus_addressing', 'base_local_part'],
                'mx',
                'hygiene' => ['domain', 'is_disposable', 'is_free_webmail', 'classification', 'risk_level'],
                'permutations',
                'categorized_tools' => [
                    'email_search',
                    'common_formats',
                    'verification',
                    'breach_data',
                    'mail_blacklists',
                ],
                'counts',
            ]);
    }

    public function test_disposable_domain_detection(): void
    {
        $service = new EmailLookupService;
        $hygiene = $service->checkDomainHygiene('tempmail.com');

        $this->assertTrue($hygiene['is_disposable']);
        $this->assertEquals('disposable_burner', $hygiene['classification']);
        $this->assertEquals('critical', $hygiene['risk_level']);
    }

    public function test_free_webmail_detection(): void
    {
        $service = new EmailLookupService;
        $hygiene = $service->checkDomainHygiene('gmail.com');

        $this->assertFalse($hygiene['is_disposable']);
        $this->assertTrue($hygiene['is_free_webmail']);
        $this->assertEquals('free_consumer_webmail', $hygiene['classification']);
        $this->assertEquals('medium', $hygiene['risk_level']);
    }

    public function test_permutation_generation(): void
    {
        $service = new EmailLookupService;
        $permutations = $service->generatePermutations('john.doe@cybercorp.com');

        $this->assertNotEmpty($permutations);
        $addresses = array_column($permutations, 'address');

        $this->assertContains('john.doe@cybercorp.com', $addresses);
        $this->assertContains('jdoe@cybercorp.com', $addresses);
        $this->assertContains('j.doe@cybercorp.com', $addresses);
        $this->assertContains('john@cybercorp.com', $addresses);
    }

    public function test_stream_endpoint_validates_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/osint/email/stream?email=invalid-no-at');

        $response->assertStatus(400);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
    }

    public function test_stream_endpoint_streams_events_for_valid_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/osint/email/stream?email=satoshin@gmx.com');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
    }

    public function test_dnsbl_query_returns_servers_structure(): void
    {
        $service = new EmailLookupService;
        $dnsbl = $service->queryDnsbl('example.com');

        $this->assertArrayHasKey('target_host', $dnsbl);
        $this->assertArrayHasKey('listed_count', $dnsbl);
        $this->assertArrayHasKey('servers', $dnsbl);
        $this->assertCount(5, $dnsbl['servers']);
    }
}

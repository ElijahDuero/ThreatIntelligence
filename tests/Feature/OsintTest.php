<?php

namespace Tests\Feature;

use App\Models\Investigation;
use App\Models\User;
use App\Services\Osint\UsernameLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OsintTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_osint_routes(): void
    {
        $this->get('/osint')->assertRedirect('/login');
        $this->get('/osint/username')->assertRedirect('/login');
        $this->get('/osint/domain')->assertRedirect('/login');
        $this->postJson('/api/osint/username/probe', ['username' => 'testuser'])->assertStatus(401);
    }

    public function test_authenticated_user_can_access_osint_hub(): void
    {
        $user = User::factory()->create();

        Investigation::create([
            'title' => 'Operation Silent Owl',
            'status' => 'active',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($user)->get('/osint');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Osint/Index')
                ->has('categories', 4)
                ->has('investigations', 1)
            );
    }

    public function test_authenticated_user_can_access_username_lookup_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/osint/username?username=satoshi');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Osint/UsernameLookup')
                ->where('initialUsername', 'satoshi')
                ->has('initialEngines', 12)
                ->has('initialSites', 7)
                ->has('investigations')
            );
    }

    public function test_probe_api_validates_username(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/username/probe', [
            'username' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_probe_api_returns_engines_and_specific_sites(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/username/probe', [
            'username' => 'testoperator',
            'probe_live' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'username' => 'testoperator',
            ])
            ->assertJsonStructure([
                'success',
                'username',
                'timestamp',
                'counts' => ['search_engines', 'specific_sites', 'live_found'],
                'search_engines',
                'specific_sites',
            ]);
    }

    public function test_probe_api_rejects_malicious_characters(): void
    {
        $user = User::factory()->create();

        // Path traversal / shell / XSS attempts
        $maliciousInputs = [
            '../../etc/passwd',
            '<script>alert(1)</script>',
            'user; DROP TABLE users;--',
            'user name with spaces',
            'user$#@!',
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->actingAs($user)->postJson('/api/osint/username/probe', [
                'username' => $input,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['username']);
        }
    }

    public function test_probe_api_strips_leading_at_sign(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/osint/username/probe', [
            'username' => '@validtarget',
            'probe_live' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'username' => 'validtarget',
            ]);
    }

    public function test_probe_specific_site_blocks_ssrf(): void
    {
        $service = app(UsernameLookupService::class);

        $fakeSite = [
            'id' => 'malicious_imds',
            'name' => 'Internal Metadata',
            'tag' => 'Method',
            'supports_live_probe' => true,
            'probe_url' => 'http://169.254.169.254/latest/meta-data',
            'method_note' => 'Test',
        ];

        $result = $service->probeSpecificSite($fakeSite, 'target');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Blocked', $result['status_label']);
        $this->assertStringContainsString('SSRF security policy', $result['details']);
    }
}

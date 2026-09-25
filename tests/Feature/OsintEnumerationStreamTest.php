<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Osint\PlatformEnumerationService;
use App\Services\Security\SafeUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OsintEnumerationStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_stream_endpoint(): void
    {
        $response = $this->get('/api/osint/username/stream?username=testuser');

        $this->assertTrue(in_array($response->getStatusCode(), [302, 401], true));
    }

    public function test_authenticated_user_receives_sse_stream_headers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/osint/username/stream?username=kenshi');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Accel-Buffering', 'no');
    }

    public function test_invalid_username_emits_error_sse_event(): void
    {
        $user = User::factory()->create();

        // Target with spaces / invalid characters
        $response = $this->actingAs($user)->get('/api/osint/username/stream?username=invalid%20name%20with%20spaces');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: error', $content);
        $this->assertStringContainsString('The target alias is invalid or missing', $content);
    }

    public function test_valid_username_stream_emits_start_and_done_events(): void
    {
        $user = User::factory()->create();

        // Scope to empty/minimal category to run fast in test suite
        $response = $this->actingAs($user)->get('/api/osint/username/stream?username=satoshinakamoto&categories=forums');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: start', $content);
        $this->assertStringContainsString('"username":"satoshinakamoto"', $content);
        $this->assertStringContainsString('event: done', $content);
        $this->assertStringContainsString('"total_probed":', $content);
    }

    public function test_platform_enumeration_service_catalog_integrity(): void
    {
        $service = app(PlatformEnumerationService::class);
        $platforms = $service->getPlatforms();

        $this->assertGreaterThanOrEqual(60, count($platforms));

        foreach ($platforms as $p) {
            $this->assertArrayHasKey('id', $p);
            $this->assertArrayHasKey('name', $p);
            $this->assertArrayHasKey('category', $p);
            $this->assertArrayHasKey('url_template', $p);
            $this->assertStringContainsString('{username}', $p['url_template']);

            $compiled = $service->buildProfileUrl($p, 'operator');
            $this->assertStringContainsString('operator', $compiled);
            $this->assertStringStartsWith('https://', $compiled);
        }
    }

    public function test_platform_enumeration_service_category_filtering(): void
    {
        $service = app(PlatformEnumerationService::class);

        $codePlatforms = $service->getPlatforms(['code']);
        $this->assertNotEmpty($codePlatforms);

        foreach ($codePlatforms as $cp) {
            $this->assertEquals('code', $cp['category']);
        }

        $allCategories = $service->getCategories();
        $this->assertArrayHasKey('code', $allCategories);
        $this->assertArrayHasKey('social', $allCategories);
        $this->assertArrayHasKey('gaming', $allCategories);
        $this->assertArrayHasKey('tech', $allCategories);
        $this->assertArrayHasKey('forums', $allCategories);
        $this->assertArrayHasKey('media', $allCategories);
    }

    public function test_ssrf_protection_drops_private_ip_targets(): void
    {
        $validator = app(SafeUrlValidator::class);

        $this->assertFalse($validator->isSafeUrl('http://127.0.0.1/profile'));
        $this->assertFalse($validator->isSafeUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($validator->isSafeUrl('http://192.168.1.1/admin'));
        $this->assertTrue($validator->isSafeUrl('https://github.com/torvalds'));
    }
}

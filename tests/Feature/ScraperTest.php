<?php

namespace Tests\Feature;

use App\Models\ScrapedTarget;
use App\Models\User;
use App\Services\DarkWeb\DeepScraperService;
use App\Services\DarkWeb\ScreenshotService;
use App\Services\DarkWeb\SearchEngineManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ScraperTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_scraper_index_page_can_be_rendered(): void
    {
        $response = $this->get(route('scraper.index'));

        $response->assertStatus(200);
    }

    public function test_screenshot_endpoint_requires_valid_url(): void
    {
        $response = $this->postJson(route('scraper.screenshot'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_screenshot_endpoint_captures_and_updates_target(): void
    {
        $target = ScrapedTarget::create([
            'url' => 'https://example.com',
            'title' => 'Example Domain',
            'status_code' => 200,
            'is_blacklisted' => false,
            'response_time_seconds' => 0.25,
            'server' => 'nginx',
            'headers' => ['content-type' => 'text/html'],
            'metadata' => [],
            'keywords' => ['example', 'domain'],
            'sentiment' => [],
            'emails' => [],
            'documents' => [],
            'images' => [],
            'internal_links' => [],
            'external_links' => [],
            'raw_text_sample' => 'Example text',
        ]);

        $this->mock(ScreenshotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('capture')
                ->once()
                ->with('https://example.com', false)
                ->andReturn([
                    'success' => true,
                    'url' => 'https://example.com',
                    'filename' => 'evidence_example_123.png',
                    'storage_path' => 'evidence_screenshots/evidence_example_123.png',
                    'public_url' => 'http://localhost/storage/evidence_screenshots/evidence_example_123.png',
                    'file_size' => 45678,
                    'file_size_formatted' => '44.6 KB',
                    'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                    'width' => 1280,
                    'height' => 800,
                    'captured_at' => now()->toIso8601String(),
                    'routed_tor' => false,
                ]);
        });

        $response = $this->postJson(route('scraper.screenshot'), [
            'url' => 'https://example.com',
            'target_id' => $target->id,
            'use_tor' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'storage_path' => 'evidence_screenshots/evidence_example_123.png',
            ]);

        $target->refresh();
        $this->assertEquals('evidence_screenshots/evidence_example_123.png', $target->screenshot_path);
        $this->assertIsArray($target->screenshot_metadata);
        $this->assertEquals('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $target->screenshot_metadata['sha256']);
    }

    public function test_scrape_endpoint_accepts_and_stores_custom_keywords_and_intel(): void
    {
        $mockKeywords = ['bitcoin', 'wallet', 'leak'];
        $mockIntel = [
            'query_keywords' => $mockKeywords,
            'total_matches_count' => 5,
            'crawl_subpages_enabled' => true,
            'crawled_pages_count' => 1,
            'summary' => 'Found 5 matches across 3 keywords',
            'keyword_matches' => [
                [
                    'keyword' => 'bitcoin',
                    'hit_count' => 3,
                    'primary_hits' => 2,
                    'subpage_hits' => 1,
                    'sources' => ['body', 'subpages'],
                    'snippets' => ['...deposit bitcoin here...'],
                ],
            ],
            'crawled_subpages' => [
                [
                    'url' => 'https://example.com/wallet',
                    'title' => 'Wallet Page',
                    'status_code' => 200,
                    'hit_count' => 1,
                    'matched_keywords' => ['bitcoin'],
                    'snippets' => ['...deposit bitcoin here...'],
                ],
            ],
        ];

        $this->mock(DeepScraperService::class, function (MockInterface $mock) use ($mockKeywords, $mockIntel) {
            $mock->shouldReceive('scrape')
                ->once()
                ->with('https://example.com', false, false, 'bitcoin, wallet, leak', true)
                ->andReturn([
                    'status' => 'success',
                    'url' => 'https://example.com',
                    'title' => 'Target Title',
                    'status_code' => 200,
                    'is_blacklisted' => false,
                    'response_time_seconds' => 0.15,
                    'server' => 'nginx',
                    'headers' => ['content-type' => 'text/html'],
                    'metadata' => [],
                    'keywords' => ['crypto', 'target'],
                    'custom_keywords' => $mockKeywords,
                    'keyword_intel' => $mockIntel,
                    'sentiment' => ['polarity' => 0, 'subjectivity' => 0, 'label' => 'Neutral'],
                    'emails' => ['intel@example.com'],
                    'documents' => [],
                    'images' => [],
                    'internal_links' => ['https://example.com/wallet'],
                    'external_links' => [],
                    'raw_text_sample' => 'deposit bitcoin here in secure wallet',
                ]);
        });

        $response = $this->postJson(route('scraper.scrape'), [
            'url' => 'https://example.com',
            'collect_images' => false,
            'use_tor' => false,
            'capture_screenshot' => false,
            'custom_keywords' => 'bitcoin, wallet, leak',
            'crawl_subpages' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'custom_keywords' => $mockKeywords,
                'keyword_intel' => [
                    'total_matches_count' => 5,
                ],
            ]);

        $this->assertDatabaseHas('scraped_targets', [
            'url' => 'https://example.com',
            'title' => 'Target Title',
        ]);

        $target = ScrapedTarget::where('url', 'https://example.com')->first();
        $this->assertNotNull($target);
        $this->assertEquals($mockKeywords, $target->custom_keywords);
        $this->assertEquals(5, $target->keyword_intel['total_matches_count']);
    }

    public function test_deep_scraper_service_parses_keywords_and_extracts_snippets(): void
    {
        /** @var DeepScraperService $service */
        $service = app(DeepScraperService::class);

        $parsed = $service->parseCustomKeywords('bitcoin, wallet; credentials, , password');
        $this->assertEquals(['bitcoin', 'wallet', 'credentials', 'password'], $parsed);

        $text = 'Notice: All users must update their wallet address before transferring any bitcoin tokens.';
        $snippets = $service->extractSnippets($text, 'wallet');
        $this->assertNotEmpty($snippets);
        $this->assertStringContainsString('wallet', $snippets[0]);
    }

    public function test_discover_targets_endpoint_returns_matching_onion_sites(): void
    {
        $this->mock(SearchEngineManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')
                ->once()
                ->with('Crypto', 10, 'onionfind', false)
                ->andReturn([
                    [
                        'idx' => 1,
                        'title' => 'CryptoMixer Onion',
                        'url' => 'http://cryptomixer7xyz.onion',
                        'description' => 'Fast and secure Bitcoin mixer on darknet',
                        'engine' => 'OnionFind',
                        'is_onion' => true,
                        'is_blacklisted' => false,
                    ],
                ]);
        });

        $response = $this->getJson(route('scraper.discover_targets', [
            'query' => 'Crypto',
            'engine' => 'onionfind',
            'amount' => 10,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'query' => 'Crypto',
                'onion_count' => 1,
                'targets' => [
                    [
                        'title' => 'CryptoMixer Onion',
                        'url' => 'http://cryptomixer7xyz.onion',
                        'is_onion' => true,
                    ],
                ],
            ]);
    }
}

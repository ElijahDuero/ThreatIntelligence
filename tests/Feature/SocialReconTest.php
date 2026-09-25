<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialReconTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_social_recon_redirects_unauthenticated_guests(): void
    {
        $response = $this->get(route('social_recon.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_social_recon_page_can_be_rendered_for_authenticated_operators(): void
    {
        $response = $this->actingAs($this->user)->get(route('social_recon.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SocialRecon/Index')
            ->has('catalog')
            ->has('investigations')
            ->has('totalPlatforms')
        );
    }

    public function test_probe_batch_requires_valid_username(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.probe_batch'), [
            'username' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_probe_batch_dispatches_http_probes_and_returns_status(): void
    {
        Http::fake([
            'https://github.com/octocat' => Http::response('OK', 200),
            'https://gitlab.com/octocat' => Http::response('Not Found', 404),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.probe_batch'), [
            'username' => 'octocat',
            'platform_ids' => ['github', 'gitlab'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'username' => 'octocat',
            ])
            ->assertJsonPath('results.0.id', 'github')
            ->assertJsonPath('results.0.exists', true)
            ->assertJsonPath('results.1.id', 'gitlab')
            ->assertJsonPath('results.1.exists', false);
    }

    public function test_reddit_search_endpoint_parses_json_results(): void
    {
        Http::fake([
            'https://www.reddit.com/search.json*' => Http::response([
                'data' => [
                    'children' => [
                        [
                            'data' => [
                                'id' => 'abc123',
                                'title' => 'Darknet vulnerability found in Tor relay',
                                'author' => 'osint_researcher',
                                'subreddit' => 'netsec',
                                'subreddit_name_prefixed' => 'r/netsec',
                                'permalink' => '/r/netsec/comments/abc123/darknet_vulnerability/',
                                'score' => 142,
                                'num_comments' => 28,
                                'created_utc' => 1700000000,
                                'selftext' => 'Full analysis of onion routing flaw...',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.reddit'), [
            'query' => 'darknet vulnerability',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total' => 1,
            ])
            ->assertJsonPath('results.0.id', 'abc123')
            ->assertJsonPath('results.0.title', 'Darknet vulnerability found in Tor relay')
            ->assertJsonPath('results.0.author', 'osint_researcher');
    }

    public function test_telegram_scraper_endpoint_parses_public_web_channel(): void
    {
        $mockHtml = '
            <html>
                <body>
                    <div class="tgme_channel_info_header_title">Cyber Threat Intel</div>
                    <div class="tgme_channel_info_description">Daily dark web leaks</div>
                    <div class="tgme_channel_info_counter"><span class="counter_value">15.4K</span></div>
                    <div class="tgme_widget_message_wrap">
                        <div class="tgme_widget_message" data-post="threatintel/42">
                            <div class="tgme_widget_message_text">Critical CVE-2026 database dump leaked</div>
                            <span class="tgme_widget_message_views">1.2K</span>
                            <a class="tgme_widget_message_date" href="https://t.me/threatintel/42"><time datetime="2026-09-15T12:00:00Z"></time></a>
                        </div>
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            'https://t.me/s/threatintel*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.telegram'), [
            'channel' => 'threatintel',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total' => 1,
            ])
            ->assertJsonPath('channel.title', 'Cyber Threat Intel')
            ->assertJsonPath('messages.0.text', 'Critical CVE-2026 database dump leaked');
    }

    public function test_telegram_scraper_extracts_threat_iocs_and_forwarded_sources(): void
    {
        $mockHtml = '
            <html>
                <body>
                    <div class="tgme_channel_info_header_title">Leak Channel</div>
                    <div class="tgme_channel_info_description">Exposing breaches</div>
                    <div class="tgme_channel_info_counter"><span class="counter_value">50K</span></div>
                    <div class="tgme_widget_message_wrap">
                        <div class="tgme_widget_message" data-post="leakfeed/101">
                            <a class="tgme_widget_message_forwarded_from_name" href="https://t.me/threat_group">Threat Group Hub</a>
                            <div class="tgme_widget_message_text">
                                Ransomware payment demand:
                                Send BTC to bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq
                                Or ETH to 0x71C7656EC7ab88b098defB751B7401B5f6d8976F
                                Contact negotiator at analyst@darkintel.net
                                SHA256: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
                                Dump combo format: admin@corp.com:P@ssword123
                            </div>
                            <a class="tgme_widget_message_photo_wrap" style="background-image:url(\'https://cdn4.telesco.pe/file/photo123.jpg\')"></a>
                            <span class="tgme_widget_message_views">4.8K</span>
                            <a class="tgme_widget_message_date" href="https://t.me/leakfeed/101"><time datetime="2026-09-16T10:00:00Z"></time></a>
                        </div>
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            'https://t.me/s/leakfeed*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.telegram'), [
            'channel' => 'leakfeed',
            'extract_iocs' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages.0.id', '101')
            ->assertJsonPath('messages.0.forwarded_from.handle', 'threat_group')
            ->assertJsonPath('messages.0.has_iocs', true)
            ->assertJsonPath('messages.0.media.0.type', 'photo')
            ->assertJsonPath('iocs_summary.crypto.btc.0', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')
            ->assertJsonPath('iocs_summary.crypto.eth.0', '0x71C7656EC7ab88b098defB751B7401B5f6d8976F')
            ->assertJsonPath('iocs_summary.emails.0', 'analyst@darkintel.net');
    }

    public function test_telegram_scraper_handles_backward_pagination(): void
    {
        $mockHtml = json_encode('
            <div class="tgme_widget_message_centered js-messages_more_wrap"><a href="/s/channel?before=80" class="tme_messages_more" data-before="80"></a></div>
            <div class="tgme_widget_message_wrap">
                <div class="tgme_widget_message" data-post="channel/90">
                    <div class="tgme_widget_message_text">Historical post 90</div>
                    <a class="tgme_widget_message_date" href="https://t.me/channel/90"><time datetime="2026-09-10T12:00:00Z"></time></a>
                </div>
            </div>
        ');

        Http::fake([
            'https://t.me/s/channel*before=100*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.telegram'), [
            'channel' => 'channel',
            'before_id' => '100',
            'limit' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages.0.id', '90')
            ->assertJsonPath('pagination.oldest_id', '90')
            ->assertJsonPath('pagination.has_more', true);
    }

    public function test_telegram_channel_search_discovers_channels_by_keyword(): void
    {
        $mockHtml = '
            <html>
                <body>
                    <div class="search-result">
                        <div class="search-result-type-wrapper" title="channel"></div>
                        <div class="search-result-title"><a href="https://t.me/threatintel_feed">Threat Intel Feed</a></div>
                        <div class="search-result-descr">Daily threat intel, IOCs, and ransomware bulletins.</div>
                    </div>
                    <div class="search-result">
                        <div class="search-result-type-wrapper" title="group"></div>
                        <div class="search-result-title"><a href="https://t.me/malware_chat">Malware Research Group</a></div>
                        <div class="search-result-descr">Discussion group for reverse engineers.</div>
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            'https://lyzem.com/search*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.telegram_search'), [
            'keyword' => 'threatintel',
            'limit' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2)
            ->assertJsonPath('channels.0.handle', 'threatintel_feed')
            ->assertJsonPath('channels.0.title', 'Threat Intel Feed')
            ->assertJsonPath('channels.0.type', 'channel')
            ->assertJsonPath('channels.1.handle', 'malware_chat')
            ->assertJsonPath('channels.1.type', 'group');
    }

    public function test_telegram_channel_search_validates_required_keyword(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.telegram_search'), [
            'keyword' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['keyword']);
    }

    public function test_social_dorks_generation_returns_all_major_targets(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.dorks'), [
            'target' => 'John Doe',
            'keyword' => 'cryptocurrency',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target' => 'John Doe',
                'count' => 21,
            ])
            ->assertJsonStructure([
                'dorks' => [
                    '*' => ['platform', 'category', 'description', 'query', 'google_url', 'duckduckgo_url', 'bing_url', 'yandex_url', 'darkdump_search_url'],
                ],
            ]);
    }

    public function test_execute_dork_requires_valid_query(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.execute_dork'), [
            'query' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_execute_dork_returns_live_search_findings(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.execute_dork'), [
            'query' => 'site:x.com octocat',
            'engine' => 'duckduckgo',
            'amount' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'query',
                'engine',
                'results',
                'total',
            ]);
    }

    public function test_user_activity_scout_validates_target(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.activity'), [
            'target' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target']);
    }

    public function test_user_activity_scout_returns_structured_activities(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.activity'), [
            'target' => 'saraichikawa',
            'keyword' => 'security',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'target',
                'activities',
                'stats' => ['total', 'comments', 'discussions', 'code', 'platforms'],
                'dorks',
            ])
            ->assertJson([
                'success' => true,
                'target' => 'saraichikawa',
            ]);
    }

    public function test_user_activity_scout_ingests_stackoverflow_discourse_and_devto(): void
    {
        Http::fake([
            'https://hn.algolia.com/*' => Http::response(['hits' => []], 200),
            'https://api.github.com/*' => Http::response([], 200),
            'https://www.reddit.com/*' => Http::response('', 200),
            'https://api.stackexchange.com/*' => Http::response([
                'items' => [
                    [
                        'item_type' => 'answer',
                        'answer_id' => 991122,
                        'title' => 'Decentralized cryptographic proof in PHP',
                        'excerpt' => 'You can implement ECDSA signature checks using openssl_verify.',
                        'owner' => ['display_name' => 'satoshi'],
                        'creation_date' => 1700000000,
                    ],
                ],
            ], 200),
            'https://meta.discourse.org/search.json*' => Http::response([
                'posts' => [
                    [
                        'id' => 5544,
                        'username' => 'satoshi',
                        'blurb' => 'I recommend configuring Tor hidden service v3 onion addresses.',
                        'post_number' => 2,
                        'topic_id' => 888,
                        'topic_title' => 'Onion Service Configuration',
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ],
                'topics' => [
                    ['id' => 888, 'title' => 'Onion Service Configuration'],
                ],
            ], 200),
            'https://dev.to/api/articles*' => Http::response([
                [
                    'id' => 7766,
                    'title' => 'Building Zero-Knowledge OSINT Recon Tools',
                    'description' => 'A deep dive into distributed thread scraping without API keys.',
                    'url' => 'https://dev.to/satoshi/building-zero-knowledge-osint-7766',
                    'published_at' => '2026-02-01T00:00:00Z',
                    'comments_count' => 12,
                    'public_reactions_count' => 45,
                    'user' => ['username' => 'satoshi'],
                ],
            ], 200),
            'https://gitlab.com/api/v4/users?username=satoshi' => Http::response([
                [
                    'id' => 12345,
                    'username' => 'satoshi',
                    'name' => 'Satoshi Nakamoto',
                    'web_url' => 'https://gitlab.com/satoshi',
                ],
            ], 200),
            'https://gitlab.com/api/v4/users/12345/events*' => Http::response([
                [
                    'id' => 8899,
                    'action_name' => 'commented on',
                    'target_type' => 'Issue',
                    'target_title' => 'Implement zero-knowledge proofs in cryptographic engine',
                    'created_at' => '2026-03-01T00:00:00Z',
                ],
            ], 200),
            'https://lemmy.world/api/v3/search*' => Http::response([
                'comments' => [
                    [
                        'comment' => [
                            'id' => 443322,
                            'content' => 'Decentralized fediverse identity is key for sovereign communication.',
                            'published' => '2026-03-02T00:00:00Z',
                        ],
                        'creator' => ['name' => 'satoshi'],
                        'community' => ['name' => 'privacy'],
                        'post' => ['name' => 'Decentralized Identity Overview'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.activity'), [
            'target' => 'satoshi',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('target', 'satoshi');

        $activities = $response->json('activities');
        $platforms = array_column($activities, 'platform_id');

        $this->assertContains('stackoverflow', $platforms);
        $this->assertContains('discourse', $platforms);
        $this->assertContains('devto', $platforms);
        $this->assertContains('gitlab', $platforms);
        $this->assertContains('lemmy', $platforms);
    }

    public function test_activity_stream_requires_valid_target(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('social_recon.activity_stream'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target']);
    }

    public function test_activity_stream_yields_server_sent_events(): void
    {
        Http::fake([
            'https://hn.algolia.com/api/v1/search*' => Http::response(['hits' => []], 200),
            'https://api.github.com/users/*' => Http::response([], 200),
            'https://gitlab.com/api/v4/*' => Http::response([], 200),
            'https://www.reddit.com/search.rss*' => Http::response('', 200),
            'https://lemmy.world/api/v3/*' => Http::response(['comments' => []], 200),
            'https://api.stackexchange.com/*' => Http::response(['items' => []], 200),
            'https://meta.discourse.org/search.json*' => Http::response(['posts' => []], 200),
            'https://dev.to/api/articles*' => Http::response([], 200),
            'https://lobste.rs/*' => Http::response('', 200),
            'https://inv.nadeko.net/*' => Http::response([], 200),
            'https://invidious.fdn.fr/*' => Http::response([], 200),
            'https://vid.puffyan.us/*' => Http::response([], 200),
            'https://html.duckduckgo.com/*' => Http::response('', 200),
            'https://medium.com/feed/*' => Http::response('', 200),
            'https://*.substack.com/*' => Http::response('', 200),
            'https://mastodon.social/*' => Http::response([], 200),
            'https://public.api.bsky.app/*' => Http::response([], 200),
        ]);

        $response = $this->actingAs($this->user)->get(route('social_recon.activity_stream', [
            'target' => 'octocat',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_google_search_requires_query(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('social_recon.persona_google'), [
            'query' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    public function test_google_search_returns_google_cards_with_title_and_description(): void
    {
        $rssFixture = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
            <channel>
                <title>Google News</title>
                <item>
                    <title>Dr Amos Kibet - Research Faculty Profile - Medical Journal</title>
                    <link>https://example.com/dr-amos-kibet</link>
                    <description>Cardiovascular immunology research findings by Dr. Amos Kibet.</description>
                    <source url="https://example.com">Medical Journal</source>
                </item>
            </channel>
        </rss>
        XML;

        Http::fake([
            'https://news.google.com/rss/search*' => Http::response($rssFixture, 200),
            'https://html.duckduckgo.com/*' => Http::response('', 200),
            'https://www.bing.com/*' => Http::response('', 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.persona_google'), [
            'query' => 'Dr.Amos Kibet',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('query', 'Dr.Amos Kibet')
            ->assertJsonPath('total', 1);

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals('Dr Amos Kibet - Research Faculty Profile', $results[0]['title']);
        $this->assertNotEmpty($results[0]['description']);
        $this->assertEquals('example.com', $results[0]['domain']);
        $this->assertEquals('Google', $results[0]['engine']);
    }

    public function test_general_persona_search_backward_compatibility_alias(): void
    {
        Http::fake([
            'https://news.google.com/rss/search*' => Http::response('', 200),
            'https://html.duckduckgo.com/*' => Http::response('', 200),
            'https://www.bing.com/*' => Http::response('', 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.persona_general'), [
            'query' => 'Dr.Amos Kibet',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('query', 'Dr.Amos Kibet');
    }

    public function test_exact_persona_search_backward_compatibility_alias(): void
    {
        Http::fake([
            'https://news.google.com/rss/search*' => Http::response('', 200),
            'https://html.duckduckgo.com/*' => Http::response('', 200),
            'https://www.bing.com/*' => Http::response('', 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('social_recon.persona_exact'), [
            'query' => 'Dr.Amos Kibet',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('query', 'Dr.Amos Kibet');
    }
}

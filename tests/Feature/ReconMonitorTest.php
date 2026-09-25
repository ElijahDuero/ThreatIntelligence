<?php

namespace Tests\Feature;

use App\Models\ReconAlert;
use App\Models\ReconMonitor;
use App\Models\User;
use App\Services\DarkWeb\DeepScraperService;
use App\Services\Infrastructure\InfrastructureReconService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReconMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_monitors_page_requires_authentication(): void
    {
        $response = $this->get(route('monitors.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_monitors_page_can_be_rendered_for_authenticated_operator(): void
    {
        $response = $this->actingAs($this->user)->get(route('monitors.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Monitors/Index')
            ->has('monitors')
            ->has('recentAlerts')
            ->has('investigations')
            ->has('stats')
        );
    }

    public function test_store_monitor_validates_required_inputs(): void
    {
        $response = $this->actingAs($this->user)->post(route('monitors.store'), [
            'title' => '',
            'target_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors(['title', 'target_type', 'target_value', 'frequency']);
    }

    public function test_store_monitor_creates_record_and_triggers_initial_baseline(): void
    {
        Http::fake([
            'https://t.me/s/cyberthreats*' => Http::response('
                <html><body>
                    <div class="tgme_channel_info_header_title">Cyber Threat Leaks</div>
                    <div class="tgme_channel_info_counter"><span class="counter_value">10K</span></div>
                    <div class="tgme_widget_message_wrap">
                        <div class="tgme_widget_message" data-post="cyberthreats/101">
                            <div class="tgme_widget_message_text">Initial breach database log</div>
                            <a class="tgme_widget_message_date" href="https://t.me/cyberthreats/101"></a>
                        </div>
                    </div>
                </body></html>
            ', 200),
        ]);

        $response = $this->actingAs($this->user)->post(route('monitors.store'), [
            'title' => 'Cyber Threat Channel',
            'target_type' => 'telegram',
            'target_value' => 'cyberthreats',
            'frequency' => '15m',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recon_monitors', [
            'title' => 'Cyber Threat Channel',
            'target_type' => 'telegram',
            'target_value' => 'cyberthreats',
            'frequency' => '15m',
            'is_active' => true,
        ]);

        $monitor = ReconMonitor::first();
        $this->assertNotNull($monitor->last_scanned_at);
        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'severity' => 'info',
        ]);
    }

    public function test_poll_telegram_monitor_detects_new_message_alert(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'Darknet Telegram Feed',
            'target_type' => 'telegram',
            'target_value' => 'darkintel',
            'frequency' => '15m',
            'is_active' => true,
            'last_seen_state' => ['seen_ids' => ['101', '102']],
            'findings_count' => 0,
        ]);

        Http::fake([
            'https://t.me/s/darkintel*' => Http::response('
                <html><body>
                    <div class="tgme_channel_info_header_title">Dark Intel</div>
                    <div class="tgme_widget_message_wrap">
                        <div class="tgme_widget_message" data-post="darkintel/103">
                            <div class="tgme_widget_message_text">BREAKING: Fresh ransomware leak posted</div>
                            <a class="tgme_widget_message_date" href="https://t.me/darkintel/103"></a>
                        </div>
                    </div>
                </body></html>
            ', 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('monitors.poll', $monitor->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_alerts_count' => 1,
            ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'title' => 'New Telegram Message in @darkintel',
            'severity' => 'medium',
        ]);

        $monitor->refresh();
        $this->assertEquals(1, $monitor->findings_count);
        $this->assertContains('103', $monitor->last_seen_state['seen_ids']);
    }

    public function test_poll_telegram_monitor_escalates_alert_severity_on_leaks(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'Breach Dump Feed',
            'target_type' => 'telegram',
            'target_value' => 'breachfeed',
            'frequency' => 'hourly',
            'is_active' => true,
            'last_seen_state' => ['seen_ids' => ['50']],
            'findings_count' => 0,
        ]);

        $mockHtml = '
            <html>
                <body>
                    <div class="tgme_channel_info_header_title">Breach Feed</div>
                    <div class="tgme_widget_message_wrap">
                        <div class="tgme_widget_message" data-post="breachfeed/51">
                            <div class="tgme_widget_message_text">Massive database dump: admin@target.com:SecretPass99 BTC: bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq</div>
                            <a class="tgme_widget_message_date" href="https://t.me/breachfeed/51"><time datetime="2026-09-17T00:00:00Z"></time></a>
                        </div>
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            'https://t.me/s/breachfeed*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('monitors.poll', $monitor->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_alerts_count' => 1,
            ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'title' => 'Threat Intelligence Alert in @breachfeed',
            'severity' => 'critical',
        ]);
    }

    public function test_poll_reddit_monitor_detects_new_discussion_post(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'Malware Keyword Tracker',
            'target_type' => 'reddit',
            'target_value' => 'lockbit leak',
            'frequency' => 'hourly',
            'is_active' => true,
            'last_seen_state' => ['seen_ids' => ['old_post_1']],
            'findings_count' => 0,
        ]);

        Http::fake([
            'https://www.reddit.com/search.json*' => Http::response([
                'data' => [
                    'children' => [
                        [
                            'data' => [
                                'id' => 'new_post_999',
                                'title' => 'LockBit 3.0 new victim published',
                                'author' => 'cyber_sleuth',
                                'subreddit_name_prefixed' => 'r/netsec',
                                'permalink' => '/r/netsec/comments/new_post_999/',
                                'score' => 88,
                                'num_comments' => 12,
                                'created_utc' => time(),
                                'selftext' => 'Full breakdown of the new leak.',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('monitors.poll', $monitor->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_alerts_count' => 1,
            ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'title' => 'New Reddit Mention: LockBit 3.0 new victim published',
            'severity' => 'high',
        ]);
    }

    public function test_alerts_api_and_mark_read(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'Test Monitor',
            'target_type' => 'reddit',
            'target_value' => 'test',
        ]);

        $alert = ReconAlert::create([
            'recon_monitor_id' => $monitor->id,
            'title' => 'Suspicious Activity Detected',
            'severity' => 'critical',
            'is_read' => false,
        ]);

        $res = $this->actingAs($this->user)->getJson(route('alerts.index'));
        $res->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        $readRes = $this->actingAs($this->user)->postJson(route('alerts.read', $alert->id));
        $readRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('recon_alerts', [
            'id' => $alert->id,
            'is_read' => true,
        ]);
    }

    public function test_console_command_runs_due_monitors(): void
    {
        ReconMonitor::create([
            'title' => 'Hourly Monitor',
            'target_type' => 'telegram',
            'target_value' => 'test_channel',
            'frequency' => 'hourly',
            'last_scanned_at' => now()->subHours(2), // Due
            'is_active' => true,
        ]);

        Http::fake([
            'https://t.me/s/test_channel*' => Http::response('<html><body>Test</body></html>', 200),
        ]);

        $this->artisan('recon:poll-monitors')
            ->assertExitCode(0);
    }

    public function test_store_onion_monitor_creates_record_and_triggers_baseline(): void
    {
        $this->mock(DeepScraperService::class, function ($mock) {
            $mock->shouldReceive('scrape')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'status_code' => 200,
                    'title' => 'Sample Onion Portal',
                    'emails' => ['contact@sampleonion.onion'],
                    'documents' => [],
                    'raw_text_sample' => 'Welcome to sample hidden service',
                    'is_blacklisted' => false,
                    'response_time_seconds' => 1.25,
                ]);
        });

        $response = $this->actingAs($this->user)->post(route('monitors.store'), [
            'title' => 'Darknet Leak Forum',
            'target_type' => 'onion',
            'target_value' => 'sampleonion234567.onion',
            'frequency' => 'hourly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recon_monitors', [
            'title' => 'Darknet Leak Forum',
            'target_type' => 'onion',
            'target_value' => 'sampleonion234567.onion',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('recon_alerts', [
            'severity' => 'info',
            'title' => 'Onion Monitor Active: sampleonion234567.onion',
        ]);
    }

    public function test_poll_onion_monitor_detects_new_leaked_emails(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'Ransomware Board',
            'target_type' => 'onion',
            'target_value' => 'ransomboard777.onion',
            'frequency' => '15m',
            'is_active' => true,
            'last_seen_state' => [
                'baseline_established' => true,
                'is_online' => true,
                'seen_emails' => ['admin@target.com'],
                'content_hash' => 'hash_v1',
            ],
            'findings_count' => 0,
        ]);

        $this->mock(DeepScraperService::class, function ($mock) {
            $mock->shouldReceive('scrape')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'status_code' => 200,
                    'emails' => ['admin@target.com', 'ceo@victimcorp.com'],
                    'documents' => [],
                    'raw_text_sample' => 'Fresh breach victim accounts',
                    'is_blacklisted' => false,
                    'response_time_seconds' => 0.8,
                ]);
        });

        $response = $this->actingAs($this->user)->postJson(route('monitors.poll', $monitor->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_alerts_count' => 1,
            ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'severity' => 'critical',
            'title' => 'New Leaked Emails Detected on ransomboard777.onion',
        ]);
    }

    public function test_poll_infrastructure_monitor_detects_new_ports_and_cves(): void
    {
        $monitor = ReconMonitor::create([
            'title' => 'C2 Gateway Watchdog',
            'target_type' => 'infrastructure',
            'target_value' => '198.51.100.42',
            'frequency' => 'daily',
            'is_active' => true,
            'last_seen_state' => [
                'baseline_established' => true,
                'seen_ports' => [80, 443],
                'seen_cves' => [],
                'reverse_dns' => 'c2.target.org',
            ],
            'findings_count' => 0,
        ]);

        $this->mock(InfrastructureReconService::class, function ($mock) {
            $mock->shouldReceive('inspectIp')
                ->with('198.51.100.42')
                ->once()
                ->andReturn([
                    'success' => true,
                    'ip' => '198.51.100.42',
                    'reverse_dns' => 'c2.target.org',
                    'ports_intel' => [
                        'open_ports' => [80, 443, 22],
                        'cves' => ['CVE-2026-9999'],
                    ],
                ]);
        });

        $response = $this->actingAs($this->user)->postJson(route('monitors.poll', $monitor->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_alerts_count' => 2,
            ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'title' => 'CRITICAL Port Exposed on 198.51.100.42',
            'severity' => 'critical',
        ]);

        $this->assertDatabaseHas('recon_alerts', [
            'recon_monitor_id' => $monitor->id,
            'title' => 'New CVE Vulnerability Detected on 198.51.100.42',
            'severity' => 'critical',
        ]);
    }
}

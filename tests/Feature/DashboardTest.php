<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Investigation;
use App\Models\ReconAlert;
use App\Models\ReconMonitor;
use App\Models\ScrapedTarget;
use App\Models\SearchQuery;
use App\Models\SearchResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_operator_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
    }

    public function test_dashboard_provides_real_time_telemetry_stats(): void
    {
        $user = User::factory()->create();

        $query = SearchQuery::create([
            'query' => 'crypto ransomware',
            'engine' => 'onionfind',
            'results_count' => 1,
            'status' => 'completed',
        ]);

        SearchResult::create([
            'search_query_id' => $query->id,
            'title' => 'Ransomware Leak Portal',
            'url' => 'http://examplelockxyz.onion',
            'is_onion' => true,
        ]);

        Investigation::create([
            'title' => 'Operation Dark Hydra',
            'status' => 'active',
            'priority' => 'high',
        ]);

        Bookmark::create([
            'title' => 'Flagged Onion Endpoint',
            'url' => 'http://examplelockxyz.onion',
        ]);

        ScrapedTarget::create([
            'url' => 'http://examplelockxyz.onion',
            'title' => 'Ransomware Leak Portal',
            'status_code' => 200,
        ]);

        $monitor = ReconMonitor::create([
            'title' => 'Watchdog Onion',
            'target_type' => 'telegram',
            'target_value' => 'leakchannel',
            'frequency' => 'hourly',
            'is_active' => true,
        ]);

        ReconAlert::create([
            'recon_monitor_id' => $monitor->id,
            'title' => 'New credentials detected',
            'summary' => 'Found password leak in channel',
            'severity' => 'critical',
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('stats')
                ->where('stats.total_queries', 1)
                ->where('stats.total_results', 1)
                ->where('stats.total_investigations', 1)
                ->where('stats.total_bookmarks', 1)
                ->where('stats.total_scraped', 1)
                ->where('stats.total_monitors', 1)
                ->where('stats.unread_alerts', 1)
                ->has('torStatus')
                ->has('recentAlerts', 1)
            );
    }
}

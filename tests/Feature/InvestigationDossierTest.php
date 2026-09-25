<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Investigation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvestigationDossierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_investigations_page_requires_authentication(): void
    {
        $response = $this->get(route('investigations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_investigation_dossier_show_page_renders_with_graph_and_evidence(): void
    {
        $investigation = Investigation::create([
            'title' => 'Operation Shadow Leviathan',
            'description' => 'Tracing darknet drug marketplace operators and cryptocurrency laundering ring.',
            'priority' => 'critical',
            'status' => 'active',
            'tags' => ['darknet', 'crypto', 'syndicate'],
        ]);

        Bookmark::create([
            'investigation_id' => $investigation->id,
            'title' => 'Silk Tor Leak Mirror',
            'url' => 'http://darkleakmarket77v3n5p27q.onion/catalog',
            'notes' => 'Contact administrator at admin@leviathan-market.onion or Telegram @shadow_operator. Send BTC deposits to 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa.',
            'severity' => 'CRITICAL',
        ]);

        $response = $this->actingAs($this->user)->get(route('investigations.show', $investigation->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Investigations/Show')
            ->has('investigation')
            ->has('bookmarks.data', 1)
            ->has('queries')
            ->has('graph')
            ->where('investigation.id', $investigation->id)
            ->where('investigation.title', 'Operation Shadow Leviathan')
            ->has('graph.nodes')
            ->has('graph.edges')
            ->has('graph.stats')
        );
    }

    public function test_entity_graph_service_auto_extracts_onions_channels_emails_and_wallets(): void
    {
        $investigation = Investigation::create([
            'title' => 'Ransomware C2 Analysis',
            'priority' => 'high',
            'tags' => ['malware'],
        ]);

        Bookmark::create([
            'investigation_id' => $investigation->id,
            'title' => 'Leaked Ransom Note',
            'url' => 'https://t.me/threatintel_alerts/42',
            'notes' => 'Threat actor uses http://lockbit3ransomleakssite7q.onion and payment wallet bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq. Inquiries: support@ransomleaks.com',
            'severity' => 'CRITICAL',
        ]);

        $response = $this->actingAs($this->user)->get(route('investigations.show', $investigation->id));

        $response->assertStatus(200);
        $props = $response->original->getData()['page']['props'];
        $nodes = collect($props['graph']['nodes']);

        // Check for auto-extracted types
        $hasChannel = $nodes->contains(fn ($n) => $n['type'] === 'channel');
        $hasOnion = $nodes->contains(fn ($n) => $n['type'] === 'onion');
        $hasWallet = $nodes->contains(fn ($n) => $n['type'] === 'wallet');
        $hasEmail = $nodes->contains(fn ($n) => $n['type'] === 'email');

        $this->assertTrue($hasChannel, 'Should auto-extract Telegram channel node');
        $this->assertTrue($hasOnion, 'Should auto-extract Onion address node');
        $this->assertTrue($hasWallet, 'Should auto-extract Crypto wallet node');
        $this->assertTrue($hasEmail, 'Should auto-extract Email node');
    }

    public function test_update_graph_persists_custom_nodes_and_edges(): void
    {
        $investigation = Investigation::create([
            'title' => 'Botnet Operator Tracker',
            'priority' => 'medium',
        ]);

        $customNodes = [
            [
                'id' => 'custom_suspect_1',
                'label' => 'Suspect Alias "Viper"',
                'type' => 'person',
                'subtitle' => 'Botnet Kingpin',
                'notes' => 'Primary lead',
                'x' => 250,
                'y' => 300,
            ],
        ];

        $customEdges = [
            [
                'source' => 'case_'.$investigation->id,
                'target' => 'custom_suspect_1',
                'label' => 'prime_suspect',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson(route('investigations.update_graph', $investigation->id), [
            'nodes' => $customNodes,
            'edges' => $customEdges,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('graph.nodes.1.id', 'custom_suspect_1')
            ->assertJsonPath('graph.nodes.1.label', 'Suspect Alias "Viper"');

        $this->assertDatabaseHas('investigations', [
            'id' => $investigation->id,
        ]);

        $investigation->refresh();
        $this->assertCount(1, $investigation->graph_data['nodes']);
        $this->assertEquals('custom_suspect_1', $investigation->graph_data['nodes'][0]['id']);
    }

    public function test_update_investigation_metadata(): void
    {
        $investigation = Investigation::create([
            'title' => 'Initial Title',
            'priority' => 'low',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->putJson(route('investigations.update', $investigation->id), [
            'title' => 'Updated Cyber Threat Dossier',
            'priority' => 'critical',
            'status' => 'closed',
            'description' => 'Target neutralized.',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('investigations', [
            'id' => $investigation->id,
            'title' => 'Updated Cyber Threat Dossier',
            'priority' => 'critical',
            'status' => 'closed',
        ]);
    }

    public function test_export_report_returns_markdown_and_json(): void
    {
        $investigation = Investigation::create([
            'title' => 'Darknet Intelligence Brief',
            'description' => 'Investigation into credentials leak.',
            'priority' => 'high',
        ]);

        // Markdown Export
        $mdResponse = $this->actingAs($this->user)->get(route('investigations.export_report', [
            'id' => $investigation->id,
            'format' => 'markdown',
        ]));

        $mdResponse->assertStatus(200);
        $mdResponse->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('DARKDUMP OSINT INTELLIGENCE DOSSIER', $mdResponse->getContent());

        // JSON Export
        $jsonResponse = $this->actingAs($this->user)->get(route('investigations.export_report', [
            'id' => $investigation->id,
            'format' => 'json',
        ]));

        $jsonResponse->assertStatus(200);
        $jsonResponse->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('Darknet Intelligence Brief', $jsonResponse->getContent());
    }

    public function test_destroy_investigation_deletes_case(): void
    {
        $investigation = Investigation::create([
            'title' => 'Temporary Case',
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->user)->delete(route('investigations.destroy', $investigation->id));

        $response->assertRedirect(route('investigations.index'));
        $this->assertDatabaseMissing('investigations', ['id' => $investigation->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\SearchQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueryRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_query_registry_page_can_be_rendered(): void
    {
        SearchQuery::create([
            'query' => 'hidden wiki onion link',
            'engine' => 'ahmia',
            'scan_type' => 'quick',
            'use_tor' => true,
            'results_count' => 12,
            'execution_time_seconds' => 1.45,
            'status' => 'completed',
        ]);

        $response = $this->get(route('queries.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Queries/Index')
            ->has('queries.data', 1)
            ->has('stats')
            ->where('stats.total_queries', 1)
            ->where('stats.tor_queries', 1)
        );
    }

    public function test_query_registry_filters_by_search_and_engine(): void
    {
        SearchQuery::create([
            'query' => 'database dump leak',
            'engine' => 'ahmia',
            'scan_type' => 'quick',
            'use_tor' => true,
            'results_count' => 5,
            'status' => 'completed',
        ]);

        SearchQuery::create([
            'query' => 'ransomware decryptor',
            'engine' => 'duckduckgo',
            'scan_type' => 'deep',
            'use_tor' => false,
            'results_count' => 20,
            'status' => 'completed',
        ]);

        $responseSearch = $this->get(route('queries.index', ['search' => 'ransomware']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertInertia(fn (Assert $page) => $page
            ->has('queries.data', 1)
            ->where('queries.data.0.query', 'ransomware decryptor')
        );

        $responseEngine = $this->get(route('queries.index', ['engine' => 'ahmia']));
        $responseEngine->assertStatus(200);
        $responseEngine->assertInertia(fn (Assert $page) => $page
            ->has('queries.data', 1)
            ->where('queries.data.0.engine', 'ahmia')
        );
    }

    public function test_query_registry_filters_by_routing(): void
    {
        SearchQuery::create([
            'query' => 'tor service',
            'engine' => 'ahmia',
            'use_tor' => true,
            'results_count' => 8,
            'status' => 'completed',
        ]);

        SearchQuery::create([
            'query' => 'clearnet service',
            'engine' => 'duckduckgo',
            'use_tor' => false,
            'results_count' => 15,
            'status' => 'completed',
        ]);

        $torResponse = $this->get(route('queries.index', ['routing' => 'tor']));
        $torResponse->assertStatus(200);
        $torResponse->assertInertia(fn (Assert $page) => $page
            ->has('queries.data', 1)
            ->where('queries.data.0.use_tor', true)
        );

        $clearnetResponse = $this->get(route('queries.index', ['routing' => 'clearnet']));
        $clearnetResponse->assertStatus(200);
        $clearnetResponse->assertInertia(fn (Assert $page) => $page
            ->has('queries.data', 1)
            ->where('queries.data.0.use_tor', false)
        );
    }

    public function test_query_record_can_be_deleted(): void
    {
        $query = SearchQuery::create([
            'query' => 'temporary test target',
            'engine' => 'ahmia',
            'use_tor' => true,
            'results_count' => 3,
            'status' => 'completed',
        ]);

        $response = $this->deleteJson(route('queries.destroy', ['id' => $query->id]));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('search_queries', [
            'id' => $query->id,
        ]);
    }

    public function test_query_records_can_be_bulk_deleted(): void
    {
        $q1 = SearchQuery::create([
            'query' => 'bulk target 1',
            'engine' => 'ahmia',
            'use_tor' => true,
            'status' => 'completed',
        ]);

        $q2 = SearchQuery::create([
            'query' => 'bulk target 2',
            'engine' => 'duckduckgo',
            'use_tor' => false,
            'status' => 'completed',
        ]);

        $response = $this->postJson(route('queries.bulk_destroy'), [
            'ids' => [$q1->id, $q2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('search_queries', ['id' => $q1->id]);
        $this->assertDatabaseMissing('search_queries', ['id' => $q2->id]);
    }

    public function test_query_registry_can_be_cleared(): void
    {
        SearchQuery::create([
            'query' => 'to be cleared 1',
            'engine' => 'ahmia',
            'use_tor' => true,
            'status' => 'completed',
        ]);

        SearchQuery::create([
            'query' => 'to be cleared 2',
            'engine' => 'duckduckgo',
            'use_tor' => false,
            'status' => 'completed',
        ]);

        $this->assertEquals(2, SearchQuery::count());

        $response = $this->postJson(route('queries.clear'));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(0, SearchQuery::count());
    }

    public function test_query_registry_export_csv(): void
    {
        SearchQuery::create([
            'query' => 'export target sample',
            'engine' => 'ahmia',
            'scan_type' => 'quick',
            'use_tor' => true,
            'results_count' => 10,
            'execution_time_seconds' => 2.1,
            'status' => 'completed',
        ]);

        $response = $this->get(route('queries.export', ['format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_query_registry_export_json(): void
    {
        SearchQuery::create([
            'query' => 'export target json sample',
            'engine' => 'duckduckgo',
            'use_tor' => false,
            'results_count' => 4,
            'status' => 'completed',
        ]);

        $response = $this->get(route('queries.export', ['format' => 'json']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }
}

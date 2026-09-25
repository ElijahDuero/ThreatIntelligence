<?php

namespace App\Services\Investigation;

use App\Models\Investigation;

class EntityGraphService
{
    /**
     * Synthesize a unified entity graph combining auto-extracted nodes/edges and custom user graph data.
     */
    public function buildGraph(Investigation $investigation): array
    {
        $nodes = [];
        $edges = [];
        $nodeIndex = [];

        // Helper to register unique nodes
        $addNode = function (string $id, string $label, string $type, ?string $subtitle = null, array $metadata = []) use (&$nodes, &$nodeIndex) {
            if (isset($nodeIndex[$id])) {
                // Merge metadata if node exists
                $nodes[$nodeIndex[$id]]['metadata'] = array_merge($nodes[$nodeIndex[$id]]['metadata'], $metadata);

                return $nodes[$nodeIndex[$id]];
            }

            $node = [
                'id' => $id,
                'label' => $label,
                'type' => $type, // case, person, onion, channel, email, wallet, domain, forum, generic
                'subtitle' => $subtitle,
                'metadata' => $metadata,
                'is_auto' => true,
            ];

            $nodeIndex[$id] = count($nodes);
            $nodes[] = $node;

            return $node;
        };

        $addEdge = function (string $source, string $target, string $label = 'links_to', ?string $type = 'auto') use (&$edges) {
            if ($source === $target) {
                return;
            }
            $edgeKey = $source.'->'.$target.'('.$label.')';
            $revKey = $target.'->'.$source.'('.$label.')';

            if (! isset($edges[$edgeKey]) && ! isset($edges[$revKey])) {
                $edges[$edgeKey] = [
                    'id' => 'edge_'.substr(md5($edgeKey), 0, 10),
                    'source' => $source,
                    'target' => $target,
                    'label' => $label,
                    'type' => $type,
                ];
            }
        };

        // 1. Root Case Node
        $caseNodeId = 'case_'.$investigation->id;
        $addNode($caseNodeId, $investigation->title, 'case', 'Investigation Root', [
            'priority' => $investigation->priority,
            'status' => $investigation->status,
        ]);

        // 2. Tags as Entity Nodes
        if (! empty($investigation->tags) && is_array($investigation->tags)) {
            foreach ($investigation->tags as $tag) {
                $cleanTag = trim((string) $tag);
                if (! empty($cleanTag)) {
                    $tagId = 'tag_'.md5(strtolower($cleanTag));
                    $addNode($tagId, '#'.$cleanTag, 'tag', 'Case Tag');
                    $addEdge($caseNodeId, $tagId, 'tagged_with');
                }
            }
        }

        // 3. Process Bookmarked Findings & Extract Entities (capped to latest 50 for scalable synthesis)
        $bookmarks = $investigation->bookmarks()->latest()->take(50)->get();
        foreach ($bookmarks as $bm) {
            $bmNodeId = 'bm_'.$bm->id;
            $url = (string) $bm->url;
            $title = (string) ($bm->title ?: 'Finding #'.$bm->id);
            $notes = (string) $bm->notes;

            // Determine primary node type from URL
            $primaryType = 'domain';
            $subtitle = 'Web Finding';

            if (preg_match('#[a-z2-7]{16,56}\.onion#i', $url, $m)) {
                $primaryType = 'onion';
                $subtitle = 'Dark Web Onion';
            } elseif (str_contains($url, 't.me/')) {
                $primaryType = 'channel';
                $subtitle = 'Telegram Public Channel';
            } elseif (str_contains($url, 'reddit.com/')) {
                $primaryType = 'forum';
                $subtitle = 'Reddit Thread/Discussion';
            } elseif (str_contains($url, 'github.com/') || str_contains($url, 'gitlab.com/')) {
                $primaryType = 'developer';
                $subtitle = 'Repository / Code Source';
            } elseif (str_contains($url, 'x.com/') || str_contains($url, 'twitter.com/')) {
                $primaryType = 'person';
                $subtitle = 'X (Twitter) Profile';
            }

            $addNode($bmNodeId, $title, $primaryType, $subtitle, [
                'url' => $url,
                'severity' => $bm->severity,
                'notes' => $notes,
                'bookmark_id' => $bm->id,
            ]);

            $addEdge($caseNodeId, $bmNodeId, 'evidence');

            // Deep text entity extraction from URL + Notes + Title
            $fullText = $url.' '.$title.' '.$notes;

            // Extract Onion Addresses
            if (preg_match_all('#\b([a-z2-7]{16,56}\.onion)\b#i', $fullText, $onionMatches)) {
                foreach (array_unique($onionMatches[1]) as $onion) {
                    $onionId = 'onion_'.md5(strtolower($onion));
                    $addNode($onionId, $onion, 'onion', 'Hidden Service Node', ['url' => 'http://'.$onion]);
                    $addEdge($bmNodeId, $onionId, 'hosts_onion');
                }
            }

            // Extract Telegram Handles
            if (preg_match_all('#(?:t\.me\/|@)([a-zA-Z0-9_]{4,32})\b#i', $fullText, $tgMatches)) {
                foreach (array_unique($tgMatches[1]) as $tgHandle) {
                    if (in_array(strtolower($tgHandle), ['share', 'joinchat', 's', 'login', 'channel'])) {
                        continue;
                    }
                    $tgId = 'tg_'.md5(strtolower($tgHandle));
                    $addNode($tgId, '@'.$tgHandle, 'channel', 'Telegram Channel/Handle', ['url' => 'https://t.me/'.$tgHandle]);
                    $addEdge($bmNodeId, $tgId, 'mentions_channel');
                }
            }

            // Extract Emails
            if (preg_match_all('#\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b#i', $fullText, $emailMatches)) {
                foreach (array_unique($emailMatches[1]) as $email) {
                    $emailId = 'email_'.md5(strtolower($email));
                    $addNode($emailId, strtolower($email), 'email', 'Identified Email');
                    $addEdge($bmNodeId, $emailId, 'contains_email');
                }
            }

            // Extract Cryptocurrency Addresses (BTC, ETH, XMR)
            // BTC:
            if (preg_match_all('#\b((?:bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39})\b#', $fullText, $btcMatches)) {
                foreach (array_unique($btcMatches[1]) as $btc) {
                    $btcId = 'crypto_'.md5($btc);
                    $addNode($btcId, $btc, 'wallet', 'BTC Address', ['crypto' => 'BTC']);
                    $addEdge($bmNodeId, $btcId, 'crypto_wallet');
                }
            }
            // ETH:
            if (preg_match_all('#\b(0x[a-fA-F0-9]{40})\b#', $fullText, $ethMatches)) {
                foreach (array_unique($ethMatches[1]) as $eth) {
                    $ethId = 'crypto_'.md5(strtolower($eth));
                    $addNode($ethId, $eth, 'wallet', 'ETH / EVM Address', ['crypto' => 'ETH']);
                    $addEdge($bmNodeId, $ethId, 'crypto_wallet');
                }
            }
        }

        // 4. Process Search Queries connected to this investigation
        $queries = $investigation->searchQueries()->take(10)->get();
        foreach ($queries as $q) {
            $qNodeId = 'query_'.$q->id;
            $addNode($qNodeId, $q->query, 'query', 'Search Lead', [
                'engine' => $q->engine,
                'results_count' => $q->results_count,
            ]);
            $addEdge($caseNodeId, $qNodeId, 'search_lead');
        }

        // 5. Merge Custom Graph Data (User-Created Nodes & Custom Relationship Edges)
        $customGraph = $investigation->graph_data ?? [];
        $customNodes = $customGraph['nodes'] ?? [];
        $customEdges = $customGraph['edges'] ?? [];

        foreach ($customNodes as $cNode) {
            if (empty($cNode['id'])) {
                continue;
            }

            // If node already exists, update properties; otherwise append as custom node
            if (isset($nodeIndex[$cNode['id']])) {
                $idx = $nodeIndex[$cNode['id']];
                $nodes[$idx]['label'] = $cNode['label'] ?? $nodes[$idx]['label'];
                $nodes[$idx]['type'] = $cNode['type'] ?? $nodes[$idx]['type'];
                $nodes[$idx]['subtitle'] = $cNode['subtitle'] ?? $nodes[$idx]['subtitle'];
                $nodes[$idx]['notes'] = $cNode['notes'] ?? null;
                if (isset($cNode['x']) && isset($cNode['y'])) {
                    $nodes[$idx]['x'] = $cNode['x'];
                    $nodes[$idx]['y'] = $cNode['y'];
                }
            } else {
                $newNode = [
                    'id' => $cNode['id'],
                    'label' => $cNode['label'] ?? 'Custom Entity',
                    'type' => $cNode['type'] ?? 'generic',
                    'subtitle' => $cNode['subtitle'] ?? 'Operator Node',
                    'notes' => $cNode['notes'] ?? '',
                    'metadata' => $cNode['metadata'] ?? [],
                    'is_auto' => false,
                ];
                if (isset($cNode['x']) && isset($cNode['y'])) {
                    $newNode['x'] = $cNode['x'];
                    $newNode['y'] = $cNode['y'];
                }

                $nodeIndex[$cNode['id']] = count($nodes);
                $nodes[] = $newNode;
            }
        }

        foreach ($customEdges as $cEdge) {
            if (empty($cEdge['source']) || empty($cEdge['target'])) {
                continue;
            }
            $edgeKey = 'custom_'.$cEdge['source'].'_'.$cEdge['target'];
            $edges[$edgeKey] = [
                'id' => $cEdge['id'] ?? ('edge_'.substr(md5($edgeKey), 0, 10)),
                'source' => $cEdge['source'],
                'target' => $cEdge['target'],
                'label' => $cEdge['label'] ?? 'related_to',
                'type' => 'custom',
                'notes' => $cEdge['notes'] ?? '',
            ];
        }

        $edgesList = array_values($edges);

        // Stats calculation
        $typesCount = [];
        foreach ($nodes as $n) {
            $t = $n['type'] ?? 'generic';
            $typesCount[$t] = ($typesCount[$t] ?? 0) + 1;
        }

        return [
            'nodes' => $nodes,
            'edges' => $edgesList,
            'stats' => [
                'total_nodes' => count($nodes),
                'total_edges' => count($edgesList),
                'types_breakdown' => $typesCount,
            ],
        ];
    }

    /**
     * Generate an intelligence Markdown dossier report.
     */
    public function generateMarkdownReport(Investigation $investigation, array $graph): string
    {
        $date = now()->toFormattedDateString();
        $md = "# DARKDUMP OSINT INTELLIGENCE DOSSIER\n";
        $md .= "### Case Reference: [CASE #{$investigation->id}] {$investigation->title}\n\n";
        $md .= "**Classification:** TLP:AMBER | Threat Intelligence\n";
        $md .= '**Status:** '.strtoupper($investigation->status).' | **Priority:** '.strtoupper($investigation->priority)."\n";
        $md .= "**Generated:** {$date} by Darkdump Intelligence Matrix\n\n";

        $md .= "---\n\n";
        $md .= "## 1. Executive Summary\n";
        $md .= ($investigation->description ?: 'No operational description provided.')."\n\n";

        if (! empty($investigation->tags)) {
            $md .= '**Case Tags:** `'.implode('`, `', $investigation->tags)."`\n\n";
        }

        $md .= "---\n\n";
        $md .= "## 2. Entity Link Graph Matrix\n";
        $md .= "- **Total Identified Nodes:** {$graph['stats']['total_nodes']}\n";
        $md .= "- **Total Relational Edges:** {$graph['stats']['total_edges']}\n";
        $md .= "- **Entity Breakdown:**\n";
        foreach ($graph['stats']['types_breakdown'] as $type => $count) {
            $md .= '  - **'.ucfirst($type).":** {$count}\n";
        }
        $md .= "\n";

        $md .= "### Key Node Entities:\n";
        $md .= "| Entity ID | Type | Label | Subtitle |\n";
        $md .= "| --- | --- | --- | --- |\n";
        foreach (array_slice($graph['nodes'], 0, 30) as $node) {
            $md .= "| `{$node['id']}` | **{$node['type']}** | ".str_replace('|', '/', $node['label'])." | {$node['subtitle']} |\n";
        }
        $md .= "\n";

        $md .= "---\n\n";
        $md .= "## 3. Evidence Catalog & Bookmarked Findings\n";
        $bookmarks = $investigation->bookmarks()->get();
        if ($bookmarks->isEmpty()) {
            $md .= "_No evidence items bookmarked for this dossier._\n\n";
        } else {
            foreach ($bookmarks as $i => $bm) {
                $idx = $i + 1;
                $md .= "### [Finding #{$idx}] {$bm->title}\n";
                $md .= "- **Target URL:** `{$bm->url}`\n";
                $md .= "- **Severity Rating:** `{$bm->severity}`\n";
                $md .= "- **Recorded At:** {$bm->created_at}\n";
                if (! empty($bm->notes)) {
                    $md .= "- **Operator Notes:** {$bm->notes}\n";
                }
                $md .= "\n";
            }
        }

        $md .= "---\n\n";
        $md .= "## 4. Search Leads & Query History\n";
        $queries = $investigation->searchQueries()->latest()->get();
        if ($queries->isEmpty()) {
            $md .= "_No search queries linked directly to this case._\n\n";
        } else {
            foreach ($queries as $q) {
                $md .= "- **Query:** `{$q->query}` | **Engine:** `{$q->engine}` | **Findings:** {$q->results_count} hits | **Logged:** {$q->created_at}\n";
            }
            $md .= "\n";
        }

        $md .= "---\n";
        $md .= "_Confidential Investigation Dossier • End of Report • Darkdump OSINT Suite v5_\n";

        return $md;
    }
}

<?php

namespace App\Services\Monitors;

use App\Models\ReconAlert;
use App\Models\ReconMonitor;
use App\Services\DarkWeb\DeepScraperService;
use App\Services\Infrastructure\InfrastructureReconService;
use App\Services\SocialRecon\SocialReconService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReconMonitorService
{
    public function __construct(
        protected SocialReconService $socialReconService,
        protected DeepScraperService $deepScraperService,
        protected InfrastructureReconService $infrastructureReconService
    ) {}

    /**
     * Poll an active monitor and dispatch alerts on new findings.
     */
    public function pollMonitor(ReconMonitor $monitor): array
    {
        $newAlerts = [];
        $state = $monitor->last_seen_state ?? [];

        try {
            switch ($monitor->target_type) {
                case 'telegram':
                    $newAlerts = $this->pollTelegram($monitor, $state);
                    break;

                case 'reddit':
                    $newAlerts = $this->pollReddit($monitor, $state);
                    break;

                case 'persona':
                    $newAlerts = $this->pollPersona($monitor, $state);
                    break;

                case 'onion':
                    $newAlerts = $this->pollOnion($monitor, $state);
                    break;

                case 'infrastructure':
                case 'domain':
                case 'ip':
                    $newAlerts = $this->pollInfrastructure($monitor, $state);
                    break;

                default:
                    throw new \InvalidArgumentException('Unsupported monitor type: '.$monitor->target_type);
            }

            $monitor->last_scanned_at = now();
            $monitor->last_seen_state = $state;
            $monitor->findings_count += count($newAlerts);
            $monitor->error_message = null;
            $monitor->save();

            // Dispatch Webhooks if configured
            if (! empty($monitor->webhook_url)) {
                foreach ($newAlerts as $alert) {
                    $this->dispatchWebhook($monitor->webhook_url, $alert);
                }
            }

            return [
                'success' => true,
                'new_alerts_count' => count($newAlerts),
                'alerts' => $newAlerts,
            ];
        } catch (\Throwable $e) {
            Log::warning("Recon monitor #{$monitor->id} poll failed: ".$e->getMessage());

            $monitor->last_scanned_at = now();
            $monitor->error_message = $e->getMessage();
            $monitor->save();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'new_alerts_count' => 0,
                'alerts' => [],
            ];
        }
    }

    /**
     * Poll a Telegram Channel for newly published messages and threat indicators.
     */
    protected function pollTelegram(ReconMonitor $monitor, array &$state): array
    {
        $res = $this->socialReconService->scrapeTelegramChannel(
            channel: $monitor->target_value,
            limit: 30,
            extractIocs: true
        );
        $messages = $res['messages'] ?? [];
        $seenIds = $state['seen_ids'] ?? [];
        $newAlerts = [];

        if (empty($seenIds)) {
            // First run: establish baseline seen message IDs
            $state['seen_ids'] = array_slice(array_column($messages, 'id'), 0, 50);

            // Create initial operational alert
            $title = $res['channel']['title'] ?? $monitor->target_value;
            $subs = $res['channel']['subscribers'] ?? 'Active';
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Telegram Monitor Active: @{$monitor->target_value}",
                'summary' => "Channel \"{$title}\" established baseline. Current Subscribers: {$subs}. Messages indexed: ".count($messages),
                'external_url' => 'https://t.me/'.$monitor->target_value,
                'severity' => 'info',
                'payload' => ['channel' => $res['channel'] ?? []],
            ]);
            $newAlerts[] = $alert;

            return $newAlerts;
        }

        // Detect new messages and threat indicators
        foreach ($messages as $msg) {
            $msgId = $msg['id'] ?? null;
            if ($msgId && ! in_array($msgId, $seenIds)) {
                $snippet = mb_substr(strip_tags((string) ($msg['text'] ?? '')), 0, 260);
                $iocs = $msg['iocs'] ?? [];
                $hasIocs = ($iocs['total'] ?? 0) > 0;
                $hasLeak = ! empty($iocs['leak_patterns']);
                $severity = $hasLeak ? 'critical' : ($hasIocs ? 'high' : 'medium');

                $summaryPrefix = '';
                if ($hasLeak) {
                    $summaryPrefix = '[LEAK DETECTED] ';
                } elseif ($hasIocs) {
                    $summaryPrefix = '[IOCs: '.$iocs['total'].'] ';
                }

                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => ($hasLeak || $hasIocs ? 'Threat Intelligence Alert in ' : 'New Telegram Message in ')."@{$monitor->target_value}",
                    'summary' => $summaryPrefix.($snippet ?: 'Media or link message published in monitored channel.'),
                    'external_url' => $msg['url'] ?? ('https://t.me/'.$monitor->target_value),
                    'severity' => $severity,
                    'payload' => $msg,
                ]);
                $newAlerts[] = $alert;
                $seenIds[] = $msgId;
            }
        }

        $state['seen_ids'] = array_slice(array_unique($seenIds), -100);

        return $newAlerts;
    }

    /**
     * Poll Reddit for new discussion posts or keyword mentions.
     */
    protected function pollReddit(ReconMonitor $monitor, array &$state): array
    {
        $res = $this->socialReconService->searchReddit($monitor->target_value, null, 'new');
        $posts = $res['results'] ?? [];
        $seenIds = $state['seen_ids'] ?? [];
        $newAlerts = [];

        if (empty($seenIds)) {
            // First run: establish baseline seen IDs
            $state['seen_ids'] = array_slice(array_column($posts, 'id'), 0, 50);

            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Reddit Monitor Active: \"{$monitor->target_value}\"",
                'summary' => 'Established baseline sweep. Initial discussions indexed: '.count($posts),
                'external_url' => 'https://reddit.com/search?q='.urlencode($monitor->target_value),
                'severity' => 'info',
                'payload' => ['initial_count' => count($posts)],
            ]);
            $newAlerts[] = $alert;

            return $newAlerts;
        }

        foreach ($posts as $post) {
            $postId = $post['id'] ?? null;
            if ($postId && ! in_array($postId, $seenIds)) {
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => 'New Reddit Mention: '.($post['title'] ?? 'Discussion'),
                    'summary' => ($post['author'] ?? 'User').' in '.($post['subreddit'] ?? 'r/all').': '.($post['snippet'] ?? ''),
                    'external_url' => $post['url'] ?? null,
                    'severity' => 'high',
                    'payload' => $post,
                ]);
                $newAlerts[] = $alert;
                $seenIds[] = $postId;
            }
        }

        $state['seen_ids'] = array_slice(array_unique($seenIds), -100);

        return $newAlerts;
    }

    /**
     * Poll Persona Footprint for newly registered accounts across networks.
     */
    protected function pollPersona(ReconMonitor $monitor, array &$state): array
    {
        // Probe top 15 key platforms
        $topPlatforms = ['github', 'gitlab', 'x', 'reddit_user', 'telegram', 'tiktok', 'instagram', 'keybase', 'steam', 'medium'];
        $res = $this->socialReconService->probeBatch($monitor->target_value, $topPlatforms);
        $discovered = array_filter($res, fn ($p) => ! empty($p['exists']));
        $discoveredIds = array_column($discovered, 'id');
        $seenPlatforms = $state['discovered_platforms'] ?? [];
        $newAlerts = [];

        if (empty($seenPlatforms)) {
            // Baseline
            $state['discovered_platforms'] = $discoveredIds;

            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Persona Monitor Active: {$monitor->target_value}",
                'summary' => 'Established baseline persona footprint. Currently discovered on '.count($discoveredIds).' networks.',
                'external_url' => '/social-recon',
                'severity' => 'info',
                'payload' => ['discovered_platforms' => $discoveredIds],
            ]);
            $newAlerts[] = $alert;

            return $newAlerts;
        }

        foreach ($discovered as $p) {
            if (! in_array($p['id'], $seenPlatforms)) {
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "New Persona Detection: {$p['name']}",
                    'summary' => "Target alias \"{$monitor->target_value}\" was identified active on {$p['name']}.",
                    'external_url' => $p['url'],
                    'severity' => 'high',
                    'payload' => $p,
                ]);
                $newAlerts[] = $alert;
                $seenPlatforms[] = $p['id'];
            }
        }

        $state['discovered_platforms'] = array_unique($seenPlatforms);

        return $newAlerts;
    }

    /**
     * Poll a Dark Web Hidden Service (.onion) for availability, defacement, content shifts, and leaked data.
     */
    protected function pollOnion(ReconMonitor $monitor, array &$state): array
    {
        $targetUrl = trim($monitor->target_value);
        if (! preg_match('#^https?://#i', $targetUrl)) {
            $targetUrl = 'http://'.$targetUrl;
        }

        $res = $this->deepScraperService->scrape(
            url: $targetUrl,
            collectImages: false,
            useTor: true,
            customKeywords: null,
            crawlSubpages: false
        );

        $newAlerts = [];
        $isSuccess = ($res['status'] ?? '') === 'success';
        $contentHash = sha1((string) ($res['raw_text_sample'] ?? ''));
        $emails = $res['emails'] ?? [];
        $docs = array_column($res['documents'] ?? [], 'name') ?: array_column($res['documents'] ?? [], 'url');
        $isBlacklisted = ! empty($res['is_blacklisted']);

        // First run baseline establishment
        if (empty($state['baseline_established'])) {
            $state['baseline_established'] = true;
            $state['is_online'] = $isSuccess;
            $state['content_hash'] = $contentHash;
            $state['seen_emails'] = array_values(array_unique($emails));
            $state['seen_documents'] = array_values(array_unique($docs));
            $state['is_blacklisted'] = $isBlacklisted;
            $state['last_status_code'] = $res['status_code'] ?? null;

            $statusText = $isSuccess ? "ONLINE (HTTP {$res['status_code']})" : 'OFFLINE / UNREACHABLE';
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Onion Monitor Active: {$monitor->target_value}",
                'summary' => "Established baseline sweep for hidden service. Status: {$statusText}. Initial emails: ".count($emails).', documents: '.count($docs).'.',
                'external_url' => $targetUrl,
                'severity' => 'info',
                'payload' => [
                    'status' => $res['status'] ?? 'unknown',
                    'status_code' => $res['status_code'] ?? null,
                    'title' => $res['title'] ?? null,
                    'response_time_seconds' => $res['response_time_seconds'] ?? null,
                ],
            ]);
            $newAlerts[] = $alert;

            return $newAlerts;
        }

        // Subsequent sweeps:
        // 1. Service state transition (offline -> online or online -> offline)
        $previousOnline = (bool) ($state['is_online'] ?? false);
        if ($previousOnline && ! $isSuccess) {
            $errorDesc = $res['error'] ?? 'Connection timed out or descriptor unreachable';
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Onion Service Offline: {$monitor->target_value}",
                'summary' => "Hidden service failed connection sweep via Tor circuit: {$errorDesc}",
                'external_url' => $targetUrl,
                'severity' => 'high',
                'payload' => $res,
            ]);
            $newAlerts[] = $alert;
        } elseif (! $previousOnline && $isSuccess) {
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Onion Service Back Online: {$monitor->target_value}",
                'summary' => "Hidden service restored Tor connectivity (HTTP {$res['status_code']}). Response latency: {$res['response_time_seconds']}s.",
                'external_url' => $targetUrl,
                'severity' => 'medium',
                'payload' => $res,
            ]);
            $newAlerts[] = $alert;
        }

        // 2. Ahmia abuse blacklist detection
        $previousBlacklisted = (bool) ($state['is_blacklisted'] ?? false);
        if (! $previousBlacklisted && $isBlacklisted) {
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Ahmia Blacklist Warning: {$monitor->target_value}",
                'summary' => 'Target onion service has been added to the public Ahmia abuse/malicious domain blacklist.',
                'external_url' => $targetUrl,
                'severity' => 'critical',
                'payload' => ['target' => $targetUrl, 'is_blacklisted' => true],
            ]);
            $newAlerts[] = $alert;
        }

        // 3. If online, check for content modifications, leaked emails, or newly posted documents
        if ($isSuccess) {
            $previousHash = $state['content_hash'] ?? '';
            $seenEmails = $state['seen_emails'] ?? [];
            $seenDocs = $state['seen_documents'] ?? [];

            $newEmails = array_values(array_diff($emails, $seenEmails));
            $newDocs = array_values(array_diff($docs, $seenDocs));

            if (! empty($newEmails)) {
                $snippet = implode(', ', array_slice($newEmails, 0, 4));
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "New Leaked Emails Detected on {$monitor->target_value}",
                    'summary' => 'Discovered '.count($newEmails)." new leaked email account(s): {$snippet}",
                    'external_url' => $targetUrl,
                    'severity' => 'critical',
                    'payload' => ['new_emails' => $newEmails],
                ]);
                $newAlerts[] = $alert;
                $state['seen_emails'] = array_slice(array_unique(array_merge($seenEmails, $newEmails)), -200);
            }

            if (! empty($newDocs)) {
                $snippet = implode(', ', array_slice($newDocs, 0, 4));
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "New Document / Data Dump Published on {$monitor->target_value}",
                    'summary' => 'Discovered '.count($newDocs)." newly posted file artifact(s): {$snippet}",
                    'external_url' => $targetUrl,
                    'severity' => 'high',
                    'payload' => ['new_documents' => $newDocs],
                ]);
                $newAlerts[] = $alert;
                $state['seen_documents'] = array_slice(array_unique(array_merge($seenDocs, $newDocs)), -200);
            }

            // General content defacement / modification
            if (empty($newEmails) && empty($newDocs) && $previousHash !== '' && $contentHash !== $previousHash) {
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "Content Modification Detected: {$monitor->target_value}",
                    'summary' => 'Onion page body digest changed. Potential site defacement, leak rotation, or hidden service notice update.',
                    'external_url' => $targetUrl,
                    'severity' => 'medium',
                    'payload' => ['old_hash' => $previousHash, 'new_hash' => $contentHash],
                ]);
                $newAlerts[] = $alert;
            }

            $state['content_hash'] = $contentHash;
        }

        $state['is_online'] = $isSuccess;
        $state['is_blacklisted'] = $isBlacklisted;
        $state['last_status_code'] = $res['status_code'] ?? null;

        return $newAlerts;
    }

    /**
     * Poll Network Infrastructure (clearnet domain or IP) for new subdomains, DNS changes, open ports, and CVEs.
     */
    protected function pollInfrastructure(ReconMonitor $monitor, array &$state): array
    {
        $target = trim($monitor->target_value);
        $newAlerts = [];
        $isIp = (bool) filter_var($target, FILTER_VALIDATE_IP);

        if ($isIp) {
            $res = $this->infrastructureReconService->inspectIp($target);
            if (! empty($res['error'])) {
                throw new \RuntimeException($res['error']);
            }

            $openPorts = $res['ports_intel']['open_ports'] ?? [];
            $cves = $res['ports_intel']['cves'] ?? [];
            $reverseDns = $res['reverse_dns'] ?? null;

            if (empty($state['baseline_established'])) {
                $state['baseline_established'] = true;
                $state['seen_ports'] = $openPorts;
                $state['seen_cves'] = $cves;
                $state['reverse_dns'] = $reverseDns;

                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "IP Watchdog Active: {$target}",
                    'summary' => "Established baseline for host {$target}. Reverse DNS: ".($reverseDns ?: 'None').'. Open ports: '.count($openPorts).', CVE vulnerabilities: '.count($cves).'.',
                    'external_url' => '/infrastructure',
                    'severity' => 'info',
                    'payload' => $res,
                ]);
                $newAlerts[] = $alert;

                return $newAlerts;
            }

            // Detect new open ports
            $seenPorts = $state['seen_ports'] ?? [];
            $newPorts = array_values(array_diff($openPorts, $seenPorts));
            if (! empty($newPorts)) {
                $dangerousPorts = [21, 22, 23, 445, 1433, 1521, 3306, 3389, 5432, 6379, 9200, 27017];
                $hasDangerous = ! empty(array_intersect($newPorts, $dangerousPorts));
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => ($hasDangerous ? 'CRITICAL Port Exposed on ' : 'New Open Port(s) on ')."{$target}",
                    'summary' => 'Newly exposed listening port(s) detected: '.implode(', ', $newPorts),
                    'external_url' => '/infrastructure',
                    'severity' => $hasDangerous ? 'critical' : 'high',
                    'payload' => ['new_ports' => $newPorts, 'all_ports' => $openPorts],
                ]);
                $newAlerts[] = $alert;
                $state['seen_ports'] = array_values(array_unique(array_merge($seenPorts, $newPorts)));
            }

            // Detect new CVEs
            $seenCves = $state['seen_cves'] ?? [];
            $newCves = array_values(array_diff($cves, $seenCves));
            if (! empty($newCves)) {
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "New CVE Vulnerability Detected on {$target}",
                    'summary' => 'InternetDB threat intelligence reported new CVE(s): '.implode(', ', array_slice($newCves, 0, 5)),
                    'external_url' => '/infrastructure',
                    'severity' => 'critical',
                    'payload' => ['new_cves' => $newCves],
                ]);
                $newAlerts[] = $alert;
                $state['seen_cves'] = array_values(array_unique(array_merge($seenCves, $newCves)));
            }

            // Detect reverse DNS hostname change
            $previousDns = $state['reverse_dns'] ?? null;
            if ($previousDns && $reverseDns && $previousDns !== $reverseDns) {
                $alert = ReconAlert::create([
                    'recon_monitor_id' => $monitor->id,
                    'investigation_id' => $monitor->investigation_id,
                    'title' => "Reverse DNS Hostname Changed: {$target}",
                    'summary' => "Pointer record updated from \"{$previousDns}\" to \"{$reverseDns}\".",
                    'external_url' => '/infrastructure',
                    'severity' => 'medium',
                    'payload' => ['old_ptr' => $previousDns, 'new_ptr' => $reverseDns],
                ]);
                $newAlerts[] = $alert;
                $state['reverse_dns'] = $reverseDns;
            }

            return $newAlerts;
        }

        // Domain inspection
        $res = $this->infrastructureReconService->inspectDomain($target);
        if (! empty($res['error']) || empty($res['success'])) {
            throw new \RuntimeException($res['error'] ?? 'Domain inspection failed');
        }

        $subdomains = array_column($res['subdomains']['subdomains'] ?? [], 'subdomain');
        $primaryIp = $res['primary_ip'] ?? null;
        $openPorts = $res['primary_ip_intel']['ports_intel']['open_ports'] ?? [];
        $cves = $res['primary_ip_intel']['ports_intel']['cves'] ?? [];

        if (empty($state['baseline_established'])) {
            $state['baseline_established'] = true;
            $state['primary_ip'] = $primaryIp;
            $state['seen_subdomains'] = $subdomains;
            $state['seen_ports'] = $openPorts;
            $state['seen_cves'] = $cves;

            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "Domain Monitor Active: {$target}",
                'summary' => "Established baseline for domain {$target}. Primary IP: ".($primaryIp ?: 'Unresolved').'. Subdomains discovered: '.count($subdomains).', Open ports: '.count($openPorts).'.',
                'external_url' => 'https://'.$target,
                'severity' => 'info',
                'payload' => $res['summary'] ?? [],
            ]);
            $newAlerts[] = $alert;

            return $newAlerts;
        }

        // Detect Primary IP Resolution Shift
        $previousIp = $state['primary_ip'] ?? null;
        if ($previousIp && $primaryIp && $previousIp !== $primaryIp) {
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "DNS Primary IP Shift Detected: {$target}",
                'summary' => "Domain A-record resolution changed from {$previousIp} to {$primaryIp}. Potential DNS migration, CDN failover, or hijacking.",
                'external_url' => 'https://'.$target,
                'severity' => 'high',
                'payload' => ['old_ip' => $previousIp, 'new_ip' => $primaryIp],
            ]);
            $newAlerts[] = $alert;
            $state['primary_ip'] = $primaryIp;
        }

        // Detect newly discovered subdomains via CT logs
        $seenSubs = $state['seen_subdomains'] ?? [];
        $newSubs = array_values(array_diff($subdomains, $seenSubs));
        if (! empty($newSubs)) {
            $snippet = implode(', ', array_slice($newSubs, 0, 4));
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => 'New Subdomain(s) Discovered: '.count($newSubs)." on {$target}",
                'summary' => "Certificate Transparency logs revealed new hostname(s): {$snippet}",
                'external_url' => 'https://'.($newSubs[0] ?? $target),
                'severity' => 'high',
                'payload' => ['new_subdomains' => $newSubs],
            ]);
            $newAlerts[] = $alert;
            $state['seen_subdomains'] = array_slice(array_unique(array_merge($seenSubs, $newSubs)), -500);
        }

        // Detect newly discovered ports on Primary IP
        $seenPorts = $state['seen_ports'] ?? [];
        $newPorts = array_values(array_diff($openPorts, $seenPorts));
        if (! empty($newPorts)) {
            $dangerousPorts = [21, 22, 23, 445, 1433, 1521, 3306, 3389, 5432, 6379, 9200, 27017];
            $hasDangerous = ! empty(array_intersect($newPorts, $dangerousPorts));
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => ($hasDangerous ? 'CRITICAL Open Port on Primary IP: ' : 'New Open Port on ')."{$target}",
                'summary' => "Host {$primaryIp} opened listening port(s): ".implode(', ', $newPorts),
                'external_url' => 'https://'.$target,
                'severity' => $hasDangerous ? 'critical' : 'high',
                'payload' => ['new_ports' => $newPorts, 'primary_ip' => $primaryIp],
            ]);
            $newAlerts[] = $alert;
            $state['seen_ports'] = array_values(array_unique(array_merge($seenPorts, $newPorts)));
        }

        // Detect newly reported CVEs
        $seenCves = $state['seen_cves'] ?? [];
        $newCves = array_values(array_diff($cves, $seenCves));
        if (! empty($newCves)) {
            $alert = ReconAlert::create([
                'recon_monitor_id' => $monitor->id,
                'investigation_id' => $monitor->investigation_id,
                'title' => "New CVE Vulnerability on {$target}",
                'summary' => "Shodan InternetDB reported new vulnerability on {$primaryIp}: ".implode(', ', array_slice($newCves, 0, 5)),
                'external_url' => 'https://'.$target,
                'severity' => 'critical',
                'payload' => ['new_cves' => $newCves, 'primary_ip' => $primaryIp],
            ]);
            $newAlerts[] = $alert;
            $state['seen_cves'] = array_values(array_unique(array_merge($seenCves, $newCves)));
        }

        return $newAlerts;
    }

    /**
     * Dispatch webhook payload (Discord / Slack / Generic API compatible).
     */
    public function dispatchWebhook(string $webhookUrl, ReconAlert $alert): bool
    {
        try {
            $color = match ($alert->severity) {
                'critical' => 15158332, // Red
                'high' => 15105570,     // Orange
                'medium' => 3447003,    // Blue
                default => 3066993,     // Green
            };

            $payload = [
                'content' => "🚨 **[DARKDUMP RECON ALERT]** {$alert->title}",
                'embeds' => [
                    [
                        'title' => $alert->title,
                        'description' => $alert->summary,
                        'url' => $alert->external_url,
                        'color' => $color,
                        'fields' => [
                            [
                                'name' => 'Severity',
                                'value' => strtoupper($alert->severity),
                                'inline' => true,
                            ],
                            [
                                'name' => 'Detection Time',
                                'value' => now()->toIso8601String(),
                                'inline' => true,
                            ],
                        ],
                        'footer' => [
                            'text' => 'Darkdump OSINT Suite v5 • Automated Monitor',
                        ],
                    ],
                ],
            ];

            $response = Http::timeout(5)->post($webhookUrl, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Webhook dispatch failed: '.$e->getMessage());

            return false;
        }
    }
}

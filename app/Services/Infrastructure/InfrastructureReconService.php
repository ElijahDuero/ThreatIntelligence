<?php

namespace App\Services\Infrastructure;

use App\Models\Investigation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfrastructureReconService
{
    /**
     * Common port to protocol service mapping.
     */
    protected array $wellKnownPorts = [
        20 => 'FTP-Data',
        21 => 'FTP Control',
        22 => 'SSH Remote Login',
        23 => 'Telnet',
        25 => 'SMTP Mail Server',
        53 => 'DNS Name Server',
        67 => 'DHCP',
        68 => 'DHCP',
        69 => 'TFTP',
        80 => 'HTTP Web Server',
        88 => 'Kerberos',
        110 => 'POP3 Mail',
        111 => 'RPCBind',
        123 => 'NTP Time Server',
        135 => 'MSRPC',
        137 => 'NetBIOS Name',
        138 => 'NetBIOS Datagram',
        139 => 'NetBIOS Session',
        143 => 'IMAP Mail',
        161 => 'SNMP',
        389 => 'LDAP Directory',
        443 => 'HTTPS Encrypted Web',
        445 => 'SMB File Sharing',
        465 => 'SMTPS Secure Mail',
        500 => 'IKE VPN',
        514 => 'Syslog',
        587 => 'SMTP Submission',
        636 => 'LDAPS Secure LDAP',
        993 => 'IMAPS Secure Mail',
        995 => 'POP3S Secure Mail',
        1080 => 'SOCKS Proxy',
        1194 => 'OpenVPN',
        1433 => 'Microsoft SQL Server',
        1521 => 'Oracle Database',
        2049 => 'NFS Network File System',
        2052 => 'ClearText Web/Proxy',
        2053 => 'Secure Web/Proxy',
        2082 => 'cPanel',
        2083 => 'cPanel SSL',
        2086 => 'WHM',
        2087 => 'WHM SSL',
        3000 => 'Node / Dev App',
        3306 => 'MySQL Database',
        3389 => 'RDP Remote Desktop',
        5432 => 'PostgreSQL Database',
        5672 => 'RabbitMQ',
        5900 => 'VNC Remote Desktop',
        6379 => 'Redis In-Memory Store',
        8000 => 'HTTP Alt / Dev Server',
        8080 => 'HTTP Proxy / Tomcat',
        8443 => 'HTTPS Alt / Management',
        8880 => 'HTTP Alt',
        8888 => 'HTTP Alt / Jupyter',
        9000 => 'PHP-FPM / SonarQube',
        9090 => 'Prometheus',
        9200 => 'Elasticsearch API',
        9300 => 'Elasticsearch Cluster',
        11211 => 'Memcached',
        27017 => 'MongoDB Database',
    ];

    /**
     * Inspect a domain: resolves DNS records, queries Certificate Transparency for subdomains,
     * fetches RDAP WHOIS metadata, and performs GeoIP analysis on the primary IP.
     */
    public function inspectDomain(string $rawDomain): array
    {
        $domain = $this->sanitizeDomain($rawDomain);
        if (empty($domain)) {
            return [
                'success' => false,
                'error' => 'Invalid domain format specified.',
            ];
        }

        $startTime = microtime(true);

        // 1. DNS Records Resolution
        $dnsRecords = $this->resolveDnsRecords($domain);

        // Extract primary IPv4
        $primaryIp = null;
        foreach ($dnsRecords['records'] as $rec) {
            if ($rec['type'] === 'A' && ! empty($rec['value'])) {
                $primaryIp = $rec['value'];
                break;
            }
        }

        // 2. GeoIP & ASN on Primary IP (if resolved)
        $primaryIpIntel = null;
        if ($primaryIp) {
            $primaryIpIntel = $this->inspectIp($primaryIp);
        }

        // 3. Passive Subdomain Discovery via Certificate Transparency (crt.sh)
        $subdomainIntel = $this->discoverSubdomainsFromCrtSh($domain);

        // 4. RDAP / WHOIS Registration Metadata
        $rdapIntel = $this->fetchRdapDomain($domain);

        $duration = round(microtime(true) - $startTime, 2);

        return [
            'success' => true,
            'target' => $domain,
            'type' => 'domain',
            'execution_time' => $duration,
            'primary_ip' => $primaryIp,
            'primary_ip_intel' => $primaryIpIntel,
            'dns' => $dnsRecords,
            'subdomains' => $subdomainIntel,
            'rdap' => $rdapIntel,
            'summary' => [
                'total_dns_records' => count($dnsRecords['records']),
                'total_subdomains' => count($subdomainIntel['subdomains']),
                'nameservers_count' => count($dnsRecords['grouped']['NS'] ?? []),
                'mail_servers_count' => count($dnsRecords['grouped']['MX'] ?? []),
                'registrar' => $rdapIntel['registrar'] ?? 'Unknown / Redacted',
                'hosting_org' => $primaryIpIntel['org'] ?? ($primaryIpIntel['isp'] ?? 'Unresolved'),
                'country' => $primaryIpIntel['country'] ?? 'Unknown',
                'total_open_ports' => $primaryIpIntel['ports_intel']['total_ports'] ?? 0,
                'total_cves' => $primaryIpIntel['ports_intel']['total_cves'] ?? 0,
            ],
        ];
    }

    /**
     * Inspect an IP address: reverse DNS, GeoIP, ASN, and RDAP network allocation.
     */
    public function inspectIp(string $rawIp): array
    {
        $ip = trim($rawIp);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'error' => 'Invalid IPv4 or IPv6 address.',
            ];
        }

        $isPrivate = ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        $reverseDns = null;
        try {
            $hostname = gethostbyaddr($ip);
            if ($hostname !== $ip && $hostname !== false) {
                $reverseDns = $hostname;
            }
        } catch (\Throwable $e) {
            // ignore DNS timeout
        }

        if ($isPrivate) {
            return [
                'success' => true,
                'ip' => $ip,
                'reverse_dns' => $reverseDns,
                'is_private' => true,
                'isp' => 'Private / Local Network (RFC 1918)',
                'org' => 'Private Network',
                'as' => 'N/A',
                'country' => 'Local / Private',
                'city' => 'Local Network',
                'region' => 'Local',
                'lat' => null,
                'lon' => null,
                'timezone' => 'UTC',
            ];
        }

        // Fetch IP-API GeoIP & ASN (Clearnet public endpoint)
        $geoData = [
            'country' => 'Unknown',
            'country_code' => '',
            'region' => '',
            'city' => '',
            'zip' => '',
            'lat' => null,
            'lon' => null,
            'timezone' => '',
            'isp' => 'Unknown ISP',
            'org' => 'Unknown Org',
            'as' => 'Unknown AS',
        ];

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'DarkDump-InfraRecon/2.0'])
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query',
                ]);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? '') === 'success') {
                    $geoData = [
                        'country' => $json['country'] ?? 'Unknown',
                        'country_code' => $json['countryCode'] ?? '',
                        'region' => $json['regionName'] ?? '',
                        'city' => $json['city'] ?? '',
                        'zip' => $json['zip'] ?? '',
                        'lat' => $json['lat'] ?? null,
                        'lon' => $json['lon'] ?? null,
                        'timezone' => $json['timezone'] ?? '',
                        'isp' => $json['isp'] ?? 'Unknown ISP',
                        'org' => $json['org'] ?? 'Unknown Org',
                        'as' => $json['as'] ?? 'Unknown AS',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InfraRecon: IP-API lookup failed: '.$e->getMessage(), ['ip' => $ip]);
        }

        // Fetch RDAP IP allocation block
        $networkBlock = null;
        try {
            $rdapRes = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/rdap+json, application/json'])
                ->get("https://rdap.org/ip/{$ip}");

            if ($rdapRes->successful()) {
                $rdapJson = $rdapRes->json();
                $networkBlock = [
                    'handle' => $rdapJson['handle'] ?? null,
                    'name' => $rdapJson['name'] ?? null,
                    'start_address' => $rdapJson['startAddress'] ?? null,
                    'end_address' => $rdapJson['endAddress'] ?? null,
                    'ip_version' => $rdapJson['ipVersion'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Optional RDAP fallback
        }

        $portsIntel = $this->fetchPassivePortsAndCves($ip);

        return array_merge([
            'success' => true,
            'ip' => $ip,
            'reverse_dns' => $reverseDns,
            'is_private' => false,
            'network_block' => $networkBlock,
            'ports_intel' => $portsIntel,
        ], $geoData);
    }

    /**
     * Resolve all standard DNS records for a domain.
     */
    protected function resolveDnsRecords(string $domain): array
    {
        $records = [];
        $grouped = [
            'A' => [],
            'AAAA' => [],
            'MX' => [],
            'NS' => [],
            'TXT' => [],
            'CNAME' => [],
            'SOA' => [],
        ];

        // Specific record types to query
        $typesToQuery = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'MX' => DNS_MX,
            'NS' => DNS_NS,
            'TXT' => DNS_TXT,
            'CNAME' => DNS_CNAME,
            'SOA' => DNS_SOA,
        ];

        foreach ($typesToQuery as $typeName => $dnsConst) {
            try {
                $results = @dns_get_record($domain, $dnsConst);
                if (is_array($results)) {
                    foreach ($results as $item) {
                        $parsed = $this->formatDnsRecord($typeName, $item);
                        if ($parsed) {
                            $records[] = $parsed;
                            $grouped[$typeName][] = $parsed;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Continue with other record types
            }
        }

        return [
            'records' => $records,
            'grouped' => $grouped,
            'counts' => array_map('count', $grouped),
        ];
    }

    /**
     * Format raw PHP DNS record array into clean uniform structure.
     */
    protected function formatDnsRecord(string $type, array $raw): ?array
    {
        $host = $raw['host'] ?? '';
        $ttl = $raw['ttl'] ?? 300;
        $class = $raw['class'] ?? 'IN';
        $value = null;
        $extra = [];

        switch ($type) {
            case 'A':
                $value = $raw['ip'] ?? null;
                break;
            case 'AAAA':
                $value = $raw['ipv6'] ?? null;
                break;
            case 'MX':
                $value = $raw['target'] ?? null;
                $extra['priority'] = $raw['pri'] ?? 10;
                break;
            case 'NS':
                $value = $raw['target'] ?? null;
                break;
            case 'CNAME':
                $value = $raw['target'] ?? null;
                break;
            case 'TXT':
                $value = $raw['txt'] ?? ($raw['entries'][0] ?? null);
                break;
            case 'SOA':
                $value = ($raw['mname'] ?? '').' '.($raw['rname'] ?? '');
                $extra['serial'] = $raw['serial'] ?? null;
                $extra['refresh'] = $raw['refresh'] ?? null;
                $extra['retry'] = $raw['retry'] ?? null;
                $extra['expire'] = $raw['expire'] ?? null;
                $extra['minimum_ttl'] = $raw['minimum-ttl'] ?? null;
                break;
            default:
                $value = $raw['target'] ?? ($raw['ip'] ?? null);
        }

        if (empty($value)) {
            return null;
        }

        return [
            'type' => $type,
            'host' => $host,
            'value' => $value,
            'ttl' => $ttl,
            'class' => $class,
            'extra' => $extra,
        ];
    }

    /**
     * Discover subdomains passively using crt.sh Certificate Transparency logs.
     */
    protected function discoverSubdomainsFromCrtSh(string $domain): array
    {
        $subdomains = [];
        $certificates = [];

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 DarkDump/OSINT',
                    'Accept' => 'application/json',
                ])
                ->get("https://crt.sh/?q=%25.{$domain}&output=json");

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    $seenSubdomains = [];

                    foreach ($data as $entry) {
                        $nameValue = $entry['name_value'] ?? '';
                        $issuerName = $entry['issuer_name'] ?? 'Unknown CA';
                        $notBefore = $entry['not_before'] ?? null;
                        $notAfter = $entry['not_after'] ?? null;
                        $certId = $entry['id'] ?? null;

                        // Names can be newline delimited in SAN entries
                        $names = explode("\n", (string) $nameValue);
                        foreach ($names as $name) {
                            $clean = strtolower(trim($name));
                            // Strip wildcard prefix
                            if (str_starts_with($clean, '*.')) {
                                $clean = substr($clean, 2);
                            }

                            // Ensure it is a valid subdomain under the root target
                            if (! empty($clean) && (str_ends_with($clean, '.'.$domain) || $clean === $domain)) {
                                if (! isset($seenSubdomains[$clean])) {
                                    $seenSubdomains[$clean] = [
                                        'subdomain' => $clean,
                                        'issuer' => $this->simplifyIssuer($issuerName),
                                        'valid_from' => $notBefore,
                                        'valid_until' => $notAfter,
                                        'cert_id' => $certId,
                                    ];
                                }
                            }
                        }
                    }

                    $subdomains = array_values($seenSubdomains);

                    // Sort subdomains alphabetically
                    usort($subdomains, function ($a, $b) {
                        return strcmp($a['subdomain'], $b['subdomain']);
                    });

                    // Cap to 120 most relevant subdomains to prevent UI bloat
                    $subdomains = array_slice($subdomains, 0, 120);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InfraRecon: crt.sh query failed: '.$e->getMessage(), ['domain' => $domain]);
        }

        return [
            'total_found' => count($subdomains),
            'source' => 'crt.sh (Certificate Transparency Logs)',
            'subdomains' => $subdomains,
        ];
    }

    /**
     * Simplify Certificate Issuer string for clean display.
     */
    protected function simplifyIssuer(string $rawIssuer): string
    {
        if (preg_match('/O=([^,]+)/', $rawIssuer, $matches)) {
            return trim($matches[1], ' "\'');
        }
        if (preg_match('/CN=([^,]+)/', $rawIssuer, $matches)) {
            return trim($matches[1], ' "\'');
        }

        return substr($rawIssuer, 0, 40);
    }

    /**
     * Query RDAP (Registration Data Access Protocol) for domain WHOIS registry data.
     */
    protected function fetchRdapDomain(string $domain): array
    {
        $info = [
            'domain' => $domain,
            'registrar' => 'Unknown / Privacy Protected',
            'status' => [],
            'registration_date' => null,
            'expiration_date' => null,
            'last_changed_date' => null,
            'nameservers' => [],
            'raw_available' => false,
        ];

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'Accept' => 'application/rdap+json, application/json',
                    'User-Agent' => 'DarkDump-OSINT-Recon/2.0',
                ])
                ->get("https://rdap.org/domain/{$domain}");

            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    $info['raw_available'] = true;
                    $info['status'] = $json['status'] ?? [];

                    // Dates from events
                    if (! empty($json['events']) && is_array($json['events'])) {
                        foreach ($json['events'] as $ev) {
                            $action = $ev['eventAction'] ?? '';
                            $date = $ev['eventDate'] ?? null;
                            if ($action === 'registration') {
                                $info['registration_date'] = $date;
                            } elseif ($action === 'expiration') {
                                $info['expiration_date'] = $date;
                            } elseif ($action === 'last changed') {
                                $info['last_changed_date'] = $date;
                            }
                        }
                    }

                    // Nameservers
                    if (! empty($json['nameservers']) && is_array($json['nameservers'])) {
                        foreach ($json['nameservers'] as $ns) {
                            if (! empty($ns['ldhName'])) {
                                $info['nameservers'][] = strtolower($ns['ldhName']);
                            }
                        }
                    }

                    // Entities (Registrar)
                    if (! empty($json['entities']) && is_array($json['entities'])) {
                        foreach ($json['entities'] as $entity) {
                            $roles = $entity['roles'] ?? [];
                            if (in_array('registrar', $roles, true)) {
                                // Extract name from vcardArray
                                if (! empty($entity['vcardArray'][1]) && is_array($entity['vcardArray'][1])) {
                                    foreach ($entity['vcardArray'][1] as $prop) {
                                        if (is_array($prop) && ($prop[0] ?? '') === 'fn') {
                                            $info['registrar'] = (string) ($prop[3] ?? 'Unknown');
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('InfraRecon: RDAP query failed: '.$e->getMessage(), ['domain' => $domain]);
        }

        return $info;
    }

    /**
     * Inject discovered infrastructure entities and relationships into an Investigation Case Dossier link graph.
     */
    public function linkToInvestigation(int $investigationId, array $nodes, array $edges): array
    {
        $investigation = Investigation::findOrFail($investigationId);

        $currentGraph = $investigation->graph_data ?? [];
        $existingNodes = $currentGraph['nodes'] ?? [];
        $existingEdges = $currentGraph['edges'] ?? [];

        $nodeMap = [];
        foreach ($existingNodes as $n) {
            if (! empty($n['id'])) {
                $nodeMap[$n['id']] = true;
            }
        }

        $edgeMap = [];
        foreach ($existingEdges as $e) {
            if (! empty($e['id'])) {
                $edgeMap[$e['id']] = true;
            }
        }

        $addedNodesCount = 0;
        foreach ($nodes as $newNode) {
            if (! empty($newNode['id']) && ! isset($nodeMap[$newNode['id']])) {
                $existingNodes[] = $newNode;
                $nodeMap[$newNode['id']] = true;
                $addedNodesCount++;
            }
        }

        $addedEdgesCount = 0;
        foreach ($edges as $newEdge) {
            if (! empty($newEdge['id']) && ! isset($edgeMap[$newEdge['id']])) {
                $existingEdges[] = $newEdge;
                $edgeMap[$newEdge['id']] = true;
                $addedEdgesCount++;
            }
        }

        $investigation->graph_data = [
            'nodes' => $existingNodes,
            'edges' => $existingEdges,
        ];
        $investigation->save();

        return [
            'success' => true,
            'investigation_id' => $investigation->id,
            'case_number' => 'DD-'.str_pad((string) $investigation->id, 4, '0', STR_PAD_LEFT),
            'added_nodes' => $addedNodesCount,
            'added_edges' => $addedEdgesCount,
            'total_nodes' => count($existingNodes),
            'total_edges' => count($existingEdges),
        ];
    }

    /**
     * Sanitize input into clean lowercase domain name without protocols, paths or ports.
     */
    protected function sanitizeDomain(string $raw): string
    {
        $domain = trim($raw);
        // Strip protocol
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Strip path and query parameters
        $domain = preg_replace('~[/\\?#].*$~', '', $domain);
        // Strip port
        $domain = preg_replace('#:\d+$#', '', $domain);
        // Lowercase
        $domain = strtolower($domain);

        // Basic domain validation
        if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i', $domain)) {
            return '';
        }

        return $domain;
    }

    /**
     * Passive Port, Service, and CVE Vulnerability Discovery via Shodan InternetDB.
     */
    public function fetchPassivePortsAndCves(string $ip): array
    {
        $data = [
            'ports' => [],
            'cves' => [],
            'cpes' => [],
            'tags' => [],
            'hostnames' => [],
            'total_ports' => 0,
            'total_cves' => 0,
            'has_vulnerabilities' => false,
        ];

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'DarkDump-InfraRecon/2.0',
                    'Accept' => 'application/json',
                ])
                ->get("https://internetdb.shodan.io/{$ip}");

            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    $rawPorts = $json['ports'] ?? [];
                    sort($rawPorts);
                    $mappedPorts = [];
                    foreach ($rawPorts as $portNum) {
                        $mappedPorts[] = [
                            'port' => (int) $portNum,
                            'service' => $this->wellKnownPorts[(int) $portNum] ?? 'TCP Service',
                            'is_common' => isset($this->wellKnownPorts[(int) $portNum]),
                        ];
                    }

                    $rawCves = $json['vulns'] ?? [];
                    $cves = [];
                    foreach ($rawCves as $cveId) {
                        $cves[] = [
                            'id' => $cveId,
                            'nvd_url' => "https://nvd.nist.gov/vuln/detail/{$cveId}",
                            'mitre_url' => "https://cve.mitre.org/cgi-bin/cvename.cgi?name={$cveId}",
                        ];
                    }

                    $data = [
                        'ports' => $mappedPorts,
                        'cves' => $cves,
                        'cpes' => $json['cpes'] ?? [],
                        'tags' => $json['tags'] ?? [],
                        'hostnames' => $json['hostnames'] ?? [],
                        'total_ports' => count($mappedPorts),
                        'total_cves' => count($cves),
                        'has_vulnerabilities' => count($cves) > 0,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::info('InfraRecon: InternetDB port lookup failed: '.$e->getMessage(), ['ip' => $ip]);
        }

        return $data;
    }
}

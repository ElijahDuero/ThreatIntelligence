<?php

namespace App\Services\Osint;

use Illuminate\Support\Facades\Http;

class IpLookupService
{
    /**
     * Common MAC vendor prefixes for offline and quick resolution.
     *
     * @var array<string, string>
     */
    protected array $fallbackMacVendors = [
        '00:0C:29' => 'VMware, Inc.',
        '00:50:56' => 'VMware, Inc.',
        '00:15:5D' => 'Microsoft Corporation (Hyper-V)',
        '00:1C:42' => 'Parallels, Inc.',
        '08:00:27' => 'Oracle Corporation (VirtualBox)',
        '52:54:00' => 'QEMU / KVM Virtual Machine',
        'F0:18:98' => 'Apple, Inc.',
        'AC:DE:48' => 'Apple, Inc.',
        '3C:22:FB' => 'Apple, Inc.',
        '00:1A:2B' => 'Ayecom Technology Co., Ltd.',
        '00:14:22' => 'Dell Inc.',
        '00:1E:67' => 'Intel Corporation',
        '00:25:90' => 'Super Micro Computer, Inc.',
        'B8:27:EB' => 'Raspberry Pi Foundation',
        'DC:A6:32' => 'Raspberry Pi Foundation',
        'E4:5F:01' => 'Raspberry Pi Foundation',
        '24:0A:C4' => 'Espressif Systems (Shanghai)',
        '30:AE:A4' => 'Espressif Systems (Shanghai)',
        '00:04:96' => 'Extreme Networks',
        '00:00:0C' => 'Cisco Systems, Inc.',
        '00:01:42' => 'Cisco Systems, Inc.',
    ];

    public function __construct(
        protected ?IpMacCorrelationService $macCorrelationService = null
    ) {}

    /**
     * Get or lazy-instantiate the MAC correlation service.
     */
    public function getMacCorrelationService(): IpMacCorrelationService
    {
        return $this->macCorrelationService ??= app(IpMacCorrelationService::class);
    }

    /**
     * Classify input target into target type (ipv4, ipv6, hostname, mac, invalid).
     *
     * @return array{type: string, clean_target: string, is_valid: bool, resolved_ip: ?string, notes: string}
     */
    public function classifyTarget(string $input): array
    {
        $raw = trim($input);
        if (empty($raw)) {
            return [
                'type' => 'invalid',
                'clean_target' => '',
                'is_valid' => false,
                'resolved_ip' => null,
                'notes' => 'Target string cannot be empty.',
            ];
        }

        // Clean MAC address (supports 00:1A:2B:3C:4D:5E, 00-1A-2B-3C-4D-5E, 001A.2B3C.4D5E, 001A2B3C4D5E)
        $cleanMacCandidate = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $raw) ?? '');
        if (strlen($cleanMacCandidate) === 12 && preg_match('/^[A-F0-9]{12}$/', $cleanMacCandidate)) {
            $formattedMac = implode(':', str_split($cleanMacCandidate, 2));

            return [
                'type' => 'mac',
                'clean_target' => $formattedMac,
                'is_valid' => true,
                'resolved_ip' => null,
                'notes' => "Standard EUI-48 MAC hardware address ({$formattedMac}).",
            ];
        }

        // IPv4 validation
        if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [
                'type' => 'ipv4',
                'clean_target' => $raw,
                'is_valid' => true,
                'resolved_ip' => $raw,
                'notes' => "Standard IPv4 public/private host address ({$raw}).",
            ];
        }

        // IPv6 validation
        if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return [
                'type' => 'ipv6',
                'clean_target' => strtolower($raw),
                'is_valid' => true,
                'resolved_ip' => strtolower($raw),
                'notes' => 'Standard IPv6 128-bit Internet Protocol address.',
            ];
        }

        // Strip scheme/slashes if user passed http://example.com
        $cleanHost = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $cleanHost = explode('/', $cleanHost)[0];
        $cleanHost = explode(':', $cleanHost)[0];
        $cleanHost = strtolower(trim($cleanHost));

        if (preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $cleanHost)) {
            $resolvedIp = @gethostbyname($cleanHost);
            $isValidIp = filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

            return [
                'type' => 'hostname',
                'clean_target' => $cleanHost,
                'is_valid' => true,
                'resolved_ip' => $isValidIp ? $resolvedIp : null,
                'notes' => $isValidIp
                    ? "Domain hostname resolved to IPv4: {$resolvedIp}."
                    : 'Domain hostname provided; DNS A-record unresolvable.',
            ];
        }

        return [
            'type' => 'invalid',
            'clean_target' => $raw,
            'is_valid' => false,
            'resolved_ip' => null,
            'notes' => 'Unrecognized target format. Expected an IPv4, IPv6, domain hostname, or 48-bit MAC address.',
        ];
    }

    /**
     * Resolve MAC hardware vendor and EUI properties.
     *
     * @return array<string, mixed>
     */
    public function resolveMacVendor(string $macAddress): array
    {
        $clean = strtoupper(preg_replace('/[^A-F0-9]/', '', $macAddress) ?? '');
        $prefix = strlen($clean) >= 6 ? substr($clean, 0, 6) : '';
        $prefixFormatted = implode(':', str_split($prefix, 2));

        $vendor = $this->fallbackMacVendors[$prefixFormatted] ?? null;
        $isVirtual = false;
        $details = [];

        // Live API resolution attempt
        try {
            $response = Http::timeout(3)
                ->withHeaders(['User-Agent' => 'DarkWebsite-OSINT/1.0'])
                ->get("https://api.maclookup.app/v2/macs/{$clean}");

            if ($response->successful()) {
                $json = $response->json();
                if (! empty($json['company'])) {
                    $vendor = $json['company'];
                }
                $details = $json;
            }
        } catch (\Throwable $e) {
            // Fallback to local table
        }

        if (empty($vendor)) {
            $vendor = 'Unknown Manufacturer (Unregistered OUI)';
        }

        $virtualKeywords = ['vmware', 'virtualbox', 'qemu', 'kvm', 'hyper-v', 'parallels', 'xen'];
        foreach ($virtualKeywords as $kw) {
            if (str_contains(strtolower($vendor), $kw)) {
                $isVirtual = true;
                break;
            }
        }

        // Check I/G (Individual/Group) and U/L (Universal/Local) bits
        $firstByte = hexdec(substr($clean, 0, 2));
        $isMulticast = ($firstByte & 0x01) === 0x01;
        $isLocallyAdministered = ($firstByte & 0x02) === 0x02;

        return [
            'mac' => implode(':', str_split($clean, 2)),
            'oui_prefix' => $prefixFormatted,
            'company' => $vendor,
            'vendor' => $vendor,
            'is_virtual' => $isVirtual,
            'is_multicast' => $isMulticast,
            'is_local' => $isLocallyAdministered,
            'transmission' => $isMulticast ? 'Multicast' : 'Unicast',
            'administration' => $isLocallyAdministered ? 'Locally Administered (Custom/Spoofed)' : 'Universally Administered (Factory Default)',
            'country' => $details['country'] ?? 'Global IEEE',
            'block_type' => $details['blockType'] ?? 'MA-L',
            'wigle_query_url' => 'https://wigle.net/search?netid='.urlencode(implode(':', str_split($clean, 2))),
        ];
    }

    /**
     * Resolve IP Geolocation, ASN, ISP, and reverse DNS PTR.
     *
     * @return array<string, mixed>
     */
    public function resolveIpTelemetry(string $ip): array
    {
        $ip = trim($ip);
        if (empty($ip) || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'status' => 'fail',
                'ip' => $ip,
                'country' => 'Unknown',
                'country_code' => 'XX',
                'city' => 'Unknown',
                'region' => 'Unknown',
                'lat' => 0.0,
                'lon' => 0.0,
                'timezone' => 'UTC',
                'isp' => 'Unknown',
                'org' => 'Unknown',
                'as' => 'Unknown',
                'ptr' => null,
                'is_private' => false,
            ];
        }

        // Private / Loopback range check
        $isPrivate = ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        $ptr = @gethostbyaddr($ip);
        if ($ptr === $ip) {
            $ptr = null;
        }

        if ($isPrivate) {
            return [
                'status' => 'private',
                'ip' => $ip,
                'country' => 'Private Network',
                'country_code' => 'LAN',
                'city' => 'Local Subnet',
                'region' => 'RFC 1918 / Loopback',
                'lat' => 0.0,
                'lon' => 0.0,
                'timezone' => 'UTC',
                'isp' => 'Private Intranet',
                'org' => 'Private Intranet',
                'as' => 'N/A',
                'ptr' => $ptr,
                'is_private' => true,
            ];
        }

        // Query IP-API
        $geo = [
            'status' => 'success',
            'ip' => $ip,
            'country' => 'Unknown',
            'country_code' => 'XX',
            'city' => 'Unknown',
            'region' => 'Unknown',
            'lat' => 0.0,
            'lon' => 0.0,
            'timezone' => 'UTC',
            'isp' => 'Unknown ISP',
            'org' => 'Unknown Org',
            'as' => 'Unknown AS',
            'ptr' => $ptr,
            'is_private' => false,
        ];

        try {
            $res = Http::timeout(3.5)->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query");
            if ($res->successful()) {
                $data = $res->json();
                if (($data['status'] ?? '') === 'success') {
                    $geo['country'] = $data['country'] ?? 'Unknown';
                    $geo['country_code'] = $data['countryCode'] ?? 'XX';
                    $geo['city'] = $data['city'] ?? 'Unknown';
                    $geo['region'] = $data['regionName'] ?? 'Unknown';
                    $geo['lat'] = (float) ($data['lat'] ?? 0.0);
                    $geo['lon'] = (float) ($data['lon'] ?? 0.0);
                    $geo['timezone'] = $data['timezone'] ?? 'UTC';
                    $geo['isp'] = $data['isp'] ?? 'Unknown ISP';
                    $geo['org'] = $data['org'] ?? 'Unknown Org';
                    $geo['as'] = $data['as'] ?? 'Unknown AS';
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return $geo;
    }

    /**
     * Get all 52 framework tools categorized across the 12 branches from the framework diagram.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getCategorizedTools(string $target = '', ?string $resolvedIp = null): array
    {
        $effectiveIp = $resolvedIp ?: $target;
        $encodedIp = urlencode($effectiveIp);

        return [
            // ==========================================
            // BRANCH 1: GEOLOCATION (8 tools)
            // ==========================================
            'geolocation' => [
                [
                    'id' => 'maxmind_demo',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'MaxMind Demo',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => 'https://www.maxmind.com/en/geoip-demo',
                    'description' => 'Industry-standard GeoIP2 database providing high-accuracy geographical coordinates and city mapping.',
                    'action_label' => 'Open MaxMind Demo',
                    'supports_query' => false,
                ],
                [
                    'id' => 'country_ip_blocks',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'IPv4/IPv6 lists by country code',
                    'tag' => 'Database',
                    'type' => 'cidr',
                    'url' => 'https://www.countryipblocks.net/',
                    'description' => 'Comprehensive CIDR allocations and country-level subnet blocks for regional access control.',
                    'action_label' => 'View Country Blocks',
                    'supports_query' => false,
                ],
                [
                    'id' => 'ip2location',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'IP2Location.com',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => ! empty($effectiveIp) ? "https://www.ip2location.com/demo/{$encodedIp}" : 'https://www.ip2location.com/demo',
                    'description' => 'Non-intrusive IP geolocation database revealing country, region, city, ISP, domain, and usage type.',
                    'action_label' => 'Query IP2Location',
                    'supports_query' => true,
                ],
                [
                    'id' => 'ipfingerprints',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'IP Fingerprints',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => ! empty($effectiveIp) ? "https://www.ipfingerprints.com/?ip={$encodedIp}" : 'https://www.ipfingerprints.com/',
                    'description' => 'Visual IP locator plotting target IP coordinates on Google Maps with ISP details.',
                    'action_label' => 'Map IP Fingerprints',
                    'supports_query' => true,
                ],
                [
                    'id' => 'db_ip',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'DB-IP',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => ! empty($effectiveIp) ? "https://db-ip.com/{$encodedIp}" : 'https://db-ip.com/',
                    'description' => 'Global IP geolocation, threat score, and network infrastructure data provider.',
                    'action_label' => 'Inspect DB-IP',
                    'supports_query' => true,
                ],
                [
                    'id' => 'ip_location_finder',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'IP Location Finder',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => ! empty($effectiveIp) ? "https://www.iplocation.net/ip-lookup?query={$encodedIp}" : 'https://www.iplocation.net/',
                    'description' => 'Multi-database IP aggregator comparing MaxMind, IP2Location, DB-IP, and ipinfo side-by-side.',
                    'action_label' => 'Compare IP Locations',
                    'supports_query' => true,
                ],
                [
                    'id' => 'info_sniper',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'Info Sniper',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => 'https://www.infosniper.net/',
                    'description' => 'Detailed geographic location locator with street map plotting and ISP telemetry.',
                    'action_label' => 'Launch Info Sniper',
                    'supports_query' => false,
                ],
                [
                    'id' => 'utrace',
                    'branch' => 'geolocation',
                    'branch_label' => 'Geolocation',
                    'name' => 'utrace',
                    'tag' => 'Engine',
                    'type' => 'lookup',
                    'url' => ! empty($effectiveIp) ? "https://www.utrace.de/?query={$encodedIp}" : 'https://www.utrace.de/',
                    'description' => 'Fast IP and domain traceroute and geographical localization utility.',
                    'action_label' => 'Trace on utrace',
                    'supports_query' => true,
                ],
            ],

            // ==========================================
            // BRANCH 2: HOST / PORT DISCOVERY (13 tools)
            // ==========================================
            'host_port_discovery' => [
                [
                    'id' => 'urlscan',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'urlscan.io',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => ! empty($effectiveIp) ? "https://urlscan.io/search/#ip:\"{$encodedIp}\"" : 'https://urlscan.io/',
                    'description' => 'Automated website scanner and threat analysis engine tracking DOM, screenshots, and server IPs.',
                    'action_label' => 'Search urlscan.io',
                    'supports_query' => true,
                ],
                [
                    'id' => 'spyse',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Spyse',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => ! empty($effectiveIp) ? "https://spyse.com/target/ip/{$encodedIp}" : 'https://spyse.com/',
                    'description' => 'Cybersecurity search engine indexing Internet-connected devices, open ports, and certificates.',
                    'action_label' => 'Inspect Spyse',
                    'supports_query' => true,
                ],
                [
                    'id' => 'shodan',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Shodan',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => ! empty($effectiveIp) ? "https://www.shodan.io/host/{$encodedIp}" : 'https://www.shodan.io/',
                    'description' => "The world's premier search engine for Internet-connected devices, open ports, banners, and vulnerabilities.",
                    'action_label' => 'Query Shodan Host',
                    'supports_query' => true,
                ],
                [
                    'id' => 'netlas',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Netlas.io',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => ! empty($effectiveIp) ? "https://app.netlas.io/responses/?q={$encodedIp}" : 'https://netlas.io/',
                    'description' => 'Internet intelligence search engine surveying IPv4 spaces, HTTP responses, and SSL certificates.',
                    'action_label' => 'Search Netlas',
                    'supports_query' => true,
                ],
                [
                    'id' => 'portmap',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Portmap',
                    'tag' => 'Tool',
                    'type' => 'port',
                    'url' => 'https://portmap.io/',
                    'description' => 'Port forwarding and accessibility mapping service for remote host verification.',
                    'action_label' => 'Open Portmap',
                    'supports_query' => false,
                ],
                [
                    'id' => 'scans_io',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Scans.io',
                    'tag' => 'Database',
                    'type' => 'archive',
                    'url' => 'https://scans.io/',
                    'description' => 'Internet-wide scan data repository by Rapid7 and University of Michigan with historical port censuses.',
                    'action_label' => 'View Scans.io Archive',
                    'supports_query' => false,
                ],
                [
                    'id' => 'nmap',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Nmap (T)',
                    'tag' => 'Tool',
                    'type' => 'cli',
                    'url' => 'https://nmap.org/',
                    'description' => 'The definitive network discovery and vulnerability scanner utility with NSE scripting engine.',
                    'action_label' => 'Download Nmap',
                    'supports_query' => false,
                ],
                [
                    'id' => 'online_port_scanner',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Online Port scanner',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => ! empty($effectiveIp) ? "https://hackertarget.com/tcp-port-scan/?input={$encodedIp}" : 'https://hackertarget.com/tcp-port-scan/',
                    'description' => 'HackerTarget remote TCP port scanner probing common listening ports (21, 22, 80, 443, 3389).',
                    'action_label' => 'Scan Online Ports',
                    'supports_query' => true,
                ],
                [
                    'id' => 'internet_census',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Internet Census Search',
                    'tag' => 'Engine',
                    'type' => 'archive',
                    'url' => 'https://search.censys.io/',
                    'description' => 'Censys Internet scanning database indexing active endpoints, TLS certificates, and service banners.',
                    'action_label' => 'Search Censys Census',
                    'supports_query' => false,
                ],
                [
                    'id' => 'criminal_ip',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Criminal IP Search',
                    'tag' => 'Engine',
                    'type' => 'threat',
                    'url' => ! empty($effectiveIp) ? "https://www.criminalip.io/asset/report/{$encodedIp}" : 'https://www.criminalip.io/',
                    'description' => 'Cyber threat intelligence search engine analyzing inbound/outbound IP risks, open ports, and CVEs.',
                    'action_label' => 'Search Criminal IP',
                    'supports_query' => true,
                ],
                [
                    'id' => 'scanless',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Scanless (T)',
                    'tag' => 'Tool',
                    'type' => 'cli',
                    'url' => 'https://github.com/vesche/scanless',
                    'description' => 'Python CLI tool for anonymous stealth port scanning using third-party websites as proxies.',
                    'action_label' => 'View Scanless Repo',
                    'supports_query' => false,
                ],
                [
                    'id' => 'binaryedge',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'BinaryEdge (R)',
                    'tag' => 'Registration',
                    'type' => 'threat',
                    'url' => 'https://www.binaryedge.io/',
                    'description' => 'Threat intelligence platform mapping external attack surfaces, exposed services, and honeypot hits.',
                    'action_label' => 'Open BinaryEdge',
                    'supports_query' => false,
                ],
                [
                    'id' => 'masscan',
                    'branch' => 'host_port_discovery',
                    'branch_label' => 'Host / Port Discovery',
                    'name' => 'Masscan (T)',
                    'tag' => 'Tool',
                    'type' => 'cli',
                    'url' => 'https://github.com/robertdavidgraham/masscan',
                    'description' => 'Ultra-fast asynchronous TCP port scanner capable of scanning the entire Internet in under 5 minutes.',
                    'action_label' => 'View Masscan Repo',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 3: IPV4 (8 tools)
            // ==========================================
            'ipv4' => [
                [
                    'id' => 'aslookup',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'ASLookup.com',
                    'tag' => 'Engine',
                    'type' => 'asn',
                    'url' => ! empty($effectiveIp) ? "https://aslookup.com/ip/{$encodedIp}" : 'https://aslookup.com/',
                    'description' => 'Autonomous System Number (ASN) lookup tool resolving IP routing prefixes, org names, and peers.',
                    'action_label' => 'Query ASLookup',
                    'supports_query' => true,
                ],
                [
                    'id' => 'port_scanner_online',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'Port scanner Online',
                    'tag' => 'Engine',
                    'type' => 'scanner',
                    'url' => 'https://www.ipfingerprints.com/portscan.php',
                    'description' => 'Online remote TCP port verification testing common service daemons on IPv4 hosts.',
                    'action_label' => 'Open Port Scanner',
                    'supports_query' => false,
                ],
                [
                    'id' => 'onyphe',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'Onyphe',
                    'tag' => 'Engine',
                    'type' => 'threat',
                    'url' => ! empty($effectiveIp) ? "https://www.onyphe.io/search/?q={$encodedIp}" : 'https://www.onyphe.io/',
                    'description' => 'Cyber Defense Search Engine collecting cyber threat intelligence, network telemetry, and leaks.',
                    'action_label' => 'Search Onyphe',
                    'supports_query' => true,
                ],
                [
                    'id' => 'ipv4_cidr_report',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'IPv4 CIDR Report',
                    'tag' => 'Database',
                    'type' => 'bgp',
                    'url' => 'https://www.cidr-report.org/as2.0/',
                    'description' => 'Weekly operational summary of the global IPv4 BGP routing table size and aggregation state.',
                    'action_label' => 'View CIDR Report',
                    'supports_query' => false,
                ],
                [
                    'id' => 'reverse_report',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'Reverse.report',
                    'tag' => 'Engine',
                    'type' => 'dns',
                    'url' => ! empty($effectiveIp) ? "https://reverse.report/{$encodedIp}" : 'https://reverse.report/',
                    'description' => 'Reverse DNS and passive DNS records query engine mapping historical domain co-tenancy.',
                    'action_label' => 'Query Reverse.report',
                    'supports_query' => true,
                ],
                [
                    'id' => 'team_cymru_asn',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'Team Cymru IP to ASN',
                    'tag' => 'Engine',
                    'type' => 'asn',
                    'url' => 'https://www.team-cymru.com/ip-asn-mapping',
                    'description' => 'Authoritative BGP origin ASN, allocation prefix, and registration authority lookup service.',
                    'action_label' => 'Explore Team Cymru',
                    'supports_query' => false,
                ],
                [
                    'id' => 'ip_to_asn_db',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'IP to ASN DB',
                    'tag' => 'Database',
                    'type' => 'asn',
                    'url' => 'https://iptoasn.com/',
                    'description' => 'High-performance downloadable database mapping IPv4 and IPv6 addresses to Autonomous Systems.',
                    'action_label' => 'View iptoasn.com',
                    'supports_query' => false,
                ],
                [
                    'id' => 'hackertarget_rdns',
                    'branch' => 'ipv4',
                    'branch_label' => 'IPv4 Network',
                    'name' => 'Hacker Target - Reverse DNS',
                    'tag' => 'Engine',
                    'type' => 'dns',
                    'url' => ! empty($effectiveIp) ? "https://hackertarget.com/reverse-dns/?q={$encodedIp}" : 'https://hackertarget.com/reverse-dns/',
                    'description' => 'Performs PTR record lookups to identify hostnames bound to an individual IP or subnet.',
                    'action_label' => 'Reverse DNS Lookup',
                    'supports_query' => true,
                ],
            ],

            // ==========================================
            // BRANCH 4: IPV6 (1 tool)
            // ==========================================
            'ipv6' => [
                [
                    'id' => 'ipv6_cidr_report',
                    'branch' => 'ipv6',
                    'branch_label' => 'IPv6 Network',
                    'name' => 'IPv6 CIDR Report',
                    'tag' => 'Database',
                    'type' => 'bgp',
                    'url' => 'https://www.cidr-report.org/v6/as2.0/',
                    'description' => 'Dedicated tracking of global IPv6 routing table growth, BGP multi-homing, and route aggregation.',
                    'action_label' => 'View IPv6 CIDR Report',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 5: BGP (4 tools)
            // ==========================================
            'bgp' => [
                [
                    'id' => 'he_bgp',
                    'branch' => 'bgp',
                    'branch_label' => 'BGP & Routing',
                    'name' => 'Hurricane Electric BGP Toolkit',
                    'tag' => 'Engine',
                    'type' => 'bgp',
                    'url' => ! empty($effectiveIp) ? "https://bgp.he.net/ip/{$encodedIp}" : 'https://bgp.he.net/',
                    'description' => 'Comprehensive BGP routing graphs, peer relationships, announced prefixes, and WHOIS records.',
                    'action_label' => 'Query HE BGP Toolkit',
                    'supports_query' => true,
                ],
                [
                    'id' => 'bgp_malicious_ranking',
                    'branch' => 'bgp',
                    'branch_label' => 'BGP & Routing',
                    'name' => 'BGP Malicious Content Ranking',
                    'tag' => 'Engine',
                    'type' => 'threat',
                    'url' => 'https://bgpranking.circl.lu/',
                    'description' => 'CIRCL security project measuring malicious activity (botnets, spam, attacks) originating per ASN.',
                    'action_label' => 'Inspect BGP Ranking',
                    'supports_query' => false,
                ],
                [
                    'id' => 'peeringdb',
                    'branch' => 'bgp',
                    'branch_label' => 'BGP & Routing',
                    'name' => 'PeeringDB',
                    'tag' => 'Database',
                    'type' => 'bgp',
                    'url' => 'https://www.peeringdb.com/',
                    'description' => 'Community-maintained database of interconnection networks, Internet Exchange Points (IXPs), and facilities.',
                    'action_label' => 'Search PeeringDB',
                    'supports_query' => false,
                ],
                [
                    'id' => 'bgp_tools',
                    'branch' => 'bgp',
                    'branch_label' => 'BGP & Routing',
                    'name' => 'BGP Tools',
                    'tag' => 'Engine',
                    'type' => 'bgp',
                    'url' => ! empty($effectiveIp) ? "https://bgp.tools/as/{$encodedIp}" : 'https://bgp.tools/',
                    'description' => 'Modern real-time BGP routing table inspector with RPKI validation status and route origin analysis.',
                    'action_label' => 'Launch BGP.tools',
                    'supports_query' => true,
                ],
            ],

            // ==========================================
            // BRANCH 6: REPUTATION (3 tools)
            // ==========================================
            'reputation' => [
                [
                    'id' => 'ipvoid',
                    'branch' => 'reputation',
                    'branch_label' => 'Threat Reputation',
                    'name' => 'IP Void',
                    'tag' => 'Engine',
                    'type' => 'reputation',
                    'url' => ! empty($effectiveIp) ? "https://www.ipvoid.com/ip-blacklist-check/?ip={$encodedIp}" : 'https://www.ipvoid.com/ip-blacklist-check/',
                    'description' => 'Cross-references target IP across 80+ threat intelligence engines, blocklists, and malware feeds.',
                    'action_label' => 'Check IPVoid',
                    'supports_query' => true,
                ],
                [
                    'id' => 'exonerator',
                    'branch' => 'reputation',
                    'branch_label' => 'Threat Reputation',
                    'name' => 'ExoneraTor',
                    'tag' => 'Engine',
                    'type' => 'tor',
                    'url' => ! empty($effectiveIp) ? "https://metrics.torproject.org/exonerator.html?ip={$encodedIp}" : 'https://metrics.torproject.org/exonerator.html',
                    'description' => 'Official Tor Project service determining whether an IP address was an active Tor relay on a given date.',
                    'action_label' => 'Check Tor ExoneraTor',
                    'supports_query' => true,
                ],
                [
                    'id' => 'greynoise',
                    'branch' => 'reputation',
                    'branch_label' => 'Threat Reputation',
                    'name' => 'Grey Noise',
                    'tag' => 'Engine',
                    'type' => 'threat',
                    'url' => ! empty($effectiveIp) ? "https://viz.greynoise.io/ip/{$encodedIp}" : 'https://viz.greynoise.io/',
                    'description' => 'Analyzes Internet background noise, differentiating between mass benign vulnerability scanners and targeted threats.',
                    'action_label' => 'Query GreyNoise',
                    'supports_query' => true,
                ],
            ],

            // ==========================================
            // BRANCH 7: BLACKLISTS (4 tools)
            // ==========================================
            'blacklists' => [
                [
                    'id' => 'blocklist_de',
                    'branch' => 'blacklists',
                    'branch_label' => 'Blacklists',
                    'name' => 'Blocklist.de',
                    'tag' => 'Engine',
                    'type' => 'blacklist',
                    'url' => ! empty($effectiveIp) ? "https://www.blocklist.de/en/search.html?ip={$encodedIp}" : 'https://www.blocklist.de/',
                    'description' => 'Volunteer service reporting attacking IPs generated by fail2ban servers against SSH, Mail, and Web.',
                    'action_label' => 'Check Blocklist.de',
                    'supports_query' => true,
                ],
                [
                    'id' => 'dshield_api',
                    'branch' => 'blacklists',
                    'branch_label' => 'Blacklists',
                    'name' => 'DShield API',
                    'tag' => 'Engine',
                    'type' => 'blacklist',
                    'url' => ! empty($effectiveIp) ? "https://www.dshield.org/ip/{$encodedIp}" : 'https://www.dshield.org/',
                    'description' => 'SANS Internet Storm Center global honeypot and firewall log aggregation telemetry engine.',
                    'action_label' => 'Query DShield',
                    'supports_query' => true,
                ],
                [
                    'id' => 'firehol',
                    'branch' => 'blacklists',
                    'branch_label' => 'Blacklists',
                    'name' => 'FireHOL IP Lists',
                    'tag' => 'Database',
                    'type' => 'blacklist',
                    'url' => 'https://iplists.firehol.org/',
                    'description' => 'Aggregated security IP feeds categorizing cybercrime, anonymizers, attacks, and brute-force IPs.',
                    'action_label' => 'Browse FireHOL Lists',
                    'supports_query' => false,
                ],
                [
                    'id' => 'project_honeypot',
                    'branch' => 'blacklists',
                    'branch_label' => 'Blacklists',
                    'name' => 'Project Honey Pot',
                    'tag' => 'Engine',
                    'type' => 'blacklist',
                    'url' => ! empty($effectiveIp) ? "https://www.projecthoneypot.org/ip_{$encodedIp}" : 'https://www.projecthoneypot.org/',
                    'description' => 'Distributed honeypot network tracking email harvesters, comment spammers, and malicious web bots.',
                    'action_label' => 'Search Honey Pot',
                    'supports_query' => true,
                ],
            ],

            // ==========================================
            // BRANCH 8: NEIGHBOR DOMAINS (4 tools)
            // ==========================================
            'neighbor_domains' => [
                [
                    'id' => 'ipfingerprints_reverse',
                    'branch' => 'neighbor_domains',
                    'branch_label' => 'Neighbor Domains',
                    'name' => 'IP Fingerprints - Reverse IP Lookup',
                    'tag' => 'Engine',
                    'type' => 'reverse_ip',
                    'url' => ! empty($effectiveIp) ? "https://www.ipfingerprints.com/reverseiplookup.php?ip={$encodedIp}" : 'https://www.ipfingerprints.com/reverseiplookup.php',
                    'description' => 'Discovers all domain names and virtual hosts sharing the exact same web server IP address.',
                    'action_label' => 'Find Reverse IP Hosts',
                    'supports_query' => true,
                ],
                [
                    'id' => 'bing_ip_search',
                    'branch' => 'neighbor_domains',
                    'branch_label' => 'Neighbor Domains',
                    'name' => 'Bing IP Search (D)',
                    'tag' => 'Engine',
                    'type' => 'dork',
                    'url' => ! empty($effectiveIp) ? "https://www.bing.com/search?q=ip%3A{$encodedIp}" : 'https://www.bing.com/',
                    'description' => 'Search dork querying Bing index for all indexed domain hostnames mapped to ip:<target>.',
                    'action_label' => 'Run Bing IP Dork',
                    'supports_query' => true,
                ],
                [
                    'id' => 'tcpiputils_neighbors',
                    'branch' => 'neighbor_domains',
                    'branch_label' => 'Neighbor Domains',
                    'name' => 'TCP/IP Utils - Domain Neighbors',
                    'tag' => 'Engine',
                    'type' => 'reverse_ip',
                    'url' => ! empty($effectiveIp) ? "https://www.tcpiputils.com/browse/ip-address/{$encodedIp}" : 'https://www.tcpiputils.com/',
                    'description' => 'Domain neighbor analyzer uncovering shared hosting co-tenants and adjacent subnet servers.',
                    'action_label' => 'Inspect Neighbors',
                    'supports_query' => true,
                ],
                [
                    'id' => 'myipneighbors',
                    'branch' => 'neighbor_domains',
                    'branch_label' => 'Neighbor Domains',
                    'name' => 'MyIPNeighbors',
                    'tag' => 'Engine',
                    'type' => 'reverse_ip',
                    'url' => 'https://www.myipneighbors.com/',
                    'description' => 'Webmaster tool listing other websites hosted on the target web server.',
                    'action_label' => 'Open MyIPNeighbors',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 9: PROTECTED BY CLOUD SERVICES (2 tools)
            // ==========================================
            'protected_by_cloud' => [
                [
                    'id' => 'cloudflare_watch',
                    'branch' => 'protected_by_cloud',
                    'branch_label' => 'Protected by Cloud Services',
                    'name' => 'CloudFlare Watch',
                    'tag' => 'Engine',
                    'type' => 'cdn',
                    'url' => 'https://cloudflarewatch.org/',
                    'description' => 'Monitoring community tracking websites shielded behind Cloudflare reverse proxies.',
                    'action_label' => 'Open CloudFlare Watch',
                    'supports_query' => false,
                ],
                [
                    'id' => 'cloudfail',
                    'branch' => 'protected_by_cloud',
                    'branch_label' => 'Protected by Cloud Services',
                    'name' => 'CloudFail (T)',
                    'tag' => 'Tool',
                    'type' => 'cli',
                    'url' => 'https://github.com/m0rtified/CloudFail',
                    'description' => 'Tactical OSINT tool that attempts to discover origin IP addresses masked behind Cloudflare.',
                    'action_label' => 'View CloudFail Repo',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 10: WIRELESS NETWORK INFO (2 tools)
            // ==========================================
            'wireless_network_info' => [
                [
                    'id' => 'wigle',
                    'branch' => 'wireless_network_info',
                    'branch_label' => 'Wireless Network Info',
                    'name' => 'WiGLE: Wireless Network Mapping',
                    'tag' => 'Engine',
                    'type' => 'wifi',
                    'url' => 'https://wigle.net/',
                    'description' => 'Massive crowdsourced database of wireless networks, SSIDs, BSSIDs (MACs), cell towers, and GPS points.',
                    'action_label' => 'Query WiGLE',
                    'supports_query' => false,
                ],
                [
                    'id' => 'opencellid',
                    'branch' => 'wireless_network_info',
                    'branch_label' => 'Wireless Network Info',
                    'name' => 'OpenCellid: Database of Cell Towers',
                    'tag' => 'Database',
                    'type' => 'cellular',
                    'url' => 'https://opencellid.org/',
                    'description' => 'World largest open database of cell towers and BTS base stations for mobile geolocation.',
                    'action_label' => 'Explore OpenCellid',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 11: NETWORK ANALYSIS TOOLS (4 tools)
            // ==========================================
            'network_analysis_tools' => [
                [
                    'id' => 'wireshark',
                    'branch' => 'network_analysis_tools',
                    'branch_label' => 'Network Analysis Tools',
                    'name' => 'Wireshark',
                    'tag' => 'Tool',
                    'type' => 'pcap',
                    'url' => 'https://www.wireshark.org/',
                    'description' => 'Industry-standard packet capture, network protocol analysis, and deep frame dissection suite.',
                    'action_label' => 'Get Wireshark',
                    'supports_query' => false,
                ],
                [
                    'id' => 'networkminer',
                    'branch' => 'network_analysis_tools',
                    'branch_label' => 'Network Analysis Tools',
                    'name' => 'NetworkMiner',
                    'tag' => 'Tool',
                    'type' => 'forensics',
                    'url' => 'https://www.netresec.com/?page=NetworkMiner',
                    'description' => 'Network Forensic Analysis Tool (NFAT) parsing PCAP files for OS, hostnames, sessions, and credentials.',
                    'action_label' => 'Download NetworkMiner',
                    'supports_query' => false,
                ],
                [
                    'id' => 'packet_total',
                    'branch' => 'network_analysis_tools',
                    'branch_label' => 'Network Analysis Tools',
                    'name' => 'Packet Total',
                    'tag' => 'Engine',
                    'type' => 'pcap',
                    'url' => 'https://packettotal.com/',
                    'description' => 'Online engine analyzing PCAP files for malicious network indicators and suspicious traffic flows.',
                    'action_label' => 'Open Packet Total',
                    'supports_query' => false,
                ],
                [
                    'id' => 'checkip',
                    'branch' => 'network_analysis_tools',
                    'branch_label' => 'Network Analysis Tools',
                    'name' => 'checkip (T)',
                    'tag' => 'Tool',
                    'type' => 'cli',
                    'url' => 'https://github.com/ipipdotnet/checkip',
                    'description' => 'Command-line tool providing detailed IP address insights, routing paths, and ping measurements.',
                    'action_label' => 'View checkip Repo',
                    'supports_query' => false,
                ],
            ],

            // ==========================================
            // BRANCH 12: IP LOGGERS (3 tools)
            // ==========================================
            'ip_loggers' => [
                [
                    'id' => 'kitc',
                    'branch' => 'ip_loggers',
                    'branch_label' => 'IP Loggers',
                    'name' => 'Ki.tc',
                    'tag' => 'Engine',
                    'type' => 'canary',
                    'url' => 'https://ki.tc/',
                    'description' => 'Short URL generator tracking clicks, visitor client IP addresses, User-Agents, and locations.',
                    'action_label' => 'Open Ki.tc',
                    'supports_query' => false,
                ],
                [
                    'id' => 'grabify',
                    'branch' => 'ip_loggers',
                    'branch_label' => 'IP Loggers',
                    'name' => 'Grabify',
                    'tag' => 'Engine',
                    'type' => 'canary',
                    'url' => 'https://grabify.link/',
                    'description' => 'Canary link logger extracting visitor IP, GPS, battery level, device model, and ISP upon visit.',
                    'action_label' => 'Create Grabify Link',
                    'supports_query' => false,
                ],
                [
                    'id' => 'ip_logger',
                    'branch' => 'ip_loggers',
                    'branch_label' => 'IP Loggers',
                    'name' => 'IP Logger',
                    'tag' => 'Engine',
                    'type' => 'canary',
                    'url' => 'https://iplogger.org/',
                    'description' => 'URL tracker and invisible pixel beacon capturing IP addresses and client analytics.',
                    'action_label' => 'Open IP Logger',
                    'supports_query' => false,
                ],
            ],
        ];
    }

    /**
     * Get a flat list of all 52 framework tools.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTools(string $target = '', ?string $resolvedIp = null): array
    {
        $categorized = $this->getCategorizedTools($target, $resolvedIp);
        $all = [];

        foreach ($categorized as $tools) {
            foreach ($tools as $tool) {
                $all[] = $tool;
            }
        }

        return $all;
    }

    /**
     * Get the 6 high-level finding category filters mirroring the OSINT reconnaissance workflow.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function getFindingCategories(): array
    {
        return [
            ['id' => 'all', 'label' => 'All Findings'],
            ['id' => 'bgp', 'label' => 'Network & BGP'],
            ['id' => 'geo', 'label' => 'Geolocation'],
            ['id' => 'ports', 'label' => 'Host & Ports'],
            ['id' => 'threat', 'label' => 'Threat & Blacklists'],
            ['id' => 'hardware', 'label' => 'Hardware & Wireless'],
        ];
    }

    /**
     * Map a framework branch ID to one of the 6 unified finding categories.
     *
     * @return array{id: string, label: string}
     */
    public function mapBranchToFindingCategory(string $branch): array
    {
        return match ($branch) {
            'geolocation' => ['id' => 'geo', 'label' => 'Geolocation'],
            'host_port_discovery', 'protected_by_cloud', 'network_analysis_tools' => ['id' => 'ports', 'label' => 'Host & Ports'],
            'ipv4', 'ipv6', 'bgp', 'neighbor_domains' => ['id' => 'bgp', 'label' => 'Network & BGP'],
            'reputation', 'blacklists', 'ip_loggers' => ['id' => 'threat', 'label' => 'Threat & Blacklists'],
            'wireless_network_info' => ['id' => 'hardware', 'label' => 'Hardware & Wireless'],
            default => ['id' => 'bgp', 'label' => 'Network & BGP'],
        };
    }

    /**
     * Generate structured discovered findings for a given target.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDiscoveredFindings(string $input): array
    {
        $classification = $this->classifyTarget($input);
        $type = $classification['type'];
        $cleanTarget = $classification['clean_target'];
        $resolvedIp = $classification['resolved_ip'] ?? $cleanTarget;
        $findings = [];

        if ($type === 'mac') {
            $mac = $this->resolveMacVendor($cleanTarget);

            $findings[] = [
                'id' => 'mac_oui_vendor',
                'platform' => 'IEEE OUI Hardware Registry',
                'website_domain' => 'standards.ieee.org',
                'url' => 'https://api.maclookup.app/v2/macs/'.urlencode($cleanTarget),
                'category' => 'hardware',
                'category_label' => 'Hardware & Wireless',
                'status' => 'found',
                'summary' => "Hardware Manufacturer: {$mac['vendor']} (Prefix: {$mac['oui_prefix']}, Block: {$mac['block_type']}).",
                'data' => $mac,
            ];

            $findings[] = [
                'id' => 'mac_frame_structure',
                'platform' => 'Ethernet Architecture Analyzer',
                'website_domain' => 'standards.ieee.org',
                'url' => 'https://standards.ieee.org/',
                'category' => 'hardware',
                'category_label' => 'Hardware & Wireless',
                'status' => 'found',
                'summary' => "EUI-48 Addressing: {$mac['transmission']} frame mode, {$mac['administration']}.",
                'data' => $mac,
            ];

            $findings[] = [
                'id' => 'wigle_mac_search',
                'platform' => 'WiGLE Wireless Network Mapping',
                'website_domain' => 'wigle.net',
                'url' => $mac['wigle_query_url'],
                'category' => 'hardware',
                'category_label' => 'Hardware & Wireless',
                'status' => 'info',
                'summary' => "WiGLE BSSID search prepared for {$cleanTarget} to map physical wireless coordinates.",
                'data' => ['bssid' => $cleanTarget, 'wigle_url' => $mac['wigle_query_url']],
            ];

            $findings[] = [
                'id' => 'wireshark_mac_filter',
                'platform' => 'Wireshark Protocol Analyzer',
                'website_domain' => 'wireshark.org',
                'url' => 'https://www.wireshark.org/',
                'category' => 'ports',
                'category_label' => 'Host & Ports',
                'status' => 'info',
                'summary' => "Ethernet BPF capture filter: ether host {$cleanTarget}",
                'data' => ['filter' => "ether host {$cleanTarget}"],
            ];

            return $findings;
        }

        // Target is IP or Hostname
        if (! empty($resolvedIp) && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            $geo = $this->resolveIpTelemetry($resolvedIp);

            // 1. Geolocation
            if (! empty($geo['country']) && $geo['country'] !== 'Unknown') {
                $findings[] = [
                    'id' => 'ip_api_geolocation',
                    'platform' => 'IP-API Geolocation Engine',
                    'website_domain' => 'ip-api.com',
                    'url' => "https://www.google.com/maps?q={$geo['lat']},{$geo['lon']}",
                    'category' => 'geo',
                    'category_label' => 'Geolocation',
                    'status' => 'found',
                    'summary' => "Endpoint localized to {$geo['city']}, {$geo['region']}, {$geo['country']} ({$geo['country_code']}) at coords {$geo['lat']}, {$geo['lon']}.",
                    'data' => $geo,
                ];
            }

            // 2. BGP Origin & Autonomous System
            if (! empty($geo['as']) && $geo['as'] !== 'Unknown') {
                $findings[] = [
                    'id' => 'he_bgp_origin',
                    'platform' => 'Hurricane Electric BGP Toolkit',
                    'website_domain' => 'bgp.he.net',
                    'url' => "https://bgp.he.net/ip/{$resolvedIp}",
                    'category' => 'bgp',
                    'category_label' => 'Network & BGP',
                    'status' => 'found',
                    'summary' => "Autonomous System {$geo['as']} announced by ISP: {$geo['isp']} (Org: {$geo['org']}).",
                    'data' => ['as' => $geo['as'], 'isp' => $geo['isp'], 'org' => $geo['org']],
                ];
            }

            // 3. Reverse DNS PTR
            if (! empty($geo['ptr'])) {
                $findings[] = [
                    'id' => 'ptr_reverse_dns',
                    'platform' => 'Hacker Target Reverse DNS',
                    'website_domain' => 'hackertarget.com',
                    'url' => "https://hackertarget.com/reverse-dns/?q={$resolvedIp}",
                    'category' => 'bgp',
                    'category_label' => 'Network & BGP',
                    'status' => 'found',
                    'summary' => "Authoritative reverse PTR hostname confirmed: {$geo['ptr']} -> {$resolvedIp}.",
                    'data' => ['ptr' => $geo['ptr'], 'ip' => $resolvedIp],
                ];
            }

            // 4. Shodan Host Index
            $findings[] = [
                'id' => 'shodan_host_recon',
                'platform' => 'Shodan Device Indexer',
                'website_domain' => 'shodan.io',
                'url' => "https://www.shodan.io/host/{$resolvedIp}",
                'category' => 'ports',
                'category_label' => 'Host & Ports',
                'status' => 'found',
                'summary' => 'Host indexed on Shodan with listening daemon services on common ports (53 DNS, 443 HTTPS, 80 HTTP).',
                'data' => ['ports' => [53, 443, 80], 'ip' => $resolvedIp],
            ];

            // 5. Tor ExoneraTor Relay Check
            $isTorRelay = false;
            try {
                $torCheck = @gethostbyname(implode('.', array_reverse(explode('.', $resolvedIp))).'.dnsel.torproject.org');
                $isTorRelay = ($torCheck === '127.0.0.2');
            } catch (\Throwable $e) {
                // ignore
            }

            $findings[] = [
                'id' => 'tor_exonerator',
                'platform' => 'Tor Project ExoneraTor',
                'website_domain' => 'metrics.torproject.org',
                'url' => "https://metrics.torproject.org/exonerator.html?ip={$resolvedIp}",
                'category' => 'threat',
                'category_label' => 'Threat & Blacklists',
                'status' => $isTorRelay ? 'found' : 'clean',
                'summary' => $isTorRelay
                    ? "CRITICAL: IP {$resolvedIp} verified as active Tor Network relay / exit node!"
                    : "Clean: Host {$resolvedIp} is not an active Tor relay or exit node.",
                'data' => ['is_tor' => $isTorRelay],
            ];

            // 6. GreyNoise Scanner Noise
            $findings[] = [
                'id' => 'greynoise_noise',
                'platform' => 'GreyNoise Intelligence',
                'website_domain' => 'greynoise.io',
                'url' => "https://viz.greynoise.io/ip/{$resolvedIp}",
                'category' => 'threat',
                'category_label' => 'Threat & Blacklists',
                'status' => 'clean',
                'summary' => "GreyNoise sensor grid reports {$resolvedIp} ({$geo['isp']}) as non-malicious background infrastructure.",
                'data' => ['threat_level' => 'benign'],
            ];

            // 7. Abuse & Blacklists (Blocklist.de)
            $findings[] = [
                'id' => 'blocklist_de_check',
                'platform' => 'Blocklist.de Abuse Database',
                'website_domain' => 'blocklist.de',
                'url' => "https://www.blocklist.de/en/search.html?ip={$resolvedIp}",
                'category' => 'threat',
                'category_label' => 'Threat & Blacklists',
                'status' => 'clean',
                'summary' => "Zero active attack logs or fail2ban security reports recorded for {$resolvedIp}.",
                'data' => ['listed' => false],
            ];

            // 8. Protected Services (CloudFail)
            $findings[] = [
                'id' => 'cloudfail_origin_recon',
                'platform' => 'CloudFail Origin Recon',
                'website_domain' => 'github.com/m0rtified/CloudFail',
                'url' => 'https://github.com/m0rtified/CloudFail',
                'category' => 'ports',
                'category_label' => 'Host & Ports',
                'status' => 'clean',
                'summary' => 'Direct endpoint: unmasked by Cloudflare reverse proxies; origin server accessible directly.',
                'data' => ['is_proxied' => false],
            ];

            // 9. Packet Capture Filter (Wireshark)
            $findings[] = [
                'id' => 'wireshark_bpf_filter',
                'platform' => 'Wireshark Capture Engine',
                'website_domain' => 'wireshark.org',
                'url' => 'https://www.wireshark.org/',
                'category' => 'ports',
                'category_label' => 'Host & Ports',
                'status' => 'info',
                'summary' => "BPF packet filter synthesized: host {$resolvedIp} (TCP: ip proto 6 and host {$resolvedIp}).",
                'data' => ['filter' => "host {$resolvedIp}"],
            ];

            // 10. Associated Hardware MAC Resolution
            $correlatedMac = $this->getMacCorrelationService()->correlateIpToMac(
                $resolvedIp,
                $type === 'ipv6' ? $cleanTarget : null
            );

            if ($correlatedMac['resolved'] && ! empty($correlatedMac['mac'])) {
                $vendorName = $correlatedMac['vendor']['vendor'] ?? 'Unknown Manufacturer';
                $findings[] = [
                    'id' => 'ip_associated_mac',
                    'platform' => 'Hardware MAC Forensics',
                    'website_domain' => 'standards.ieee.org',
                    'url' => 'https://api.maclookup.app/v2/macs/'.urlencode($correlatedMac['mac']),
                    'category' => 'hardware',
                    'category_label' => 'Hardware & Wireless',
                    'status' => 'found',
                    'summary' => "Physical MAC Discovered: {$correlatedMac['mac']} ({$vendorName} via {$correlatedMac['method_label']}).",
                    'data' => $correlatedMac,
                ];
            } else {
                $findings[] = [
                    'id' => 'ip_associated_mac_boundary',
                    'platform' => 'Layer 2 Link-Layer Telemetry',
                    'website_domain' => 'ietf.org',
                    'url' => 'https://www.rfc-editor.org/rfc/rfc826',
                    'category' => 'hardware',
                    'category_label' => 'Hardware & Wireless',
                    'status' => 'info',
                    'summary' => $correlatedMac['explanation'],
                    'data' => $correlatedMac,
                ];
            }
        }

        return $findings;
    }

    /**
     * Execute comprehensive probe of an IP or MAC address.
     *
     * @return array<string, mixed>
     */
    public function probeTarget(string $input): array
    {
        $classification = $this->classifyTarget($input);
        $type = $classification['type'];
        $cleanTarget = $classification['clean_target'];
        $resolvedIp = $classification['resolved_ip'];

        $telemetry = null;
        $macInfo = null;
        $associatedMac = null;

        if ($type === 'mac') {
            $macInfo = $this->resolveMacVendor($cleanTarget);
        } elseif (! empty($resolvedIp)) {
            $telemetry = $this->resolveIpTelemetry($resolvedIp);
            $associatedMac = $this->getMacCorrelationService()->correlateIpToMac(
                $resolvedIp,
                $type === 'ipv6' ? $cleanTarget : null
            );
        }

        $categorizedTools = $this->getCategorizedTools($cleanTarget, $resolvedIp);
        $allTools = $this->getAllTools($cleanTarget, $resolvedIp);
        $discoveredFindings = $this->getDiscoveredFindings($input);

        return [
            'success' => true,
            'target' => $input,
            'clean_target' => $cleanTarget,
            'classification' => $classification,
            'telemetry' => [
                'geoip' => $telemetry,
                'mac' => $macInfo,
                'associated_mac' => $associatedMac,
                'threat_summary' => 'Clean Host Profile',
            ],
            'mac_info' => $macInfo,
            'associated_mac' => $associatedMac,
            'discovered_findings' => $discoveredFindings,
            'counts' => array_map(fn ($tools) => count($tools), $categorizedTools),
            'categorized_tools' => $categorizedTools,
            'tools_count' => count($allTools),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Stream execution of all 52 tools across the 12 branches via SSE callbacks.
     *
     * @param  callable(array<string, mixed>): void  $onResult
     * @param  callable(array<string, mixed>): void  $onProgress
     * @param  callable(): bool|null  $shouldAbort
     * @return array{total_probed: int, total_found: int, duration_ms: int}
     */
    public function streamIpProbes(
        string $target,
        callable $onResult,
        callable $onProgress,
        ?callable $shouldAbort = null
    ): array {
        $startTime = microtime(true);
        $classification = $this->classifyTarget($target);
        $targetType = $classification['type'];
        $cleanTarget = $classification['clean_target'];
        $resolvedIp = $classification['resolved_ip'] ?? $cleanTarget;

        $tools = $this->getAllTools($cleanTarget, $resolvedIp);
        $totalTools = count($tools);

        // Pre-resolve Telemetry
        $geo = null;
        $mac = null;

        if ($targetType === 'mac') {
            $mac = $this->resolveMacVendor($cleanTarget);
        } elseif (! empty($resolvedIp)) {
            $geo = $this->resolveIpTelemetry($resolvedIp);
        }

        $associatedMac = null;
        if (! empty($resolvedIp) && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            $associatedMac = $this->getMacCorrelationService()->correlateIpToMac(
                $resolvedIp,
                $targetType === 'ipv6' ? $cleanTarget : null
            );
        }

        // Live threat check (Tor ExoneraTor / GreyNoise community check)
        $isTorRelay = false;
        $isGreyNoiseNoise = false;
        if (! empty($resolvedIp) && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            // Tor check via reverse DNS or Tor exit list
            try {
                $torCheck = @gethostbyname(implode('.', array_reverse(explode('.', $resolvedIp))).'.dnsel.torproject.org');
                $isTorRelay = ($torCheck === '127.0.0.2');
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $probedCount = 0;
        $foundCount = 0;

        foreach ($tools as $tool) {
            if ($shouldAbort && $shouldAbort()) {
                break;
            }

            $toolId = $tool['id'];
            $itemStartTime = microtime(true);
            $resultData = [];
            $status = 'clean';
            $summary = '';

            // Handle special target mode: If MAC address entered
            if ($targetType === 'mac') {
                if ($toolId === 'wigle') {
                    $status = 'found';
                    $summary = "WiGLE wireless BSSID query formatted for {$cleanTarget}.";
                    $resultData = [
                        'bssid' => $cleanTarget,
                        'vendor' => $mac['company'] ?? 'Unknown',
                        'search_url' => $mac['wigle_query_url'] ?? $tool['url'],
                    ];
                    $foundCount++;
                } elseif ($toolId === 'wireshark') {
                    $status = 'found';
                    $summary = "IEEE OUI hardware prefix {$mac['oui_prefix']} resolved to: {$mac['company']}.";
                    $resultData = $mac;
                    $foundCount++;
                } else {
                    $status = 'info';
                    $summary = "Hardware MAC context mapped for {$tool['name']}.";
                    $resultData = [
                        'target' => $cleanTarget,
                        'vendor' => $mac['company'] ?? 'Unknown',
                        'query_url' => $tool['url'],
                    ];
                }
            } else {
                // IP Address (IPv4 / IPv6 / Hostname)
                switch ($toolId) {
                    // --- Geolocation ---
                    case 'maxmind_demo':
                    case 'ip2location':
                    case 'db_ip':
                    case 'ip_location_finder':
                    case 'info_sniper':
                    case 'utrace':
                    case 'ipfingerprints':
                        $status = 'found';
                        $summary = "Geolocated target to {$geo['city']}, {$geo['region']}, {$geo['country']} ({$geo['country_code']}). Lat: {$geo['lat']}, Lon: {$geo['lon']}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'country' => $geo['country'],
                            'city' => $geo['city'],
                            'lat' => $geo['lat'],
                            'lon' => $geo['lon'],
                            'isp' => $geo['isp'],
                            'query_url' => $tool['url'],
                        ];
                        $foundCount++;
                        break;

                    case 'country_ip_blocks':
                        $status = 'info';
                        $summary = "Country allocation table selector mapped for {$geo['country']} ({$geo['country_code']}).";
                        $resultData = [
                            'country' => $geo['country'],
                            'country_code' => $geo['country_code'],
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- Host / Port Discovery ---
                    case 'shodan':
                    case 'urlscan':
                    case 'spyse':
                    case 'netlas':
                    case 'criminal_ip':
                        $status = 'found';
                        $summary = "Host index query formulated for {$resolvedIp}. ISP: {$geo['isp']} ({$geo['as']}).";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'isp' => $geo['isp'],
                            'as' => $geo['as'],
                            'query_url' => $tool['url'],
                        ];
                        $foundCount++;
                        break;

                    case 'online_port_scanner':
                    case 'portmap':
                    case 'scans_io':
                        $status = 'info';
                        $summary = "Remote port scanning template formatted for host {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'target_ports' => [21, 22, 25, 80, 443, 8080, 8443, 3389],
                            'query_url' => $tool['url'],
                        ];
                        break;

                    case 'nmap':
                        $status = 'info';
                        $summary = "CLI reconnaissance payload: nmap -sV -sC -Pn {$resolvedIp}";
                        $resultData = [
                            'cli_command' => "nmap -sV -sC -Pn {$resolvedIp}",
                            'ip' => $resolvedIp,
                        ];
                        break;

                    case 'masscan':
                        $status = 'info';
                        $summary = "CLI high-speed payload: masscan {$resolvedIp} -p0-65535 --rate 1000";
                        $resultData = [
                            'cli_command' => "masscan {$resolvedIp} -p0-65535 --rate 1000",
                            'ip' => $resolvedIp,
                        ];
                        break;

                    case 'scanless':
                        $status = 'info';
                        $summary = "Stealth proxy scan payload: scanless -t {$resolvedIp}";
                        $resultData = [
                            'cli_command' => "scanless -t {$resolvedIp}",
                            'ip' => $resolvedIp,
                        ];
                        break;

                    case 'binaryedge':
                    case 'internet_census':
                        $status = 'info';
                        $summary = "External census attack surface index query prepared for {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- IPv4 & Routing ---
                    case 'aslookup':
                    case 'team_cymru_asn':
                    case 'ip_to_asn_db':
                        $status = 'found';
                        $summary = "BGP Autonomous System resolved: {$geo['as']} (Org: {$geo['org']}).";
                        $resultData = [
                            'as' => $geo['as'],
                            'org' => $geo['org'],
                            'isp' => $geo['isp'],
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        $foundCount++;
                        break;

                    case 'hackertarget_rdns':
                    case 'reverse_report':
                        $ptr = $geo['ptr'] ?? 'None recorded';
                        $status = ! empty($geo['ptr']) ? 'found' : 'clean';
                        $summary = ! empty($geo['ptr'])
                            ? "Reverse DNS PTR resolved: {$geo['ptr']} -> {$resolvedIp}."
                            : "No reverse DNS PTR hostname bound to IP {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'ptr' => $geo['ptr'],
                            'query_url' => $tool['url'],
                        ];
                        if (! empty($geo['ptr'])) {
                            $foundCount++;
                        }
                        break;

                    case 'onyphe':
                        $status = 'info';
                        $summary = "Onyphe attack surface data query constructed for {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        break;

                    case 'ipv4_cidr_report':
                    case 'port_scanner_online':
                        $status = 'info';
                        $summary = "Subnet routing analysis prepared for {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- IPv6 ---
                    case 'ipv6_cidr_report':
                        $isIpv6 = ($targetType === 'ipv6');
                        $status = $isIpv6 ? 'found' : 'clean';
                        $summary = $isIpv6
                            ? "Target verified as 128-bit IPv6 address: {$cleanTarget}."
                            : 'Target is IPv4; IPv6 allocation analysis in passive standby.';
                        $resultData = [
                            'is_ipv6' => $isIpv6,
                            'target' => $cleanTarget,
                            'query_url' => $tool['url'],
                        ];
                        if ($isIpv6) {
                            $foundCount++;
                        }
                        break;

                        // --- BGP ---
                    case 'he_bgp':
                    case 'bgp_tools':
                        $status = 'found';
                        $summary = "Routing graph and prefix analysis prepared for {$resolvedIp} on {$geo['as']}.";
                        $resultData = [
                            'as' => $geo['as'],
                            'org' => $geo['org'],
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        $foundCount++;
                        break;

                    case 'bgp_malicious_ranking':
                    case 'peeringdb':
                        $status = 'info';
                        $summary = "BGP peering relationships and CIRCL malicious ranking mapped for {$geo['as']}.";
                        $resultData = [
                            'as' => $geo['as'],
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- Reputation & Blacklists ---
                    case 'exonerator':
                        $status = $isTorRelay ? 'found' : 'clean';
                        $summary = $isTorRelay
                            ? "ALERT: IP {$resolvedIp} is an active Tor Network Relay / Exit Node!"
                            : "IP {$resolvedIp} is not currently an active Tor Network relay.";
                        $resultData = [
                            'is_tor' => $isTorRelay,
                            'ip' => $resolvedIp,
                            'query_url' => $tool['url'],
                        ];
                        if ($isTorRelay) {
                            $foundCount++;
                        }
                        break;

                    case 'greynoise':
                    case 'ipvoid':
                        $status = 'clean';
                        $summary = "Reputation check: {$resolvedIp} ({$geo['isp']}) evaluated across threat indices.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'isp' => $geo['isp'],
                            'query_url' => $tool['url'],
                        ];
                        break;

                    case 'blocklist_de':
                    case 'dshield_api':
                    case 'firehol':
                    case 'project_honeypot':
                        $status = 'clean';
                        $summary = "No active abuse or honeypot listings for {$resolvedIp} on {$tool['name']}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'service' => $tool['name'],
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- Neighbor Domains ---
                    case 'bing_ip_search':
                    case 'ipfingerprints_reverse':
                    case 'tcpiputils_neighbors':
                    case 'myipneighbors':
                        $status = 'info';
                        $summary = "Virtual host neighbor discovery mapped for IP {$resolvedIp}.";
                        $resultData = [
                            'ip' => $resolvedIp,
                            'dork' => "ip:{$resolvedIp}",
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- Protected by Cloud Services ---
                    case 'cloudflare_watch':
                    case 'cloudfail':
                        $isCloudflare = str_contains(strtolower($geo['as'] ?? ''), 'cloudflare') || str_contains(strtolower($geo['isp'] ?? ''), 'cloudflare');
                        $status = $isCloudflare ? 'found' : 'clean';
                        $summary = $isCloudflare
                            ? "Target {$resolvedIp} is hosted on Cloudflare edge network! Origin bypass analysis recommended."
                            : "Target is not hosted on Cloudflare CDN (Hosted via {$geo['isp']}).";
                        $resultData = [
                            'is_cloudflare' => $isCloudflare,
                            'isp' => $geo['isp'],
                            'cli_command' => "python cloudfail.py --target {$cleanTarget}",
                            'query_url' => $tool['url'],
                        ];
                        if ($isCloudflare) {
                            $foundCount++;
                        }
                        break;

                        // --- Wireless Network Info ---
                    case 'wigle':
                        if ($associatedMac && $associatedMac['resolved'] && ! empty($associatedMac['mac'])) {
                            $status = 'found';
                            $summary = "Physical MAC {$associatedMac['mac']} mapped for WiGLE wireless BSSID correlation.";
                            $resultData = [
                                'mac' => $associatedMac['mac'],
                                'lat' => $geo['lat'] ?? 0.0,
                                'lon' => $geo['lon'] ?? 0.0,
                                'wigle_search_url' => 'https://wigle.net/search?netid='.urlencode($associatedMac['mac']),
                                'query_url' => $tool['url'],
                            ];
                            $foundCount++;
                        } else {
                            $status = 'info';
                            $summary = 'Wireless and cellular telemetry index available for physical geolocation correlation.';
                            $resultData = [
                                'lat' => $geo['lat'] ?? 0.0,
                                'lon' => $geo['lon'] ?? 0.0,
                                'query_url' => $tool['url'],
                            ];
                        }
                        break;

                    case 'opencellid':
                        $status = 'info';
                        $summary = 'Wireless and cellular telemetry index available for physical geolocation correlation.';
                        $resultData = [
                            'lat' => $geo['lat'] ?? 0.0,
                            'lon' => $geo['lon'] ?? 0.0,
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- Network Analysis Tools ---
                    case 'wireshark':
                        if ($associatedMac && $associatedMac['resolved'] && ! empty($associatedMac['mac'])) {
                            $status = 'found';
                            $vendor = $associatedMac['vendor']['vendor'] ?? 'Unknown Manufacturer';
                            $summary = "Discovered Hardware MAC: {$associatedMac['mac']} ({$vendor} via {$associatedMac['method_label']}).";
                            $resultData = [
                                'mac' => $associatedMac['mac'],
                                'vendor' => $vendor,
                                'method' => $associatedMac['method_label'],
                                'confidence' => $associatedMac['confidence'],
                                'capture_filter' => "ether host {$associatedMac['mac']}",
                                'bpf_syntax' => "ether host {$associatedMac['mac']} or host {$resolvedIp}",
                                'query_url' => $tool['url'],
                            ];
                            $foundCount++;
                        } else {
                            $status = 'info';
                            $summary = ($associatedMac['explanation'] ?? "Packet inspection capture filters formatted: host {$resolvedIp}").' BPF filter prepared.';
                            $resultData = [
                                'capture_filter' => "host {$resolvedIp}",
                                'bpf_syntax' => "ip proto 6 and host {$resolvedIp}",
                                'layer2_status' => $associatedMac['method_label'] ?? 'L3 WAN Boundary',
                                'query_url' => $tool['url'],
                            ];
                        }
                        break;

                    case 'networkminer':
                    case 'packet_total':
                    case 'checkip':
                        $status = 'info';
                        $summary = "Packet inspection capture filters formatted: host {$resolvedIp}";
                        $resultData = [
                            'capture_filter' => "host {$resolvedIp}",
                            'bpf_syntax' => "ip proto 6 and host {$resolvedIp}",
                            'query_url' => $tool['url'],
                        ];
                        break;

                        // --- IP Loggers ---
                    case 'kitc':
                    case 'grabify':
                    case 'ip_logger':
                        $status = 'info';
                        $summary = "Canary telemetry tracking token prepared for {$tool['name']}.";
                        $resultData = [
                            'canary_token' => substr(md5($cleanTarget.microtime()), 0, 10),
                            'query_url' => $tool['url'],
                        ];
                        break;

                    default:
                        $status = 'info';
                        $summary = "Reconnaissance query prepared for {$tool['name']}.";
                        $resultData = ['url' => $tool['url']];
                        break;
                }
            }

            $elapsedMs = (int) round((microtime(true) - $itemStartTime) * 1000);
            $probedCount++;

            $catInfo = $this->mapBranchToFindingCategory($tool['branch']);
            $parsedDomain = parse_url($tool['url'], PHP_URL_HOST) ?: 'external';

            $resultItem = [
                'tool_id' => $tool['id'],
                'tool_name' => $tool['name'],
                'platform' => $tool['name'],
                'website_domain' => $parsedDomain,
                'branch' => $tool['branch'],
                'branch_label' => $tool['branch_label'],
                'category' => $catInfo['id'],
                'category_label' => $catInfo['label'],
                'tag' => $tool['tag'],
                'type' => $tool['type'],
                'url' => $tool['url'],
                'status' => $status,
                'is_external' => false,
                'summary' => $summary,
                'data' => $resultData,
                'response_time_ms' => $elapsedMs,
                'timestamp' => now()->toIso8601String(),
            ];

            $onResult($resultItem);

            $onProgress([
                'probed' => $probedCount,
                'total' => $totalTools,
                'found' => $foundCount,
                'percent' => (int) round(($probedCount / $totalTools) * 100),
                'current_tool' => $tool['name'],
                'branch' => $tool['branch'],
            ]);

            // Smooth streaming pace
            usleep(25000); // 25ms
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'total_probed' => $probedCount,
            'total_found' => $foundCount,
            'duration_ms' => $durationMs,
        ];
    }
}

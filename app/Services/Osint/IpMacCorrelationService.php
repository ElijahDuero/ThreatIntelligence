<?php

namespace App\Services\Osint;

use App\Services\Security\SafeUrlValidator;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class IpMacCorrelationService
{
    public function __construct(
        protected SafeUrlValidator $safeUrlValidator,
        protected ?IpLookupService $ipLookupService = null
    ) {}

    /**
     * Correlate an IPv4 (and optional dual-stack IPv6) target to its associated hardware MAC.
     *
     * @return array{
     *     resolved: bool,
     *     mac: string|null,
     *     method: string,
     *     method_label: string,
     *     confidence: string,
     *     explanation: string,
     *     vendor: array<string, mixed>|null
     * }
     */
    public function correlateIpToMac(string $ip, ?string $ipv6 = null): array
    {
        $ip = trim($ip);

        // Vector 1: Dual-Stack SLAAC EUI-64 Inversion (Mathematical certainty)
        if (! empty($ipv6)) {
            $eui64Mac = $this->reverseEui64($ipv6);
            if ($eui64Mac) {
                return [
                    'resolved' => true,
                    'mac' => $eui64Mac,
                    'method' => 'eui64_inversion',
                    'method_label' => 'SLAAC EUI-64 Inversion',
                    'confidence' => 'high',
                    'explanation' => "Reconstructed physical 48-bit MAC address from RFC 4291 SLAAC IPv6 interface identifier ({$ipv6}).",
                    'vendor' => $this->resolveVendor($eui64Mac),
                ];
            }
        }

        // Vector 2: Local Subnet / Private IP ARP & Neighbor Cache
        if ($this->isPrivateIp($ip)) {
            $arpMac = $this->resolveArpMac($ip);
            if ($arpMac) {
                return [
                    'resolved' => true,
                    'mac' => $arpMac,
                    'method' => 'arp_cache',
                    'method_label' => 'Local Subnet ARP Cache',
                    'confidence' => 'high',
                    'explanation' => "Resolved live Layer 2 physical address from host operating system ARP neighbor table for subnet IP {$ip}.",
                    'vendor' => $this->resolveVendor($arpMac),
                ];
            }

            return [
                'resolved' => false,
                'mac' => null,
                'method' => 'arp_unresolved',
                'method_label' => 'Local Subnet (Uncached)',
                'confidence' => 'none',
                'explanation' => "Target is in private RFC 1918 range ({$ip}), but no active ARP entry is currently cached on the local interface.",
                'vendor' => null,
            ];
        }

        // Vector 3: Active HTTP Header Sniffing (Router/CPE device leaks)
        $headerMac = $this->sniffHttpMacHeaders($ip);
        if ($headerMac) {
            return [
                'resolved' => true,
                'mac' => $headerMac,
                'method' => 'http_header_leak',
                'method_label' => 'Device HTTP Header Leak',
                'confidence' => 'high',
                'explanation' => "Hardware MAC address leaked by target device via HTTP response headers on public interface ({$ip}).",
                'vendor' => $this->resolveVendor($headerMac),
            ];
        }

        // Vector 4: Layer 3 WAN Boundary (Standard internet routing)
        return [
            'resolved' => false,
            'mac' => null,
            'method' => 'l3_wan_boundary',
            'method_label' => 'Layer 3 WAN Boundary',
            'confidence' => 'none',
            'explanation' => "Target is a remote public WAN host ({$ip}). Layer 2 frames (MAC) are terminated and stripped by upstream ISP internet routing gateways.",
            'vendor' => null,
        ];
    }

    /**
     * Mathematically reverse RFC 4291 EUI-64 SLAAC IPv6 address to its physical 48-bit MAC.
     */
    public function reverseEui64(string $ipv6): ?string
    {
        $binary = @inet_pton(trim($ipv6));
        if ($binary === false || strlen($binary) !== 16) {
            return null;
        }

        $bytes = array_values(unpack('C*', $binary));

        // Interface ID spans bytes 8 to 15 (0-indexed).
        // Middle bytes 11 and 12 must be 0xFF and 0xFE in EUI-64.
        if ($bytes[11] !== 0xFF || $bytes[12] !== 0xFE) {
            return null;
        }

        // Invert the Universal/Local bit (bit 7, bitwise 0x02) of the first byte
        $firstByte = $bytes[8] ^ 0x02;

        return sprintf(
            '%02X:%02X:%02X:%02X:%02X:%02X',
            $firstByte,
            $bytes[9],
            $bytes[10],
            $bytes[13],
            $bytes[14],
            $bytes[15]
        );
    }

    /**
     * Query host operating system ARP table for a private IPv4 target.
     */
    public function resolveArpMac(string $ip): ?string
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        // Linux fast-path: Read /proc/net/arp directly
        if (PHP_OS_FAMILY !== 'Windows' && is_readable('/proc/net/arp')) {
            $content = @file_get_contents('/proc/net/arp');
            if ($content !== false) {
                $mac = $this->parseArpOutput($content, $ip);
                if ($mac) {
                    return $mac;
                }
            }
        }

        // Cross-platform command execution via Symfony Process
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $process = new Process(['arp', '-a', $ip]);
            } else {
                $process = new Process(['arp', '-n', $ip]);
            }

            $process->setTimeout(1.0);
            $process->run();

            if ($process->isSuccessful()) {
                return $this->parseArpOutput($process->getOutput(), $ip);
            }
        } catch (\Throwable) {
            // Fallback gracefully
        }

        return null;
    }

    /**
     * Parse raw tabular ARP output from Windows, Linux /proc/net/arp, or ip neigh.
     */
    public function parseArpOutput(string $rawOutput, string $targetIp): ?string
    {
        $targetIp = trim($targetIp);
        if (empty($targetIp)) {
            return null;
        }

        $lines = explode("\n", $rawOutput);
        $escapedIp = preg_quote($targetIp, '/');

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Check if this line starts with or contains the exact target IP
            if (preg_match('/(?:^|\s)'.$escapedIp.'(?:\s|$)/i', $trimmed)) {
                if (preg_match('/([0-9a-fA-F]{2}[-:][0-9a-fA-F]{2}[-:][0-9a-fA-F]{2}[-:][0-9a-fA-F]{2}[-:][0-9a-fA-F]{2}[-:][0-9a-fA-F]{2})/i', $trimmed, $matches)) {
                    $mac = $this->normalizeMac($matches[1]);
                    if ($mac) {
                        return $mac;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Sniff HTTP response headers on common web ports for router/CPE MAC leaks.
     */
    public function sniffHttpMacHeaders(string $ip): ?string
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $this->isPrivateIp($ip)) {
            return null;
        }

        $ports = [80, 8080];
        foreach ($ports as $port) {
            $url = $port === 80 ? "http://{$ip}/" : "http://{$ip}:{$port}/";

            if (! $this->safeUrlValidator->isSafeUrl($url)) {
                continue;
            }

            try {
                $response = Http::timeout(1.5)
                    ->withHeaders(['User-Agent' => 'DarkWebsite-OSINT/1.0'])
                    ->head($url);

                $mac = $this->extractHttpMacHeaders($response->headers());
                if ($mac) {
                    return $mac;
                }
            } catch (\Throwable) {
                // Ignore timeout or connection failure
            }
        }

        return null;
    }

    /**
     * Extract and validate MAC address from HTTP response header array.
     *
     * @param  array<string, array<int, string>>  $headers
     */
    public function extractHttpMacHeaders(array $headers): ?string
    {
        $targetKeys = [
            'x-mac-address',
            'x-device-mac',
            'x-router-mac',
            'x-cpe-mac',
            'mac-address',
            'hw-address',
        ];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $targetKeys, true)) {
                $candidate = is_array($values) ? ($values[0] ?? '') : (string) $values;
                $mac = $this->normalizeMac($candidate);
                if ($mac) {
                    return $mac;
                }
            }
        }

        return null;
    }

    /**
     * Extract physical MAC address from unstructured banner text using regex.
     */
    public function extractBannerMac(string $banner): ?string
    {
        if (preg_match('/\b([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})\b/', $banner, $matches)) {
            return $this->normalizeMac($matches[0]);
        }

        return null;
    }

    /**
     * Determine if an IPv4 address is in private RFC 1918, loopback, or link-local range.
     */
    public function isPrivateIp(string $ip): bool
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Normalize MAC string to standard uppercase colon notation, discarding invalid/multicast/broadcast.
     */
    public function normalizeMac(string $candidate): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-F0-9]/i', '', $candidate) ?? '');
        if (strlen($clean) !== 12) {
            return null;
        }

        // Discard all-zeros and broadcast
        if ($clean === '000000000000' || $clean === 'FFFFFFFFFFFF') {
            return null;
        }

        // Discard IPv4 multicast range (01:00:5E:xx:xx:xx)
        if (str_starts_with($clean, '01005E')) {
            return null;
        }

        return implode(':', str_split($clean, 2));
    }

    /**
     * Resolve vendor information for a MAC address using injected or resolved IpLookupService.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveVendor(string $mac): ?array
    {
        if ($this->ipLookupService) {
            return $this->ipLookupService->resolveMacVendor($mac);
        }

        if (function_exists('app')) {
            try {
                return app(IpLookupService::class)->resolveMacVendor($mac);
            } catch (\Throwable) {
                // Ignore
            }
        }

        return null;
    }
}

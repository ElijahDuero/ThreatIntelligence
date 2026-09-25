<?php

namespace App\Services\Security;

use InvalidArgumentException;

class SafeUrlValidator
{
    /**
     * Private and reserved IPv4 CIDR blocks to prevent SSRF and metadata exfiltration.
     *
     * @var array<string>
     */
    protected array $blockedCidrs = [
        '0.0.0.0/8',          // Current network
        '10.0.0.0/8',         // Private-use networks (RFC 1918)
        '100.64.0.0/10',      // Carrier-grade NAT (RFC 6598)
        '127.0.0.0/8',        // Loopback (RFC 1122)
        '169.254.0.0/16',     // Link-local / Cloud IMDS metadata (RFC 3927)
        '172.16.0.0/12',      // Private-use networks (RFC 1918)
        '192.0.0.0/24',       // IETF Protocol Assignments
        '192.0.2.0/24',       // Documentation (TEST-NET-1)
        '192.168.0.0/16',     // Private-use networks (RFC 1918)
        '198.18.0.0/15',      // Network benchmark tests
        '198.51.100.0/24',    // Documentation (TEST-NET-2)
        '203.0.113.0/24',     // Documentation (TEST-NET-3)
        '224.0.0.0/4',        // Multicast
        '240.0.0.0/4',        // Reserved for future use
        '255.255.255.255/32', // Broadcast
    ];

    /**
     * Check whether a given URL is safe to request from the server backend.
     */
    public function isSafeUrl(string $url, bool $requireHttps = true): bool
    {
        try {
            $this->assertSafeUrl($url, $requireHttps);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Assert that a given URL is safe, or throw an InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     */
    public function assertSafeUrl(string $url, bool $requireHttps = true): string
    {
        $trimmed = trim($url);

        if (empty($trimmed)) {
            throw new InvalidArgumentException('URL cannot be empty.');
        }

        $parts = parse_url($trimmed);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Malformed URL structure.');
        }

        $scheme = strtolower($parts['scheme']);

        if ($requireHttps && $scheme !== 'https') {
            throw new InvalidArgumentException("Insecure scheme [{$scheme}] disallowed. Only HTTPS allowed.");
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Scheme [{$scheme}] is forbidden.");
        }

        $host = strtolower($parts['host']);

        // Remove IPv6 brackets if present
        $cleanHost = trim($host, '[]');

        // Check for local names
        if (in_array($cleanHost, ['localhost', 'loopback', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            throw new InvalidArgumentException("Target host [{$host}] is a local/loopback address.");
        }

        // Allow .onion addresses to pass through if they are destined for Tor proxying
        if (str_ends_with($cleanHost, '.onion')) {
            return $trimmed;
        }

        // Check if host is direct IP
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateOrReservedIp($cleanHost)) {
                throw new InvalidArgumentException("Direct IP [{$cleanHost}] is in a private or reserved range.");
            }

            return $trimmed;
        }

        // Resolve DNS to verify all returned IPs
        $resolvedIps = $this->resolveHost($cleanHost);

        if (empty($resolvedIps)) {
            throw new InvalidArgumentException("Host [{$cleanHost}] could not be resolved.");
        }

        foreach ($resolvedIps as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                throw new InvalidArgumentException("Host [{$cleanHost}] resolved to private/reserved IP [{$ip}].");
            }
        }

        return $trimmed;
    }

    /**
     * Check whether an IP address belongs to private, loopback, or cloud metadata ranges.
     */
    public function isPrivateOrReservedIp(string $ip): bool
    {
        // Standard PHP validation for private and reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Check IPv4 CIDR blocks (covers link-local / IMDS 169.254.x.x, CGNAT, etc.)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach ($this->blockedCidrs as $cidr) {
                if ($this->ipv4InCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        // Check IPv6 specific private/link-local ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            if ($lower === '::1' || str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve host to array of IP addresses.
     *
     * @return array<string>
     */
    public function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (! empty($records)) {
            $ips = [];
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }

            if (! empty($ips)) {
                return array_unique($ips);
            }
        }

        // Fallback to gethostbynamel
        $fallback = @gethostbynamel($host);

        return $fallback ?: [];
    }

    /**
     * Check if an IPv4 address is contained in a CIDR block.
     */
    protected function ipv4InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskBits = (int) $mask;
        $netmask = ~((1 << (32 - $maskBits)) - 1);

        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }
}

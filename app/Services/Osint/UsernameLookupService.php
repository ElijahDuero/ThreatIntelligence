<?php

namespace App\Services\Osint;

use App\Services\Security\SafeUrlValidator;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class UsernameLookupService
{
    protected Client $client;

    public function __construct(
        protected ?SafeUrlValidator $urlValidator = null
    ) {
        $this->urlValidator = $urlValidator ?? new SafeUrlValidator;

        $this->client = new Client([
            RequestOptions::TIMEOUT => 5,
            RequestOptions::CONNECT_TIMEOUT => 3,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 2,
                'strict' => true,
                'referer' => false,
                'protocols' => ['https'],
            ],
            RequestOptions::HEADERS => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
    }

    /**
     * Get the registry of Username Search Engines based on OSINT Framework.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSearchEngines(string $username): array
    {
        $cleanUser = ltrim(trim($username), '@');
        $encodedUser = urlencode($cleanUser);

        return [
            [
                'id' => 'whatsmyname_web',
                'name' => 'WhatsMyName Web',
                'tag' => 'Web',
                'type' => 'engine',
                'url' => "https://whatsmyname.app/?q={$encodedUser}",
                'description' => 'Fast username enumeration across hundreds of social networks and community forums.',
                'action_label' => 'Query WhatsMyName',
            ],
            [
                'id' => 'whatsmyname_tool',
                'name' => 'WhatsMyName (T)',
                'tag' => 'Tool',
                'type' => 'tool',
                'url' => 'https://github.com/WebBreacher/WhatsMyName',
                'description' => 'Official Python and JSON repository used by OSINT investigators globally.',
                'action_label' => 'View Tool Repo',
            ],
            [
                'id' => 'sylva_identity',
                'name' => 'Sylva Identity Discovery (T)',
                'tag' => 'Tool',
                'type' => 'tool',
                'url' => 'https://github.com/sylva-dev/sylva',
                'description' => 'Identity correlation and automated username discovery tool framework.',
                'action_label' => 'View Sylva Repo',
            ],
            [
                'id' => 'sherlock_tool',
                'name' => 'Sherlock (T)',
                'tag' => 'Tool',
                'type' => 'tool',
                'url' => 'https://github.com/sherlock-project/sherlock',
                'description' => 'Hunt down social media accounts by username across social networks via CLI.',
                'action_label' => 'Sherlock CLI',
            ],
            [
                'id' => 'namechk',
                'name' => 'Namechk',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://namechk.com/custom_search?q={$encodedUser}",
                'description' => 'Check username and domain availability and footprint on popular social platforms.',
                'action_label' => 'Check Namechk',
            ],
            [
                'id' => 'thatsthem',
                'name' => 'Thats Them',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://thatsthem.com/name/{$encodedUser}",
                'description' => 'People search engine linking usernames and real-world alias records.',
                'action_label' => 'Search ThatsThem',
            ],
            [
                'id' => 'namecheckup',
                'name' => 'NameCheckup',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://namecheckup.com?search={$encodedUser}",
                'description' => 'Brand and alias availability lookup across social and Web2 networks.',
                'action_label' => 'Open NameCheckup',
            ],
            [
                'id' => 'footprintiq',
                'name' => 'FootprintIQ',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => 'https://footprintiq.com',
                'description' => 'Digital identity footprint analysis and exposure risk assessment platform.',
                'action_label' => 'Open FootprintIQ',
            ],
            [
                'id' => 'gitfive',
                'name' => 'GitFive (T)',
                'tag' => 'Tool',
                'type' => 'tool',
                'url' => 'https://github.com/mxrch/gitfive',
                'description' => 'OSINT tool to track and discover targets using GitHub accounts and email commits.',
                'action_label' => 'View GitFive',
            ],
            [
                'id' => 'sherlock_web',
                'name' => 'Sherlock',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://sherlock-project.github.io?search={$encodedUser}",
                'description' => 'Sherlock project public web tracker and documentation portal.',
                'action_label' => 'Sherlock Web',
            ],
            [
                'id' => 'names_directory',
                'name' => 'Names Directory',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://namesdir.com/{$encodedUser}",
                'description' => 'International naming directory and alias profile repository.',
                'action_label' => 'Lookup Directory',
            ],
            [
                'id' => 'lullar',
                'name' => 'Lullar',
                'tag' => 'Engine',
                'type' => 'engine',
                'url' => "https://com.lullar.com/?username={$encodedUser}",
                'description' => 'Multi-platform social profile locator for aliases and handles.',
                'action_label' => 'Open Lullar',
            ],
        ];
    }

    /**
     * Get the registry of Specific Sites based on OSINT Framework.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSpecificSites(string $username): array
    {
        $cleanUser = ltrim(trim($username), '@');
        $encodedUser = urlencode($cleanUser);

        return [
            [
                'id' => 'amazon_usernames',
                'name' => 'Amazon Usernames (M)',
                'tag' => 'Method',
                'type' => 'ecommerce',
                'target_url' => "https://www.amazon.com/gp/profile/amzn1.account.{$cleanUser}",
                'method_note' => 'Manual review of Amazon reviewer profile, public wishlists, and purchase badges.',
                'probe_url' => null,
                'supports_live_probe' => false,
            ],
            [
                'id' => 'github_user',
                'name' => 'Github User (M)',
                'tag' => 'Method',
                'type' => 'profile',
                'target_url' => "https://github.com/{$cleanUser}",
                'method_note' => 'Inspects public profile, commit history, SSH public keys, and gists.',
                'probe_url' => "https://api.github.com/users/{$cleanUser}",
                'supports_live_probe' => true,
            ],
            [
                'id' => 'tinder_usernames',
                'name' => 'Tinder Usernames (M)',
                'tag' => 'Method',
                'type' => 'social',
                'target_url' => "https://tinder.com/@{$cleanUser}",
                'method_note' => 'Public Tinder web username profile lookup. Direct manual review required if restricted.',
                'probe_url' => "https://tinder.com/@{$cleanUser}",
                'supports_live_probe' => true,
            ],
            [
                'id' => 'keybase',
                'name' => 'Keybase',
                'tag' => 'Platform',
                'type' => 'profile',
                'target_url' => "https://keybase.io/{$cleanUser}",
                'method_note' => 'Cryptographic identity linking GitHub, Twitter/X, PGP keys, and crypto wallets.',
                'probe_url' => "https://keybase.io/_/api/1.0/user/lookup.json?usernames={$cleanUser}",
                'supports_live_probe' => true,
            ],
            [
                'id' => 'mit_pgp',
                'name' => 'MIT PGP Key Server',
                'tag' => 'Keyserver',
                'type' => 'keyserver',
                'target_url' => "https://pgp.mit.edu/pks/lookup?search={$encodedUser}&op=index",
                'method_note' => 'Query OpenPGP public key directory for keys registered under alias or handle.',
                'probe_url' => "https://pgp.mit.edu/pks/lookup?search={$encodedUser}&op=index",
                'supports_live_probe' => true,
            ],
            [
                'id' => 'protonmail_users',
                'name' => 'ProtonMail users (M)',
                'tag' => 'Method',
                'type' => 'mail',
                'target_url' => "https://api.protonmail.ch/pks/lookup?op=get&search={$encodedUser}@protonmail.com",
                'method_note' => 'Probes Proton OpenPGP key lookup endpoint. Live public key confirms active mailbox.',
                'probe_url' => "https://api.protonmail.ch/pks/lookup?op=get&search={$encodedUser}@protonmail.com",
                'supports_live_probe' => true,
            ],
            [
                'id' => 'protonmail_domains',
                'name' => 'ProtonMail Domains (M)',
                'tag' => 'Method',
                'type' => 'mail',
                'target_url' => "https://api.protonmail.ch/pks/lookup?op=get&search={$encodedUser}@proton.me",
                'method_note' => 'Checks modern @proton.me and @pm.me alias domain variants via public key lookup.',
                'probe_url' => "https://api.protonmail.ch/pks/lookup?op=get&search={$encodedUser}@proton.me",
                'supports_live_probe' => true,
            ],
        ];
    }

    /**
     * Actively probe a specific site for a live profile.
     *
     * @return array<string, mixed>
     */
    public function probeSpecificSite(array $site, string $username): array
    {
        $id = $site['id'];
        $cleanUser = ltrim(trim($username), '@');

        if (! $site['supports_live_probe'] || empty($site['probe_url'])) {
            return array_merge($site, [
                'status' => 'manual_only',
                'status_label' => 'Manual Method (M)',
                'status_color' => 'amber',
                'details' => $site['method_note'],
                'metadata' => null,
            ]);
        }

        // Assert URL safety against SSRF and private/reserved ranges before making outbound call
        try {
            $this->urlValidator->assertSafeUrl($site['probe_url'], requireHttps: true);
        } catch (\Throwable $e) {
            Log::warning("SSRF security policy blocked OSINT probe for [{$id}]: ".$e->getMessage());

            return array_merge($site, [
                'status' => 'error',
                'status_label' => 'Blocked',
                'status_color' => 'rose',
                'details' => 'Outbound probe was blocked by workstation SSRF security policy.',
                'metadata' => null,
            ]);
        }

        try {
            switch ($id) {
                case 'github_user':
                    $res = $this->client->get($site['probe_url'], [
                        RequestOptions::HEADERS => [
                            'User-Agent' => 'DarkWebsite-OSINT-Workstation',
                            'Accept' => 'application/vnd.github.v3+json',
                        ],
                    ]);

                    if ($res->getStatusCode() === 200) {
                        $json = json_decode((string) $res->getBody(), true);

                        return array_merge($site, [
                            'status' => 'found',
                            'status_label' => 'Profile Found',
                            'status_color' => 'emerald',
                            'details' => $json['bio'] ?: ($json['name'] ? "Name: {$json['name']}" : 'Public GitHub profile active'),
                            'metadata' => [
                                'avatar_url' => $json['avatar_url'] ?? null,
                                'public_repos' => $json['public_repos'] ?? 0,
                                'followers' => $json['followers'] ?? 0,
                                'name' => $json['name'] ?? $cleanUser,
                                'location' => $json['location'] ?? null,
                                'created_at' => $json['created_at'] ?? null,
                            ],
                        ]);
                    } elseif ($res->getStatusCode() === 404) {
                        return array_merge($site, [
                            'status' => 'not_found',
                            'status_label' => 'Not Found',
                            'status_color' => 'slate',
                            'details' => 'No GitHub user exists with this username.',
                            'metadata' => null,
                        ]);
                    }
                    break;

                case 'keybase':
                    $res = $this->client->get($site['probe_url']);
                    if ($res->getStatusCode() === 200) {
                        $json = json_decode((string) $res->getBody(), true);
                        $them = $json['them'] ?? [];
                        if (! empty($them) && ! empty($them[0]['id'])) {
                            $userObj = $them[0];
                            $bio = $userObj['profile']['bio'] ?? 'Active Keybase cryptographic identity';

                            return array_merge($site, [
                                'status' => 'found',
                                'status_label' => 'Identity Found',
                                'status_color' => 'emerald',
                                'details' => $bio,
                                'metadata' => [
                                    'full_name' => $userObj['profile']['full_name'] ?? null,
                                    'location' => $userObj['profile']['location'] ?? null,
                                    'proofs_count' => count($userObj['proofs_summary']['by_presentation_group'] ?? []),
                                ],
                            ]);
                        }
                    }

                    return array_merge($site, [
                        'status' => 'not_found',
                        'status_label' => 'Not Found',
                        'status_color' => 'slate',
                        'details' => 'No Keybase identity registered under this handle.',
                        'metadata' => null,
                    ]);

                case 'mit_pgp':
                    $res = $this->client->get($site['probe_url']);
                    $body = (string) $res->getBody();
                    if ($res->getStatusCode() === 200 && (str_contains($body, 'pub ') || str_contains($body, 'Type bits'))) {
                        preg_match_all('/<a href="([^"]+)">([0-9A-Fa-f]{8,})<\/a>/i', $body, $keyMatches);
                        $keyCount = count($keyMatches[2] ?? []);

                        return array_merge($site, [
                            'status' => 'found',
                            'status_label' => 'PGP Keys Found',
                            'status_color' => 'emerald',
                            'details' => $keyCount > 0 ? "Found {$keyCount} PGP public key(s) registered for this alias" : 'PGP Key match discovered',
                            'metadata' => [
                                'keys' => array_slice($keyMatches[2] ?? [], 0, 5),
                            ],
                        ]);
                    }

                    return array_merge($site, [
                        'status' => 'not_found',
                        'status_label' => 'No Keys Found',
                        'status_color' => 'slate',
                        'details' => 'No OpenPGP public keys listed on MIT Keyserver for this search term.',
                        'metadata' => null,
                    ]);

                case 'protonmail_users':
                case 'protonmail_domains':
                    $res = $this->client->get($site['probe_url']);
                    $body = (string) $res->getBody();
                    if ($res->getStatusCode() === 200 && str_contains($body, 'BEGIN PGP PUBLIC KEY BLOCK')) {
                        return array_merge($site, [
                            'status' => 'found',
                            'status_label' => 'Proton Account Active',
                            'status_color' => 'emerald',
                            'details' => 'Verified active Proton mailbox (OpenPGP key published on key server).',
                            'metadata' => [
                                'pgp_verified' => true,
                            ],
                        ]);
                    }

                    return array_merge($site, [
                        'status' => 'not_found',
                        'status_label' => 'Not Registered',
                        'status_color' => 'slate',
                        'details' => 'No public OpenPGP key returned for this Proton address.',
                        'metadata' => null,
                    ]);

                case 'tinder_usernames':
                    $res = $this->client->get($site['probe_url']);
                    $status = $res->getStatusCode();
                    if ($status === 200) {
                        return array_merge($site, [
                            'status' => 'found',
                            'status_label' => 'Profile Route 200',
                            'status_color' => 'emerald',
                            'details' => 'Tinder web profile route returned HTTP 200. Review profile link.',
                            'metadata' => null,
                        ]);
                    }

                    return array_merge($site, [
                        'status' => 'not_found',
                        'status_label' => 'Not Found / 404',
                        'status_color' => 'slate',
                        'details' => 'Tinder profile returned 404 or redirect.',
                        'metadata' => null,
                    ]);
            }
        } catch (\Throwable $e) {
            Log::info("OSINT probe failed for [{$id}] [{$cleanUser}]: ".$e->getMessage());

            return array_merge($site, [
                'status' => 'error',
                'status_label' => 'Probe Timeout',
                'status_color' => 'rose',
                'details' => 'Target server timed out or blocked automated HTTP request. Use direct minitext link.',
                'metadata' => null,
            ]);
        }

        return array_merge($site, [
            'status' => 'manual_only',
            'status_label' => 'Method (M)',
            'status_color' => 'amber',
            'details' => $site['method_note'],
            'metadata' => null,
        ]);
    }

    /**
     * Probe all Specific Sites for a target username.
     *
     * @return array<int, array<string, mixed>>
     */
    public function probeAllSpecificSites(string $username): array
    {
        $sites = $this->getSpecificSites($username);
        $results = [];

        foreach ($sites as $site) {
            $results[] = $this->probeSpecificSite($site, $username);
        }

        return $results;
    }
}

# Statement of Intent: IPv4-to-MAC Address Intelligence Engine

## 1. Outcome
A unified, context-aware IPv4-to-MAC correlation engine that automatically resolves hardware MAC addresses, IEEE OUI manufacturers, NIC types (physical hardware vs. virtual machine), and frame modes from IPv4 and dual-stack targets.

## 2. User & Persona
Security analysts, OSINT investigators, and network forensics engineers operating DarkWebsite to profile network infrastructure, trace physical hardware assets, and enrich threat intelligence dossiers.

## 3. Why Now
IP address and MAC address intelligence currently operate in isolated silos. When an investigator queries an IPv4 address, the platform yields geolocation and BGP data but provides zero hardware or link-layer attribution, forcing manual correlation across external tools.

## 4. Success Criteria
1. **Local / Subnet IPv4 (RFC 1918):** Automatically queries the host OS neighbor/ARP cache (e.g., `192.168.50.1` -> `48:8F:5A:AF:C0:C0`) and identifies the IEEE OUI vendor (`Routerboard.com / MikroTik`).
2. **Dual-Stack SLAAC IPv6:** Inverts RFC 4291 EUI-64 interface identifiers (`ff:fe` extraction + bit 7 inversion) to mathematically reconstruct physical NIC MACs without active packet transmissions.
3. **Device Header Leaks:** Passively extracts hardware MACs from HTTP response headers (`X-MAC-Address`, `X-Device-MAC`, TR-069) and common router web signatures.
4. **Public WAN Boundary:** Remote internet IPs without leaks cleanly report `[L3 WAN BOUNDARY: ENCAPSULATED]` with a technical explanation rather than blank/error cards.
5. **Analyst Pivots:** Provides instant 1-click actions:
   - *"Pivot to OUI Forensics"* (re-runs investigation centered on the discovered MAC)
   - *"Query WiGLE BSSID"* (maps physical wireless coordinates if wireless BSSID detected)
   - *"Bookmark to Case Dossier"* (saves linked IP + MAC telemetry directly into active investigation dossier).

## 5. Binding Constraints
- **Security Hardening:** Zero shell command injection. Strict `FILTER_VALIDATE_IP` validation must gate any OS-level command execution.
- **SSRF & Network Safety:** All outbound HTTP requests must pass through `SafeUrlValidator` with bounded 1.5s timeouts.
- **Intellectual Honesty:** Zero synthetic or hallucinated MAC addresses. WAN packet boundaries must be reported truthfully.

## 6. Out of Scope
- Promiscuous-mode raw packet sniffing (`libpcap` / raw sockets) requiring elevated kernel-level root/administrator privileges.
- Aggressive port flooding or active exploit payload delivery that triggers network IDSes or firewalls.

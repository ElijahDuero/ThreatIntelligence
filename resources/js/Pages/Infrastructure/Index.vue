<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Server, 
    Globe, 
    Network, 
    FolderGit2, 
    Layers, 
    MapPin, 
    CheckCircle2, 
    ShieldAlert 
} from 'lucide-vue-next';
import InfraConsole from '@/Components/Infrastructure/InfraConsole.vue';
import DomainSummaryHud from '@/Components/Infrastructure/DomainSummaryHud.vue';
import DomainSubdomainsPanel from '@/Components/Infrastructure/DomainSubdomainsPanel.vue';
import DomainDnsPanel from '@/Components/Infrastructure/DomainDnsPanel.vue';
import DomainRdapPanel from '@/Components/Infrastructure/DomainRdapPanel.vue';
import DomainGeoIpPanel from '@/Components/Infrastructure/DomainGeoIpPanel.vue';
import PortsAndCvePanel from '@/Components/Infrastructure/PortsAndCvePanel.vue';
import StandaloneIpPanel from '@/Components/Infrastructure/StandaloneIpPanel.vue';
import DossierDeployModal from '@/Components/Infrastructure/DossierDeployModal.vue';

const props = defineProps({
    investigations: {
        type: Array,
        default: () => [],
    },
});

const activeMode = ref('domain');
const targetInput = ref('');
const isAnalyzing = ref(false);
const errorMessage = ref(null);
const toastMessage = ref(null);

const domainResult = ref(null);
const ipResult = ref(null);

const activeDomainTab = ref('subdomains');

const showDossierModal = ref(false);
const isDeployingToDossier = ref(false);
const deploySuccessResult = ref(null);

const showToast = (msg) => {
    toastMessage.value = msg;
    setTimeout(() => {
        toastMessage.value = null;
    }, 3500);
};

const applyPreset = ({ value, mode }) => {
    activeMode.value = mode || 'domain';
    targetInput.value = value;
    runAnalysis();
};

const runAnalysis = async () => {
    const raw = targetInput.value.trim();
    if (!raw) return;

    errorMessage.value = null;
    isAnalyzing.value = true;

    try {
        if (activeMode.value === 'domain') {
            const res = await axios.post('/api/infra-recon/domain', { domain: raw });
            if (res.data.success) {
                domainResult.value = res.data;
                ipResult.value = res.data.primary_ip_intel || null;
            } else {
                errorMessage.value = res.data.error || 'Failed to inspect domain infrastructure.';
            }
        } else {
            const res = await axios.post('/api/infra-recon/ip', { ip: raw });
            if (res.data.success) {
                ipResult.value = res.data;
                domainResult.value = null;
            } else {
                errorMessage.value = res.data.error || 'Failed to inspect IP address telemetry.';
            }
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || err.message || 'Network anomaly encountered during probe.';
    } finally {
        isAnalyzing.value = false;
    }
};

const inspectSubdomain = (sub) => {
    activeMode.value = 'domain';
    targetInput.value = sub;
    runAnalysis();
};

const inspectIpDirect = (ip) => {
    activeMode.value = 'ip';
    targetInput.value = ip;
    runAnalysis();
};

const activePortsIntel = computed(() => {
    if (activeMode.value === 'domain') {
        return domainResult.value?.primary_ip_intel?.ports_intel || { ports: [], cves: [], cpes: [], tags: [], total_ports: 0, total_cves: 0, has_vulnerabilities: false };
    }
    return ipResult.value?.ports_intel || { ports: [], cves: [], cpes: [], tags: [], total_ports: 0, total_cves: 0, has_vulnerabilities: false };
});

const candidateNodesAndEdges = computed(() => {
    const nodes = [];
    const edges = [];
    const nodeIds = new Set();

    const pushNode = (id, label, type, subtitle, meta = {}) => {
        if (!nodeIds.has(id)) {
            nodeIds.add(id);
            nodes.push({ id, label, type, subtitle, metadata: meta, is_auto: true });
        }
    };

    const pushEdge = (source, target, label) => {
        if (source && target && source !== target) {
            edges.push({
                id: 'edge_' + Math.random().toString(36).substring(2, 9),
                source,
                target,
                label,
                type: 'auto'
            });
        }
    };

    if (domainResult.value && domainResult.value.success) {
        const dom = domainResult.value.target;
        const domId = 'dom_' + dom.replace(/[^a-zA-Z0-9]/g, '_');
        pushNode(domId, dom, 'domain', 'Target Domain', {
            registrar: domainResult.value.summary?.registrar,
            records_count: domainResult.value.summary?.total_dns_records
        });

        if (domainResult.value.primary_ip) {
            const ip = domainResult.value.primary_ip;
            const ipId = 'ip_' + ip.replace(/[^a-zA-Z0-9]/g, '_');
            const intel = domainResult.value.primary_ip_intel || {};
            pushNode(ipId, ip, 'ip', intel.org || intel.isp || 'IPv4 Address', {
                country: intel.country,
                city: intel.city,
                asn: intel.as
            });
            pushEdge(domId, ipId, 'resolves_to');

            const ports = intel.ports_intel?.ports || [];
            ports.forEach(p => {
                const portId = 'port_' + ip.replace(/[^a-zA-Z0-9]/g, '_') + '_' + p.port;
                pushNode(portId, 'Port ' + p.port + ' (' + p.service + ')', 'server', 'Passive Open Port', { port: p.port, service: p.service });
                pushEdge(ipId, portId, 'exposes_port');
            });

            const cves = intel.ports_intel?.cves || [];
            cves.forEach(c => {
                const cveId = 'cve_' + c.id.replace(/[^a-zA-Z0-9]/g, '_');
                pushNode(cveId, c.id, 'cve', 'Reported Vulnerability', { nvd_url: c.nvd_url });
                pushEdge(ipId, cveId, 'vulnerable_to');
            });
        }

        const subs = (domainResult.value.subdomains?.subdomains || []).slice(0, 15);
        subs.forEach(s => {
            const subId = 'sub_' + s.subdomain.replace(/[^a-zA-Z0-9]/g, '_');
            pushNode(subId, s.subdomain, 'domain', 'Subdomain (' + (s.issuer || 'CT Log') + ')', {
                valid_until: s.valid_until
            });
            pushEdge(subId, domId, 'subdomain_of');
        });

        const nsList = domainResult.value.dns?.grouped?.NS || [];
        nsList.forEach(ns => {
            const nsId = 'ns_' + (ns.value || '').replace(/[^a-zA-Z0-9]/g, '_');
            pushNode(nsId, ns.value, 'server', 'Authoritative NS', { ttl: ns.ttl });
            pushEdge(domId, nsId, 'managed_by');
        });

        const mxList = domainResult.value.dns?.grouped?.MX || [];
        mxList.forEach(mx => {
            const mxId = 'mx_' + (mx.value || '').replace(/[^a-zA-Z0-9]/g, '_');
            pushNode(mxId, mx.value, 'server', 'Mail Server (MX)', { priority: mx.extra?.priority });
            pushEdge(domId, mxId, 'mail_exchanger');
        });
    } else if (ipResult.value && ipResult.value.success) {
        const ip = ipResult.value.ip;
        const ipId = 'ip_' + ip.replace(/[^a-zA-Z0-9]/g, '_');
        pushNode(ipId, ip, 'ip', ipResult.value.org || ipResult.value.isp || 'Host IP', {
            country: ipResult.value.country,
            city: ipResult.value.city,
            asn: ipResult.value.as
        });

        if (ipResult.value.reverse_dns) {
            const rdnsId = 'rdns_' + ipResult.value.reverse_dns.replace(/[^a-zA-Z0-9]/g, '_');
            pushNode(rdnsId, ipResult.value.reverse_dns, 'domain', 'Reverse DNS Hostname');
            pushEdge(ipId, rdnsId, 'reverse_dns');
        }

        const ports = ipResult.value.ports_intel?.ports || [];
        ports.forEach(p => {
            const portId = 'port_' + ip.replace(/[^a-zA-Z0-9]/g, '_') + '_' + p.port;
            pushNode(portId, 'Port ' + p.port + ' (' + p.service + ')', 'server', 'Passive Open Port', { port: p.port, service: p.service });
            pushEdge(ipId, portId, 'exposes_port');
        });

        const cves = ipResult.value.ports_intel?.cves || [];
        cves.forEach(c => {
            const cveId = 'cve_' + c.id.replace(/[^a-zA-Z0-9]/g, '_');
            pushNode(cveId, c.id, 'cve', 'Reported Vulnerability', { nvd_url: c.nvd_url });
            pushEdge(ipId, cveId, 'vulnerable_to');
        });
    }

    return { nodes, edges };
});

const deployToDossier = async (investigationId) => {
    if (!investigationId) return;
    const { nodes, edges } = candidateNodesAndEdges.value;
    if (nodes.length === 0) return;

    isDeployingToDossier.value = true;
    try {
        const res = await axios.post('/api/infra-recon/link-graph', {
            investigation_id: investigationId,
            nodes,
            edges
        });

        if (res.data.success) {
            deploySuccessResult.value = res.data;
            showToast(`Injected ${res.data.added_nodes} entities into Dossier #${res.data.case_number}`);
        }
    } catch (err) {
        errorMessage.value = 'Failed to link entities to case dossier.';
    } finally {
        isDeployingToDossier.value = false;
    }
};
</script>

<template>
    <div class="space-y-6">
        <Head title="Infrastructure & DNS Recon | DarkDump OSINT" />

        <!-- Toast Notification -->
            <div 
                v-if="toastMessage" 
                class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl bg-slate-900 border border-emerald-500/80 text-emerald-300 font-mono text-xs shadow-2xl flex items-center space-x-2 animate-bounce"
            >
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>

            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/30 flex items-center justify-center">
                            <Server class="w-4 h-4 text-sky-400" />
                        </div>
                        <h1 class="text-xl font-bold font-serif text-white tracking-wide uppercase">
                            Infrastructure & DNS Recon
                        </h1>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-sky-950 text-sky-300 border border-sky-500/40">
                            v2.0 ACTIVE
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 font-mono">
                        Passive Domain Enumeration, Certificate Transparency (crt.sh), RDAP/WHOIS & GeoIP Network Mapping
                    </p>
                </div>

                <!-- Right Actions: Dossier Injector button -->
                <div class="flex items-center space-x-3">
                    <button
                        v-if="domainResult || ipResult"
                        type="button"
                        @click="showDossierModal = true"
                        class="px-3.5 py-2 rounded-xl bg-indigo-950/80 border border-indigo-500/50 hover:border-indigo-400 text-indigo-300 hover:text-white text-xs font-mono font-bold flex items-center space-x-2 transition shadow-lg shadow-indigo-950/40 cursor-pointer cartoon-btn"
                    >
                        <FolderGit2 class="w-4 h-4 text-indigo-400" />
                        <span>Deploy to Case Dossier ({{ candidateNodesAndEdges.nodes.length }})</span>
                    </button>
                </div>
            </div>

            <!-- Target Search Console -->
            <InfraConsole 
                v-model="targetInput"
                v-model:activeMode="activeMode"
                :is-analyzing="isAnalyzing"
                :error-message="errorMessage"
                @analyze="runAnalysis"
                @preset-selected="applyPreset"
            />

            <!-- Domain Results Section -->
            <div v-if="domainResult && domainResult.success" class="space-y-6">
                <!-- Summary HUD Banner -->
                <DomainSummaryHud :domain-result="domainResult" />

                <!-- Domain Navigation Tabs -->
                <div class="flex items-center space-x-2 border-b border-slate-800 pb-2 text-xs font-mono overflow-x-auto">
                    <button
                        type="button"
                        @click="activeDomainTab = 'subdomains'"
                        :class="activeDomainTab === 'subdomains' ? 'text-sky-300 border-b-2 border-sky-400 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                        class="px-3 py-2 transition flex items-center space-x-2 cursor-pointer whitespace-nowrap"
                    >
                        <Layers class="w-4 h-4 text-sky-400" />
                        <span>Subdomains Matrix ({{ domainResult.subdomains?.total_found || 0 }})</span>
                    </button>
                    <button
                        type="button"
                        @click="activeDomainTab = 'dns'"
                        :class="activeDomainTab === 'dns' ? 'text-sky-300 border-b-2 border-sky-400 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                        class="px-3 py-2 transition flex items-center space-x-2 cursor-pointer whitespace-nowrap"
                    >
                        <Network class="w-4 h-4 text-cyan-400" />
                        <span>DNS Records ({{ domainResult.dns?.records?.length || 0 }})</span>
                    </button>
                    <button
                        type="button"
                        @click="activeDomainTab = 'rdap'"
                        :class="activeDomainTab === 'rdap' ? 'text-sky-300 border-b-2 border-sky-400 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                        class="px-3 py-2 transition flex items-center space-x-2 cursor-pointer whitespace-nowrap"
                    >
                        <Globe class="w-4 h-4 text-amber-400" />
                        <span>RDAP / WHOIS Registry</span>
                    </button>
                    <button
                        type="button"
                        @click="activeDomainTab = 'geoip'"
                        :class="activeDomainTab === 'geoip' ? 'text-sky-300 border-b-2 border-sky-400 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                        class="px-3 py-2 transition flex items-center space-x-2 cursor-pointer whitespace-nowrap"
                    >
                        <MapPin class="w-4 h-4 text-emerald-400" />
                        <span>GeoIP & Routing (Primary Host)</span>
                    </button>
                    <button
                        type="button"
                        @click="activeDomainTab = 'ports'"
                        :class="activeDomainTab === 'ports' ? 'text-sky-300 border-b-2 border-sky-400 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                        class="px-3 py-2 transition flex items-center space-x-2 cursor-pointer whitespace-nowrap"
                    >
                        <ShieldAlert v-if="activePortsIntel.has_vulnerabilities" class="w-4 h-4 text-rose-400 animate-pulse" />
                        <Server v-else class="w-4 h-4 text-indigo-400" />
                        <span>Open Ports & CVE Intel ({{ activePortsIntel.total_ports }} ports / {{ activePortsIntel.total_cves }} CVEs)</span>
                    </button>
                </div>

                <!-- TAB CONTENTS -->
                <DomainSubdomainsPanel 
                    v-if="activeDomainTab === 'subdomains'" 
                    :subdomains="domainResult.subdomains"
                    :target-domain="domainResult.target"
                    @inspect-subdomain="inspectSubdomain"
                />

                <DomainDnsPanel 
                    v-else-if="activeDomainTab === 'dns'" 
                    :dns="domainResult.dns"
                    @inspect-ip="inspectIpDirect"
                />

                <DomainRdapPanel 
                    v-else-if="activeDomainTab === 'rdap'" 
                    :rdap="domainResult.rdap"
                />

                <DomainGeoIpPanel 
                    v-else-if="activeDomainTab === 'geoip'" 
                    :ip-intel="domainResult.primary_ip_intel"
                />

                <PortsAndCvePanel 
                    v-else-if="activeDomainTab === 'ports'" 
                    :ports-intel="activePortsIntel"
                    :host-label="domainResult.primary_ip"
                />
            </div>

            <!-- Standalone IP Results Section -->
            <StandaloneIpPanel 
                v-else-if="ipResult && ipResult.success && activeMode === 'ip'"
                :ip-result="ipResult"
            />

            <!-- Empty State / Welcome HUD -->
            <div v-else-if="!isAnalyzing" class="p-12 text-center rounded-2xl bg-slate-900/40 border border-slate-800 font-mono space-y-4">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/30 flex items-center justify-center mx-auto text-sky-400">
                    <Server class="w-6 h-6" />
                </div>
                <div class="max-w-md mx-auto">
                    <h3 class="font-serif font-bold text-white text-base uppercase tracking-wider">AWAITING INFRASTRUCTURE TARGET</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Enter a domain or IP above to resolve DNS records, inspect SSL Certificate Transparency logs (crt.sh), query RDAP registry, and trace geolocation routing.
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 pt-2 text-xs">
                    <button 
                        type="button"
                        @click="applyPreset({ value: 'torproject.org', mode: 'domain' })" 
                        class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-sky-500/40 text-slate-300 hover:text-white transition cursor-pointer"
                    >
                        Probe torproject.org
                    </button>
                    <button 
                        type="button"
                        @click="applyPreset({ value: 'proton.me', mode: 'domain' })" 
                        class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-sky-500/40 text-slate-300 hover:text-white transition cursor-pointer"
                    >
                        Probe proton.me
                    </button>
                    <button 
                        type="button"
                        @click="applyPreset({ value: '1.1.1.1', mode: 'ip' })" 
                        class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-500/40 text-slate-300 hover:text-white transition cursor-pointer"
                    >
                        Trace 1.1.1.1
                    </button>
                </div>
            </div>

            <!-- MODAL: DEPLOY FINDINGS TO CASE DOSSIER GRAPH -->
            <DossierDeployModal 
                :show="showDossierModal"
                :investigations="props.investigations"
                :candidate-nodes-and-edges="candidateNodesAndEdges"
                :is-deploying="isDeployingToDossier"
                :deploy-success-result="deploySuccessResult"
                @close="showDossierModal = false"
                @deploy="deployToDossier"
            />
        </div>
</template>

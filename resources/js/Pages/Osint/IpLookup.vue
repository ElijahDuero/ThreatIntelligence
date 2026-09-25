<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Network, 
    Search, 
    ExternalLink, 
    Copy, 
    Check, 
    FolderPlus, 
    Globe, 
    Terminal, 
    ShieldAlert, 
    ShieldCheck, 
    Loader2, 
    RefreshCw, 
    ArrowRight, 
    X, 
    Filter, 
    Download, 
    AlertTriangle, 
    Server, 
    CheckCircle2, 
    Database, 
    Shield, 
    StopCircle, 
    Activity, 
    Cpu, 
    Compass, 
    MapPin, 
    RadioTower, 
    Layers, 
    FileText,
    Binary,
    ChevronDown,
    Wrench,
    Wifi,
} from 'lucide-vue-next';

import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';
import { downloadJson, downloadMarkdown } from '@/Utils/exportHelpers';

const props = defineProps({
    initialTarget: String,
    initialClassification: Object,
    initialProbe: Object,
    initialFindings: Array,
    findingCategories: Array,
    categorizedTools: Object,
    investigations: Array,
    totalToolsCount: Number,
});

const targetInput = ref(props.initialTarget || '');
const currentTarget = ref(props.initialTarget || '');
const isProbing = ref(false);
const showHitsOnly = ref(false);
const selectedCategory = ref('all');
const toolSearchQuery = ref('');
const activeBranch = ref('all');
const showExportMenu = ref(false);

// Real-Time SSE Multi-Tool Streaming State
let eventSource = null;
const isStreaming = ref(false);
const discoveredFindings = ref(props.initialFindings ? [...props.initialFindings] : []);
const allStreamedResults = ref([]);
const streamProgress = ref({
    probed: 0,
    total: props.totalToolsCount || 56,
    found: props.initialFindings?.length || 0,
    percent: 0,
    last_tool: '',
    last_status: '',
    duration_ms: 0,
});

// Telemetry State
const classification = ref(props.initialClassification || null);
const probeData = ref(props.initialProbe || null);
const categorizedTools = ref(props.categorizedTools || {
    geolocation: [],
    host_port_discovery: [],
    ipv4: [],
    ipv6: [],
    bgp: [],
    reputation: [],
    blacklists: [],
    neighbor_domains: [],
    protected_by_cloud: [],
    wireless_network_info: [],
    network_analysis_tools: [],
    ip_loggers: [],
});

// Bookmark Modal State
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);
const copiedField = ref(null);
const copiedUrlId = ref(null);

// 6 High-Level Category Filter Definitions
const categoriesList = computed(() => props.findingCategories || [
    { id: 'all', label: 'All Findings' },
    { id: 'bgp', label: 'Network & BGP' },
    { id: 'geo', label: 'Geolocation' },
    { id: 'ports', label: 'Host & Ports' },
    { id: 'threat', label: 'Threat & Blacklists' },
    { id: 'hardware', label: 'Hardware & Wireless' },
]);

// 12 Branch Definitions for Manual Tools Directory Sidebar
const branchDefinitions = [
    { id: 'geolocation', name: 'Geolocation', icon: MapPin, count: 8 },
    { id: 'host_port_discovery', name: 'Host / Port Discovery', icon: Server, count: 13 },
    { id: 'ipv4', name: 'IPv4 Network', icon: Network, count: 8 },
    { id: 'ipv6', name: 'IPv6 Network', icon: Binary, count: 1 },
    { id: 'bgp', name: 'BGP & Routing', icon: Activity, count: 4 },
    { id: 'reputation', name: 'Threat Reputation', icon: ShieldAlert, count: 3 },
    { id: 'blacklists', name: 'Blacklists', icon: AlertTriangle, count: 4 },
    { id: 'neighbor_domains', name: 'Neighbor Domains', icon: Globe, count: 4 },
    { id: 'protected_by_cloud', name: 'Cloud Services', icon: Shield, count: 2 },
    { id: 'wireless_network_info', name: 'Wireless Info', icon: RadioTower, count: 2 },
    { id: 'network_analysis_tools', name: 'Network Analysis', icon: Terminal, count: 4 },
    { id: 'ip_loggers', name: 'IP Loggers', icon: Shield, count: 3 },
];

// Flat list of all 56 framework catalog tools
const allToolsList = computed(() => {
    const list = [];
    Object.values(categorizedTools.value).forEach(branchTools => {
        if (Array.isArray(branchTools)) {
            list.push(...branchTools);
        }
    });
    return list;
});

// Category Counts for Findings
const categoryCounts = computed(() => {
    const list = showHitsOnly.value 
        ? discoveredFindings.value.filter(f => f.status === 'found') 
        : discoveredFindings.value;

    const counts = { all: list.length, bgp: 0, geo: 0, ports: 0, threat: 0, hardware: 0 };
    list.forEach(item => {
        if (item.category && counts[item.category] !== undefined) {
            counts[item.category]++;
        }
    });
    return counts;
});

// Filtered Discovered Findings List
const filteredDiscoveredFindings = computed(() => {
    let list = discoveredFindings.value;

    if (showHitsOnly.value) {
        list = list.filter(f => f.status === 'found');
    }

    if (selectedCategory.value !== 'all') {
        list = list.filter(f => f.category === selectedCategory.value);
    }

    return list;
});

// Branch Category Metadata for Bento Container Grid
const branchCategoryConfig = [
    {
        id: 'bgp',
        name: 'Network & BGP Routing Matrix',
        shortName: 'Network & BGP',
        description: 'Autonomous System mapping, BGP prefix routing, peering topology and ASN routing statistics.',
        icon: Activity,
        accentColor: 'teal',
        accentBorder: 'border-teal-500/40 hover:border-teal-500/60',
        accentBg: 'bg-teal-500/10',
        accentText: 'text-teal-400',
        badgeClass: 'bg-teal-950/70 border-teal-500/40 text-teal-300',
        stripeGradient: 'from-teal-500/40 via-teal-500/10 to-transparent',
    },
    {
        id: 'threat',
        name: 'Threat Reputation & Blacklist Intelligence',
        shortName: 'Threat & Blacklists',
        description: 'Global IP blacklists, honeypot telemetry, abuse reporting and security risk indices.',
        icon: ShieldAlert,
        accentColor: 'rose',
        accentBorder: 'border-rose-500/40 hover:border-rose-500/60',
        accentBg: 'bg-rose-500/10',
        accentText: 'text-rose-400',
        badgeClass: 'bg-rose-950/70 border-rose-500/40 text-rose-300',
        stripeGradient: 'from-rose-500/40 via-rose-500/10 to-transparent',
    },
    {
        id: 'geo',
        name: 'Geolocation & Routing Infrastructure',
        shortName: 'Geolocation',
        description: 'Physical coordinates, carrier routing, reverse DNS domain neighbors and datacenter attributes.',
        icon: MapPin,
        accentColor: 'amber',
        accentBorder: 'border-amber-500/40 hover:border-amber-500/60',
        accentBg: 'bg-amber-500/10',
        accentText: 'text-amber-400',
        badgeClass: 'bg-amber-950/70 border-amber-500/40 text-amber-300',
        stripeGradient: 'from-amber-500/40 via-amber-500/10 to-transparent',
    },
    {
        id: 'ports',
        name: 'Host & Port Service Fingerprinting',
        shortName: 'Host & Ports',
        description: 'Active service discovery, port listeners, transport layers, and protocol banners.',
        icon: Server,
        accentColor: 'cyan',
        accentBorder: 'border-cyan-500/40 hover:border-cyan-500/60',
        accentBg: 'bg-cyan-500/10',
        accentText: 'text-cyan-400',
        badgeClass: 'bg-cyan-950/70 border-cyan-500/40 text-cyan-300',
        stripeGradient: 'from-cyan-500/40 via-cyan-500/10 to-transparent',
    },
    {
        id: 'hardware',
        name: 'Hardware MAC & Wireless OSINT',
        shortName: 'Hardware & MAC',
        description: 'Layer 2 MAC hardware correlation, IEEE OUI vendor telemetry, and WiGLE wireless matrices.',
        icon: Cpu,
        accentColor: 'emerald',
        accentBorder: 'border-emerald-500/40 hover:border-emerald-500/60',
        accentBg: 'bg-emerald-500/10',
        accentText: 'text-emerald-400',
        badgeClass: 'bg-emerald-950/70 border-emerald-500/40 text-emerald-300',
        stripeGradient: 'from-emerald-500/40 via-emerald-500/10 to-transparent',
    },
];

const collapsedBranches = ref({});

const toggleBranchCollapse = (branchId) => {
    collapsedBranches.value[branchId] = !collapsedBranches.value[branchId];
};

// Grouped Discovered Findings by Branch for Bento Layout
const groupedDiscoveredFindings = computed(() => {
    let sourceList = discoveredFindings.value;
    if (showHitsOnly.value) {
        sourceList = sourceList.filter(f => f.status === 'found');
    }

    const groups = branchCategoryConfig.map(branch => {
        const findings = sourceList.filter(f => f.category === branch.id);
        const verifiedHits = findings.filter(f => f.status === 'found').length;
        return {
            ...branch,
            findings,
            totalCount: findings.length,
            verifiedHits,
            isClean: findings.length === 0 || (branch.id === 'threat' && verifiedHits === 0),
        };
    });

    const knownIds = new Set(branchCategoryConfig.map(b => b.id));
    const extraFindings = sourceList.filter(f => !f.category || !knownIds.has(f.category));
    if (extraFindings.length > 0) {
        groups.push({
            id: 'other',
            name: 'Supplementary Intelligence Vectors',
            shortName: 'Supplementary',
            description: 'Additional passive telemetry, headers, and OSINT correlation findings.',
            icon: Compass,
            accentColor: 'indigo',
            accentBorder: 'border-indigo-500/40 hover:border-indigo-500/60',
            accentBg: 'bg-indigo-500/10',
            accentText: 'text-indigo-400',
            badgeClass: 'bg-indigo-950/70 border-indigo-500/40 text-indigo-300',
            stripeGradient: 'from-indigo-500/40 via-indigo-500/10 to-transparent',
            findings: extraFindings,
            totalCount: extraFindings.length,
            verifiedHits: extraFindings.filter(f => f.status === 'found').length,
            isClean: false,
        });
    }

    if (selectedCategory.value !== 'all') {
        return groups.filter(g => g.id === selectedCategory.value);
    }

    return groups;
});

const scrollToBranch = (branchId) => {
    if (branchId === 'all') {
        selectedCategory.value = 'all';
        return;
    }
    selectedCategory.value = 'all';
    nextTick(() => {
        const el = document.getElementById(`branch-${branchId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

// Filtered Catalog Tools for Sidebar
const filteredCatalogTools = computed(() => {
    const q = toolSearchQuery.value.trim().toLowerCase();
    let tools = activeBranch.value === 'all' 
        ? allToolsList.value 
        : (categorizedTools.value[activeBranch.value] || []);

    if (q) {
        tools = tools.filter(t => 
            t.name.toLowerCase().includes(q) ||
            t.description.toLowerCase().includes(q) ||
            t.tag.toLowerCase().includes(q)
        );
    }

    return tools;
});

// Helper for finding icon / status color
const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'found':
            return 'bg-teal-950/80 text-teal-300 border border-teal-700/60 font-bold';
        case 'clean':
            return 'bg-cyan-950/70 text-cyan-300 border border-cyan-800/50';
        case 'info':
            return 'bg-slate-900 text-slate-300 border border-slate-700/60';
        default:
            return 'bg-slate-900 text-slate-400 border border-slate-800';
    }
};

// Clipboard Helpers
const copyText = (text, id = 'field') => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedField.value = id;
    setTimeout(() => {
        if (copiedField.value === id) copiedField.value = null;
    }, 2000);
};

const copyUrl = (url, id) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    copiedUrlId.value = id;
    setTimeout(() => {
        if (copiedUrlId.value === id) copiedUrlId.value = null;
    }, 2000);
};

// Start Real-Time SSE Probing Stream
const startLiveStream = (target) => {
    stopLiveStream();
    if (!target) return;

    isStreaming.value = true;
    allStreamedResults.value = [];
    streamProgress.value = {
        probed: 0,
        total: allToolsList.value.length || 56,
        found: discoveredFindings.value.filter(f => f.status === 'found').length,
        percent: 0,
        last_tool: 'Initializing Scanner...',
        last_status: '',
        duration_ms: 0,
    };

    const streamUrl = `/api/osint/ip/stream?target=${encodeURIComponent(target)}`;
    eventSource = new EventSource(streamUrl);

    eventSource.addEventListener('start', (event) => {
        try {
            const data = JSON.parse(event.data);
            streamProgress.value.total = data.total_tools || 56;
        } catch (e) {
            console.error('Failed to parse SSE start event', e);
        }
    });

    eventSource.addEventListener('result', (event) => {
        try {
            const result = JSON.parse(event.data);
            allStreamedResults.value.push(result);

            // If the streamed tool returned actionable data, add it to discoveredFindings
            const exists = discoveredFindings.value.some(f => f.id === result.tool_id);
            if (!exists) {
                discoveredFindings.value.push({
                    id: result.tool_id,
                    platform: result.platform || result.tool_name,
                    website_domain: result.website_domain || 'external',
                    url: result.url,
                    category: result.category || 'bgp',
                    category_label: result.category_label || result.branch_label,
                    status: result.status,
                    summary: result.summary,
                    data: result.data || {},
                });
            }
        } catch (e) {
            console.error('Failed to parse SSE result event', e);
        }
    });

    eventSource.addEventListener('progress', (event) => {
        try {
            const progress = JSON.parse(event.data);
            streamProgress.value.probed = progress.probed;
            streamProgress.value.total = progress.total;
            streamProgress.value.found = discoveredFindings.value.filter(f => f.status === 'found').length;
            streamProgress.value.percent = progress.percent;
            streamProgress.value.last_tool = progress.current_tool;
            streamProgress.value.last_status = progress.branch;
        } catch (e) {
            console.error('Failed to parse SSE progress event', e);
        }
    });

    eventSource.addEventListener('done', (event) => {
        try {
            const summary = JSON.parse(event.data);
            streamProgress.value.duration_ms = summary.duration_ms;
            streamProgress.value.percent = 100;
        } catch (e) {
            console.error('Failed to parse SSE done event', e);
        } finally {
            stopLiveStream();
        }
    });

    eventSource.addEventListener('error', () => {
        stopLiveStream();
    });
};

const stopLiveStream = () => {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    isStreaming.value = false;
};

// Execute Target Probe & Start SSE Stream
const executeLookup = async (targetParam = null) => {
    const target = (targetParam || targetInput.value || '').trim();
    if (!target) return;

    targetInput.value = target;
    currentTarget.value = target;
    isProbing.value = true;

    try {
        const response = await axios.post('/api/osint/ip/probe', { target });
        probeData.value = response.data;
        classification.value = response.data.classification || null;
        discoveredFindings.value = response.data.discovered_findings ? [...response.data.discovered_findings] : [];

        // Update URL query state
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('target', target);
        window.history.replaceState({}, '', `${window.location.pathname}?${currentParams.toString()}`);

        // Start SSE stream
        startLiveStream(target);
    } catch (err) {
        console.error('Error probing IP target:', err);
    } finally {
        isProbing.value = false;
    }
};

const setExampleTarget = (val) => {
    targetInput.value = val;
    executeLookup(val);
};

const pivotToMac = (mac) => {
    if (!mac) return;
    targetInput.value = mac;
    executeLookup(mac);
};

// Bookmark Modal
const openBookmarkModal = (item) => {
    openBookmark({
        title: `OSINT [${item.category_label || 'Network'}]: ${item.platform || item.name} (${currentTarget.value})`,
        url: item.url,
        notes: item.summary || item.description || '',
        severity: item.status === 'found' ? 'high' : 'medium',
        investigation_id: props.investigations?.length ? props.investigations[0].id : null,
    });
};

// Export Intelligence Report
const exportReport = (format) => {
    const target = currentTarget.value || 'target';
    const geo = probeData.value?.telemetry?.geoip;
    const mac = probeData.value?.telemetry?.mac;
    showExportMenu.value = false;

    if (format === 'json') {
        const payload = {
            target: currentTarget.value,
            classification: classification.value,
            telemetry: probeData.value?.telemetry || {},
            discovered_findings: discoveredFindings.value,
            exported_at: new Date().toISOString(),
        };
        downloadJson(payload, `osint-network-${target.replace(/[^a-z0-9]/gi, '_')}.json`);
    } else {
        let md = `# OSINT Network Intelligence Report: ${target}\n\n`;
        md += `- **Classification:** \`${classification.value?.type?.toUpperCase() || 'UNKNOWN'}\`\n`;
        md += `- **Clean Target:** \`${classification.value?.clean_target || target}\`\n`;
        md += `- **Resolved IP:** \`${classification.value?.resolved_ip || 'N/A'}\`\n`;
        md += `- **Export Date:** ${new Date().toUTCString()}\n\n`;

        if (geo && geo.country) {
            md += `## Geolocation & Network Telemetry\n`;
            md += `- **Location:** ${geo.city}, ${geo.region}, ${geo.country} (${geo.country_code})\n`;
            md += `- **Coordinates:** ${geo.lat}, ${geo.lon}\n`;
            md += `- **Autonomous System:** ${geo.as}\n`;
            md += `- **ISP:** ${geo.isp}\n`;
            md += `- **Reverse PTR:** ${geo.ptr || 'None'}\n\n`;
        }

        if (mac && mac.vendor) {
            md += `## MAC Hardware Telemetry\n`;
            md += `- **Manufacturer / OUI:** ${mac.vendor}\n`;
            md += `- **Prefix:** \`${mac.oui_prefix}\`\n`;
            md += `- **Frame Mode:** ${mac.transmission}\n\n`;
        }

        md += `## Discovered Intelligence Findings (${discoveredFindings.value.length})\n\n`;
        md += `| Service / Website | Category | Status | Summary Finding | Direct URL |\n`;
        md += `| :--- | :--- | :--- | :--- | :--- |\n`;
        discoveredFindings.value.forEach(f => {
            md += `| ${f.platform} | ${f.category_label || f.category} | \`${f.status.toUpperCase()}\` | ${f.summary} | ${f.url} |\n`;
        });
        md += `\n---\n*Generated by Darkdump OSINT Investigation Workstation*\n`;

        downloadMarkdown(md, `osint-network-${target.replace(/[^a-z0-9]/gi, '_')}.md`);
    }
};

onMounted(() => {
    if (props.initialTarget) {
        startLiveStream(props.initialTarget);
    }
});

onBeforeUnmount(() => {
    stopLiveStream();
});
</script>

<template>
    <div class="space-y-6 w-full">
            <!-- Top Navigation Breadcrumb & Actions -->
            <div class="border-b border-slate-800/80 pb-4">
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500 mb-2">
                    <Link href="/osint" class="hover:text-teal-400 transition flex items-center gap-1">
                        <Compass class="w-3.5 h-3.5" />
                        <span>OSINT Framework</span>
                    </Link>
                    <span>/</span>
                    <span class="text-teal-400/90 font-semibold">IP &amp; MAC Address</span>
                    <span class="px-2 py-0.5 text-[10px] bg-teal-500/10 border border-teal-500/20 text-teal-400 rounded-full font-bold uppercase tracking-wider">
                        Active Telemetry
                    </span>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-serif font-black tracking-tight text-white uppercase flex items-center gap-2.5">
                            <Network class="w-6 h-6 text-teal-400" />
                            <span>IP &amp; MAC Address Intelligence</span>
                        </h1>
                        <p class="text-slate-400 text-xs font-mono mt-0.5">
                            Unified target telemetry, Geolocation coordinates, BGP/ASN routing, Threat scoring, and IEEE MAC OUI forensics.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5 font-mono text-xs">
                        <div v-if="currentTarget" class="px-2.5 py-1 rounded-lg bg-slate-900/80 border border-slate-800 flex items-center gap-2">
                            <span class="text-slate-500">Target:</span>
                            <span class="text-slate-200 font-bold">{{ currentTarget }}</span>
                        </div>

                        <div v-if="currentTarget" class="px-2.5 py-1 rounded-lg bg-teal-950/40 border border-teal-800/50 text-teal-300 font-bold flex items-center gap-1.5">
                            <ShieldCheck class="w-3.5 h-3.5 text-teal-400" />
                            <span>{{ discoveredFindings.length }} Findings</span>
                        </div>

                        <!-- Export Dropdown -->
                        <div v-if="currentTarget" class="relative">
                            <button
                                type="button"
                                @click="showExportMenu = !showExportMenu"
                                class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition text-xs"
                            >
                                <Download class="w-3.5 h-3.5 text-teal-400" />
                                <span>Export</span>
                                <ChevronDown class="w-3 h-3 text-slate-500" />
                            </button>

                            <div
                                v-if="showExportMenu"
                                class="absolute right-0 mt-2 w-48 bg-slate-900/95 border border-slate-800 rounded-xl shadow-2xl py-1 z-50 font-mono text-xs backdrop-blur-md"
                            >
                                <button
                                    type="button"
                                    @click="exportReport('markdown')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-2 transition"
                                >
                                    <FileText class="w-3.5 h-3.5 text-teal-400" />
                                    <span>Export Markdown (.md)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="exportReport('json')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-2 transition"
                                >
                                    <FileText class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>Export JSON (.json)</span>
                                </button>
                            </div>
                        </div>

                        <Link
                            href="/osint"
                            class="px-3 py-1 rounded bg-slate-900/90 border border-slate-800 hover:border-teal-500/40 text-slate-400 hover:text-teal-300 transition-colors"
                        >
                            &larr; Hub
                        </Link>
                    </div>
                </div>

                <!-- Search Input Console -->
                <div class="mt-4 p-3 rounded-xl bg-slate-900/70 border border-slate-800 shadow-xl">
                    <form @submit.prevent="executeLookup()" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <Search class="w-4 h-4 text-teal-400" />
                            </div>
                            <input
                                v-model="targetInput"
                                type="text"
                                placeholder="Enter IPv4 (8.8.8.8), IPv6, domain hostname (github.com), or MAC (00:1A:2B:3C:4D:5E)..."
                                class="w-full pl-10 pr-24 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-teal-500/60 focus:ring-1 focus:ring-teal-500/30 text-xs font-mono transition"
                                required
                            />
                            <!-- Type Pill -->
                            <div v-if="classification && classification.is_valid" class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-teal-500/10 border border-teal-500/30 text-teal-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-pulse"></span>
                                    {{ classification.type }}
                                </span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <button
                                type="submit"
                                :disabled="isProbing || !targetInput.trim()"
                                class="px-5 py-2 rounded-lg bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-slate-950 font-mono text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 shadow transition-all shrink-0"
                            >
                                <Loader2 v-if="isProbing" class="w-3.5 h-3.5 animate-spin" />
                                <Activity v-else class="w-3.5 h-3.5" />
                                <span>Deep Recon</span>
                            </button>

                            <button
                                v-if="isStreaming"
                                type="button"
                                @click="stopLiveStream()"
                                class="px-3 py-2 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 font-mono text-xs rounded-lg transition-colors flex items-center gap-1.5 shrink-0"
                                title="Stop Live Stream"
                            >
                                <StopCircle class="w-3.5 h-3.5" />
                                <span>Halt</span>
                            </button>
                        </div>
                    </form>

                    <!-- Example Targets & Auto-Detection -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 text-[11px] font-mono">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-slate-500">Quick Targets:</span>
                            <button
                                type="button"
                                @click="setExampleTarget('8.8.8.8')"
                                class="px-2 py-0.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-teal-400 rounded transition-colors"
                            >
                                8.8.8.8 (Google DNS)
                            </button>
                            <button
                                type="button"
                                @click="setExampleTarget('1.1.1.1')"
                                class="px-2 py-0.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-cyan-400 rounded transition-colors"
                            >
                                1.1.1.1 (Cloudflare)
                            </button>
                            <button
                                type="button"
                                @click="setExampleTarget('github.com')"
                                class="px-2 py-0.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-indigo-400 rounded transition-colors"
                            >
                                github.com (Host)
                            </button>
                            <button
                                type="button"
                                @click="setExampleTarget('00:1A:2B:3C:4D:5E')"
                                class="px-2 py-0.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-fuchsia-400 rounded transition-colors"
                            >
                                00:1A:2B:3C:4D:5E (MAC)
                            </button>
                        </div>

                        <div v-if="classification" class="text-slate-400 truncate max-w-md">
                            <span class="text-slate-500">Auto-Detected:</span>
                            <span class="text-slate-300 font-semibold ml-1">{{ classification.notes }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Executive Telemetry Header (When Target Evaluated) -->
            <div v-if="probeData && probeData.telemetry" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5 font-mono text-xs">
                <!-- Card 1: Target Classification -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-2">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase tracking-wider text-slate-500">Target Type</span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold bg-slate-800 text-teal-400">
                            {{ classification?.type || 'UNKNOWN' }}
                        </span>
                    </div>

                    <div class="text-sm font-bold text-white truncate font-mono flex items-center justify-between">
                        <span class="truncate" :title="classification?.clean_target || currentTarget">
                            {{ classification?.clean_target || currentTarget }}
                        </span>
                        <button
                            type="button"
                            @click="copyText(classification?.clean_target || currentTarget, 'target_addr')"
                            class="text-slate-500 hover:text-teal-400 transition-colors"
                            title="Copy Target Address"
                        >
                            <Check v-if="copiedField === 'target_addr'" class="w-3.5 h-3.5 text-emerald-400" />
                            <Copy v-else class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                        <span>DNS IP:</span>
                        <span class="text-teal-300 truncate max-w-[180px]">{{ classification?.resolved_ip || 'Direct' }}</span>
                    </div>
                </div>

                <!-- Card 2: Geolocation Coordinates -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-2">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase tracking-wider text-slate-500">Geolocation</span>
                        <MapPin class="w-3.5 h-3.5 text-teal-400" />
                    </div>

                    <div class="text-sm font-bold text-white truncate">
                        {{ probeData.telemetry.geoip?.country || 'Unknown Country' }}
                        <span v-if="probeData.telemetry.geoip?.country_code" class="text-xs text-slate-400 font-normal">({{ probeData.telemetry.geoip.country_code }})</span>
                    </div>

                    <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                        <span class="truncate">{{ probeData.telemetry.geoip?.city || 'City' }}, {{ probeData.telemetry.geoip?.region || 'Region' }}</span>
                        <a
                            v-if="probeData.telemetry.geoip?.lat"
                            :href="`https://www.google.com/maps?q=${probeData.telemetry.geoip.lat},${probeData.telemetry.geoip.lon}`"
                            target="_blank"
                            rel="noreferrer"
                            class="text-teal-400 hover:text-teal-300 shrink-0 ml-1"
                            title="Google Maps"
                        >
                            <ExternalLink class="w-3 h-3" />
                        </a>
                    </div>
                </div>

                <!-- Card 3: Network & BGP Routing -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-2">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase tracking-wider text-slate-500">Network / BGP</span>
                        <Network class="w-3.5 h-3.5 text-cyan-400" />
                    </div>

                    <div class="text-sm font-bold text-white truncate" :title="probeData.telemetry.geoip?.as">
                        {{ probeData.telemetry.geoip?.as || 'Unknown AS' }}
                    </div>

                    <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                        <span class="truncate">ISP: {{ probeData.telemetry.geoip?.isp || 'Private' }}</span>
                        <span v-if="probeData.telemetry.geoip?.ptr" class="text-teal-300 font-semibold truncate max-w-[200px]" :title="probeData.telemetry.geoip.ptr">
                            PTR: {{ probeData.telemetry.geoip.ptr }}
                        </span>
                    </div>
                </div>

                <!-- Card 4: Threat / Associated Hardware MAC -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-2">
                    <!-- Scenario A: Target itself is a MAC address -->
                    <template v-if="classification?.type === 'mac'">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-[10px] uppercase tracking-wider text-slate-500">MAC Hardware OUI</span>
                            <Cpu class="w-3.5 h-3.5 text-fuchsia-400" />
                        </div>
                        <div class="text-sm font-bold text-white truncate" :title="probeData.telemetry.mac?.vendor">
                            {{ probeData.telemetry.mac?.vendor || 'Unknown Vendor' }}
                        </div>
                        <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                            <span>OUI: {{ probeData.telemetry.mac?.oui_prefix }}</span>
                            <span class="text-slate-300">{{ probeData.telemetry.mac?.transmission }}</span>
                        </div>
                    </template>

                    <!-- Scenario B: Associated MAC Discovered for IP Target -->
                    <template v-else-if="probeData?.telemetry?.associated_mac?.resolved">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-[10px] uppercase tracking-wider text-teal-400 font-bold flex items-center gap-1">
                                <Cpu class="w-3 h-3 text-teal-400" />
                                Associated MAC
                            </span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold uppercase tracking-wider bg-teal-950/80 text-teal-300 border border-teal-500/40">
                                {{ probeData.telemetry.associated_mac.method_label }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-1">
                            <div class="text-sm font-bold text-white truncate" :title="probeData.telemetry.associated_mac.vendor?.vendor">
                                {{ probeData.telemetry.associated_mac.vendor?.vendor || 'Discovered Physical Device' }}
                            </div>
                            <button
                                type="button"
                                @click="pivotToMac(probeData.telemetry.associated_mac.mac)"
                                class="px-1.5 py-0.5 text-[9px] rounded bg-teal-950/80 hover:bg-teal-900 border border-teal-500/40 text-teal-300 hover:text-white transition font-mono shrink-0 flex items-center gap-0.5"
                                title="Pivot investigation to this MAC address"
                            >
                                <span>Pivot OUI</span>
                                <ArrowRight class="w-2.5 h-2.5" />
                            </button>
                        </div>
                        <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                            <span class="text-teal-300 font-bold select-all">{{ probeData.telemetry.associated_mac.mac }}</span>
                            <span class="text-slate-400">{{ probeData.telemetry.associated_mac.vendor?.transmission || 'Unicast' }}</span>
                        </div>
                    </template>

                    <!-- Scenario C: Layer 3 WAN Boundary (Terminated at ISP Gateway) -->
                    <template v-else-if="probeData?.telemetry?.associated_mac?.method === 'l3_wan_boundary'">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-[10px] uppercase tracking-wider text-slate-500">Layer 2 Attribution</span>
                            <Network class="w-3.5 h-3.5 text-amber-400" />
                        </div>
                        <div class="text-xs font-bold text-amber-400 flex items-center gap-1.5" :title="probeData.telemetry.associated_mac.explanation">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                            <span class="truncate">L3 WAN BOUNDARY: ENCAPSULATED</span>
                        </div>
                        <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[10px] text-slate-500" :title="probeData.telemetry.associated_mac.explanation">
                            <span class="truncate">Stripped at Gateway</span>
                            <span class="text-slate-400 font-mono">Hop-by-hop</span>
                        </div>
                    </template>

                    <!-- Scenario D: Threat & Hygiene fallback -->
                    <template v-else>
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-[10px] uppercase tracking-wider text-slate-500">Threat &amp; Hygiene</span>
                            <ShieldCheck class="w-3.5 h-3.5 text-emerald-400" />
                        </div>
                        <div class="text-sm font-bold text-emerald-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>{{ probeData.telemetry.threat_summary || 'Clean Host Profile' }}</span>
                        </div>
                        <div class="border-t border-slate-800/80 pt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Tor Relay: Clean</span>
                            <span class="text-emerald-400 font-bold">0 Listed</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- DUAL-COLUMN RECONNAISSANCE WORKSPACE (Username Recon Pattern)             -->
            <!-- ========================================================================= -->
            <div class="flex flex-col lg:flex-row items-start gap-6">

                <!-- ===================================================================== -->
                <!-- LEFT COLUMN: MANUAL FRAMEWORK CATALOG (Sidebar Panel ~340px)          -->
                <!-- ===================================================================== -->
                <aside class="w-full lg:w-80 xl:w-96 shrink-0 space-y-4 lg:sticky lg:top-4">
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 shadow-xl overflow-hidden">
                        <!-- Panel Header -->
                        <div class="p-3.5 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-teal-950/50 border border-teal-800/50 flex items-center justify-center text-teal-400 shrink-0">
                                    <Wrench class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wide">
                                        Manual Framework Tools
                                    </h2>
                                    <p class="text-[10px] font-mono text-slate-500">
                                        Taxonomy catalog for manual correlation
                                    </p>
                                </div>
                            </div>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-teal-300 border border-slate-700/60">
                                {{ allToolsList.length || 56 }} TOOLS
                            </span>
                        </div>

                        <!-- Search Filter within Directory Tools -->
                        <div class="p-3 border-b border-slate-800/80 space-y-2">
                            <div class="relative">
                                <input
                                    v-model="toolSearchQuery"
                                    type="text"
                                    placeholder="Filter 56 catalog tools..."
                                    class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 text-xs font-mono focus:outline-none focus:border-teal-500/50"
                                />
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-500">
                                    <Filter class="w-3 h-3" />
                                </div>
                            </div>

                            <!-- Branch Filter Selector -->
                            <select
                                v-model="activeBranch"
                                class="w-full bg-slate-950 border border-slate-800 rounded-lg p-1.5 text-slate-300 font-mono text-[11px] focus:outline-none focus:border-teal-500/50"
                            >
                                <option value="all">All 12 Branches ({{ allToolsList.length }})</option>
                                <option v-for="b in branchDefinitions" :key="b.id" :value="b.id">
                                    {{ b.name }} ({{ categorizedTools[b.id]?.length || b.count }})
                                </option>
                            </select>
                        </div>

                        <!-- Micro-Rows Tools List -->
                        <div class="p-3 space-y-2 max-h-[calc(100vh-320px)] overflow-y-auto no-scrollbar">
                            <div
                                v-for="tool in filteredCatalogTools"
                                :key="tool.id"
                                class="p-2.5 rounded-lg bg-slate-950/70 border border-slate-800/90 border-l-2 border-l-teal-600/70 hover:border-l-teal-400 hover:border-slate-700 hover:bg-slate-950 transition-all flex flex-col justify-between group shadow-sm font-mono"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-bold text-xs text-slate-200 group-hover:text-teal-300 transition-colors truncate">
                                                    {{ tool.name }}
                                                </span>
                                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-900 border border-slate-800 text-slate-400 uppercase">
                                                    {{ tool.tag }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1 shrink-0">
                                            <button
                                                type="button"
                                                @click="openBookmarkModal(tool)"
                                                class="p-1 rounded bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 transition"
                                                title="Bookmark to Case"
                                            >
                                                <FolderPlus class="w-3 h-3" />
                                            </button>
                                            <a
                                                :href="tool.url"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-teal-300 hover:text-teal-200 text-[10px] flex items-center gap-1 transition"
                                                title="Open Tool"
                                            >
                                                <span>Launch</span>
                                                <ExternalLink class="w-2.5 h-2.5" />
                                            </a>
                                        </div>
                                    </div>

                                    <p class="text-[10px] text-slate-400 font-sans leading-snug line-clamp-2">
                                        {{ tool.description }}
                                    </p>
                                </div>

                                <div class="mt-2 pt-1.5 border-t border-slate-900 flex items-center justify-between text-[10px]">
                                    <div class="text-[9px] text-slate-500 truncate max-w-[180px]">
                                        {{ tool.url }}
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyUrl(tool.url, tool.id)"
                                        class="px-1.5 py-0.5 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 flex items-center gap-1 text-[9px]"
                                    >
                                        <Check v-if="copiedUrlId === tool.id" class="w-2.5 h-2.5 text-teal-400" />
                                        <Copy v-else class="w-2.5 h-2.5" />
                                        <span>{{ copiedUrlId === tool.id ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredCatalogTools.length === 0" class="p-6 text-center text-slate-500 text-xs">
                                No tools matched filter.
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- ===================================================================== -->
                <!-- RIGHT COLUMN: DISCOVERED INTELLIGENCE FINDINGS (Main Feed)            -->
                <!-- ===================================================================== -->
                <main class="flex-1 min-w-0 space-y-5">
                    <!-- Awaiting Target Empty State -->
                    <div 
                        v-if="!currentTarget && !isProbing" 
                        class="p-12 rounded-xl bg-slate-900/60 border border-slate-800/80 text-center space-y-3"
                    >
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500 mx-auto">
                            <Compass class="w-7 h-7 text-teal-400/80" />
                        </div>
                        <h3 class="text-base font-serif font-bold text-slate-200 uppercase tracking-tight">
                            Awaiting Target IP or MAC Address
                        </h3>
                        <p class="text-xs font-mono text-slate-400 max-w-md mx-auto leading-relaxed">
                            Enter an IP address, domain hostname, or MAC hardware address in the console above to initiate real-time multi-branch reconnaissance across 56 OSINT tools.
                        </p>
                    </div>

                    <!-- Loading State -->
                    <div 
                        v-if="isProbing && !discoveredFindings.length && !isStreaming" 
                        class="p-12 rounded-xl bg-slate-900/70 border border-slate-800 text-center space-y-4 font-mono"
                    >
                        <Loader2 class="w-8 h-8 text-teal-400 animate-spin mx-auto" />
                        <div class="text-sm text-slate-200 font-bold uppercase tracking-wide">
                            Probing Global Network Infrastructure...
                        </div>
                        <div class="text-xs text-slate-500">
                            Querying Geolocation coordinates, BGP origin routing, Tor relays, and IEEE MAC registries
                        </div>
                    </div>

                    <!-- 1. LIVE DISCOVERED INTELLIGENCE FINDINGS SECTION -->
                    <section
                        v-if="currentTarget && (isStreaming || discoveredFindings.length > 0)"
                        class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4"
                    >
                        <!-- Section Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-teal-950/50 border border-teal-800/50 flex items-center justify-center text-teal-400 shrink-0">
                                    <Activity class="w-4 h-4" :class="{ 'animate-pulse text-teal-300': isStreaming }" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wider">
                                            Discovered Intelligence Findings
                                        </h2>
                                        <span v-if="isStreaming" class="flex h-2 w-2 relative" title="Live reconnaissance stream active">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-teal-500"></span>
                                        </span>
                                        <span v-else class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800/80 text-slate-400 border border-slate-700/50">
                                            Scan Complete
                                        </span>
                                    </div>
                                    <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                        Confirmed findings and intelligence extracted from verified endpoints across the Internet
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-auto font-mono text-xs">
                                <div class="px-2.5 py-1 rounded-lg bg-teal-950/40 border border-teal-800/50 text-teal-300 font-bold flex items-center gap-1.5">
                                    <CheckCircle2 class="w-3.5 h-3.5 text-teal-400" />
                                    <span>{{ discoveredFindings.length }} Findings</span>
                                </div>

                                <button
                                    v-if="isStreaming"
                                    type="button"
                                    @click="stopLiveStream()"
                                    class="px-2.5 py-1 rounded-lg bg-rose-950/40 hover:bg-rose-900/50 border border-rose-800/60 text-rose-300 flex items-center gap-1.5 transition text-xs"
                                >
                                    <StopCircle class="w-3.5 h-3.5" />
                                    <span>Stop</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="startLiveStream(currentTarget)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition text-xs"
                                >
                                    <RefreshCw class="w-3.5 h-3.5" />
                                    <span>Re-scan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Live Telemetry Progress Bar -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] font-mono text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-300 font-semibold">
                                        {{ streamProgress.percent === 100 ? 'Recon Scan Complete' : `Probing ${streamProgress.total} tools...` }}
                                    </span>
                                    <span class="font-bold" :class="streamProgress.percent === 100 ? 'text-emerald-400' : 'text-teal-400'">
                                        [{{ streamProgress.probed }}/{{ streamProgress.total }}]
                                    </span>
                                    <span class="text-slate-500">—</span>
                                    <span class="text-slate-400">{{ discoveredFindings.length }} Findings Logged</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="isStreaming && streamProgress.last_tool" class="text-slate-500 truncate max-w-[180px]">
                                        Checking: {{ streamProgress.last_tool }}
                                    </span>
                                    <span
                                        class="font-bold px-1.5 py-0.5 rounded text-[10px] transition-all duration-300"
                                        :class="streamProgress.percent === 100
                                            ? 'bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 shadow-[0_0_8px_rgba(52,211,153,0.3)]'
                                            : 'text-teal-400'"
                                    >
                                        {{ streamProgress.percent }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Outer Track Container with Glow & Border Glow -->
                            <div
                                class="h-2 w-full bg-slate-950 rounded-full overflow-hidden border relative transition-all duration-500"
                                :class="streamProgress.percent === 100
                                    ? 'animate-complete-glow'
                                    : (isStreaming ? 'border-teal-500/40 shadow-[0_0_10px_rgba(45,212,191,0.2)]' : 'border-slate-800')"
                            >
                                <!-- Inner Progress Fill with Dynamic Gradient, Tactical Stripes & Glistening Effects -->
                                <div
                                    class="h-full rounded-full relative transition-all duration-300 overflow-hidden"
                                    :class="streamProgress.percent === 100
                                        ? 'animate-complete-flow'
                                        : 'bg-gradient-to-r from-teal-500 via-cyan-400 to-indigo-500'"
                                    :style="{ width: `${streamProgress.percent}%` }"
                                >
                                    <!-- 1. Active Tactical Moving Stripes (While Loading) -->
                                    <div
                                        v-if="isStreaming"
                                        class="absolute inset-0 animate-tactical-stripes opacity-40"
                                    ></div>

                                    <!-- 2. Traveling Laser Highlight Beam (While Loading) -->
                                    <div
                                        v-if="isStreaming"
                                        class="absolute inset-0 w-24 bg-gradient-to-r from-transparent via-white/50 to-transparent animate-scan-laser"
                                    ></div>

                                    <!-- 3. Continuous Micro-Drift Tactical Pattern (When 100% Finished) -->
                                    <div
                                        v-if="streamProgress.percent === 100"
                                        class="absolute inset-0 animate-complete-drift pointer-events-none"
                                    ></div>

                                    <!-- 4. Glistening Crystalline Sheen Sweep from Left to Right (When 100% Finished) -->
                                    <div
                                        v-if="streamProgress.percent === 100"
                                        class="absolute inset-0 w-full animate-glisten-sweep pointer-events-none"
                                    ></div>
                                </div>

                                <!-- 5. Pulsing Leading-Edge Scanner Tip (While Loading) -->
                                <div
                                    v-if="isStreaming && streamProgress.percent > 0 && streamProgress.percent < 100"
                                    class="absolute top-0 bottom-0 w-2.5 bg-cyan-200 rounded-full animate-pulse-tip pointer-events-none -ml-1.5"
                                    :style="{ left: `${streamProgress.percent}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- 6 Category Filter Tabs & Verified Hits Toggle -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-800/60">
                            <!-- 6 Category Pills -->
                            <div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                                <button
                                    v-for="cat in categoriesList"
                                    :key="cat.id"
                                    type="button"
                                    @click="scrollToBranch(cat.id)"
                                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1.5 border cursor-pointer"
                                    :class="selectedCategory === cat.id
                                        ? 'bg-teal-950/60 border-teal-500/50 text-teal-300 font-bold shadow-sm'
                                        : 'bg-slate-950/60 hover:bg-slate-900 border-slate-800/80 text-slate-400 hover:text-slate-200'"
                                    :title="cat.id === 'all' ? 'View all branches' : `Glide down to ${cat.label}`"
                                >
                                    <span>{{ cat.label }}</span>
                                    <span class="px-1 py-0.2 rounded text-[10px]" :class="selectedCategory === cat.id ? 'bg-teal-800/40 text-teal-200' : 'bg-slate-900 text-slate-500'">
                                        {{ categoryCounts[cat.id] || 0 }}
                                    </span>
                                </button>
                            </div>

                            <!-- Verified Hits Only Checkbox -->
                            <label class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="showHitsOnly"
                                    class="rounded bg-slate-950 border-slate-800 text-teal-500 focus:ring-teal-500/30 w-3.5 h-3.5"
                                />
                                <span>Show verified hits only</span>
                            </label>
                        </div>
                    </section>

                    <!-- ============================================================= -->
                    <!-- BENTO BRANCH FINDINGS CONTAINERS (Separated Window per Branch)-->
                    <!-- ============================================================= -->
                    <template v-if="currentTarget && (isStreaming || discoveredFindings.length > 0)">
                        <template v-if="discoveredFindings.length > 0">
                            <!-- Loop over each Branch Bento Box Container as a Separated Window -->
                            <div
                                v-for="branch in groupedDiscoveredFindings"
                                :key="branch.id"
                                :id="`branch-${branch.id}`"
                                class="rounded-2xl bg-slate-900/40 border border-slate-800/90 shadow-xl p-4 sm:p-5 space-y-4 backdrop-blur-sm relative overflow-hidden transition-all scroll-mt-20 group/bento"
                                :class="branch.accentBorder"
                            >
                                <!-- Top Accent Indicator Gradient Stripe -->
                                <div 
                                    class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r"
                                    :class="branch.stripeGradient"
                                ></div>

                                <!-- Bento Container Header Bar -->
                                <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-800/70">
                                    <div class="flex items-center space-x-3 min-w-0">
                                        <div 
                                            class="w-8 h-8 rounded-xl border flex items-center justify-center shrink-0 shadow-sm"
                                            :class="[branch.accentBg, branch.accentBorder]"
                                        >
                                            <component :is="branch.icon" class="w-4 h-4" :class="branch.accentText" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="font-mono font-bold text-xs uppercase tracking-wider text-slate-100">
                                                    {{ branch.name }}
                                                </h3>
                                                <span 
                                                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider border shadow-sm"
                                                    :class="branch.badgeClass"
                                                >
                                                    {{ branch.totalCount }} {{ branch.totalCount === 1 ? 'FINDING' : 'FINDINGS' }}
                                                </span>
                                                <span 
                                                    v-if="branch.verifiedHits > 0"
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold uppercase tracking-wider bg-rose-950/80 border border-rose-500/40 text-rose-300"
                                                >
                                                    {{ branch.verifiedHits }} VERIFIED HITS
                                                </span>
                                            </div>
                                            <p class="text-[10px] font-mono text-slate-400 truncate mt-0.5">
                                                {{ branch.description }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Collapse / Expand Toggle Button -->
                                    <button 
                                        type="button"
                                        @click="toggleBranchCollapse(branch.id)"
                                        class="px-2 py-1 rounded-lg bg-slate-950/80 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 transition text-[11px] shrink-0 flex items-center gap-1 font-mono"
                                        :title="collapsedBranches[branch.id] ? 'Expand Branch' : 'Collapse Branch'"
                                    >
                                        <span class="hidden sm:inline">{{ collapsedBranches[branch.id] ? 'Expand' : 'Collapse' }}</span>
                                        <ChevronDown 
                                            class="w-3.5 h-3.5 transition-transform duration-200"
                                            :class="collapsedBranches[branch.id] ? '-rotate-90' : 'rotate-0'"
                                        />
                                    </button>
                                </div>

                                <!-- Bento Container Body (Collapsible) -->
                                <div v-show="!collapsedBranches[branch.id]">
                                    <!-- Inner Bento Grid Cells (When findings exist) -->
                                    <div v-if="branch.findings.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5">
                                        <div
                                            v-for="finding in branch.findings"
                                            :key="finding.id || finding.url"
                                            class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800/90 hover:border-teal-500/40 transition-all flex flex-col justify-between group shadow-sm font-mono"
                                        >
                                            <div class="space-y-2">
                                                <!-- Card Header: Platform Name & Badges -->
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <span class="font-bold text-xs text-white group-hover:text-teal-300 transition-colors">
                                                                {{ finding.platform }}
                                                            </span>
                                                        </div>
                                                        <!-- Website Domain -->
                                                        <div class="text-[10px] text-teal-400/90 flex items-center gap-1 mt-0.5">
                                                            <Globe class="w-3 h-3 text-slate-500 shrink-0" />
                                                            <span class="truncate">{{ finding.website_domain }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-slate-900 text-slate-400 border border-slate-800">
                                                            {{ finding.category_label || finding.category }}
                                                        </span>
                                                        <span
                                                            class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                                                            :class="getStatusBadgeClass(finding.status)"
                                                        >
                                                            {{ finding.status }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Brief Summary Finding -->
                                                <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 text-[11px] text-slate-300 leading-relaxed">
                                                    {{ finding.summary }}
                                                </div>

                                                <!-- Direct URL Link Snippet -->
                                                <div v-if="finding.url" class="text-[10px] text-slate-500 truncate flex items-center gap-1">
                                                    <span class="text-slate-600">Link:</span>
                                                    <span class="truncate select-all text-slate-400" :title="finding.url">
                                                        {{ finding.url }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Card Actions -->
                                            <div class="flex items-center justify-between gap-1 pt-2.5 mt-2 border-t border-slate-900 text-[10px]">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <button
                                                        v-if="finding.data?.mac"
                                                        type="button"
                                                        @click="pivotToMac(finding.data.mac)"
                                                        class="px-2 py-1 rounded bg-teal-950/80 hover:bg-teal-900 border border-teal-500/40 text-teal-300 hover:text-white flex items-center gap-1 transition"
                                                        title="Pivot OUI Forensics to this MAC"
                                                    >
                                                        <Cpu class="w-2.5 h-2.5" />
                                                        <span>Pivot MAC</span>
                                                    </button>

                                                    <a
                                                        v-if="finding.data?.mac"
                                                        :href="'https://wigle.net/search?netid=' + encodeURIComponent(finding.data.mac)"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-cyan-300 flex items-center gap-1 transition"
                                                        title="Query WiGLE Wireless BSSID Database"
                                                    >
                                                        <Wifi class="w-2.5 h-2.5" />
                                                        <span>WiGLE</span>
                                                    </a>

                                                    <a
                                                        v-if="finding.url"
                                                        :href="finding.url"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="px-2.5 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-1 transition"
                                                        title="Open Website Finding"
                                                    >
                                                        <span>Open Site</span>
                                                        <ExternalLink class="w-2.5 h-2.5" />
                                                    </a>

                                                    <button
                                                        v-if="finding.url"
                                                        type="button"
                                                        @click="copyUrl(finding.url, finding.id)"
                                                        class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 flex items-center gap-1 transition"
                                                        title="Copy Finding URL"
                                                    >
                                                        <Check v-if="copiedUrlId === finding.id" class="w-2.5 h-2.5 text-teal-400" />
                                                        <Copy v-else class="w-2.5 h-2.5" />
                                                        <span>{{ copiedUrlId === finding.id ? 'Copied' : 'Copy' }}</span>
                                                    </button>
                                                </div>

                                                <button
                                                    type="button"
                                                    @click="openBookmarkModal(finding)"
                                                    class="px-2.5 py-1 rounded bg-teal-950/30 hover:bg-teal-900/40 border border-teal-800/40 hover:border-teal-700/60 text-teal-300 flex items-center gap-1 transition"
                                                    title="Save to Case Dossier"
                                                >
                                                    <FolderPlus class="w-3 h-3" />
                                                    <span>Dossier</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Clean Verification State Card (0 Findings or All Checks Clear) -->
                                    <div 
                                        v-else
                                        class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs font-mono"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                                                <CheckCircle2 class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="text-white font-bold text-[11px] uppercase tracking-wide flex items-center gap-2">
                                                    <span>All Checks Clear — Zero Detections</span>
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                                </div>
                                                <div class="text-[10px] text-slate-400 mt-0.5">
                                                    No anomalies, malicious indicators, or blacklist listings flagged in this intelligence domain.
                                                </div>
                                            </div>
                                        </div>
                                        <span class="text-[9px] px-2 py-0.5 rounded bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 font-bold uppercase shrink-0">
                                            STATUS: VERIFIED CLEAN
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Empty Findings State (Before probe starts or zero total findings) -->
                        <div v-else class="p-8 text-center text-slate-500 font-mono text-xs rounded-2xl bg-slate-900/60 border border-slate-800/80 shadow-xl">
                            No findings logged yet. Enter an IP or hostname above to initiate automated multi-branch reconnaissance.
                        </div>
                    </template>
                </main>
            </div>

            <!-- Case Dossier Bookmarking Modal -->
            <EvidenceBookmarkModal
                v-model="showBookmarkModal"
                :target="bookmarkTarget"
                :investigations="investigations"
                source="osint_ip_framework"
            />
        </div>
</template>

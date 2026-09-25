<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    UserCheck, 
    Search, 
    ExternalLink, 
    Copy, 
    Check, 
    FolderPlus, 
    FolderGit2, 
    Globe, 
    Terminal, 
    ShieldAlert, 
    ShieldCheck, 
    Loader2, 
    RefreshCw, 
    ArrowRight, 
    Layers, 
    X,
    Filter,
    Compass,
    Key, 
    Mail, 
    ShoppingBag, 
    Download, 
    FileText, 
    ChevronDown, 
    User,
    Activity,
    CheckCircle2,
    StopCircle,
    Gamepad2,
    Share2,
    Music,
} from 'lucide-vue-next';

import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';
import { useClipboard } from '@/Utils/clipboard';
import { downloadJson, downloadMarkdown } from '@/Utils/exportHelpers';

const props = defineProps({
    initialUsername: String,
    initialEngines: Array,
    initialSites: Array,
    investigations: Array,
    platformCategories: Object,
    totalCatalogPlatforms: Number,
});

const usernameInput = ref(props.initialUsername || '');
const currentTarget = ref(props.initialUsername || '');
const isProbing = ref(false);
const probeLive = ref(true);
const searchEngines = ref(props.initialEngines || []);
const specificSites = ref(props.initialSites || []);

// Real-Time SSE Multi-Platform Enumeration Stream State
let eventSource = null;
const isStreaming = ref(false);
const discoveredAccounts = ref([]);
const allProbedResults = ref([]);
const showHitsOnly = ref(true);
const selectedCategory = ref('all');
const streamProgress = ref({
    probed: 0,
    total: props.totalCatalogPlatforms || 76,
    found: 0,
    percent: 0,
    last_platform: '',
    last_status: '',
    duration_ms: 0,
});

// Category Filter Definitions & Live Counters
const categoriesList = computed(() => [
    { id: 'all', label: 'All Targets' },
    { id: 'code', label: 'Code & Dev' },
    { id: 'social', label: 'Social' },
    { id: 'gaming', label: 'Gaming' },
    { id: 'tech', label: 'Tech & Blogs' },
    { id: 'forums', label: 'Forums' },
    { id: 'media', label: 'Media' },
]);

const categoryCounts = computed(() => {
    const list = showHitsOnly.value ? discoveredAccounts.value : allProbedResults.value;
    const counts = { all: list.length, code: 0, social: 0, gaming: 0, tech: 0, forums: 0, media: 0 };
    list.forEach(item => {
        if (item.category && counts[item.category] !== undefined) {
            counts[item.category]++;
        }
    });
    return counts;
});

const filteredDiscoveredAccounts = computed(() => {
    const list = showHitsOnly.value ? discoveredAccounts.value : allProbedResults.value;
    if (selectedCategory.value === 'all') {
        return list;
    }
    return list.filter(item => item.category === selectedCategory.value);
});

// Dossier Bookmark & Clipboard
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);
const { copiedId: copiedUrlId, copy: copyMinitextUrl } = useClipboard();
const selectedTarget = ref(null);

// Statistics
const foundProfilesCount = computed(() => {
    return specificSites.value.filter(s => s.status === 'found').length;
});

const totalDiscoveredCount = computed(() => {
    return discoveredAccounts.value.length + foundProfilesCount.value;
});

const stopLiveStream = () => {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    isStreaming.value = false;
};

const startLiveStream = (user) => {
    stopLiveStream();
    discoveredAccounts.value = [];
    allProbedResults.value = [];
    isStreaming.value = true;
    streamProgress.value = {
        probed: 0,
        total: props.totalCatalogPlatforms || 76,
        found: 0,
        percent: 0,
        last_platform: '',
        last_status: '',
        duration_ms: 0,
    };

    const streamUrl = `/api/osint/username/stream?username=${encodeURIComponent(user)}`;
    eventSource = new EventSource(streamUrl);

    eventSource.addEventListener('start', (event) => {
        try {
            const data = JSON.parse(event.data);
            if (data.total_platforms) {
                streamProgress.value.total = data.total_platforms;
            }
        } catch (e) {}
    });

    eventSource.addEventListener('hit', (event) => {
        try {
            const hit = JSON.parse(event.data);
            if (!discoveredAccounts.value.some(a => a.platform_id === hit.platform_id)) {
                discoveredAccounts.value.unshift(hit);
            }
            if (!allProbedResults.value.some(p => p.platform_id === hit.platform_id)) {
                allProbedResults.value.unshift(hit);
            }
        } catch (e) {}
    });

    eventSource.addEventListener('progress', (event) => {
        try {
            const data = JSON.parse(event.data);
            streamProgress.value.probed = data.probed;
            streamProgress.value.total = data.total;
            streamProgress.value.found = data.found;
            streamProgress.value.percent = data.percent;
            streamProgress.value.last_platform = data.last_platform || '';
            streamProgress.value.last_status = data.last_status || '';

            if (data.last_status !== 'found' && data.last_platform) {
                const probeObj = {
                    platform_id: data.last_platform.toLowerCase().replace(/\s+/g, '_'),
                    platform: data.last_platform,
                    status: data.last_status,
                    category: 'tech',
                    url: null,
                };
                if (!allProbedResults.value.some(p => p.platform === data.last_platform)) {
                    allProbedResults.value.push(probeObj);
                }
            }
        } catch (e) {}
    });

    eventSource.addEventListener('done', (event) => {
        try {
            const data = JSON.parse(event.data);
            streamProgress.value.duration_ms = data.duration_ms || 0;
            streamProgress.value.percent = 100;
        } catch (e) {}
        stopLiveStream();
    });

    eventSource.addEventListener('error', () => {
        stopLiveStream();
    });
};

const executeLookup = async () => {
    const raw = usernameInput.value.trim();
    const user = raw.replace(/^@+/, '');
    if (!user) return;

    currentTarget.value = user;
    isProbing.value = true;

    // Start live SSE multi-platform enumeration
    startLiveStream(user);

    try {
        const res = await axios.post('/api/osint/username/probe', {
            username: user,
            probe_live: probeLive.value,
        });

        if (res.data.success) {
            searchEngines.value = res.data.search_engines;
            specificSites.value = res.data.specific_sites;
        }
    } catch (err) {
        console.error('OSINT Probe Error:', err);
    } finally {
        isProbing.value = false;
    }
};

const openBookmarkModal = (item) => {
    selectedTarget.value = item;
    const titleName = item.platform || item.name || 'Account';
    const cleanUser = currentTarget.value || usernameInput.value || '';
    const notes = item.response_time_ms
        ? `Live Discovered Account on ${titleName} (${item.category || 'general'}). Response latency: ${item.response_time_ms}ms.`
        : (item.details || item.method_note || item.description || '');

    openBookmark({
        title: `[OSINT] ${titleName}: @${cleanUser}`,
        url: item.url || item.target_url || '',
        notes,
        severity: (item.status === 'found' || item.status === 'live') ? 'high' : 'medium',
        investigation_id: props.investigations?.length ? props.investigations[0].id : null,
    });
};

const showExportMenu = ref(false);

const exportReport = (format = 'json') => {
    showExportMenu.value = false;
    const target = currentTarget.value || usernameInput.value || 'target';
    const timestamp = new Date().toISOString();

    if (format === 'json') {
        const payload = {
            investigation_tool: 'Darkdump OSINT Workstation v5',
            framework_category: 'Username Lookup',
            target_username: target,
            generated_at: timestamp,
            summary: {
                total_live_discovered: discoveredAccounts.value.length,
                total_specific_sites_found: foundProfilesCount.value,
                total_found_combined: totalDiscoveredCount.value,
                total_engines: searchEngines.value.length,
                total_specific_sites: specificSites.value.length,
            },
            discovered_accounts: discoveredAccounts.value,
            specific_sites: specificSites.value,
            search_engines: searchEngines.value,
        };

        downloadJson(payload, `osint-username-${target}-${Date.now()}.json`);
    } else {
        let md = `# OSINT Forensic Dossier: Alias @${target}\n\n`;
        md += `**Workstation:** Darkdump OSINT Suite v5\n`;
        md += `**Framework Category:** Username Lookup & Live Enumeration\n`;
        md += `**Target Alias:** @${target}\n`;
        md += `**Export Timestamp:** ${timestamp}\n`;
        md += `**Total Live Profiles Identified:** ${totalDiscoveredCount.value}\n\n`;

        if (discoveredAccounts.value.length > 0) {
            md += `## 1. Real-Time Discovered Platform Profiles (${discoveredAccounts.value.length} Confirmed)\n\n`;
            md += `| Platform | Category | Verified Profile URL | Response Latency |\n`;
            md += `| :--- | :--- | :--- | :--- |\n`;
            discoveredAccounts.value.forEach(a => {
                md += `| **${a.platform}** | \`${a.category}\` | ${a.url} | ${a.response_time_ms ? a.response_time_ms + 'ms' : 'N/A'} |\n`;
            });
            md += `\n`;
        }

        md += `## 2. Specific Sites & Framework Methods Checklist\n\n`;
        const founds = specificSites.value.filter(s => s.status === 'found');
        if (founds.length > 0) {
            md += `| Platform | Status | Direct Profile URL | Identified Metadata |\n`;
            md += `| :--- | :--- | :--- | :--- |\n`;
            founds.forEach(f => {
                const metaStr = f.metadata ? Object.entries(f.metadata).map(([k, v]) => `${k}: ${v}`).join(', ') : (f.details || 'N/A');
                md += `| **${f.name}** | ${f.status_label || 'Found'} | ${f.target_url} | ${metaStr} |\n`;
            });
            md += `\n`;
        } else {
            md += `*No specific sites confirmed via direct probe.*\n\n`;
        }

        md += `## 3. All Specific Sites Probed\n\n`;
        md += `| Platform | Status | Direct Verification Link | Method Notes |\n`;
        md += `| :--- | :--- | :--- | :--- |\n`;
        specificSites.value.forEach(s => {
            md += `| ${s.name} | \`${s.status || 'pending'}\` | ${s.target_url} | ${s.method_note || s.details || '-'} |\n`;
        });
        md += `\n`;

        md += `## 4. Username Search Engines & Tools\n\n`;
        md += `| Tool / Engine | Type | Query Launch Link |\n`;
        md += `| :--- | :--- | :--- |\n`;
        searchEngines.value.forEach(e => {
            md += `| ${e.name} | ${e.tag} | ${e.url} |\n`;
        });
        md += `\n---\n*Generated by Darkdump OSINT Investigation Workstation*\n`;

        downloadMarkdown(md, `osint-username-${target}-${Date.now()}.md`);
    }
};

onMounted(() => {
    if (props.initialUsername) {
        if (!searchEngines.value.length || !specificSites.value.length) {
            executeLookup();
        } else {
            startLiveStream(props.initialUsername);
        }
    }
});
</script>

<template>
    <div class="space-y-6 w-full">
            <!-- Navigation Breadcrumb & Header -->
            <div class="border-b border-slate-800/80 pb-5">
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500 mb-2">
                    <Link href="/osint" class="hover:text-teal-400 transition">OSINT Framework</Link>
                    <span>/</span>
                    <span class="text-teal-400/90 font-semibold">Username Lookup</span>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-serif font-black tracking-tight text-white uppercase flex items-center gap-2.5">
                            <UserCheck class="w-6 h-6 text-teal-400" />
                            <span>Username Reconnaissance</span>
                        </h1>
                        <p class="text-slate-400 text-xs font-mono mt-0.5">
                            OSINT Framework taxonomy: Multi-platform alias tracing, automated search engines &amp; live profile probing.
                        </p>
                    </div>

                    <div v-if="currentTarget" class="flex flex-wrap items-center gap-2.5 font-mono text-xs">
                        <div class="px-2.5 py-1 rounded-lg bg-slate-900/80 border border-slate-800 flex items-center gap-2">
                            <span class="text-slate-500">Target:</span>
                            <span class="text-slate-200 font-bold">@{{ currentTarget }}</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-slate-800/70 border border-slate-700/60 text-teal-300 flex items-center gap-1.5">
                            <ShieldCheck class="w-3.5 h-3.5 text-teal-400" />
                            <span>{{ foundProfilesCount }} Live Profiles</span>
                        </div>

                        <!-- Export Actions Dropdown -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="showExportMenu = !showExportMenu"
                                class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition text-xs"
                                title="Export Findings"
                            >
                                <Download class="w-3.5 h-3.5 text-slate-400" />
                                <span>Export Report</span>
                                <ChevronDown class="w-3 h-3 text-slate-500" />
                            </button>

                            <div
                                v-if="showExportMenu"
                                class="absolute right-0 mt-2 w-48 bg-slate-900/95 border border-slate-800 rounded-xl shadow-2xl py-1 z-50 font-mono text-xs backdrop-blur-md"
                            >
                                <button
                                    type="button"
                                    @click="exportReport('json')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-2 transition"
                                >
                                    <FileText class="w-3.5 h-3.5 text-teal-400" />
                                    <span>Export JSON (.json)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="exportReport('markdown')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-2 transition"
                                >
                                    <FileText class="w-3.5 h-3.5 text-slate-400" />
                                    <span>Export Markdown (.md)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Form Card -->
                <div class="mt-4 p-3 rounded-xl bg-slate-900/70 border border-slate-800 shadow-xl">
                    <form @submit.prevent="executeLookup" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <span class="font-mono text-teal-400/80 font-bold text-xs">@</span>
                            </div>
                            <input
                                v-model="usernameInput"
                                type="text"
                                placeholder="Enter username, alias, or handle (e.g. Satoshi, darklord, kenshi)..."
                                class="w-full pl-8 pr-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-teal-500/60 focus:ring-1 focus:ring-teal-500/30 text-xs font-mono transition"
                                required
                            />
                        </div>

                        <!-- Live Probe Toggle -->
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-xs font-mono text-slate-300 cursor-pointer select-none shrink-0">
                            <input
                                type="checkbox"
                                v-model="probeLive"
                                class="rounded bg-slate-900 border-slate-700 text-teal-500 focus:ring-teal-500/40"
                            />
                            <span>Active Probing</span>
                        </label>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="isProbing"
                            class="px-5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700/80 text-teal-300 hover:text-teal-200 font-mono text-xs font-semibold uppercase tracking-wider flex items-center justify-center gap-2 shadow transition-all shrink-0 disabled:opacity-50"
                        >
                            <Loader2 v-if="isProbing" class="w-3.5 h-3.5 animate-spin text-teal-400" />
                            <Search v-else class="w-3.5 h-3.5 text-teal-400" />
                            <span>{{ isProbing ? 'Probing...' : 'Trace Alias' }}</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Reconnaissance Workspace: 2-Column Responsive Layout -->
            <div class="flex flex-col lg:flex-row gap-5 items-start">

                <!-- ========================================================================= -->
                <!-- LEFT COLUMN: SUGGESTED MANUAL SEARCH TOOLS (Persistent Side Rail)          -->
                <!-- ========================================================================= -->
                <aside class="w-full lg:w-80 xl:w-96 shrink-0 space-y-3.5 lg:sticky lg:top-4">
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 shadow-xl overflow-hidden">
                        
                        <!-- Panel Header -->
                        <div class="p-3.5 border-b border-slate-800/80 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded bg-teal-950/50 border border-teal-800/50 flex items-center justify-center text-teal-400 shrink-0">
                                    <Globe class="w-3.5 h-3.5" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wide truncate">
                                        Manual Search Tools
                                    </h2>
                                    <p class="text-[10px] font-mono text-slate-500 truncate">
                                        External aggregators &amp; query engines
                                    </p>
                                </div>
                            </div>
                            <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-800/70 border border-slate-700/60 text-teal-300 shrink-0">
                                {{ searchEngines.length }} TOOLS
                            </span>
                        </div>

                        <!-- Analyst Suggestion Callout Banner -->
                        <div class="mx-3 mt-3 p-2.5 rounded-lg bg-teal-950/30 border border-teal-800/40 text-[10px] font-mono text-teal-300/90 leading-relaxed flex items-start gap-2">
                            <Compass class="w-3.5 h-3.5 text-teal-400 shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold text-teal-200">Analyst Suggestion:</span>
                                <span> Use these external engines for manual correlation if automated probes are inconclusive or rate-limited.</span>
                            </div>
                        </div>

                        <!-- Active List (Non-collapsible, High-Density Micro-Rows) -->
                        <div 
                            v-if="searchEngines.length > 0" 
                            class="p-3 space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto no-scrollbar"
                        >
                            <div
                                v-for="engine in searchEngines"
                                :key="engine.id"
                                class="p-2.5 rounded-lg bg-slate-950/70 border border-slate-800/90 border-l-2 border-l-teal-600/70 hover:border-l-teal-400 hover:border-slate-700 hover:bg-slate-950 transition-all flex flex-col justify-between group shadow-sm"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-mono font-bold text-xs text-slate-200 group-hover:text-teal-300 transition-colors truncate">
                                                    {{ engine.name }}
                                                </span>
                                                <span 
                                                    class="text-[9px] font-mono font-medium px-1.5 py-0.2 rounded border uppercase shrink-0"
                                                    :class="engine.tag === 'Tool' ? 'bg-slate-800 text-teal-300/90 border-teal-800/40' : 'bg-slate-800/80 text-slate-400 border-slate-700/60'"
                                                >
                                                    {{ engine.tag }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1 shrink-0">
                                            <button
                                                type="button"
                                                @click="openBookmarkModal(engine)"
                                                class="p-1 rounded bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 transition"
                                                title="Save to Case Dossier"
                                            >
                                                <FolderPlus class="w-3 h-3" />
                                            </button>
                                            <a
                                                :href="engine.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-teal-300 hover:text-teal-200 text-[10px] font-mono flex items-center gap-1 transition"
                                                title="Open in new tab"
                                            >
                                                <span>Launch</span>
                                                <ExternalLink class="w-2.5 h-2.5" />
                                            </a>
                                        </div>
                                    </div>

                                    <p class="text-[10px] text-slate-400 font-sans leading-snug line-clamp-2">
                                        {{ engine.description }}
                                    </p>
                                </div>

                                <!-- Minitext direct link with high contrast and one-click copy -->
                                <div class="mt-2 pt-1.5 border-t border-slate-900 flex items-center justify-between text-[10px] font-mono">
                                    <div class="flex items-center gap-1.5 truncate max-w-[150px] sm:max-w-[200px]">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Query:</span>
                                        <span class="text-slate-400 select-all truncate text-[9px]" :title="engine.url">
                                            {{ engine.url }}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyMinitextUrl(engine.url, engine.id)"
                                        class="px-1.5 py-0.5 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 flex items-center gap-1 shrink-0 transition text-[9px]"
                                        title="Copy Direct URL"
                                    >
                                        <Check v-if="copiedUrlId === engine.id" class="w-2.5 h-2.5 text-teal-400" />
                                        <Copy v-else class="w-2.5 h-2.5" />
                                        <span>{{ copiedUrlId === engine.id ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State if no engines loaded -->
                        <div v-else class="p-6 text-center text-slate-500 font-mono text-xs">
                            No manual tools loaded in registry.
                        </div>
                    </div>
                </aside>

                <!-- ========================================================================= -->
                <!-- RIGHT COLUMN: INTELLIGENCE FINDINGS & PROBES (Flex-1 Main Content Area)     -->
                <!-- ========================================================================= -->
                <main class="flex-1 min-w-0 space-y-5">
                    
                    <!-- Empty State (Awaiting Target Alias) -->
                    <div 
                        v-if="!currentTarget && !isProbing" 
                        class="p-12 rounded-xl bg-slate-900/60 border border-slate-800/80 text-center space-y-3"
                    >
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500 mx-auto">
                            <Compass class="w-7 h-7 text-teal-400/80" />
                        </div>
                        <h3 class="text-base font-serif font-bold text-slate-200 uppercase tracking-tight">
                            Awaiting Target Alias
                        </h3>
                        <p class="text-xs font-mono text-slate-400 max-w-md mx-auto leading-relaxed">
                            Input a username or handle in the console above to initiate real-time cross-platform enumeration across 75+ networks and direct platform probes.
                        </p>
                    </div>

                    <!-- Loading Skeleton when probe initiated -->
                    <div 
                        v-if="isProbing && !discoveredAccounts.length && !specificSites.length && !isStreaming" 
                        class="p-12 rounded-xl bg-slate-900/70 border border-slate-800 text-center space-y-4 font-mono"
                    >
                        <Loader2 class="w-8 h-8 text-teal-400 animate-spin mx-auto" />
                        <div class="text-sm text-slate-200 font-bold uppercase tracking-wide">
                            Probing Global Identity Infrastructure...
                        </div>
                        <div class="text-xs text-slate-500">
                            Querying OpenPGP keyservers, code repositories, and identity platforms
                        </div>
                    </div>

                    <!-- ===================================================================== -->
                    <!-- 1. LIVE DISCOVERED ACCOUNTS (Real-Time SSE Stream) - TOP OF FINDINGS   -->
                    <!-- ===================================================================== -->
                    <section
                        v-if="currentTarget && (isStreaming || discoveredAccounts.length > 0 || allProbedResults.length > 0)"
                        class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-3.5"
                    >
                        <!-- Panel Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-teal-950/50 border border-teal-800/50 flex items-center justify-center text-teal-400 shrink-0">
                                    <Activity class="w-4 h-4" :class="{ 'animate-pulse text-teal-300': isStreaming }" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wider">
                                            Live Discovered Accounts
                                        </h2>
                                        <span v-if="isStreaming" class="flex h-2 w-2 relative" title="Live stream active">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-teal-500"></span>
                                        </span>
                                        <span v-else class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800/80 text-slate-400 border border-slate-700/50">
                                            Scan Complete
                                        </span>
                                    </div>
                                    <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                        Real-time cross-platform alias reconnaissance across 75+ global web services
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-auto font-mono text-xs">
                                <div class="px-2.5 py-1 rounded-lg bg-teal-950/40 border border-teal-800/50 text-teal-300 font-bold flex items-center gap-1.5">
                                    <CheckCircle2 class="w-3.5 h-3.5 text-teal-400" />
                                    <span>{{ discoveredAccounts.length }} Found</span>
                                </div>

                                <button
                                    v-if="isStreaming"
                                    type="button"
                                    @click="stopLiveStream"
                                    class="px-2.5 py-1 rounded-lg bg-rose-950/40 hover:bg-rose-900/50 border border-rose-800/60 text-rose-300 flex items-center gap-1.5 transition text-xs"
                                    title="Stop Stream"
                                >
                                    <StopCircle class="w-3.5 h-3.5" />
                                    <span>Stop Probe</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="startLiveStream(currentTarget)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition text-xs"
                                    title="Re-run Stream"
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
                                        {{ streamProgress.percent === 100 ? 'Recon Scan Complete' : `Probing ${streamProgress.total} platforms...` }}
                                    </span>
                                    <span class="font-bold" :class="streamProgress.percent === 100 ? 'text-emerald-400' : 'text-teal-400'">
                                        [{{ streamProgress.probed }}/{{ streamProgress.total }}]
                                    </span>
                                    <span class="text-slate-500">—</span>
                                    <span class="text-slate-400">{{ discoveredAccounts.length }} Accounts Identified</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="isStreaming && streamProgress.last_platform" class="text-slate-500 truncate max-w-[180px]">
                                        Checking: {{ streamProgress.last_platform }}
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

                            <!-- Progress Track -->
                            <div
                                class="h-2 w-full bg-slate-950 rounded-full overflow-hidden border relative transition-all duration-500"
                                :class="streamProgress.percent === 100
                                    ? 'animate-complete-glow'
                                    : (isStreaming ? 'border-teal-500/40 shadow-[0_0_10px_rgba(45,212,191,0.2)]' : 'border-slate-800')"
                            >
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

                        <!-- Category Filter Tabs & Hits Toggle -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <!-- Category Pills -->
                            <div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                                <button
                                    v-for="cat in categoriesList"
                                    :key="cat.id"
                                    type="button"
                                    @click="selectedCategory = cat.id"
                                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1.5 border"
                                    :class="selectedCategory === cat.id
                                        ? 'bg-teal-950/60 border-teal-500/50 text-teal-300 font-bold shadow-sm'
                                        : 'bg-slate-950/60 hover:bg-slate-900 border-slate-800/80 text-slate-400 hover:text-slate-200'"
                                >
                                    <span>{{ cat.label }}</span>
                                    <span class="px-1 py-0.2 rounded text-[10px]" :class="selectedCategory === cat.id ? 'bg-teal-800/40 text-teal-200' : 'bg-slate-900 text-slate-500'">
                                        {{ categoryCounts[cat.id] || 0 }}
                                    </span>
                                </button>
                            </div>

                            <!-- Hits Only Toggle -->
                            <label class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="showHitsOnly"
                                    class="rounded bg-slate-950 border-slate-800 text-teal-500 focus:ring-teal-500/30 w-3.5 h-3.5"
                                />
                                <span>Show verified hits only</span>
                            </label>
                        </div>

                        <!-- Results Cards Grid -->
                        <div v-if="filteredDiscoveredAccounts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5 pt-1">
                            <div
                                v-for="hit in filteredDiscoveredAccounts"
                                :key="hit.platform_id || hit.url"
                                class="p-2.5 rounded-lg bg-slate-950/80 border border-slate-800/90 hover:border-teal-500/40 transition-all flex flex-col justify-between group shadow-sm"
                            >
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span class="font-mono text-xs font-bold text-slate-200 group-hover:text-teal-300 transition truncate">
                                            {{ hit.platform }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase tracking-wider bg-slate-900 text-slate-400 border border-slate-800">
                                                {{ hit.category }}
                                            </span>
                                            <span
                                                v-if="hit.status === 'found'"
                                                class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase tracking-wider bg-teal-950/80 text-teal-300 border border-teal-800/60"
                                            >
                                                FOUND
                                            </span>
                                            <span
                                                v-else
                                                class="px-1.5 py-0.5 rounded text-[9px] font-mono uppercase tracking-wider bg-slate-900 text-slate-500 border border-slate-800"
                                            >
                                                {{ hit.status }}
                                            </span>
                                        </div>
                                    </div>

                                    <div v-if="hit.url" class="font-mono text-[10px] text-slate-500 truncate group-hover:text-slate-400 transition" :title="hit.url">
                                        {{ hit.url }}
                                    </div>
                                    <div v-else class="font-mono text-[10px] text-slate-600 italic">
                                        No direct profile link registered
                                    </div>
                                </div>

                                <!-- Card Actions -->
                                <div class="flex items-center justify-between gap-1 pt-2 mt-2 border-t border-slate-900 font-mono text-[10px]">
                                    <div class="flex items-center gap-1.5">
                                        <a
                                            v-if="hit.url"
                                            :href="hit.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-teal-300 flex items-center gap-1 transition"
                                            title="Open Profile"
                                        >
                                            <span>Open</span>
                                            <ExternalLink class="w-2.5 h-2.5" />
                                        </a>

                                        <button
                                            v-if="hit.url"
                                            type="button"
                                            @click="copyMinitextUrl(hit.url, hit.platform_id)"
                                            class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 flex items-center gap-1 transition"
                                            title="Copy Profile URL"
                                        >
                                            <Check v-if="copiedUrlId === hit.platform_id" class="w-2.5 h-2.5 text-teal-400" />
                                            <Copy v-else class="w-2.5 h-2.5" />
                                            <span>{{ copiedUrlId === hit.platform_id ? 'Copied' : 'Copy' }}</span>
                                        </button>
                                    </div>

                                    <button
                                        v-if="hit.url"
                                        type="button"
                                        @click="openBookmarkModal(hit)"
                                        class="px-2 py-1 rounded bg-teal-950/30 hover:bg-teal-900/40 border border-teal-800/40 hover:border-teal-700/60 text-teal-300 flex items-center gap-1 transition"
                                        title="Save to Case Dossier"
                                    >
                                        <FolderPlus class="w-2.5 h-2.5" />
                                        <span>Dossier</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Filtered Results State -->
                        <div v-else-if="!isStreaming" class="py-6 text-center text-slate-500 font-mono text-xs">
                            No discovered accounts match the selected category filter.
                        </div>
                    </section>

                    <!-- ===================================================================== -->
                    <!-- 2. SPECIFIC SITES & METHODS (Curated Platform Probes) - DIRECTLY UNDER -->
                    <!-- ===================================================================== -->
                    <section v-if="specificSites.length > 0" class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-3.5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-800/80">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-slate-800/70 border border-slate-700/60 flex items-center justify-center text-teal-400">
                                    <UserCheck class="w-4 h-4" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wider">
                                        Specific Platform Probes &amp; Methods
                                    </h2>
                                    <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                        Direct profile verification &amp; manual platform methods
                                    </p>
                                </div>
                            </div>
                            <span class="text-[10px] font-mono font-semibold px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">
                                {{ specificSites.length }} PLATFORMS
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 pt-1">
                            <div
                                v-for="site in specificSites"
                                :key="site.id"
                                class="p-3 rounded-lg border transition-all flex flex-col justify-between group"
                                :class="[
                                    site.status === 'found' ? 'bg-slate-950/90 border-slate-800/90 border-l-2 border-l-teal-500' :
                                    site.status === 'manual_only' ? 'bg-slate-950/70 border-slate-800/80 border-l-2 border-l-amber-500/80' :
                                    site.status === 'error' ? 'bg-slate-950/50 border-slate-800/70 border-l-2 border-l-slate-700' :
                                    'bg-slate-950/50 border-slate-800/70 border-l-2 border-l-slate-700'
                                ]"
                            >
                                <div>
                                    <!-- Header & Status Badge -->
                                    <div class="flex items-start justify-between gap-2 mb-1.5">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="font-mono font-bold text-xs text-slate-200 group-hover:text-teal-300 transition-colors truncate">
                                                {{ site.name }}
                                            </span>
                                            <span 
                                                class="text-[9px] font-mono font-medium px-1.5 py-0.2 rounded border uppercase shrink-0"
                                                :class="site.tag === 'Method' ? 'bg-slate-800 text-amber-300/80 border-amber-800/40' : 'bg-slate-800/80 text-slate-400 border-slate-700/60'"
                                            >
                                                {{ site.tag }}
                                            </span>
                                        </div>

                                        <!-- Status Badge -->
                                        <div class="flex items-center gap-1 shrink-0">
                                            <span 
                                                class="text-[9px] font-mono font-medium px-2 py-0.5 rounded border flex items-center gap-1 tracking-wide uppercase"
                                                :class="[
                                                    site.status === 'found' ? 'bg-teal-950/80 text-teal-300 border-teal-800/60' :
                                                    site.status === 'manual_only' ? 'bg-amber-950/40 text-amber-300 border-amber-800/50' :
                                                    'bg-slate-800/60 text-slate-400 border-slate-700/50'
                                                ]"
                                            >
                                                <span v-if="site.status === 'found'" class="w-1.5 h-1.5 rounded-full bg-teal-400"></span>
                                                <Terminal v-else-if="site.status === 'manual_only'" class="w-2.5 h-2.5 text-amber-400" />
                                                <span>{{ site.status_label || (site.status === 'found' ? 'PROFILE FOUND' : site.status === 'manual_only' ? 'MANUAL METHOD' : 'NO RECORD') }}</span>
                                            </span>

                                            <!-- Save to Dossier Action -->
                                            <button
                                                type="button"
                                                @click="openBookmarkModal(site)"
                                                class="p-1 rounded bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 transition"
                                                title="Save to Case Dossier"
                                            >
                                                <FolderPlus class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Live Profile Bio / Metadata Box -->
                                    <div class="text-[11px] font-mono text-slate-300 leading-normal">
                                        <div v-if="site.metadata" class="p-2 rounded bg-slate-900/90 border border-slate-800 mb-1.5 space-y-1">
                                            <div v-if="site.metadata.name" class="text-teal-300 font-bold text-[11px] flex items-center gap-1.5">
                                                <User class="w-3 h-3 text-teal-400" />
                                                <span>{{ site.metadata.name }}</span>
                                                <span v-if="site.metadata.location" class="text-slate-400 text-[10px] font-normal">({{ site.metadata.location }})</span>
                                            </div>
                                            <div v-if="site.details" class="text-slate-300 text-[10px] italic">
                                                "{{ site.details }}"
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5 text-[9px]">
                                                <span v-if="site.metadata.public_repos !== undefined" class="px-1.5 py-0.2 rounded bg-slate-950 border border-slate-800 text-slate-400">
                                                    Repos: <strong class="text-slate-200">{{ site.metadata.public_repos }}</strong>
                                                </span>
                                                <span v-if="site.metadata.followers !== undefined" class="px-1.5 py-0.2 rounded bg-slate-950 border border-slate-800 text-slate-400">
                                                    Followers: <strong class="text-slate-200">{{ site.metadata.followers }}</strong>
                                                </span>
                                                <span v-if="site.metadata.proofs_count !== undefined" class="px-1.5 py-0.2 rounded bg-slate-950 border border-slate-800 text-slate-400">
                                                    Identities: <strong class="text-slate-200">{{ site.metadata.proofs_count }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="text-slate-400 text-[11px] font-sans">
                                            {{ site.details || site.method_note }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Direct Minitext Link with High Contrast & Copy Button -->
                                <div class="mt-2.5 pt-2 border-t border-slate-900 flex items-center justify-between text-[10px] font-mono">
                                    <div class="flex items-center gap-1.5 truncate max-w-[200px] sm:max-w-xs">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Direct:</span>
                                        <a 
                                            :href="site.target_url" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            class="text-slate-400 hover:text-teal-300 truncate text-[10px] underline underline-offset-2 transition"
                                            :title="site.target_url"
                                        >
                                            {{ site.target_url }}
                                        </a>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="copyMinitextUrl(site.target_url, site.id)"
                                            class="px-1.5 py-0.5 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 flex items-center gap-1 transition text-[9px]"
                                            title="Copy Direct URL"
                                        >
                                            <Check v-if="copiedUrlId === site.id" class="w-2.5 h-2.5 text-teal-400" />
                                            <Copy v-else class="w-2.5 h-2.5" />
                                            <span>{{ copiedUrlId === site.id ? 'Copied' : 'Copy' }}</span>
                                        </button>
                                        <a
                                            :href="site.target_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="p-0.5 rounded bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-teal-300 transition"
                                            title="Open in new tab"
                                        >
                                            <ExternalLink class="w-3 h-3" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                </main>
            </div>

            <!-- Save To Case Dossier Modal -->
            <EvidenceBookmarkModal
                v-model="showBookmarkModal"
                :target="bookmarkTarget"
                :investigations="investigations"
            />
        </div>
</template>

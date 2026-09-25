<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Mail, 
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
    ChevronDown, 
    AlertTriangle,
    AlertCircle,
    Server,
    CheckCircle2,
    Database,
    Shield,
    FolderGit2,
    Lock,
    StopCircle,
    Eye,
    Activity,
    Cpu,
    Radio,
    Key,
} from 'lucide-vue-next';

import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';
import { downloadJson, downloadMarkdown } from '@/Utils/exportHelpers';

const props = defineProps({
    initialEmail: String,
    initialProbe: Object,
    categorizedTools: Object,
    investigations: Array,
    totalToolsCount: Number,
});

const emailInput = ref(props.initialEmail || '');
const currentTarget = ref(props.initialEmail || '');
const isProbing = ref(false);
const probeLive = ref(true);
const searchQuery = ref('');
const activeBranch = ref('all');
const showHitsOnly = ref(false);

// Real-Time SSE Multi-Tool Streaming State
let eventSource = null;
const isStreaming = ref(false);
const streamedResults = ref({}); // keyed by tool_id
const streamProgress = ref({
    probed: 0,
    total: props.totalToolsCount || 30,
    found: 0,
    percent: 0,
    current_tool: '',
    branch: '',
    duration_ms: 0,
});

// Telemetry State
const probeData = ref(props.initialProbe || null);
const categorizedTools = ref(props.categorizedTools || {
    email_search: [],
    common_formats: [],
    verification: [],
    breach_data: [],
    mail_blacklists: [],
});

// Bookmark Modal State
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);
const copiedUrlId = ref(null);
const copiedPermutation = ref(null);
const copiedAllWordlist = ref(false);

// Branch metadata definitions
const branchDefinitions = [
    { 
        id: 'email_search', 
        name: 'Email Search', 
        icon: Search, 
        count: 13,
        accent: 'teal',
        description: 'Identity search engines, reverse lookups, and OSINT enumeration repositories.' 
    },
    { 
        id: 'common_formats', 
        name: 'Common Formats', 
        icon: Terminal, 
        count: 2,
        accent: 'cyan',
        description: 'Corporate email address conventions, syntax catalogs, and permutation generators.' 
    },
    { 
        id: 'verification', 
        name: 'Email Verification', 
        icon: ShieldCheck, 
        count: 10,
        accent: 'emerald',
        description: 'SMTP mailbox deliverability verifiers, disposable domain databases, and reputation scoring.' 
    },
    { 
        id: 'breach_data', 
        name: 'Breach Data', 
        icon: Database, 
        count: 4,
        accent: 'amber',
        description: 'Historical breach indices, infostealer malware compromises, and leaked credential databases.' 
    },
    { 
        id: 'mail_blacklists', 
        name: 'Mail Blacklists', 
        icon: ShieldAlert, 
        count: 1,
        accent: 'rose',
        description: 'DNS-based spam blacklists (DNSBL/RBL) assessing host reputation and delivery status.' 
    },
];

// Flat list of all tools
const allToolsList = computed(() => {
    const list = [];
    Object.values(categorizedTools.value).forEach(branchTools => {
        if (Array.isArray(branchTools)) {
            list.push(...branchTools);
        }
    });
    return list;
});

// Helper to get streamed result for a tool
const getToolResult = (toolId) => {
    return streamedResults.value[toolId] || null;
};

// Filtered tools by active branch tab, search query, and findings filter
const filteredToolsByBranch = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const result = {};

    branchDefinitions.forEach(branch => {
        let tools = categorizedTools.value[branch.id] || [];

        if (showHitsOnly.value) {
            tools = tools.filter(t => {
                const res = streamedResults.value[t.id];
                return res && res.status !== 'external_skipped';
            });
        }

        if (q) {
            tools = tools.filter(t => 
                t.name.toLowerCase().includes(q) ||
                t.description.toLowerCase().includes(q) ||
                t.tag.toLowerCase().includes(q) ||
                t.type.toLowerCase().includes(q)
            );
        }

        result[branch.id] = tools;
    });

    return result;
});

const totalVisibleToolsCount = computed(() => {
    let count = 0;
    Object.values(filteredToolsByBranch.value).forEach(list => {
        count += list.length;
    });
    return count;
});

// Counts of completed stream results
const streamSummaryCounts = computed(() => {
    const values = Object.values(streamedResults.value);
    let hits = 0;
    let clean = 0;
    let skipped = 0;

    values.forEach(v => {
        if (v.status === 'found') hits++;
        else if (v.status === 'clean' || v.status === 'info') clean++;
        else if (v.status === 'external_skipped') skipped++;
    });

    return {
        total: values.length,
        hits,
        clean,
        skipped,
    };
});

// Start Real-Time SSE Probing Stream
const startLiveStream = (email) => {
    stopLiveStream();
    if (!email) return;

    isStreaming.value = true;
    streamedResults.value = {};
    streamProgress.value = {
        probed: 0,
        total: allToolsList.value.length || 30,
        found: 0,
        percent: 0,
        current_tool: 'Initializing Scanner...',
        branch: '',
        duration_ms: 0,
    };

    const streamUrl = `/api/osint/email/stream?email=${encodeURIComponent(email)}`;
    eventSource = new EventSource(streamUrl);

    eventSource.addEventListener('start', (event) => {
        try {
            const data = JSON.parse(event.data);
            streamProgress.value.total = data.total_tools || 30;
        } catch (e) {
            console.error('Failed to parse SSE start event', e);
        }
    });

    eventSource.addEventListener('result', (event) => {
        try {
            const result = JSON.parse(event.data);
            streamedResults.value[result.tool_id] = result;
        } catch (e) {
            console.error('Failed to parse SSE result event', e);
        }
    });

    eventSource.addEventListener('progress', (event) => {
        try {
            const progress = JSON.parse(event.data);
            streamProgress.value.probed = progress.probed;
            streamProgress.value.total = progress.total;
            streamProgress.value.found = progress.found;
            streamProgress.value.percent = progress.percent;
            streamProgress.value.current_tool = progress.current_tool;
            streamProgress.value.branch = progress.branch;
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

    eventSource.addEventListener('error', (e) => {
        console.warn('SSE stream closed or encountered error', e);
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

// Execute target probe & start SSE stream
const executeLookup = async (targetEmail = null) => {
    const target = (targetEmail || emailInput.value || '').trim();
    if (!target) return;

    emailInput.value = target;
    currentTarget.value = target;
    isProbing.value = true;

    try {
        const response = await axios.post('/api/osint/email/probe', {
            email: target,
            probe_live: probeLive.value,
        });

        if (response.data && response.data.success) {
            probeData.value = response.data;
            if (response.data.categorized_tools) {
                categorizedTools.value = response.data.categorized_tools;
            }

            // Sync URL query state without full reload
            const url = new URL(window.location.href);
            url.searchParams.set('email', target);
            window.history.replaceState({}, '', url.toString());

            // Initiate real-time multi-tool streaming execution
            startLiveStream(target);
        }
    } catch (err) {
        console.error('Email probe failure:', err);
    } finally {
        isProbing.value = false;
    }
};

const setDemoEmail = (demo) => {
    emailInput.value = demo;
    executeLookup(demo);
};

const copyToClipboard = (text, id = null) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    if (id) {
        copiedUrlId.value = id;
        setTimeout(() => {
            copiedUrlId.value = null;
        }, 2000);
    }
};

const copyPermutation = (address, index) => {
    navigator.clipboard.writeText(address);
    copiedPermutation.value = index;
    setTimeout(() => {
        copiedPermutation.value = null;
    }, 2000);
};

const copyAllPermutations = () => {
    if (!probeData.value?.permutations?.length) return;
    const wordlist = probeData.value.permutations.map(p => p.address).join('\n');
    navigator.clipboard.writeText(wordlist);
    copiedAllWordlist.value = true;
    setTimeout(() => {
        copiedAllWordlist.value = false;
    }, 2500);
};

// Open Dossier Bookmark Modal
const openBookmarkModal = (tool, customTitle = null, customNotes = null) => {
    const target = currentTarget.value || emailInput.value || '';
    const res = getToolResult(tool.id);
    const title = customTitle || `[OSINT] ${tool.name}: ${target}`;
    const notes = customNotes || (res ? `${res.summary}` : `Tool: ${tool.name} (${tool.branch_label} / ${tool.tag}). ${tool.description}`);
    const severity = (res?.status === 'found' || tool.branch === 'breach_data') ? 'critical' : (tool.branch === 'mail_blacklists' ? 'high' : 'medium');

    openBookmark({
        title,
        url: tool.url,
        notes,
        severity,
        investigation_id: props.investigations?.length ? props.investigations[0].id : null,
    });
};

// Export Reconnaissance Dossier
const showExportMenu = ref(false);

const exportReport = (format = 'json') => {
    showExportMenu.value = false;
    const target = currentTarget.value || emailInput.value || 'target';
    const timestamp = new Date().toISOString();

    if (format === 'json') {
        const payload = {
            investigation_tool: 'Darkdump OSINT Workstation v5',
            framework_category: 'Email Addresses',
            target_email: target,
            generated_at: timestamp,
            stream_summary: streamSummaryCounts.value,
            telemetry: probeData.value || {},
            streamed_tool_results: streamedResults.value,
            categorized_tools: categorizedTools.value,
        };

        downloadJson(payload, `osint-email-${target.replace(/[^a-zA-Z0-9]/g, '_')}-${Date.now()}.json`);
    } else {
        let md = `# OSINT Investigation Dossier: ${target}\n`;
        md += `**Framework Category:** Email Addresses\n`;
        md += `**Generated At:** ${timestamp}\n\n`;

        if (probeData.value) {
            md += `## Mailbox & DNS Telemetry\n`;
            md += `- **Target:** \`${target}\`\n`;
            md += `- **RFC Syntax Valid:** ${probeData.value.syntax?.is_valid ? 'YES' : 'NO'}\n`;
            md += `- **Local Part:** \`${probeData.value.syntax?.local_part || ''}\`\n`;
            md += `- **Domain:** \`${probeData.value.syntax?.domain || ''}\`\n`;
            md += `- **Domain Classification:** ${probeData.value.hygiene?.classification || 'N/A'}\n`;
            md += `- **Risk Level:** ${probeData.value.hygiene?.risk_level || 'N/A'}\n`;
            md += `- **MX Provider:** ${probeData.value.mx?.provider || 'N/A'}\n`;
            md += `- **MX Primary Host:** ${probeData.value.mx?.primary_host || 'None'}\n\n`;

            if (probeData.value.permutations?.length) {
                md += `### Common Format Permutations\n`;
                probeData.value.permutations.forEach(p => {
                    md += `- \`${p.address}\` (${p.format})\n`;
                });
                md += `\n`;
            }
        }

        md += `## Automated Tool Reconnaissance Stream Findings\n`;
        branchDefinitions.forEach(branch => {
            const list = categorizedTools.value[branch.id] || [];
            md += `### ${branch.name} (${list.length})\n`;
            list.forEach(tool => {
                const res = streamedResults.value[tool.id];
                const statusStr = res ? `[${res.status.toUpperCase()}]` : `[PENDING]`;
                const summaryStr = res ? res.summary : tool.description;
                md += `- **${statusStr} [${tool.tag}] ${tool.name}**: ${tool.url}\n  *${summaryStr}*\n`;
            });
            md += `\n`;
        });

        md += `\n---\n*Generated by Darkdump OSINT Investigation Workstation*\n`;

        downloadMarkdown(md, `osint-email-${target.replace(/[^a-zA-Z0-9]/g, '_')}-${Date.now()}.md`);
    }
};

onMounted(() => {
    if (props.initialEmail) {
        if (!props.initialProbe) {
            executeLookup(props.initialEmail);
        } else {
            startLiveStream(props.initialEmail);
        }
    }
});

onBeforeUnmount(() => {
    stopLiveStream();
});
</script>

<template>
    <div class="space-y-6 w-full">
            <!-- Navigation Breadcrumb & Header -->
            <div class="border-b border-slate-800/80 pb-5">
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500 mb-2">
                    <Link href="/osint" class="hover:text-teal-400 transition">OSINT Framework</Link>
                    <span>/</span>
                    <span class="text-teal-400/90 font-semibold">Email Addresses</span>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-serif font-black tracking-tight text-white uppercase flex items-center gap-2.5">
                            <Mail class="w-6 h-6 text-teal-400" />
                            <span>Email Address Reconnaissance</span>
                        </h1>
                        <p class="text-slate-400 text-xs font-mono mt-0.5">
                            Real-time multi-tool stream: Live data extraction across Email Search, Formats, Verification, Breach Data &amp; Blacklists.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 font-mono text-xs">
                        <div v-if="currentTarget" class="px-2.5 py-1 rounded-lg bg-slate-900/80 border border-slate-800 flex items-center gap-2">
                            <span class="text-slate-500">Target:</span>
                            <span class="text-teal-300 font-bold">{{ currentTarget }}</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-slate-800/70 border border-slate-700/60 text-slate-300 flex items-center gap-1.5">
                            <Radio class="w-3.5 h-3.5 text-teal-400" :class="isStreaming ? 'animate-pulse text-teal-400' : 'text-slate-400'" />
                            <span>{{ isStreaming ? 'Live Stream Active' : '30 Framework Tools' }}</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-slate-800/70 border border-slate-700/60 text-slate-300 flex items-center gap-1.5">
                            <FolderGit2 class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ investigations?.length || 0 }} Active Cases</span>
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
                                <span>Export Dossier</span>
                                <ChevronDown class="w-3 h-3 text-slate-500" />
                            </button>

                            <div
                                v-if="showExportMenu"
                                class="absolute right-0 mt-2 w-48 bg-slate-900/95 border border-slate-800 rounded-xl shadow-2xl py-1 z-50 font-mono text-xs backdrop-blur-md"
                            >
                                <button
                                    type="button"
                                    @click="exportReport('json')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800/80 text-slate-300 hover:text-white flex items-center gap-2"
                                >
                                    <Database class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>Export JSON Schema</span>
                                </button>
                                <button
                                    type="button"
                                    @click="exportReport('markdown')"
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-800/80 text-slate-300 hover:text-white flex items-center gap-2 border-t border-slate-800/60"
                                >
                                    <Terminal class="w-3.5 h-3.5 text-teal-400" />
                                    <span>Export Markdown Doc</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Input & Probe Bar (Full Width Edge-to-Edge) -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl w-full">
                <form @submit.prevent="executeLookup()" class="space-y-3.5">
                    <div class="flex flex-col md:flex-row items-stretch gap-3">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <Mail class="w-4 h-4 text-teal-400/80" />
                            </div>
                            <input
                                v-model="emailInput"
                                type="email"
                                placeholder="Enter target email (e.g. target.analyst@company.org)..."
                                class="w-full pl-10 pr-10 py-2.5 bg-slate-950/90 border border-slate-800 focus:border-teal-500/80 focus:ring-1 focus:ring-teal-500/80 rounded-xl text-slate-100 placeholder-slate-500 font-mono text-xs transition shadow-inner"
                                required
                            />
                            <button
                                v-if="emailInput"
                                type="button"
                                @click="emailInput = ''"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <label class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-950/60 border border-slate-800 text-slate-300 text-xs font-mono cursor-pointer hover:border-slate-700 transition select-none">
                                <input
                                    v-model="probeLive"
                                    type="checkbox"
                                    class="rounded bg-slate-900 border-slate-700 text-teal-500 focus:ring-0 focus:ring-offset-0 w-3.5 h-3.5"
                                />
                                <span>Live MX &amp; Blacklist Probe</span>
                            </label>

                            <button
                                type="submit"
                                :disabled="isProbing || !emailInput.trim()"
                                class="px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider flex items-center gap-2 transition shadow-lg"
                                :class="isProbing || !emailInput.trim() 
                                    ? 'bg-slate-800 text-slate-500 border border-slate-700/60 cursor-not-allowed' 
                                    : 'bg-teal-500 hover:bg-teal-400 text-slate-950 shadow-teal-950/40 cursor-pointer'"
                            >
                                <Loader2 v-if="isProbing" class="w-3.5 h-3.5 animate-spin" />
                                <Search v-else class="w-3.5 h-3.5" />
                                <span>{{ isProbing ? 'Probing Target...' : 'Inspect Target' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Sample Presets -->
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono text-slate-500 pt-1">
                        <span class="text-slate-600 font-semibold uppercase text-[10px] tracking-wider">Example Targets:</span>
                        <button
                            type="button"
                            @click="setDemoEmail('satoshin@gmx.com')"
                            class="px-2 py-0.5 rounded bg-slate-950/80 hover:bg-slate-800/80 border border-slate-800/90 text-slate-400 hover:text-teal-300 transition"
                        >
                            satoshin@gmx.com
                        </button>
                        <button
                            type="button"
                            @click="setDemoEmail('security@github.com')"
                            class="px-2 py-0.5 rounded bg-slate-950/80 hover:bg-slate-800/80 border border-slate-800/90 text-slate-400 hover:text-cyan-300 transition"
                        >
                            security@github.com
                        </button>
                        <button
                            type="button"
                            @click="setDemoEmail('operator@tempmail.com')"
                            class="px-2 py-0.5 rounded bg-slate-950/80 hover:bg-slate-800/80 border border-slate-800/90 text-slate-400 hover:text-rose-300 transition"
                        >
                            operator@tempmail.com (Burner)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Live SSE Reconnaissance Stream Telemetry Bar -->
            <div v-if="isStreaming || streamProgress.probed > 0" class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-teal-500/30 shadow-xl w-full font-mono space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-2.5 h-2.5 rounded-full" :class="isStreaming ? 'bg-teal-400 animate-ping' : 'bg-slate-500'"></div>
                        <span class="font-bold text-slate-200 uppercase tracking-wider">
                            {{ isStreaming ? 'Executing Real-Time Multi-Tool Stream' : 'Multi-Tool Reconnaissance Complete' }}
                        </span>
                        <span v-if="streamProgress.current_tool" class="text-teal-400 truncate max-w-[200px] sm:max-w-[300px]">
                            &gt; {{ streamProgress.current_tool }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 self-end sm:self-auto">
                        <div class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                            <span>{{ streamProgress.probed }} / {{ streamProgress.total }} Tools Probed</span>
                            <span>•</span>
                            <span class="text-teal-300 font-bold">{{ streamProgress.found }} Findings</span>
                            <span v-if="streamProgress.duration_ms > 0" class="text-slate-500">• {{ streamProgress.duration_ms }}ms</span>
                        </div>

                        <button
                            v-if="isStreaming"
                            type="button"
                            @click="stopLiveStream()"
                            class="px-2.5 py-1 rounded-lg bg-rose-950/80 hover:bg-rose-900 border border-rose-800 text-rose-300 text-[11px] font-bold uppercase flex items-center gap-1 transition"
                        >
                            <StopCircle class="w-3 h-3 text-rose-400" />
                            <span>Stop Stream</span>
                        </button>
                    </div>
                </div>

                <!-- Progress Bar with Active Scanning, Glow & Glistening Complete Animations -->
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

                <!-- Stream Output Telemetry Counters -->
                <div class="flex flex-wrap items-center gap-2 text-[10px] text-slate-400 pt-0.5">
                    <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-300">
                        Probed: <strong class="text-slate-100">{{ streamSummaryCounts.total }}</strong>
                    </span>
                    <span class="px-2 py-0.5 rounded bg-teal-950/60 border border-teal-800/60 text-teal-300">
                        Active Findings: <strong class="text-teal-200">{{ streamSummaryCounts.hits }}</strong>
                    </span>
                    <span class="px-2 py-0.5 rounded bg-cyan-950/60 border border-cyan-800/60 text-cyan-300">
                        Clean / Ready: <strong class="text-cyan-200">{{ streamSummaryCounts.clean }}</strong>
                    </span>
                    <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">
                        Paid / External (Skipped): <strong class="text-slate-300">{{ streamSummaryCounts.skipped }}</strong>
                    </span>
                </div>
            </div>

            <!-- Automated Intelligence & Telemetry Cards (When Target Analyzed) -->
            <div v-if="probeData" class="space-y-4 w-full">
                <!-- Top Telemetry Row: 3 Grid Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 font-mono text-xs w-full">
                    <!-- Card 1: RFC Syntax & Components -->
                    <div class="p-4 sm:p-5 rounded-xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-slate-400 mb-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500">RFC 5322 Syntax &amp; Decomposition</span>
                                <span 
                                    class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border"
                                    :class="probeData.syntax?.is_valid ? 'bg-teal-950/80 text-teal-300 border-teal-800/70' : 'bg-rose-950/80 text-rose-300 border-rose-800/70'"
                                >
                                    {{ probeData.syntax?.is_valid ? 'Valid RFC Syntax' : 'Invalid Syntax' }}
                                </span>
                            </div>

                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Local Part (Alias):</span>
                                    <span class="text-slate-200 font-semibold truncate max-w-[180px]">{{ probeData.syntax?.local_part || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Domain Host:</span>
                                    <span class="text-teal-300 font-semibold truncate max-w-[180px]">{{ probeData.syntax?.domain || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Plus Tagging:</span>
                                    <span :class="probeData.syntax?.has_plus_addressing ? 'text-amber-400 font-bold' : 'text-slate-400'">
                                        {{ probeData.syntax?.has_plus_addressing ? `+${probeData.syntax?.tag}` : 'None' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="text-[10px] text-slate-500 pt-1">
                            Length: {{ probeData.syntax?.length || 0 }} chars (Within RFC limit)
                        </div>
                    </div>

                    <!-- Card 2: Live DNS MX Resolution -->
                    <div class="p-4 sm:p-5 rounded-xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-slate-400 mb-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Live DNS MX Routing</span>
                                <span 
                                    class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border"
                                    :class="probeData.mx?.has_mx ? 'bg-cyan-950/80 text-cyan-300 border-cyan-800/70' : 'bg-slate-900 text-slate-400 border-slate-800'"
                                >
                                    {{ probeData.mx?.has_mx ? 'Active MX Routing' : 'No MX Records' }}
                                </span>
                            </div>

                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Mail Provider:</span>
                                    <span class="text-cyan-300 font-bold truncate max-w-[180px]">{{ probeData.mx?.provider || 'Unknown' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Primary MX:</span>
                                    <span class="text-slate-200 font-mono truncate max-w-[180px]" :title="probeData.mx?.primary_host || 'None'">
                                        {{ probeData.mx?.primary_host || 'None' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Total MX Nodes:</span>
                                    <span class="text-slate-300">{{ probeData.mx?.records?.length || 0 }} Exchange Servers</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-[10px] text-slate-500 pt-1 truncate" :title="probeData.mx?.message">
                            {{ probeData.mx?.message }}
                        </div>
                    </div>

                    <!-- Card 3: Domain Hygiene & Risk -->
                    <div class="p-4 sm:p-5 rounded-xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-slate-400 mb-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Domain Classification &amp; Risk</span>
                                <span 
                                    class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border"
                                    :class="[
                                        probeData.hygiene?.risk_level === 'critical' ? 'bg-rose-950/80 text-rose-300 border-rose-800/70' :
                                        probeData.hygiene?.risk_level === 'medium' ? 'bg-amber-950/80 text-amber-300 border-amber-800/70' :
                                        'bg-teal-950/80 text-teal-300 border-teal-800/70'
                                    ]"
                                >
                                    Risk: {{ (probeData.hygiene?.risk_level || 'low').toUpperCase() }}
                                </span>
                            </div>

                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Classification:</span>
                                    <span class="text-slate-200 font-semibold capitalize">
                                        {{ (probeData.hygiene?.classification || 'corporate').replace(/_/g, ' ') }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Disposable Burner:</span>
                                    <span :class="probeData.hygiene?.is_disposable ? 'text-rose-400 font-bold' : 'text-slate-400'">
                                        {{ probeData.hygiene?.is_disposable ? 'YES (High Exposure)' : 'NO' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-800/60">
                                    <span class="text-slate-500">Free Consumer Mail:</span>
                                    <span :class="probeData.hygiene?.is_free_webmail ? 'text-amber-400 font-bold' : 'text-slate-400'">
                                        {{ probeData.hygiene?.is_free_webmail ? 'YES (Public Webmail)' : 'NO (Private Domain)' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="text-[10px] text-slate-500 pt-1">
                            {{ probeData.hygiene?.is_disposable ? 'Temporary mailbox service detected.' : 'Organizational or webmail delivery domain.' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Quick-Jump Filter Tabs & In-Page Search Bar -->
            <div class="sticky top-0 z-20 bg-slate-950/95 backdrop-blur-md py-3 border-y border-slate-800/80 w-full space-y-2.5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Branch Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs">
                        <button
                            type="button"
                            @click="activeBranch = 'all'"
                            class="px-3 py-1.5 rounded-lg transition font-semibold flex items-center gap-1.5"
                            :class="activeBranch === 'all' 
                                ? 'bg-teal-500/20 text-teal-300 border border-teal-500/50' 
                                : 'bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800'"
                        >
                            <span>All Branches</span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-950/80 border border-slate-800">
                                {{ allToolsList.length }}
                            </span>
                        </button>

                        <button
                            v-for="branch in branchDefinitions"
                            :key="branch.id"
                            type="button"
                            @click="activeBranch = branch.id"
                            class="px-3 py-1.5 rounded-lg transition font-semibold flex items-center gap-1.5"
                            :class="activeBranch === branch.id 
                                ? 'bg-teal-500/20 text-teal-300 border border-teal-500/50' 
                                : 'bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800'"
                        >
                            <span>{{ branch.name }}</span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-950/80 border border-slate-800">
                                {{ categorizedTools[branch.id]?.length || 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Right Controls: Toggle Filter and Search Bar -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showHitsOnly = !showHitsOnly"
                            class="px-2.5 py-1.5 rounded-lg border font-mono text-xs flex items-center gap-1.5 transition select-none"
                            :class="showHitsOnly 
                                ? 'bg-teal-950/80 border-teal-800 text-teal-300' 
                                : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'"
                            title="Filter out skipped/external tools"
                        >
                            <Eye class="w-3.5 h-3.5" />
                            <span>{{ showHitsOnly ? 'Active Probes Only' : 'Show All 30 Tools' }}</span>
                        </button>

                        <div class="relative w-full sm:w-64">
                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-500">
                                <Filter class="w-3.5 h-3.5" />
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Filter tools by name, tag..."
                                class="w-full pl-8 pr-7 py-1.5 bg-slate-900 border border-slate-800 focus:border-teal-500/60 rounded-lg text-slate-200 placeholder-slate-500 font-mono text-xs transition"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="searchQuery = ''"
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-500 hover:text-slate-300"
                            >
                                <X class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- The 5 Branch Sections with Tool Output Cards -->
            <div class="space-y-8 w-full">
                <div
                    v-for="branch in branchDefinitions"
                    :key="branch.id"
                    v-show="activeBranch === 'all' || activeBranch === branch.id"
                    :id="'branch-' + branch.id"
                    class="space-y-4 w-full"
                >
                    <!-- Branch Header -->
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-800/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-6 h-6 rounded-lg bg-teal-950/60 border border-teal-800/50 flex items-center justify-center text-teal-400">
                                <component :is="branch.icon" class="w-3.5 h-3.5" />
                            </div>
                            <div>
                                <h2 class="text-sm font-mono font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                    <span>{{ branch.name }}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-900 border border-slate-800 text-teal-400">
                                        {{ filteredToolsByBranch[branch.id]?.length || 0 }} Tools
                                    </span>
                                </h2>
                            </div>
                        </div>
                        <p class="hidden sm:block text-[11px] font-mono text-slate-400">
                            {{ branch.description }}
                        </p>
                    </div>

                    <!-- Tools Grid for this Branch -->
                    <div v-if="filteredToolsByBranch[branch.id]?.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 w-full">
                        <div
                            v-for="tool in filteredToolsByBranch[branch.id]"
                            :key="tool.id"
                            class="p-4 sm:p-5 rounded-xl bg-slate-900/80 border transition-all flex flex-col justify-between group shadow-sm font-mono"
                            :class="[
                                getToolResult(tool.id)?.status === 'found' ? 'border-teal-500/50 hover:border-teal-400' :
                                getToolResult(tool.id)?.status === 'external_skipped' ? 'border-slate-800/60 opacity-85' :
                                'border-slate-800/90 hover:border-teal-500/40'
                            ]"
                        >
                            <div class="space-y-3">
                                <!-- Top row: Name, Output Status & Tag Badge -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div 
                                            class="w-2 h-2 rounded-full shrink-0"
                                            :class="[
                                                getToolResult(tool.id)?.status === 'found' ? 'bg-teal-400 animate-pulse' :
                                                getToolResult(tool.id)?.status === 'external_skipped' ? 'bg-slate-600' :
                                                (getToolResult(tool.id)?.status === 'clean' ? 'bg-emerald-400' : 'bg-cyan-400')
                                            ]"
                                        ></div>
                                        <h3 class="text-xs font-bold text-slate-100 group-hover:text-teal-300 transition-colors truncate">
                                            {{ tool.name }}
                                        </h3>
                                    </div>

                                    <!-- Status / Tag Pills -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <!-- Streamed Output Status Pill -->
                                        <span
                                            v-if="getToolResult(tool.id)"
                                            class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border"
                                            :class="[
                                                getToolResult(tool.id).status === 'found' ? 'bg-teal-950 text-teal-300 border-teal-800/70' :
                                                getToolResult(tool.id).status === 'external_skipped' ? 'bg-slate-950 text-slate-500 border-slate-800' :
                                                (getToolResult(tool.id).status === 'error' ? 'bg-rose-950 text-rose-300 border-rose-800/70' : 'bg-slate-950 text-cyan-300 border-cyan-800/60')
                                            ]"
                                        >
                                            {{ getToolResult(tool.id).status === 'external_skipped' ? 'Skipped (Paid/CLI)' : getToolResult(tool.id).status.toUpperCase() }}
                                        </span>

                                        <!-- Tag Badge -->
                                        <span
                                            class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border"
                                            :class="[
                                                tool.tag === 'Tool' ? 'bg-cyan-950/80 text-cyan-300 border-cyan-800/60' :
                                                tool.tag === 'Engine' ? 'bg-teal-950/80 text-teal-300 border-teal-800/60' :
                                                tool.tag === 'Registration' ? 'bg-amber-950/80 text-amber-300 border-amber-800/60' :
                                                tool.tag === 'Paid' ? 'bg-purple-950/80 text-purple-300 border-purple-800/60' :
                                                'bg-slate-800 text-slate-300 border-slate-700'
                                            ]"
                                        >
                                            {{ tool.tag }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Tool Description / Live Output Summary -->
                                <div class="space-y-1.5 text-xs">
                                    <div v-if="getToolResult(tool.id)" class="p-2.5 rounded-lg bg-slate-950/80 border border-slate-800/80 text-[11px] leading-relaxed">
                                        <div class="text-slate-300 flex items-start gap-1.5">
                                            <span class="text-teal-400 font-bold shrink-0">&gt;</span>
                                            <span>{{ getToolResult(tool.id).summary }}</span>
                                        </div>

                                        <!-- Tool-Specific Live Extracted Data Card Output -->
                                        <!-- 1. Hudson Rock Live Infostealer Infections Output -->
                                        <div v-if="tool.id === 'hudson_rock' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-2 text-[10px]">
                                            <div v-if="getToolResult(tool.id).data.stealers_count > 0" class="space-y-2">
                                                <div class="flex items-center justify-between text-rose-400 font-bold bg-rose-950/50 px-2 py-1 rounded border border-rose-800/50">
                                                    <span class="flex items-center gap-1.5">
                                                        <AlertTriangle class="w-3.5 h-3.5 text-rose-400 animate-pulse shrink-0" />
                                                        <span>{{ getToolResult(tool.id).data.stealers_count }} Infostealer Infection(s) Discovered</span>
                                                    </span>
                                                    <span class="text-[9px] bg-rose-900/80 px-1.5 py-0.5 rounded text-rose-200">MALWARE EXPOSURE</span>
                                                </div>

                                                <div class="space-y-1.5 max-h-44 overflow-y-auto no-scrollbar">
                                                    <div 
                                                        v-for="(st, sIdx) in getToolResult(tool.id).data.stealers" 
                                                        :key="sIdx"
                                                        class="p-2 rounded bg-slate-900/90 border border-rose-900/50 space-y-1 text-[10px]"
                                                    >
                                                        <div class="flex items-center justify-between text-slate-300">
                                                            <span class="font-bold text-slate-100 flex items-center gap-1">
                                                                <Terminal class="w-3 h-3 text-rose-400" />
                                                                {{ st.computer_name || 'Infected PC' }}
                                                            </span>
                                                            <span class="text-[9px] text-slate-400">{{ st.date_compromised ? st.date_compromised.split('T')[0] : 'Compromised' }}</span>
                                                        </div>
                                                        <div class="flex items-center justify-between text-slate-400 text-[9px]">
                                                            <span>OS: <strong class="text-slate-300">{{ st.operating_system || 'Windows' }}</strong></span>
                                                            <span>IP: <strong class="text-slate-300 font-mono">{{ st.ip || 'Masked' }}</strong></span>
                                                        </div>
                                                        <!-- Compromised Passwords -->
                                                        <div v-if="st.top_passwords && st.top_passwords.length" class="pt-0.5">
                                                            <div class="text-[9px] text-rose-300/90 mb-0.5 font-semibold">Exposed Passwords (Sample):</div>
                                                            <div class="flex flex-wrap gap-1">
                                                                <span 
                                                                    v-for="(pwd, pIdx) in st.top_passwords.slice(0, 4)" 
                                                                    :key="pIdx"
                                                                    class="px-1.5 py-0.2 bg-rose-950/90 border border-rose-800/70 text-rose-200 font-mono text-[9px] rounded select-all cursor-pointer hover:border-rose-400"
                                                                    @click="copyToClipboard(pwd)"
                                                                    title="Click to copy password"
                                                                >
                                                                    {{ pwd }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-[9px] text-slate-400 pt-0.5 flex justify-between">
                                                    <span>Compromised User Services: <strong class="text-slate-200">{{ getToolResult(tool.id).data.total_user_services || 0 }}</strong></span>
                                                    <span>Corporate Services: <strong class="text-slate-200">{{ getToolResult(tool.id).data.total_corporate_services || 0 }}</strong></span>
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center gap-1.5 text-emerald-400 bg-emerald-950/40 px-2 py-1 rounded border border-emerald-800/40 text-[10px]">
                                                <CheckCircle2 class="w-3 h-3 text-emerald-400 shrink-0" />
                                                <span>Clean: Zero infostealer infections cataloged in Hudson Rock Cavalier index.</span>
                                            </div>
                                        </div>

                                        <!-- 2. Sylva Identity & GitHub Accounts Output -->
                                        <div v-if="tool.id === 'sylva_identity' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1.5 text-[10px]">
                                            <div v-if="getToolResult(tool.id).data.github_users && getToolResult(tool.id).data.github_users.length" class="space-y-1">
                                                <div class="text-slate-500 font-semibold text-[9px] uppercase tracking-wider">Discovered Developer Profiles:</div>
                                                <div 
                                                    v-for="(u, uIdx) in getToolResult(tool.id).data.github_users" 
                                                    :key="uIdx"
                                                    class="flex items-center justify-between p-1.5 rounded bg-slate-900/90 border border-slate-800 text-[10px]"
                                                >
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <img :src="u.avatar_url" class="w-5 h-5 rounded-full border border-teal-500/40 bg-slate-950 shrink-0" />
                                                        <span class="text-teal-300 font-bold truncate">{{ u.login }}</span>
                                                        <span class="text-slate-500 text-[9px]">ID: {{ u.id }}</span>
                                                    </div>
                                                    <a :href="u.html_url" target="_blank" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-teal-300 text-[9px] font-mono shrink-0">
                                                        Profile &rarr;
                                                    </a>
                                                </div>
                                            </div>
                                            <div v-if="getToolResult(tool.id).data.keybase" class="p-1.5 rounded bg-slate-900/90 border border-slate-800 flex items-center justify-between text-[10px]">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <Key class="w-3 h-3 text-cyan-400 shrink-0" />
                                                    <span class="text-slate-300">Keybase:</span>
                                                    <span class="text-cyan-300 font-bold truncate">{{ getToolResult(tool.id).data.keybase.full_name || getToolResult(tool.id).data.keybase.username }}</span>
                                                </div>
                                                <span class="text-[9px] text-slate-500">ID: {{ getToolResult(tool.id).data.keybase.id?.substring(0, 8) }}...</span>
                                            </div>
                                            <div v-if="!getToolResult(tool.id).data.matched" class="text-slate-500 text-[10px]">
                                                No linked public developer or Keybase profiles indexed for this target.
                                            </div>
                                        </div>

                                        <!-- 3. Holehe & OSINT Industries Platform Registration Matrix -->
                                        <div v-if="(tool.id === 'osint_industries' || tool.id === 'holehe') && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1.5 text-[10px]">
                                            <div class="text-slate-500 font-semibold text-[9px] uppercase tracking-wider">Passive Service Registrations:</div>
                                            <div class="grid grid-cols-2 gap-1 font-mono text-[9px]">
                                                <div 
                                                    class="flex items-center justify-between px-2 py-1 rounded border"
                                                    :class="getToolResult(tool.id).data.platforms?.github || getToolResult(tool.id).data.registered_services?.includes('GitHub') ? 'bg-teal-950/60 border-teal-800/60 text-teal-300' : 'bg-slate-900/50 border-slate-800 text-slate-500'"
                                                >
                                                    <span>GitHub</span>
                                                    <span class="font-bold">{{ (getToolResult(tool.id).data.platforms?.github || getToolResult(tool.id).data.registered_services?.includes('GitHub')) ? 'ACTIVE' : 'CLEAN' }}</span>
                                                </div>
                                                <div 
                                                    class="flex items-center justify-between px-2 py-1 rounded border"
                                                    :class="getToolResult(tool.id).data.platforms?.keybase || getToolResult(tool.id).data.registered_services?.includes('Keybase') ? 'bg-cyan-950/60 border-cyan-800/60 text-cyan-300' : 'bg-slate-900/50 border-slate-800 text-slate-500'"
                                                >
                                                    <span>Keybase</span>
                                                    <span class="font-bold">{{ (getToolResult(tool.id).data.platforms?.keybase || getToolResult(tool.id).data.registered_services?.includes('Keybase')) ? 'ACTIVE' : 'CLEAN' }}</span>
                                                </div>
                                                <div 
                                                    class="flex items-center justify-between px-2 py-1 rounded border"
                                                    :class="getToolResult(tool.id).data.platforms?.protonmail || getToolResult(tool.id).data.registered_services?.includes('ProtonMail') ? 'bg-purple-950/60 border-purple-800/60 text-purple-300' : 'bg-slate-900/50 border-slate-800 text-slate-500'"
                                                >
                                                    <span>ProtonMail</span>
                                                    <span class="font-bold">{{ (getToolResult(tool.id).data.platforms?.protonmail || getToolResult(tool.id).data.registered_services?.includes('ProtonMail')) ? 'ACTIVE' : 'CLEAN' }}</span>
                                                </div>
                                                <div 
                                                    class="flex items-center justify-between px-2 py-1 rounded border"
                                                    :class="getToolResult(tool.id).data.platforms?.gravatar || getToolResult(tool.id).data.registered_services?.includes('Gravatar') ? 'bg-amber-950/60 border-amber-800/60 text-amber-300' : 'bg-slate-900/50 border-slate-800 text-slate-500'"
                                                >
                                                    <span>Gravatar</span>
                                                    <span class="font-bold">{{ (getToolResult(tool.id).data.platforms?.gravatar || getToolResult(tool.id).data.registered_services?.includes('Gravatar')) ? 'ACTIVE' : 'CLEAN' }}</span>
                                                </div>
                                            </div>
                                            <div v-if="getToolResult(tool.id).data.cli_command" class="flex items-center justify-between pt-1 text-[9px] text-slate-500">
                                                <span class="truncate">CLI: <code class="text-teal-300 bg-slate-900 px-1 py-0.5 rounded">{{ getToolResult(tool.id).data.cli_command }}</code></span>
                                                <button type="button" @click="copyToClipboard(getToolResult(tool.id).data.cli_command)" class="hover:text-white transition">Copy</button>
                                            </div>
                                        </div>

                                        <!-- 4. Epieos Gravatar / PGP Output -->
                                        <div v-if="tool.id === 'epieos' && getToolResult(tool.id)?.data?.gravatar_hash" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1.5 text-[10px]">
                                            <div class="flex items-center gap-2">
                                                <img 
                                                    :src="getToolResult(tool.id).data.avatar_url" 
                                                    alt="Gravatar" 
                                                    class="w-6 h-6 rounded-full border border-teal-500/50 bg-slate-900 shrink-0" 
                                                />
                                                <div class="truncate flex-1">
                                                    <span class="text-slate-500">Gravatar MD5: </span>
                                                    <span class="text-teal-300 font-mono select-all">{{ getToolResult(tool.id).data.gravatar_hash }}</span>
                                                </div>
                                            </div>
                                            <div class="text-slate-400 flex justify-between items-center text-[9px]">
                                                <span>OpenPGP Keyserver: <strong :class="getToolResult(tool.id).data.pgp_found ? 'text-teal-400' : 'text-slate-400'">{{ getToolResult(tool.id).data.pgp_found ? 'Key Found' : 'None' }}</strong></span>
                                                <a :href="'https://keys.openpgp.org/vks/v1/by-email/' + encodeURIComponent(currentTarget || emailInput)" target="_blank" class="text-cyan-400 hover:underline">Query Keyserver</a>
                                            </div>
                                        </div>

                                        <!-- 5. theHarvester Search Dorks & Commands -->
                                        <div v-if="tool.id === 'theharvester' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="text-slate-500 font-semibold text-[9px] uppercase tracking-wider">Search Engine Harvest Dorks:</div>
                                            <div class="flex flex-wrap gap-1">
                                                <span 
                                                    v-for="(dork, dIdx) in getToolResult(tool.id).data.dorks" 
                                                    :key="dIdx"
                                                    class="px-1.5 py-0.5 rounded bg-slate-900/90 border border-slate-800 text-cyan-300 font-mono text-[9px] select-all cursor-pointer hover:border-cyan-500/50"
                                                    @click="copyToClipboard(dork)"
                                                    title="Click to copy search dork"
                                                >
                                                    {{ dork }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between pt-1 text-[9px] text-slate-500">
                                                <span class="truncate">CLI: <code class="text-teal-300 bg-slate-900 px-1 py-0.5 rounded">{{ getToolResult(tool.id).data.cli_command }}</code></span>
                                                <button type="button" @click="copyToClipboard(getToolResult(tool.id).data.cli_command)" class="hover:text-white transition">Copy</button>
                                            </div>
                                        </div>

                                        <!-- 6. Infoga OpenPGP Registry Output -->
                                        <div v-if="tool.id === 'infoga' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-500">Universal Keyserver:</span>
                                                <span 
                                                    class="px-1.5 py-0.5 rounded font-bold text-[9px] border"
                                                    :class="getToolResult(tool.id).data.pgp_found ? 'bg-teal-950 text-teal-300 border-teal-800' : 'bg-slate-900 text-slate-400 border-slate-800'"
                                                >
                                                    {{ getToolResult(tool.id).data.pgp_found ? 'PGP KEY PUBLISHED' : 'NO KEY ON RECORD' }}
                                                </span>
                                            </div>
                                            <div class="text-slate-400 text-[9px]">
                                                Source: <a :href="getToolResult(tool.id).data.query_url" target="_blank" class="text-teal-400 hover:underline">keys.openpgp.org by-email</a>
                                            </div>
                                        </div>

                                        <!-- 7. Email Format Organizational Schema -->
                                        <div v-if="tool.id === 'email_format' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Corporate Pattern:</span>
                                                <span class="text-teal-300 font-bold font-mono">{{ getToolResult(tool.id).data.primary_format }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Secondary Pattern:</span>
                                                <span class="text-slate-300 font-mono">{{ getToolResult(tool.id).data.secondary_format }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[9px]">
                                                <span class="text-slate-500">Sample Target:</span>
                                                <span class="text-slate-400 font-mono truncate max-w-[160px]">{{ getToolResult(tool.id).data.example }}</span>
                                            </div>
                                        </div>

                                        <!-- 8. Email Permutator Candidate Addresses Matrix Output -->
                                        <div v-if="tool.id === 'email_permutator' && getToolResult(tool.id)?.data?.permutations" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="text-slate-500 font-semibold mb-1">Generated Candidate Addresses:</div>
                                            <div class="max-h-24 overflow-y-auto space-y-1 no-scrollbar">
                                                <div 
                                                    v-for="(p, pIdx) in getToolResult(tool.id).data.permutations.slice(0, 6)" 
                                                    :key="pIdx"
                                                    class="flex items-center justify-between bg-slate-900/60 px-1.5 py-0.5 rounded text-[10px]"
                                                >
                                                    <span class="text-slate-200 select-all truncate">{{ p.address }}</span>
                                                    <span class="text-slate-500 shrink-0 text-[9px]">{{ p.format }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 9. GHunt Google OSINT Output -->
                                        <div v-if="tool.id === 'ghunt' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Google Workspace / Gmail:</span>
                                                <span 
                                                    class="px-1.5 py-0.5 rounded font-bold text-[9px] border"
                                                    :class="getToolResult(tool.id).data.is_google ? 'bg-cyan-950 text-cyan-300 border-cyan-800' : 'bg-slate-900 text-slate-400 border-slate-800'"
                                                >
                                                    {{ getToolResult(tool.id).data.is_google ? 'GOOGLE HOSTED' : 'NON-GOOGLE HOST' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between pt-0.5 text-[9px] text-slate-500">
                                                <span class="truncate">CLI: <code class="text-cyan-300 bg-slate-900 px-1 py-0.5 rounded">{{ getToolResult(tool.id).data.cli_command }}</code></span>
                                                <button type="button" @click="copyToClipboard(getToolResult(tool.id).data.cli_command)" class="hover:text-white transition">Copy</button>
                                            </div>
                                        </div>

                                        <!-- 10. Reacher GitHub & Verification Output -->
                                        <div v-if="tool.id === 'reacher_github' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Mailbox Reachability:</span>
                                                <span 
                                                    class="px-1.5 py-0.5 rounded font-bold text-[9px] border"
                                                    :class="getToolResult(tool.id).data.has_mx ? 'bg-emerald-950 text-emerald-300 border-emerald-800' : 'bg-rose-950 text-rose-300 border-rose-800'"
                                                >
                                                    {{ getToolResult(tool.id).data.has_mx ? 'REACHABLE' : 'UNREACHABLE' }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center text-[9px]">
                                                <span class="text-slate-500">Exchange Host:</span>
                                                <span class="text-slate-300 font-mono truncate max-w-[150px]">{{ getToolResult(tool.id).data.mx_host }}</span>
                                            </div>
                                            <div v-if="getToolResult(tool.id).data.cli_command" class="flex items-center justify-between pt-0.5 text-[9px] text-slate-500">
                                                <span class="truncate">CLI: <code class="text-teal-300 bg-slate-900 px-1 py-0.5 rounded">{{ getToolResult(tool.id).data.cli_command }}</code></span>
                                                <button type="button" @click="copyToClipboard(getToolResult(tool.id).data.cli_command)" class="hover:text-white transition">Copy</button>
                                            </div>
                                        </div>

                                        <!-- 11. Reacher Demo Delivery Routing Output -->
                                        <div v-if="tool.id === 'reacher_demo' && getToolResult(tool.id)?.data?.mx_routing" class="mt-2 pt-2 border-t border-slate-800/80 text-[10px] space-y-0.5">
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Inbound Mail Delivery:</span>
                                                <span class="text-emerald-400 font-bold">{{ getToolResult(tool.id).data.delivery_status }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Primary Exchange Server:</span>
                                                <span class="text-slate-200 truncate max-w-[150px]">{{ getToolResult(tool.id).data.mx_routing.primary_host || 'None' }}</span>
                                            </div>
                                        </div>

                                        <!-- 12. Disposable / Burner Check Output -->
                                        <div v-if="['mailscrap', 'disposable_email_domains', 'disposable_emails_registry', 'burner_email_providers'].includes(tool.id) && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Disposable Burner Check:</span>
                                                <span 
                                                    class="px-1.5 py-0.5 rounded font-bold text-[9px] border"
                                                    :class="getToolResult(tool.id).data.is_disposable ? 'bg-rose-950 text-rose-300 border-rose-800' : 'bg-emerald-950 text-emerald-300 border-emerald-800'"
                                                >
                                                    {{ getToolResult(tool.id).data.is_disposable ? 'DISPOSABLE BURNER' : 'CLEAN / LEGITIMATE' }}
                                                </span>
                                            </div>
                                            <div v-if="getToolResult(tool.id).data.classification" class="text-slate-400 text-[9px]">
                                                Classification: <strong class="text-slate-200 capitalize">{{ getToolResult(tool.id).data.classification.replace(/_/g, ' ') }}</strong>
                                            </div>
                                        </div>

                                        <!-- 13. Email Reputation Output -->
                                        <div v-if="tool.id === 'email_reputation' && getToolResult(tool.id)?.data" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-slate-500">Risk Assessment:</span>
                                                <span 
                                                    class="px-1.5 py-0.5 rounded font-bold text-[9px] border uppercase"
                                                    :class="[
                                                        getToolResult(tool.id).data.risk_level === 'critical' ? 'bg-rose-950 text-rose-300 border-rose-800' :
                                                        getToolResult(tool.id).data.risk_level === 'medium' ? 'bg-amber-950 text-amber-300 border-amber-800' :
                                                        'bg-teal-950 text-teal-300 border-teal-800'
                                                    ]"
                                                >
                                                    {{ getToolResult(tool.id).data.risk_level }} RISK
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center text-[9px]">
                                                <span class="text-slate-500">Deliverability Score:</span>
                                                <span class="text-teal-300 font-bold">{{ getToolResult(tool.id).data.deliverability_score }}/100</span>
                                            </div>
                                        </div>

                                        <!-- 14. MxToolbox DNSBL Server Blacklist Check Output -->
                                        <div v-if="tool.id === 'mxtoolbox' && getToolResult(tool.id)?.data?.servers" class="mt-2 pt-2 border-t border-slate-800/80 space-y-1 text-[10px]">
                                            <div class="flex items-center justify-between text-slate-400 text-[10px] pb-1">
                                                <span>Tested Host: {{ getToolResult(tool.id).data.target_host }}</span>
                                                <span :class="getToolResult(tool.id).data.listed_count > 0 ? 'text-rose-400 font-bold' : 'text-emerald-400 font-bold'">
                                                    {{ getToolResult(tool.id).data.listed_count > 0 ? `${getToolResult(tool.id).data.listed_count} BLACKLISTED` : 'CLEAN (0 Listed)' }}
                                                </span>
                                            </div>
                                            <div class="space-y-1">
                                                <div 
                                                    v-for="(srv, sIdx) in getToolResult(tool.id).data.servers" 
                                                    :key="sIdx"
                                                    class="flex items-center justify-between px-1.5 py-0.5 rounded text-[10px]"
                                                    :class="srv.listed ? 'bg-rose-950/60 text-rose-300' : 'bg-slate-900/60 text-slate-300'"
                                                >
                                                    <span class="truncate">{{ srv.server }}</span>
                                                    <span class="font-bold text-[9px]" :class="srv.listed ? 'text-rose-400' : 'text-emerald-400'">
                                                        {{ srv.listed ? 'LISTED' : 'OK' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 15. Query Selector & Direct Search Launch Output -->
                                        <div v-if="['haveibeenpwned', 'dehashed', 'breach_vip', 'thatsthem', 'hunter', 'voilanorbert', 'skymem', 'melissa_email', 'verify_email'].includes(tool.id) && getToolResult(tool.id)?.data?.query_url" class="mt-2 pt-1.5 border-t border-slate-800/60 space-y-1 text-[10px]">
                                            <div class="flex justify-between items-center text-[9px]">
                                                <span class="text-slate-500">Query Target:</span>
                                                <span class="text-cyan-300 font-mono truncate max-w-[170px]">{{ currentTarget || emailInput }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Default description if tool hasn't streamed yet -->
                                    <p v-else class="text-[11px] text-slate-400 leading-relaxed min-h-[38px]">
                                        {{ tool.description }}
                                    </p>
                                </div>

                                <!-- Query Support Indicator & Latency -->
                                <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="tool.supports_query ? 'bg-teal-400' : 'bg-slate-600'"></span>
                                        <span>{{ tool.supports_query ? 'Direct Query Interpolation' : 'Standard External Tool/Repo' }}</span>
                                    </div>
                                    <span v-if="getToolResult(tool.id)?.response_time_ms !== undefined" class="text-slate-600">
                                        {{ getToolResult(tool.id).response_time_ms }}ms
                                    </span>
                                </div>
                            </div>

                            <!-- Bottom Action Bar -->
                            <div class="pt-3 mt-3 border-t border-slate-800/70 flex items-center justify-between gap-2 font-mono">
                                <!-- Launch Button -->
                                <a
                                    :href="tool.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex-1 px-3 py-1.5 rounded-lg border text-xs font-semibold uppercase tracking-wider flex items-center justify-center gap-1.5 transition shadow"
                                    :class="[
                                        getToolResult(tool.id)?.status === 'external_skipped' 
                                            ? 'bg-slate-900 hover:bg-slate-800 border-slate-800 text-slate-400 hover:text-slate-200' 
                                            : 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-teal-300 hover:text-teal-200'
                                    ]"
                                >
                                    <span>{{ tool.action_label }}</span>
                                    <ExternalLink class="w-3 h-3" />
                                </a>

                                <!-- Copy URL -->
                                <button
                                    type="button"
                                    @click="copyToClipboard(tool.url, tool.id)"
                                    class="p-2 rounded-lg bg-slate-950/80 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 transition"
                                    :title="'Copy URL: ' + tool.url"
                                >
                                    <Check v-if="copiedUrlId === tool.id" class="w-3.5 h-3.5 text-teal-400" />
                                    <Copy v-else class="w-3.5 h-3.5" />
                                </button>

                                <!-- Bookmark to Dossier -->
                                <button
                                    type="button"
                                    @click="openBookmarkModal(tool)"
                                    class="p-2 rounded-lg bg-slate-950/80 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-teal-400 transition"
                                    title="Save Tool Output Finding to Case Dossier"
                                >
                                    <FolderPlus class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state if filter doesn't match -->
                    <div v-else class="p-6 rounded-xl bg-slate-900/40 border border-slate-800/60 text-center font-mono text-xs text-slate-500">
                        No tools in {{ branch.name }} match your search filter "{{ searchQuery }}".
                    </div>
                </div>
            </div>

            <!-- Case Dossier Bookmark Modal -->
            <EvidenceBookmarkModal
                v-model="showBookmarkModal"
                :target="bookmarkTarget"
                :investigations="investigations"
            />
        </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { 
    Search, 
    Users, 
    Terminal, 
    RotateCcw, 
    Loader2, 
    Check, 
    Copy, 
    ExternalLink, 
    Bookmark, 
    Globe, 
    Clock, 
    AlertTriangle, 
    Download 
} from 'lucide-vue-next';

const props = defineProps({
    catalog: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['bookmark']);

// Sub-mode toggle: 'google' (Google Search) | 'probe' (50+ networks)
const personaMode = ref('google');

// --- Google Engine Search State & Results Cards ---
const googleQuery = ref('');
const isSearchingGoogle = ref(false);
const googleResults = ref([]);
const googleStats = ref({ total: 0, provider: 'Google Search Engine', engines: [] });
const googleSearchError = ref(null);
const googleSearchedQuery = ref('');
const copiedGoogleUrl = ref(null);
const copiedAllGoogleUrls = ref(false);

const executeGoogleSearch = async () => {
    const q = googleQuery.value.trim();
    if (!q) return;

    isSearchingGoogle.value = true;
    googleSearchError.value = null;

    try {
        const response = await axios.post('/api/social-recon/persona/google', {
            query: q,
            limit: 35
        });

        if (response.data && response.data.success) {
            googleResults.value = response.data.results || [];
            googleStats.value = {
                total: response.data.total ?? googleResults.value.length,
                provider: response.data.provider || 'Google Search Engine',
                engines: response.data.engines_searched || ['Google']
            };
            googleSearchedQuery.value = q;
        } else {
            googleResults.value = [];
            googleSearchError.value = response.data?.message || 'No search results found';
        }
    } catch (err) {
        googleSearchError.value = err.response?.data?.message || err.message || 'Failed to complete Google search query';
        googleResults.value = [];
    } finally {
        isSearchingGoogle.value = false;
    }
};

const setGoogleExample = (name) => {
    googleQuery.value = name;
    executeGoogleSearch();
};

const resetGoogleSearch = () => {
    googleQuery.value = '';
    googleResults.value = [];
    googleSearchedQuery.value = '';
    googleSearchError.value = null;
    googleStats.value = { total: 0, provider: 'Google Search Engine', engines: [] };
};

const copyCardUrl = (url, id) => {
    navigator.clipboard.writeText(url);
    copiedGoogleUrl.value = id;
    setTimeout(() => {
        if (copiedGoogleUrl.value === id) {
            copiedGoogleUrl.value = null;
        }
    }, 2000);
};

const copyAllGoogleUrls = () => {
    if (!googleResults.value.length) return;
    const urlsText = googleResults.value.map(r => `${r.title}\n${r.url}`).join('\n\n');
    navigator.clipboard.writeText(urlsText);
    copiedAllGoogleUrls.value = true;
    setTimeout(() => {
        copiedAllGoogleUrls.value = false;
    }, 2000);
};

const applyGoogleDork = (operator) => {
    if (operator === 'exact') {
        const trimmed = googleQuery.value.trim();
        if (trimmed && !trimmed.startsWith('"') && !trimmed.endsWith('"')) {
            googleQuery.value = `"${trimmed}"`;
        } else if (!trimmed) {
            googleQuery.value = '""';
        }
    } else {
        googleQuery.value = `${googleQuery.value.trim()} ${operator} `.trimStart();
    }
};

const openGoogleInNewTab = () => {
    const q = googleQuery.value.trim() || googleSearchedQuery.value;
    const url = q ? `https://www.google.com/search?q=${encodeURIComponent(q)}` : 'https://www.google.com';
    window.open(url, '_blank', 'noopener,noreferrer');
};

// --- Network Handle Probe State ---
const targetUsername = ref('');
const selectedCategory = ref('all');
const isProbing = ref(false);
const probeProgress = ref(0);
const probeResults = ref([]);
const showDiscoveredOnly = ref(false);

const categories = [
    { id: 'all', label: 'All Platforms' },
    { id: 'developer', label: 'Dev & Code' },
    { id: 'social', label: 'Social & Identity' },
    { id: 'messaging', label: 'Messaging' },
    { id: 'gaming', label: 'Gaming & Media' },
    { id: 'security', label: 'Security & Research' },
];

const filteredCatalog = computed(() => {
    if (selectedCategory.value === 'all') return props.catalog;
    return props.catalog.filter(p => p.category === selectedCategory.value);
});

const discoveredCount = computed(() => probeResults.value.filter(r => r.exists).length);
const absentCount = computed(() => probeResults.value.filter(r => !r.exists && !r.error).length);

const displayedProbeResults = computed(() => {
    if (!showDiscoveredOnly.value) return probeResults.value;
    return probeResults.value.filter(r => r.exists);
});

// Run real-time progressive scan in concurrent batches of 6
const runPersonaProbe = async () => {
    const username = targetUsername.value.trim();
    if (!username || isProbing.value) return;

    isProbing.value = true;
    probeProgress.value = 0;
    probeResults.value = [];

    const platformsToScan = filteredCatalog.value;
    const total = platformsToScan.length;
    if (total === 0) {
        isProbing.value = false;
        return;
    }

    const batchSize = 6;
    let completed = 0;

    for (let i = 0; i < total; i += batchSize) {
        const slice = platformsToScan.slice(i, i + batchSize);
        const platformIds = slice.map(p => p.id);

        try {
            const res = await axios.post('/api/social-recon/probe-batch', {
                username,
                platform_ids: platformIds,
                category: selectedCategory.value,
            });

            if (res.data?.results) {
                probeResults.value.push(...res.data.results);
            }
        } catch (err) {
            slice.forEach(p => {
                probeResults.value.push({
                    id: p.id,
                    name: p.name,
                    category: p.category,
                    url: p.url.replace('{username}', encodeURIComponent(username)),
                    exists: false,
                    status_code: 0,
                    latency_ms: 0,
                    error: 'Probe unreachable',
                });
            });
        }

        completed += slice.length;
        probeProgress.value = Math.min(100, Math.round((completed / total) * 100));
    }

    isProbing.value = false;
};

// Export Discovered Profiles to JSON
const exportDiscoveredJSON = () => {
    const data = JSON.stringify(probeResults.value.filter(r => r.exists), null, 2);
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `social_persona_${targetUsername.value || 'intel'}.json`;
    a.click();
    URL.revokeObjectURL(url);
};

const handleBookmark = (payload) => {
    emit('bookmark', payload);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Sub-Mode Switcher: Live Google Engine vs. Direct 50-Network Probe -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-3">
            <div class="flex items-center space-x-2 font-mono text-xs">
                <button
                    @click="personaMode = 'google'"
                    type="button"
                    :class="[
                        personaMode === 'google'
                            ? 'bg-amber-500/20 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20'
                            : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-slate-200',
                        'px-3.5 py-2 rounded-xl border transition flex items-center space-x-2 cartoon-btn cursor-pointer'
                    ]"
                >
                    <Search class="w-4 h-4 text-amber-400" />
                    <span>Live Google Engine</span>
                </button>

                <button
                    @click="personaMode = 'probe'"
                    type="button"
                    :class="[
                        personaMode === 'probe'
                            ? 'bg-amber-500/20 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20'
                            : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-slate-200',
                        'px-3.5 py-2 rounded-xl border transition flex items-center space-x-2 cartoon-btn cursor-pointer'
                    ]"
                >
                    <Users class="w-4 h-4 text-cyan-400" />
                    <span>Handle Prober (50+ Networks)</span>
                </button>
            </div>

            <div class="text-[11px] text-slate-500 px-2 hidden md:block">
                <span v-if="personaMode === 'google'">Live Google search engine reconnaissance: returns indexed articles, persona citations, and web results</span>
                <span v-else>Dispatches direct HTTP status probes across global platform username routes</span>
            </div>
        </div>

        <!-- SUB-MODE 1: GOOGLE ENGINE WORKSPACE -->
        <div v-if="personaMode === 'google'" class="space-y-4">
            <!-- Google Search Command Console -->
            <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
                <form @submit.prevent="executeGoogleSearch" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                        <input 
                            v-model="googleQuery"
                            type="text"
                            placeholder="Search the Google engine directly (e.g. Dr.Amos Kibet, &quot;exact match&quot;, site:github.com)..."
                            class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition shadow-inner"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="submit"
                            :disabled="isSearchingGoogle"
                            class="px-5 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase tracking-wider flex items-center justify-center space-x-2 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer shrink-0 disabled:opacity-50"
                            title="Search Google"
                        >
                            <Loader2 v-if="isSearchingGoogle" class="w-4 h-4 animate-spin" />
                            <Search v-else class="w-4 h-4" />
                            <span>{{ isSearchingGoogle ? 'Searching...' : 'Search Google' }}</span>
                        </button>

                        <button 
                            @click="resetGoogleSearch"
                            type="button"
                            class="px-4 py-3.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-600 text-slate-300 hover:text-white font-mono text-xs font-semibold flex items-center space-x-1.5 transition cartoon-btn cursor-pointer shrink-0"
                            title="Clear Query and Results"
                        >
                            <RotateCcw class="w-4 h-4 text-slate-400" />
                            <span class="hidden md:inline">Clear</span>
                        </button>
                    </div>
                </form>

                <!-- Quick Dork Operators & Sample Searches -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80 text-xs font-mono">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-slate-500 uppercase mr-1 text-[11px]">Dork Helpers:</span>
                        <button
                            @click="applyGoogleDork('exact')"
                            type="button"
                            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-amber-300 transition text-[11px] cartoon-btn cursor-pointer"
                            title="Wrap in exact quotes"
                        >
                            "Exact Quote"
                        </button>
                        <button
                            @click="applyGoogleDork('site:linkedin.com')"
                            type="button"
                            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-blue-400 text-slate-300 hover:text-blue-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            site:linkedin.com
                        </button>
                        <button
                            @click="applyGoogleDork('site:twitter.com OR site:x.com')"
                            type="button"
                            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-sky-400 text-slate-300 hover:text-sky-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            site:x.com
                        </button>
                        <button
                            @click="applyGoogleDork('filetype:pdf')"
                            type="button"
                            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-red-400 text-slate-300 hover:text-red-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            filetype:pdf
                        </button>
                        <button
                            @click="applyGoogleDork('inurl:resume OR inurl:cv')"
                            type="button"
                            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-emerald-400 text-slate-300 hover:text-emerald-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            inurl:cv
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-slate-500 text-[11px]">Quick Pivot:</span>
                        <button
                            @click="setGoogleExample('Dr.Amos Kibet')"
                            type="button"
                            class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400/60 text-slate-300 hover:text-amber-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            Dr.Amos Kibet
                        </button>
                        <button
                            @click="setGoogleExample('Satoshi Nakamoto')"
                            type="button"
                            class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400/60 text-slate-300 hover:text-amber-300 transition text-[11px] cartoon-btn cursor-pointer"
                        >
                            Satoshi Nakamoto
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search Results Telemetry & Action Bar -->
            <div 
                v-if="googleSearchedQuery || isSearchingGoogle || googleResults.length > 0" 
                class="px-4 py-3 rounded-2xl bg-slate-950 border border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs font-mono shadow-md"
            >
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <span 
                            class="w-2.5 h-2.5 rounded-full inline-block"
                            :class="isSearchingGoogle ? 'bg-amber-400 animate-pulse' : (googleResults.length > 0 ? 'bg-emerald-400' : 'bg-slate-500')"
                        ></span>
                        <span class="text-slate-200 font-bold">
                            {{ isSearchingGoogle ? 'Querying Google...' : `Found ${googleResults.length} Result${googleResults.length === 1 ? '' : 's'}` }}
                        </span>
                    </div>
                    <span v-if="googleSearchedQuery" class="text-slate-400 hidden sm:inline truncate max-w-xs md:max-w-md">
                        for <span class="text-amber-300 font-bold">"{{ googleSearchedQuery }}"</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-md bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] font-semibold hidden md:inline-block">
                        {{ googleStats.provider || 'Google Engine' }}
                    </span>
                </div>

                <div class="flex items-center space-x-2">
                    <button 
                        v-if="googleResults.length > 0"
                        @click="copyAllGoogleUrls"
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-600 text-slate-300 hover:text-white flex items-center space-x-1.5 transition cartoon-btn cursor-pointer text-[11px]"
                        title="Copy all result titles and URLs"
                    >
                        <Check v-if="copiedAllGoogleUrls" class="w-3.5 h-3.5 text-emerald-400" />
                        <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                        <span>{{ copiedAllGoogleUrls ? 'Copied All' : 'Copy All Links' }}</span>
                    </button>

                    <button 
                        @click="openGoogleInNewTab"
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg bg-blue-950/60 border border-blue-500/40 hover:border-blue-400 text-blue-300 hover:text-blue-200 flex items-center space-x-1.5 transition cartoon-btn cursor-pointer text-[11px]"
                        title="Open search on Google.com in a new tab"
                    >
                        <ExternalLink class="w-3.5 h-3.5 text-blue-400" />
                        <span class="hidden sm:inline">Google Browser</span>
                    </button>

                    <button 
                        v-if="googleResults.length > 0 || googleSearchedQuery"
                        @click="resetGoogleSearch"
                        type="button"
                        class="px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-rose-500/40 text-slate-400 hover:text-rose-300 flex items-center space-x-1.5 transition cartoon-btn cursor-pointer text-[11px]"
                        title="Clear results"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Clear</span>
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton State -->
            <div v-if="isSearchingGoogle" class="space-y-3 font-mono">
                <div v-for="n in 3" :key="n" class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800/80 animate-pulse space-y-3">
                    <div class="flex items-center space-x-2">
                        <div class="w-3.5 h-3.5 rounded-full bg-slate-800"></div>
                        <div class="h-3 w-40 bg-slate-800 rounded"></div>
                        <div class="h-3 w-16 bg-slate-800 rounded ml-auto"></div>
                    </div>
                    <div class="h-5 w-3/4 bg-slate-800 rounded"></div>
                    <div class="h-3 w-1/2 bg-slate-800/60 rounded"></div>
                    <div class="space-y-1.5 pt-1">
                        <div class="h-3.5 w-full bg-slate-800/60 rounded"></div>
                        <div class="h-3.5 w-5/6 bg-slate-800/40 rounded"></div>
                    </div>
                </div>
            </div>

            <!-- Error Alert State -->
            <div 
                v-else-if="googleSearchError" 
                class="p-5 rounded-2xl bg-rose-950/40 border border-rose-800/60 text-rose-200 font-mono text-xs flex items-start justify-between gap-3"
            >
                <div class="flex items-start space-x-3">
                    <AlertTriangle class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <div class="font-bold text-rose-300">Google Search Encountered an Issue</div>
                        <div class="text-rose-200/80 text-[11px] leading-relaxed">{{ googleSearchError }}</div>
                    </div>
                </div>
                <button 
                    @click="executeGoogleSearch"
                    type="button"
                    class="px-3 py-1.5 rounded-lg bg-rose-900/60 border border-rose-700/60 hover:border-rose-400 text-rose-200 text-xs font-semibold cartoon-btn cursor-pointer shrink-0"
                >
                    Retry
                </button>
            </div>

            <!-- Results Cards Layout -->
            <div v-else-if="googleResults.length > 0" class="space-y-3.5 font-mono">
                <div 
                    v-for="(item, idx) in googleResults" 
                    :key="item.id || idx"
                    class="p-5 sm:p-6 rounded-2xl bg-slate-900/90 border border-slate-800 hover:border-amber-500/50 transition-all duration-200 shadow-xl group space-y-3.5 hover:shadow-amber-500/5"
                >
                    <!-- Card Header: Rank, Breadcrumb, Source, Engine -->
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center space-x-2 truncate max-w-full sm:max-w-xl">
                            <!-- Index Rank Badge -->
                            <span class="px-2 py-0.5 rounded-md bg-slate-950 border border-slate-800 text-[11px] text-slate-400 font-semibold shrink-0">
                                #{{ idx + 1 }}
                            </span>

                            <!-- Domain & Breadcrumb -->
                            <div class="flex items-center space-x-1.5 text-xs text-slate-400 truncate">
                                <Globe class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                <span class="text-slate-300 font-semibold truncate">{{ item.breadcrumb || item.domain }}</span>
                            </div>

                            <!-- Source Publication Badge -->
                            <span 
                                v-if="item.source" 
                                class="px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[10px] font-semibold shrink-0"
                            >
                                {{ item.source }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-2 text-[11px] shrink-0">
                            <!-- Published Date -->
                            <span v-if="item.published_at" class="text-slate-500 hidden sm:inline-flex items-center space-x-1">
                                <Clock class="w-3 h-3 text-slate-500" />
                                <span>{{ item.published_at }}</span>
                            </span>

                            <!-- Engine Badge -->
                            <span class="px-2 py-0.5 rounded-md bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] font-semibold">
                                {{ item.engine || 'Google' }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Title -->
                    <div>
                        <a 
                            :href="item.url" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="text-base sm:text-lg font-bold text-amber-400 hover:text-amber-300 hover:underline leading-snug flex items-start justify-between gap-3 group-hover:text-amber-300 transition"
                        >
                            <span>{{ item.title }}</span>
                            <ExternalLink class="w-4 h-4 shrink-0 text-slate-500 group-hover:text-amber-400 transition mt-1" />
                        </a>
                    </div>

                    <!-- Destination URL -->
                    <div>
                        <a 
                            :href="item.url" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="text-xs text-emerald-400/90 hover:text-emerald-300 hover:underline truncate block max-w-full"
                            title="Open link in new tab"
                        >
                            {{ item.url }}
                        </a>
                    </div>

                    <!-- Description Snippet -->
                    <div class="pt-1 border-t border-slate-800/60">
                        <p class="text-sm text-slate-300 font-sans leading-relaxed">
                            {{ item.description || item.snippet || 'No description preview available for this indexed URL.' }}
                        </p>
                    </div>

                    <!-- Action Bar Footer -->
                    <div class="pt-2 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center space-x-2">
                            <a 
                                :href="item.url" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-white text-xs font-mono flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                                title="Open result link"
                            >
                                <ExternalLink class="w-3.5 h-3.5 text-amber-400" />
                                <span>Open Link</span>
                            </a>

                            <button 
                                @click="copyCardUrl(item.url, item.id)"
                                type="button"
                                class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-slate-600 text-slate-300 hover:text-white text-xs font-mono flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                                title="Copy URL to clipboard"
                            >
                                <Check v-if="copiedGoogleUrl === item.id" class="w-3.5 h-3.5 text-emerald-400" />
                                <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                <span>{{ copiedGoogleUrl === item.id ? 'Copied' : 'Copy URL' }}</span>
                            </button>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button 
                                @click="handleBookmark({ title: item.title, url: item.url, snippet: item.description })"
                                type="button"
                                class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-500/50 text-slate-300 hover:text-amber-300 text-xs font-mono flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                                title="Save this finding into the Case Dossier"
                            >
                                <Bookmark class="w-3.5 h-3.5 text-amber-400" />
                                <span>Bookmark to Dossier</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State: Initial -->
            <div v-else-if="!googleSearchedQuery" class="p-8 sm:p-12 rounded-2xl bg-slate-900/60 border border-slate-800/80 text-center space-y-4 font-mono">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mx-auto text-amber-400">
                    <Search class="w-7 h-7" />
                </div>
                <div class="space-y-1.5 max-w-lg mx-auto">
                    <h3 class="text-base font-bold text-white uppercase tracking-wider">
                        Google Search Engine Intelligence
                    </h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-sans">
                        Enter a person's name, target handle, organization, or dork query above to scour Google search results directly into structured intelligence cards without leaving the platform.
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <span class="text-slate-500 text-xs">Try sample:</span>
                    <button 
                        @click="setGoogleExample('Dr.Amos Kibet')" 
                        type="button" 
                        class="px-3 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-amber-300 text-xs transition cartoon-btn cursor-pointer"
                    >
                        Dr.Amos Kibet
                    </button>
                    <button 
                        @click="setGoogleExample('Satoshi Nakamoto')" 
                        type="button" 
                        class="px-3 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-amber-300 text-xs transition cartoon-btn cursor-pointer"
                    >
                        Satoshi Nakamoto
                    </button>
                </div>
            </div>

            <!-- Empty State: 0 Results -->
            <div v-else class="p-8 sm:p-12 rounded-2xl bg-slate-900/60 border border-slate-800/80 text-center space-y-4 font-mono">
                <div class="w-14 h-14 rounded-2xl bg-slate-800/50 border border-slate-700/50 flex items-center justify-center mx-auto text-slate-400">
                    <AlertTriangle class="w-7 h-7" />
                </div>
                <div class="space-y-1.5 max-w-lg mx-auto">
                    <h3 class="text-base font-bold text-white uppercase tracking-wider">
                        No Google Results Found
                    </h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-sans">
                        No indexed links were returned for <span class="text-amber-400 font-mono">"{{ googleSearchedQuery }}"</span>. Try loosening dork operators or checking for spelling variations.
                    </p>
                </div>
                <div class="flex items-center justify-center gap-2 pt-2">
                    <button 
                        @click="openGoogleInNewTab"
                        type="button" 
                        class="px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 hover:border-blue-400 text-slate-300 hover:text-blue-300 text-xs font-mono flex items-center space-x-2 transition cartoon-btn cursor-pointer"
                    >
                        <ExternalLink class="w-3.5 h-3.5 text-blue-400" />
                        <span>Open Query in Google Browser</span>
                    </button>
                    <button 
                        @click="resetGoogleSearch"
                        type="button" 
                        class="px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-600 text-slate-400 hover:text-white text-xs font-mono transition cartoon-btn cursor-pointer"
                    >
                        Clear Search
                    </button>
                </div>
            </div>
        </div>

        <!-- SUB-MODE 2: 50-NETWORK HANDLE PROBE -->
        <div v-else-if="personaMode === 'probe'" class="space-y-6">
            <!-- Search & Filter Controls -->
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-5">
                <form @submit.prevent="runPersonaProbe" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Terminal class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                        <input 
                            v-model="targetUsername"
                            type="text"
                            required
                            placeholder="Enter target handle, alias, or username (e.g. satoshi, octocat)..."
                            class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition shadow-inner"
                        />
                    </div>

                    <button 
                        type="submit"
                        :disabled="isProbing || !targetUsername.trim()"
                        class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase tracking-wider flex items-center justify-center space-x-2 transition shadow-lg shadow-amber-500/25 disabled:opacity-50 disabled:cursor-not-allowed cartoon-btn cursor-pointer shrink-0"
                    >
                        <Loader2 v-if="isProbing" class="w-4 h-4 animate-spin" />
                        <Users v-else class="w-4 h-4" />
                        <span>{{ isProbing ? 'Probing Networks...' : 'Launch Persona Probe' }}</span>
                    </button>
                </form>

                <!-- Category Filter Buttons & Scope Selection -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/80 text-xs font-mono">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-slate-500 uppercase mr-1">Category:</span>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="selectedCategory = cat.id"
                            :class="[
                                selectedCategory === cat.id 
                                    ? 'bg-amber-500/20 border-amber-500/60 text-amber-300 font-bold' 
                                    : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-slate-200',
                                'px-2.5 py-1 rounded-lg border transition cartoon-btn cursor-pointer'
                            ]"
                        >
                            {{ cat.label }}
                        </button>
                    </div>

                    <!-- Filter Options: Show Discovered Only & Export -->
                    <div v-if="probeResults.length > 0" class="flex items-center gap-2">
                        <label class="flex items-center space-x-2 text-slate-400 cursor-pointer select-none">
                            <input 
                                v-model="showDiscoveredOnly"
                                type="checkbox"
                                class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-0"
                            />
                            <span>Discovered Only ({{ discoveredCount }})</span>
                        </label>

                        <button
                            v-if="discoveredCount > 0"
                            @click="exportDiscoveredJSON"
                            class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-700 hover:border-amber-400 text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                        >
                            <Download class="w-3 h-3" />
                            <span>Export JSON</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Progressive Scan Progress Bar -->
            <div v-if="isProbing || probeProgress > 0" class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-2 font-mono text-xs">
                <div class="flex items-center justify-between text-slate-300">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full" :class="isProbing ? 'bg-amber-400 animate-ping' : 'bg-emerald-400'"></span>
                        <span class="font-bold uppercase">{{ isProbing ? 'Probing Multi-Network Matrix...' : 'Scan Complete' }}</span>
                    </div>
                    <span class="text-amber-400 font-bold">{{ probeProgress }}%</span>
                </div>

                <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
                    <div 
                        class="bg-gradient-to-r from-amber-500 to-emerald-400 h-2 rounded-full transition-all duration-300"
                        :style="{ width: `${probeProgress}%` }"
                    ></div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                    <span>Resolved: {{ probeResults.length }} / {{ filteredCatalog.length }}</span>
                    <div class="flex items-center space-x-3">
                        <span class="text-emerald-400 font-bold">Discovered: {{ discoveredCount }}</span>
                        <span class="text-slate-500">Absent: {{ absentCount }}</span>
                    </div>
                </div>
            </div>

            <!-- Discovered Grid Results -->
            <div v-if="displayedProbeResults.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div 
                    v-for="item in displayedProbeResults" 
                    :key="item.id"
                    class="p-4 rounded-2xl border transition-all duration-200 flex flex-col justify-between cartoon-card"
                    :class="[
                        item.exists 
                            ? 'bg-slate-900/90 border-emerald-500/40 hover:border-emerald-400 shadow-sm shadow-emerald-950/20' 
                            : 'bg-slate-950/60 border-slate-800/80 opacity-60 hover:opacity-100'
                    ]"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="font-mono font-bold text-sm" :class="item.exists ? 'text-white' : 'text-slate-400'">{{ item.name }}</span>
                                <span class="text-[9px] font-mono px-1.5 py-0.5 rounded uppercase tracking-wider bg-slate-950 border border-slate-800 text-slate-400">{{ item.category }}</span>
                            </div>
                            <span 
                                class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full border flex items-center gap-1"
                                :class="item.exists ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300' : 'bg-slate-900 border-slate-800 text-slate-500'"
                            >
                                <Check v-if="item.exists" class="w-3 h-3 text-emerald-400" />
                                <span>{{ item.exists ? 'DISCOVERED' : 'ABSENT' }}</span>
                            </span>
                        </div>

                        <div class="text-[11px] font-mono text-slate-400 truncate max-w-full">
                            {{ item.url }}
                        </div>
                    </div>

                    <div class="pt-3 mt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono">
                        <span class="text-[10px] text-slate-500">{{ item.latency_ms }}ms</span>
                        
                        <div class="flex items-center space-x-2">
                            <button
                                v-if="item.exists"
                                @click="handleBookmark(item)"
                                class="p-1.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-amber-400 transition cursor-pointer"
                                title="Save to Case Dossier"
                            >
                                <Bookmark class="w-3.5 h-3.5" />
                            </button>

                            <a 
                                :href="item.url" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="p-1.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-400 text-slate-400 hover:text-white transition flex items-center space-x-1"
                                title="Open profile in external tab"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State for Probe -->
            <div v-else-if="!isProbing && probeResults.length === 0" class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-3">
                <Users class="w-10 h-10 text-slate-600 mx-auto" />
                <div class="font-serif font-bold text-lg text-slate-300 uppercase">Awaiting Target Persona</div>
                <div class="text-xs text-slate-500 font-mono max-w-md mx-auto">
                    Enter an online handle or alias above to dispatch asynchronous probes across 50+ developer, social, messaging, and security networks.
                </div>
            </div>
        </div>
    </div>
</template>

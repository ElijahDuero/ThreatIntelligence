<script setup>
import { ref, computed, onUnmounted, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { 
    Search, 
    Terminal, 
    ShieldAlert, 
    Globe, 
    Layers, 
    Download, 
    Bookmark, 
    Check, 
    Copy, 
    ExternalLink, 
    Loader2, 
    AlertTriangle, 
    Zap, 
    ShieldCheck 
} from 'lucide-vue-next';
import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';
import { useClipboard } from '@/Utils/clipboard';

const props = defineProps({
    engines: Object,
    investigations: Array,
    recentSearches: Array,
});

// Form state
const query = ref('');
const engine = ref('onionfind');
const amount = ref(15);
const useTor = ref(false);
const uniqueOnly = ref(true);
const deepScrape = ref(false);
const collectImages = ref(false);

// Execution & Streaming State
const isSearching = ref(false);
const streamLogs = ref([]);
const searchResults = ref([]);
const currentSearchId = ref(null);
const executionTime = ref(null);
const activeTab = ref('results'); // 'results' | 'terminal'
const { isCopied, copy: copyUrl } = useClipboard(2000);
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);
const bookmarkedUrls = ref(new Set());

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const qParam = urlParams.get('q');
    const engineParam = urlParams.get('engine');
    if (qParam) {
        query.value = qParam;
        if (engineParam && props.engines[engineParam]) {
            engine.value = engineParam;
        }
        startSearch();
    }
});

let eventSource = null;

const currentEngineMeta = computed(() => {
    return props.engines ? props.engines[engine.value] : null;
});

const startSearch = () => {
    if (!query.value.trim() || isSearching.value) return;

    isSearching.value = true;
    searchResults.value = [];
    streamLogs.value = [];
    currentSearchId.value = null;
    executionTime.value = null;

    addLog(`[INITIALIZE] Dispatching query "${query.value}" via [${engine.value.toUpperCase()}] engine...`);

    const params = new URLSearchParams({
        q: query.value,
        engine: engine.value,
        amount: amount.value.toString(),
        use_tor: useTor.value.toString(),
        unique: uniqueOnly.value.toString(),
        scrape: deepScrape.value.toString(),
        images: collectImages.value.toString(),
    });

    if (eventSource) {
        eventSource.close();
    }

    eventSource = new EventSource(`/search/stream?${params.toString()}`);

    eventSource.addEventListener('status', (e) => {
        const data = JSON.parse(e.data);
        addLog(`[INFO] ${data.message}`);
    });

    eventSource.addEventListener('result', (e) => {
        const result = JSON.parse(e.data);
        searchResults.value.push(result);
        addLog(`[RESULT #${result.idx}] ${result.title} -> ${result.url}`);
    });

    eventSource.addEventListener('result_update', (e) => {
        const update = JSON.parse(e.data);
        const item = searchResults.value.find(r => r.idx === update.idx || (update.url && r.url === update.url));
        if (item) {
            item.scrape_data = update.scrape_data;
            item.is_blacklisted = update.is_blacklisted || item.is_blacklisted;
        }
        const emailCount = update.scrape_data?.emails?.length || 0;
        const docCount = update.scrape_data?.documents?.length || 0;
        addLog(`[INTEL ENRICHED #${update.idx}] Metadata extracted: ${emailCount} emails, ${docCount} documents.`);
    });

    eventSource.addEventListener('error', (e) => {
        try {
            const data = JSON.parse(e.data);
            addLog(`[ERROR] ${data.message}`);
        } catch (err) {
            addLog(`[ERROR] Stream connection closed.`);
        }
    });

    eventSource.addEventListener('done', (e) => {
        const data = JSON.parse(e.data);
        isSearching.value = false;
        currentSearchId.value = data.search_id;
        executionTime.value = data.execution_time;
        addLog(`[COMPLETE] Retrieval finished. ${data.total} results retrieved in ${data.execution_time}s.`);
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    });

    eventSource.onerror = () => {
        isSearching.value = false;
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    };
};

const stopSearch = () => {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    isSearching.value = false;
    addLog(`[ABORTED] Query stopped by user.`);
};

const addLog = (msg) => {
    const timestamp = new Date().toLocaleTimeString();
    streamLogs.value.push(`[${timestamp}] ${msg}`);
};

const bookmarkResult = (item) => {
    openBookmark({
        title: item.title,
        url: item.url,
        notes: `Found via ${item.engine || engine.value} search for: "${query.value}"${item.description ? `\n\n${item.description}` : ''}`,
        severity: 'low',
        investigation_id: props.investigations?.[0]?.id || null,
    });
    bookmarkedUrls.value.add(item.url);
};

onUnmounted(() => {
    if (eventSource) {
        eventSource.close();
    }
});
</script>

<template>
    <div class="space-y-8">
            <!-- Header Section (DigitalManagement Authority Header) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6 reveal-item is-revealed">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-amber-500/30 text-amber-400 text-xs font-mono font-semibold uppercase mb-2">
                        <span>Multi-Engine OSINT Crawler</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                        Deep Web Reconnaissance
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-1 font-sans">
                        Query multiple darknet indexes with real-time SSE streaming, live blacklist checks, and automated deduplication.
                    </p>
                </div>

                <!-- Export Bar if results available -->
                <div v-if="currentSearchId" class="flex items-center space-x-2 shrink-0">
                    <span class="text-xs font-mono text-slate-400">EXPORT:</span>
                    <a 
                        :href="`/export/${currentSearchId}?format=json`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>JSON</span>
                    </a>
                    <a 
                        :href="`/export/${currentSearchId}?format=csv`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>CSV</span>
                    </a>
                    <a 
                        :href="`/export/${currentSearchId}?format=markdown`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>MD REPORT</span>
                    </a>
                </div>
            </div>

            <!-- Search Configuration Console -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 reveal-item is-revealed reveal-delay-100">
                <!-- Search Query Input -->
                <div class="relative">
                    <Search class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
                    <input 
                        v-model="query"
                        @keydown.enter="startSearch"
                        type="text"
                        placeholder="Enter investigation query, onion keyword, marketplace, paste, or domain..."
                        class="w-full bg-slate-900 border border-slate-700/80 rounded-2xl pl-12 pr-36 py-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition shadow-inner"
                    />
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center space-x-2">
                        <button 
                            v-if="isSearching"
                            @click="stopSearch"
                            type="button"
                            class="px-3.5 py-2 rounded-xl bg-red-950/60 border border-red-500/40 text-red-400 hover:bg-red-900/60 text-xs font-mono font-bold transition cartoon-btn"
                        >
                            ABORT
                        </button>
                        <button 
                            v-else
                            @click="startSearch"
                            type="button"
                            :disabled="!query.trim()"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 text-xs font-mono font-bold flex items-center space-x-1.5 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer"
                        >
                            <span>SEARCH</span>
                            <Zap class="w-3.5 h-3.5 fill-slate-950" />
                        </button>
                    </div>
                </div>

                <!-- Parameters Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 pt-2">
                    <!-- Engine Dropdown -->
                    <div class="space-y-2">
                        <label class="text-[11px] font-mono font-semibold text-slate-400 block uppercase tracking-wider">SEARCH ENGINE</label>
                        <select 
                            v-model="engine"
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white focus:outline-none focus:border-amber-400"
                        >
                            <option v-for="(meta, key) in engines" :key="key" :value="key">
                                {{ meta.name }} {{ meta.requires_tor ? '(Tor)' : '' }} {{ meta.filtered ? '[Filtered]' : '[Uncensored]' }}
                            </option>
                        </select>
                    </div>

                    <!-- Results Amount Slider -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-mono font-semibold text-slate-400 uppercase tracking-wider">
                            <span>RESULTS LIMIT</span>
                            <span class="text-amber-400 font-bold font-mono">{{ amount }} ITEMS</span>
                        </div>
                        <input 
                            v-model.number="amount"
                            type="range"
                            min="5"
                            max="50"
                            step="5"
                            class="w-full accent-amber-500 bg-slate-800 rounded-lg h-2 cursor-pointer mt-3"
                        />
                    </div>

                    <!-- Tor SOCKS5 Switch -->
                    <div class="space-y-2 flex flex-col justify-center">
                        <label class="text-[11px] font-mono font-semibold text-slate-400 block uppercase tracking-wider">TOR ROUTING</label>
                        <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                            <input 
                                v-model="useTor"
                                type="checkbox"
                                class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-0 w-4 h-4"
                            />
                            <span class="text-xs font-mono text-slate-300">Route via Tor SOCKS5</span>
                        </label>
                    </div>

                    <!-- Deduplication Switch -->
                    <div class="space-y-2 flex flex-col justify-center">
                        <label class="text-[11px] font-mono font-semibold text-slate-400 block uppercase tracking-wider">DEDUPLICATION</label>
                        <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                            <input 
                                v-model="uniqueOnly"
                                type="checkbox"
                                class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-0 w-4 h-4"
                            />
                            <span class="text-xs font-mono text-slate-300">Omit duplicate titles</span>
                        </label>
                    </div>

                    <!-- Deep Recon Switches (Original darkdump-web) -->
                    <div class="space-y-2 flex flex-col justify-center">
                        <label class="text-[11px] font-mono font-semibold text-slate-400 block uppercase tracking-wider">DEEP RECON</label>
                        <div class="flex flex-col space-y-2">
                            <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                                <input 
                                    v-model="deepScrape"
                                    type="checkbox"
                                    class="rounded bg-slate-900 border-slate-700 text-cyan-400 focus:ring-0 w-4 h-4"
                                />
                                <span class="text-xs font-mono text-cyan-300 font-bold">Deep scrape (Cautious)</span>
                            </label>
                            <label v-if="deepScrape" class="inline-flex items-center space-x-2 cursor-pointer pl-4">
                                <input 
                                    v-model="collectImages"
                                    type="checkbox"
                                    class="rounded bg-slate-900 border-slate-700 text-cyan-400 focus:ring-0 w-3.5 h-3.5"
                                />
                                <span class="text-[11px] font-mono text-slate-300">Scrape images</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Uncensored Engine Alert Callout -->
                <div v-if="currentEngineMeta && !currentEngineMeta.filtered" class="p-4 rounded-2xl bg-amber-950/40 border border-amber-500/40 flex items-start space-x-3 text-xs font-mono text-amber-300">
                    <AlertTriangle class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold uppercase font-serif">Uncensored Engine Advisory:</span> 
                        {{ currentEngineMeta.name }} indexes raw deep web content. While results are filtered against Ahmia's public abuse blacklist, exercise high operational security.
                    </div>
                </div>
            </div>

            <!-- View Switcher Tabs (DigitalManagement Style) -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 reveal-item is-revealed reveal-delay-200">
                <div class="flex space-x-2 sm:space-x-3 font-mono text-xs font-bold">
                    <button 
                        @click="activeTab = 'results'"
                        :class="[
                            activeTab === 'results' 
                                ? 'bg-amber-500/15 border border-amber-500/40 text-amber-300 shadow-sm shadow-amber-500/20' 
                                : 'text-slate-400 hover:text-white border border-transparent',
                            'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn'
                        ]"
                    >
                        <Layers class="w-4 h-4" />
                        <span>RESULTS FEED ({{ searchResults.length }})</span>
                    </button>
                    <button 
                        @click="activeTab = 'terminal'"
                        :class="[
                            activeTab === 'terminal' 
                                ? 'bg-amber-500/15 border border-amber-500/40 text-amber-300 shadow-sm shadow-amber-500/20' 
                                : 'text-slate-400 hover:text-white border border-transparent',
                            'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn'
                        ]"
                    >
                        <Terminal class="w-4 h-4" />
                        <span>LIVE STREAM LOG ({{ streamLogs.length }})</span>
                        <span v-if="isSearching" class="w-2 h-2 rounded-full bg-amber-400 animate-ping ml-1"></span>
                    </button>
                </div>

                <div v-if="executionTime" class="text-xs font-mono text-slate-400">
                    Query completed in <span class="text-amber-400 font-bold">{{ executionTime }}s</span>
                </div>
            </div>

            <!-- Tab 1: Results Feed -->
            <div v-if="activeTab === 'results'" class="space-y-4 reveal-item is-revealed reveal-delay-300">
                <div v-if="isSearching && searchResults.length === 0" class="text-center py-20 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-3">
                    <Loader2 class="w-8 h-8 text-amber-400 animate-spin mx-auto" />
                    <p class="text-xs font-mono text-slate-400">Negotiating SOCKS proxy & streaming engine hits...</p>
                </div>

                <div v-else-if="searchResults.length === 0" class="text-center py-20 bg-slate-950/80 border border-slate-800 rounded-2xl">
                    <Search class="w-8 h-8 text-slate-600 mx-auto mb-2" />
                    <h3 class="text-sm font-serif font-bold text-slate-300 uppercase">Awaiting Reconnaissance</h3>
                    <p class="text-xs text-slate-400 font-mono mt-1">Enter a query above to initiate live darknet streaming.</p>
                </div>

                <!-- Result Cards -->
                <div v-else class="space-y-4">
                    <div 
                        v-for="(item, idx) in searchResults" 
                        :key="idx"
                        class="bg-slate-950/90 border border-slate-800 hover:border-amber-500/50 rounded-2xl p-6 transition-all shadow-xl cartoon-card space-y-3 group"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2.5">
                                    <span class="text-xs font-mono font-black text-amber-400 bg-amber-500/10 border border-amber-500/30 px-2 py-0.5 rounded-md">
                                        #{{ item.idx || (idx + 1) }}
                                    </span>
                                    <h3 class="text-base font-bold text-white group-hover:text-amber-300 transition-colors">
                                        {{ item.title }}
                                    </h3>
                                </div>
                                <div class="text-xs font-mono text-cyan-400 break-all flex items-center space-x-1.5 pt-0.5">
                                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center space-x-1">
                                        <span>{{ item.url }}</span>
                                        <ExternalLink class="w-3 h-3 inline-block shrink-0" />
                                    </a>
                                </div>
                            </div>

                            <!-- Badges -->
                            <div class="flex items-center space-x-2 shrink-0">
                                <span 
                                    v-if="item.is_onion"
                                    class="px-2.5 py-1 rounded-lg bg-purple-950/60 border border-purple-500/40 text-purple-300 font-mono text-[10px] font-bold tracking-wider"
                                >
                                    .ONION
                                </span>
                                <span 
                                    v-if="item.is_blacklisted"
                                    class="px-2.5 py-1 rounded-lg bg-red-950/60 border border-red-500/40 text-red-400 font-mono text-[10px] font-bold flex items-center space-x-1"
                                >
                                    <ShieldAlert class="w-3 h-3" />
                                    <span>BLACKLISTED</span>
                                </span>
                                <span 
                                    v-else-if="item.is_onion"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-950/50 border border-emerald-500/30 text-emerald-300 font-mono text-[10px] flex items-center space-x-1"
                                >
                                    <ShieldCheck class="w-3 h-3 text-emerald-400" />
                                    <span>CLEAN</span>
                                </span>
                            </div>
                        </div>

                        <!-- Snippet -->
                        <p v-if="item.description" class="text-xs text-slate-300 font-sans leading-relaxed">
                            {{ item.description }}
                        </p>

                        <!-- Deep Scrape Inline Intel Box (from original darkdump-web) -->
                        <div v-if="item.scrape_data" class="p-3.5 rounded-2xl bg-slate-950/90 border border-cyan-500/30 font-mono text-xs space-y-2.5">
                            <div class="flex items-center justify-between text-[11px] text-cyan-300 font-bold border-b border-slate-800 pb-1.5">
                                <span class="flex items-center space-x-1.5">
                                    <Globe class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>DEEP RECON INTEL HARVESTED</span>
                                </span>
                                <span class="text-slate-500 font-normal">
                                    {{ item.scrape_data.links_count }} links discovered
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                <div>
                                    <span class="text-slate-400 block uppercase font-bold text-[10px]">EMAILS:</span>
                                    <span class="text-slate-200 truncate block">
                                        {{ item.scrape_data.emails && item.scrape_data.emails.length ? item.scrape_data.emails.join(', ') : 'No emails found' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block uppercase font-bold text-[10px]">DOCUMENTS:</span>
                                    <span class="text-slate-200 truncate block">
                                        {{ item.scrape_data.documents && item.scrape_data.documents.length ? item.scrape_data.documents.map(d => d.name).join(', ') : 'No document files' }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="item.scrape_data.keywords && item.scrape_data.keywords.length > 0" class="pt-1 flex flex-wrap gap-1">
                                <span 
                                    v-for="kw in item.scrape_data.keywords.slice(0, 6)" 
                                    :key="kw"
                                    class="px-2 py-0.5 rounded-md bg-slate-900 border border-slate-800 text-cyan-300 text-[10px]"
                                >
                                    #{{ kw }}
                                </span>
                            </div>
                        </div>

                        <!-- Action Bar for Result Card -->
                        <div class="flex items-center justify-between pt-3 border-t border-slate-800/80 text-xs font-mono">
                            <span class="text-[11px] text-slate-400">
                                Engine: <span class="text-amber-400 font-bold">{{ item.engine }}</span>
                            </span>

                            <div class="flex items-center space-x-3">
                                <button 
                                    @click="copyUrl(item.url, item.url)"
                                    class="text-slate-400 hover:text-amber-400 flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                >
                                    <Check v-if="isCopied(item.url)" class="w-3.5 h-3.5 text-emerald-400" />
                                    <Copy v-else class="w-3.5 h-3.5" />
                                    <span>{{ isCopied(item.url) ? 'Copied' : 'Copy URL' }}</span>
                                </button>

                                <button 
                                    @click="bookmarkResult(item)"
                                    class="text-slate-400 hover:text-amber-400 flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                    :title="bookmarkedUrls.has(item.url) ? 'Bookmarked' : 'Add to Case Dossier'"
                                >
                                    <Bookmark class="w-3.5 h-3.5" :class="bookmarkedUrls.has(item.url) ? 'text-amber-400 fill-amber-400/20' : ''" />
                                    <span>{{ bookmarkedUrls.has(item.url) ? 'Saved' : 'Bookmark' }}</span>
                                </button>

                                <Link 
                                    :href="`/scraper?target=${encodeURIComponent(item.url)}`"
                                    class="text-amber-400 hover:text-amber-300 flex items-center space-x-1 font-bold transition cartoon-btn"
                                >
                                    <Globe class="w-3.5 h-3.5" />
                                    <span>Deep Scrape →</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Terminal Logs -->
            <div v-if="activeTab === 'terminal'" class="bg-slate-950 border border-slate-800 rounded-3xl p-6 font-mono text-xs space-y-1.5 shadow-2xl h-[560px] overflow-y-auto no-scrollbar">
                <div class="text-slate-500 pb-3 border-b border-slate-800 flex items-center justify-between">
                    <span class="font-serif uppercase tracking-wider text-slate-400">--- DARKDUMP REAL-TIME RECONNAISSANCE STREAM ---</span>
                    <span v-if="isSearching" class="text-amber-400 animate-pulse font-bold">STREAM ACTIVE</span>
                </div>
                <div v-if="streamLogs.length === 0" class="text-slate-600 py-4">
                    Awaiting query dispatch...
                </div>
                <div 
                    v-for="(log, idx) in streamLogs" 
                    :key="idx" 
                    :class="[
                        log.includes('[ERROR]') ? 'text-red-400' : '',
                        log.includes('[RESULT') ? 'text-amber-300 font-semibold' : '',
                        log.includes('[COMPLETE]') ? 'text-emerald-300 font-bold' : '',
                        log.includes('[INFO]') ? 'text-slate-400' : 'text-slate-300'
                    ]"
                >
                    {{ log }}
                </div>
            </div>

            <!-- Evidence Bookmark Modal -->
            <EvidenceBookmarkModal
                v-model="showBookmarkModal"
                :target="bookmarkTarget"
                :investigations="investigations"
                source="deep_search"
            />
        </div>
</template>

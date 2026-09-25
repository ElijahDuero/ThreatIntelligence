<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { 
    Globe, 
    Search, 
    Loader2, 
    Check, 
    Copy, 
    ArrowRight, 
    Zap, 
    AlertCircle 
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    isScraping: {
        type: Boolean,
        default: false,
    },
    useTor: {
        type: Boolean,
        default: false,
    },
    crawlSubpages: {
        type: Boolean,
        default: true,
    },
    collectImages: {
        type: Boolean,
        default: true,
    },
    captureScreenshot: {
        type: Boolean,
        default: true,
    },
    customKeywords: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:useTor',
    'update:crawlSubpages',
    'update:collectImages',
    'update:captureScreenshot',
    'update:customKeywords',
    'scrape',
    'select-target',
]);

const discoveryQuery = ref('');
const discoveryEngine = ref('onionfind');
const isDiscovering = ref(false);
const discoveryResults = ref([]);
const discoveryError = ref(null);
const copiedDiscoveryUrl = ref(null);

const discoveryEngines = [
    { id: 'onionfind', name: 'OnionFind', desc: 'Unfiltered Darknet Index' },
    { id: 'ahmia', name: 'Ahmia', desc: 'Verified Onion Index' },
    { id: 'tordex', name: 'TorDex', desc: 'Deep Web Directory' },
    { id: 'vormweb', name: 'VormWeb', desc: 'Global Dark Web Index' },
];

const executeDiscoverySearch = async (query = null) => {
    if (query && typeof query === 'string') {
        discoveryQuery.value = query.trim();
    }
    if (!discoveryQuery.value.trim() || isDiscovering.value) return;

    isDiscovering.value = true;
    discoveryError.value = null;
    discoveryResults.value = [];

    try {
        const res = await axios.get('/api/scraper/discover-targets', {
            params: {
                query: discoveryQuery.value.trim(),
                engine: discoveryEngine.value,
                amount: 15,
            }
        });

        if (res.data?.status === 'success') {
            discoveryResults.value = res.data.targets || [];
            if (discoveryResults.value.length === 0) {
                discoveryError.value = `No .onion results found for "${discoveryQuery.value}" on [${discoveryEngine.value}]. Try switching search engines or using related keywords.`;
            }
        } else {
            discoveryError.value = res.data?.error || 'Failed to query dark web index.';
        }
    } catch (e) {
        discoveryError.value = e.response?.data?.message || e.message || 'Error communicating with dark web search engine.';
    } finally {
        isDiscovering.value = false;
    }
};

const clearDiscoveryResults = () => {
    discoveryResults.value = [];
    discoveryError.value = null;
};

const selectDiscoveredTarget = (url, scrapeImmediately = false) => {
    emit('update:modelValue', url);
    if (url.includes('.onion')) {
        emit('update:useTor', true);
    }
    const q = discoveryQuery.value.trim();
    if (q) {
        emit('update:customKeywords', q);
    }
    emit('select-target', { url, immediate: scrapeImmediately });
    if (scrapeImmediately) {
        emit('scrape');
    }
};

const copyDiscoveryUrl = (url) => {
    navigator.clipboard.writeText(url);
    copiedDiscoveryUrl.value = url;
    setTimeout(() => {
        copiedDiscoveryUrl.value = null;
    }, 2000);
};

const onFormSubmit = () => {
    emit('scrape');
};
</script>

<template>
    <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-4 reveal-item is-revealed reveal-delay-100">
        <form @submit.prevent="onFormSubmit" class="space-y-4">
            <div class="relative">
                <Globe class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
                <input 
                    :value="modelValue"
                    @input="$emit('update:modelValue', $event.target.value)"
                    type="text"
                    placeholder="Enter target URL to deep scrape (e.g. http://site.onion or https://target.com)..."
                    class="w-full bg-slate-900 border border-slate-700/80 rounded-2xl pl-12 pr-44 py-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 font-mono transition shadow-inner"
                />
                <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center space-x-2">
                    <button 
                        type="submit"
                        :disabled="!modelValue.trim() || isScraping"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-400 hover:to-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 text-xs font-mono font-bold flex items-center space-x-1.5 transition shadow-lg shadow-cyan-500/25 cartoon-btn cursor-pointer"
                    >
                        <Loader2 v-if="isScraping" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isScraping ? 'SCRAPING...' : 'SCRAPE TARGET' }}</span>
                    </button>
                </div>
            </div>

            <!-- Target Discovery using Keywords Console -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-purple-500/30 space-y-3.5 cartoon-card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center space-x-2">
                        <Search class="w-4 h-4 text-purple-400" />
                        <span class="text-xs font-mono font-bold text-white uppercase tracking-wider">
                            TARGET DISCOVERY USING KEYWORDS
                        </span>
                        <span class="hidden sm:inline-block px-2 py-0.5 rounded-full bg-purple-950 border border-purple-500/40 text-purple-300 text-[10px] font-mono font-bold">
                            LIVE .ONION RECON
                        </span>
                    </div>

                    <!-- Engine Selector & Clear Action -->
                    <div class="flex items-center gap-2 text-xs font-mono flex-wrap">
                        <div class="flex items-center space-x-1">
                            <span class="text-slate-500 text-[10px] uppercase">Engine:</span>
                            <button 
                                v-for="eng in discoveryEngines"
                                :key="eng.id"
                                type="button"
                                @click="discoveryEngine = eng.id; if (discoveryQuery.trim()) executeDiscoverySearch();"
                                :class="[
                                    discoveryEngine === eng.id ? 'bg-purple-950 border-purple-500/60 text-purple-300 font-bold' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white',
                                    'px-2 py-0.5 rounded-lg border text-[10px] transition cartoon-btn cursor-pointer'
                                ]"
                                :title="eng.desc"
                            >
                                {{ eng.name }}
                            </button>
                        </div>
                        <button
                            v-if="discoveryResults.length > 0 || discoveryQuery"
                            type="button"
                            @click="clearDiscoveryResults(); discoveryQuery = '';"
                            class="text-rose-400 hover:text-rose-300 text-[10px] ml-1 cursor-pointer"
                            title="Clear discovered targets"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Search Input & Discover Button -->
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                        <input 
                            v-model="discoveryQuery"
                            type="text"
                            @keydown.enter.prevent="executeDiscoverySearch()"
                            placeholder="Enter target keyword to find .onion sites (e.g. Crypto, Bitcoin, Leaks, Forum, Market)..."
                            class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-10 pr-4 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-400 focus:ring-1 focus:ring-purple-400 font-mono transition shadow-inner"
                        />
                    </div>
                    <button 
                        type="button"
                        @click="executeDiscoverySearch()"
                        :disabled="!discoveryQuery.trim() || isDiscovering"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-mono font-bold flex items-center justify-center space-x-1.5 transition cartoon-btn cursor-pointer shadow-lg shadow-purple-600/20 shrink-0"
                    >
                        <Loader2 v-if="isDiscovering" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isDiscovering ? 'SEARCHING...' : 'DISCOVER .ONION' }}</span>
                    </button>
                </div>

                <!-- Quick Presets -->
                <div class="flex items-center gap-1.5 text-[11px] font-mono flex-wrap">
                    <span class="text-slate-500 text-[10px] uppercase">Quick Keywords:</span>
                    <button 
                        v-for="kw in ['Crypto', 'Bitcoin', 'Leaks', 'Market', 'Security', 'Exploits', 'Wallets']"
                        :key="kw"
                        type="button"
                        @click="executeDiscoverySearch(kw)"
                        class="text-[10px] px-2 py-0.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 text-slate-400 hover:text-purple-300 transition cursor-pointer cartoon-btn"
                    >
                        {{ kw }}
                    </button>
                </div>

                <!-- Inline Loading -->
                <div v-if="isDiscovering" class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-center space-y-2">
                    <Loader2 class="w-5 h-5 animate-spin text-purple-400 mx-auto" />
                    <p class="text-xs font-mono text-slate-300">
                        Querying darknet index <span class="text-purple-400 font-bold uppercase">[{{ discoveryEngine }}]</span> for <span class="text-white font-bold">"{{ discoveryQuery }}"</span>...
                    </p>
                </div>

                <!-- Inline Error Notice -->
                <div v-else-if="discoveryError" class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs font-mono flex items-start space-x-2.5">
                    <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-rose-400" />
                    <div class="space-y-0.5">
                        <p class="font-bold">Discovery Notice</p>
                        <p class="text-rose-300/90 text-[11px] font-sans">{{ discoveryError }}</p>
                    </div>
                </div>

                <!-- Inline Discovered Targets List -->
                <div v-else-if="discoveryResults.length > 0" class="pt-2 border-t border-slate-800/80 space-y-2.5">
                    <div class="flex items-center justify-between text-xs font-mono text-slate-400 px-1">
                        <span class="font-bold text-purple-300 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                            <span>FOUND {{ discoveryResults.length }} ONION TARGETS FOR "{{ discoveryQuery }}"</span>
                        </span>
                        <button 
                            type="button" 
                            @click="clearDiscoveryResults" 
                            class="text-[11px] text-slate-500 hover:text-slate-300 cursor-pointer"
                        >
                            Dismiss List (✕)
                        </button>
                    </div>

                    <div class="max-h-64 overflow-y-auto space-y-2 pr-1 no-scrollbar">
                        <div 
                            v-for="(target, idx) in discoveryResults" 
                            :key="target.url + idx"
                            class="p-3.5 rounded-xl bg-slate-950 border border-slate-800/90 hover:border-purple-500/40 transition cartoon-card space-y-2"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center space-x-2 truncate min-w-0">
                                    <span 
                                        :class="[
                                            target.is_onion ? 'bg-purple-950 border-purple-500/40 text-purple-300' : 'bg-slate-900 border-slate-700 text-slate-300',
                                            'px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase shrink-0 border'
                                        ]"
                                    >
                                        {{ target.is_onion ? '.ONION' : 'CLEANET' }}
                                    </span>
                                    <h4 class="text-xs font-bold text-white truncate font-sans">
                                        {{ target.title || 'Untitled Target' }}
                                    </h4>
                                </div>

                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button 
                                        type="button"
                                        @click="copyDiscoveryUrl(target.url)"
                                        class="px-2 py-1 rounded-lg bg-slate-900 hover:bg-slate-850 border border-slate-700 text-slate-300 hover:text-white text-[11px] font-mono flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                        :title="'Copy ' + target.url"
                                    >
                                        <Check v-if="copiedDiscoveryUrl === target.url" class="w-3 h-3 text-emerald-400" />
                                        <Copy v-else class="w-3 h-3 text-slate-400" />
                                        <span class="text-[10px]">{{ copiedDiscoveryUrl === target.url ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                    <button 
                                        type="button"
                                        @click="selectDiscoveredTarget(target.url, false)"
                                        class="px-2.5 py-1 rounded-lg bg-cyan-950/80 hover:bg-cyan-900 border border-cyan-500/40 text-cyan-300 hover:text-white text-[11px] font-mono font-bold flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                        title="Load URL into deep scraper"
                                    >
                                        <span>Select Target</span>
                                        <ArrowRight class="w-3 h-3 text-cyan-400" />
                                    </button>
                                    <button 
                                        type="button"
                                        @click="selectDiscoveredTarget(target.url, true)"
                                        class="px-2.5 py-1 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-[11px] font-mono font-bold flex items-center space-x-1 transition shadow cartoon-btn cursor-pointer"
                                        title="Load target and immediately execute deep scrape"
                                    >
                                        <Zap class="w-3 h-3 text-amber-300" />
                                        <span>Scrape Now</span>
                                    </button>
                                </div>
                            </div>

                            <div class="font-mono text-[11px] text-purple-400/90 truncate">
                                {{ target.url }}
                            </div>

                            <p v-if="target.snippet" class="text-[11px] text-slate-400 font-sans line-clamp-2 leading-relaxed">
                                {{ target.snippet }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scrape Execution Options -->
            <div class="flex flex-wrap items-center gap-6 pt-1 text-xs font-mono">
                <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                    <input 
                        :checked="useTor"
                        @change="$emit('update:useTor', $event.target.checked)"
                        type="checkbox"
                        class="rounded bg-slate-900 border-slate-700 text-cyan-500 focus:ring-0 w-4 h-4"
                    />
                    <span class="text-slate-300">Route via Tor SOCKS5 (Auto for .onion)</span>
                </label>

                <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                    <input 
                        :checked="crawlSubpages"
                        @change="$emit('update:crawlSubpages', $event.target.checked)"
                        type="checkbox"
                        class="rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-0 w-4 h-4"
                    />
                    <span class="text-slate-300">Crawl Matching Subpages (Deep keyword discovery)</span>
                </label>

                <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                    <input 
                        :checked="collectImages"
                        @change="$emit('update:collectImages', $event.target.checked)"
                        type="checkbox"
                        class="rounded bg-slate-900 border-slate-700 text-cyan-500 focus:ring-0 w-4 h-4"
                    />
                    <span class="text-slate-300">Extract Visual Gallery & Images</span>
                </label>

                <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                    <input 
                        :checked="captureScreenshot"
                        @change="$emit('update:captureScreenshot', $event.target.checked)"
                        type="checkbox"
                        class="rounded bg-slate-900 border-slate-700 text-cyan-500 focus:ring-0 w-4 h-4"
                    />
                    <span class="text-slate-300">Capture Forensic Screenshot (Headless Chrome)</span>
                </label>
            </div>
        </form>
    </div>
</template>

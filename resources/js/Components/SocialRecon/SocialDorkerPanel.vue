<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    MessageSquare, 
    Sparkles, 
    Loader2, 
    AlertTriangle, 
    ChevronUp, 
    ChevronDown, 
    Clock, 
    ExternalLink, 
    Check, 
    Copy, 
    Bookmark, 
    Globe, 
    CheckCircle2, 
    Zap 
} from 'lucide-vue-next';

const emit = defineEmits(['bookmark']);

// Social activity and comment scout (SSE streaming, 25 platforms)
const activityTarget = ref('');
const activityKeyword = ref('');
const isScoutingActivity = ref(false);
const activityError = ref(null);
const scoutedTarget = ref('');
const copiedActivityId = ref(null);

// SSE streaming state
const scanningPlatform = ref(null);
const scanProgress = ref(0);
const scanTotal = ref(25);
const scanIndex = ref(0);
const scanComplete = ref(false);

// Grouped platform results: { platformId: { name, activities, count, error, status } }
const platformResults = ref({});
const collapsedPlatforms = ref({});

// Surgical dorks
const generatedDorks = ref([]);
const selectedDorkCategory = ref('all');
const copiedDorkIdx = ref(null);
const executingDorkIdx = ref(null);
const dorkLiveResults = ref({});
const dorkLiveErrors = ref({});
const expandedDorkResults = ref({});

const selectedActivityFilter = ref('all'); // 'all' | 'comment' | 'discussion' | 'code' | 'dorks'

const dorkCategories = [
    { id: 'all', label: 'All Dorks' },
    { id: 'social', label: 'Social & Bios' },
    { id: 'messaging', label: 'Messaging & Forums' },
    { id: 'developer', label: 'Code & Repos' },
    { id: 'security', label: 'Leaks & Files' },
];

const platformColorMap = {
    hackernews: { bg: 'bg-orange-500/15', border: 'border-orange-500/40', text: 'text-orange-400' },
    github: { bg: 'bg-purple-500/15', border: 'border-purple-500/40', text: 'text-purple-300' },
    gitlab: { bg: 'bg-orange-600/15', border: 'border-orange-500/40', text: 'text-orange-400' },
    reddit: { bg: 'bg-amber-500/15', border: 'border-amber-500/40', text: 'text-amber-400' },
    lemmy: { bg: 'bg-teal-500/15', border: 'border-teal-500/40', text: 'text-teal-300' },
    stackoverflow: { bg: 'bg-orange-600/15', border: 'border-orange-500/40', text: 'text-orange-300' },
    discourse: { bg: 'bg-indigo-500/15', border: 'border-indigo-500/40', text: 'text-indigo-300' },
    devto: { bg: 'bg-emerald-500/15', border: 'border-emerald-500/40', text: 'text-emerald-300' },
    lobsters: { bg: 'bg-red-700/15', border: 'border-red-600/40', text: 'text-red-400' },
    youtube: { bg: 'bg-red-500/15', border: 'border-red-500/40', text: 'text-red-400' },
    twitter: { bg: 'bg-sky-500/15', border: 'border-sky-500/40', text: 'text-sky-300' },
    medium: { bg: 'bg-green-500/15', border: 'border-green-500/40', text: 'text-green-300' },
    substack: { bg: 'bg-amber-600/15', border: 'border-amber-500/40', text: 'text-amber-400' },
    mastodon: { bg: 'bg-violet-500/15', border: 'border-violet-500/40', text: 'text-violet-300' },
    bluesky: { bg: 'bg-blue-500/15', border: 'border-blue-500/40', text: 'text-blue-300' },
    tumblr: { bg: 'bg-indigo-400/15', border: 'border-indigo-400/40', text: 'text-indigo-300' },
    pinterest: { bg: 'bg-rose-500/15', border: 'border-rose-500/40', text: 'text-rose-400' },
    linkedin: { bg: 'bg-blue-600/15', border: 'border-blue-600/40', text: 'text-blue-400' },
    quora: { bg: 'bg-red-600/15', border: 'border-red-600/40', text: 'text-red-300' },
    producthunt: { bg: 'bg-orange-500/15', border: 'border-orange-500/40', text: 'text-orange-300' },
    facebook: { bg: 'bg-blue-500/15', border: 'border-blue-500/40', text: 'text-blue-400' },
    threads: { bg: 'bg-slate-400/15', border: 'border-slate-400/40', text: 'text-slate-300' },
    snapchat: { bg: 'bg-yellow-400/15', border: 'border-yellow-400/40', text: 'text-yellow-300' },
    vk: { bg: 'bg-sky-600/15', border: 'border-sky-600/40', text: 'text-sky-300' },
    tiktok: { bg: 'bg-pink-500/15', border: 'border-pink-500/40', text: 'text-pink-300' },
};

const getPlatformColors = (platformId) => {
    return platformColorMap[platformId] || { bg: 'bg-cyan-500/15', border: 'border-cyan-500/40', text: 'text-cyan-400' };
};

const allActivities = computed(() => {
    const all = [];
    for (const p of Object.values(platformResults.value)) {
        if (p.activities) all.push(...p.activities);
    }
    return all;
});

const activityStats = computed(() => {
    const acts = allActivities.value;
    return {
        total: acts.length,
        comments: acts.filter(a => a.category === 'comment').length,
        discussions: acts.filter(a => ['discussion', 'post', 'web_mention'].includes(a.category)).length,
        code: acts.filter(a => a.category === 'code').length,
        platforms: Object.keys(platformResults.value).filter(k => (platformResults.value[k]?.count || 0) > 0),
    };
});

const platformsWithResults = computed(() => {
    return Object.entries(platformResults.value)
        .filter(([, p]) => p.count > 0)
        .sort((a, b) => b[1].count - a[1].count)
        .map(([id, p]) => ({ id, ...p }));
});

const platformsEmpty = computed(() => {
    return Object.entries(platformResults.value)
        .filter(([, p]) => p.count === 0 && p.status === 'done')
        .map(([id, p]) => ({ id, ...p }));
});

const filteredDorks = computed(() => {
    if (selectedDorkCategory.value === 'all') return generatedDorks.value;
    return generatedDorks.value.filter(d => d.category === selectedDorkCategory.value);
});

let activeEventSource = null;

const executeActivityScout = () => {
    const target = activityTarget.value.trim();
    if (!target || isScoutingActivity.value) return;

    if (activeEventSource) {
        activeEventSource.close();
        activeEventSource = null;
    }

    isScoutingActivity.value = true;
    activityError.value = null;
    scoutedTarget.value = target;
    scanComplete.value = false;
    scanProgress.value = 0;
    scanIndex.value = 0;
    scanningPlatform.value = null;
    platformResults.value = {};
    collapsedPlatforms.value = {};
    generatedDorks.value = [];
    selectedActivityFilter.value = 'all';

    const params = new URLSearchParams({ target });
    if (activityKeyword.value.trim()) {
        params.set('keyword', activityKeyword.value.trim());
    }

    const eventSource = new EventSource(`/api/social-recon/activity/stream?${params.toString()}`);
    activeEventSource = eventSource;

    eventSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);

            if (data.type === 'status') {
                scanningPlatform.value = data.platform;
                scanIndex.value = data.index;
                scanTotal.value = data.total;
                scanProgress.value = data.progress;
                if (!platformResults.value[data.platform_id]) {
                    platformResults.value[data.platform_id] = {
                        name: data.platform,
                        activities: [],
                        count: 0,
                        error: null,
                        status: 'scanning',
                    };
                }
            } else if (data.type === 'result') {
                scanProgress.value = data.progress;
                platformResults.value[data.platform_id] = {
                    name: data.platform,
                    activities: data.activities || [],
                    count: data.count || 0,
                    error: data.error || null,
                    status: 'done',
                };
                if (data.count === 0) {
                    collapsedPlatforms.value[data.platform_id] = true;
                }
            } else if (data.type === 'complete') {
                generatedDorks.value = data.dorks || [];
                scanComplete.value = true;
                scanProgress.value = 100;
                scanningPlatform.value = null;
                isScoutingActivity.value = false;
                eventSource.close();
                activeEventSource = null;
            }
        } catch (err) {
            // Ignore malformed chunks
        }
    };

    eventSource.onerror = () => {
        if (!scanComplete.value) {
            activityError.value = 'Stream connection lost. Partial results may be shown.';
            isScoutingActivity.value = false;
            scanningPlatform.value = null;
        }
        eventSource.close();
        activeEventSource = null;
    };
};

const togglePlatformCollapse = (platformId) => {
    collapsedPlatforms.value[platformId] = !collapsedPlatforms.value[platformId];
};

const setActivityExample = (target, keyword = '') => {
    activityTarget.value = target;
    activityKeyword.value = keyword;
    executeActivityScout();
};

const copyActivityContent = (text, id) => {
    navigator.clipboard.writeText(text);
    copiedActivityId.value = id;
    setTimeout(() => {
        copiedActivityId.value = null;
    }, 2500);
};

const getDiscussionSearchUrl = (engine, target) => {
    if (!target) return '#';
    const q = `"${target}" (comment OR reply OR discussion OR profile)`;
    if (engine === 'google') return `https://www.google.com/search?q=${encodeURIComponent(q)}`;
    if (engine === 'duckduckgo') return `https://duckduckgo.com/?q=${encodeURIComponent(`"${target}"`)}`;
    if (engine === 'bing') return `https://www.bing.com/search?q=${encodeURIComponent(`"${target}"`)}`;
    return '#';
};

const runLiveDorkScan = async (dork, idx) => {
    if (executingDorkIdx.value !== null) return;

    executingDorkIdx.value = idx;
    expandedDorkResults.value[idx] = true;
    dorkLiveErrors.value[idx] = null;

    try {
        const res = await axios.post('/api/social-recon/execute-dork', {
            query: dork.query,
            engine: 'duckduckgo',
            amount: 8,
        });

        if (res.data?.results) {
            dorkLiveResults.value[idx] = res.data.results;
        } else if (res.data?.error) {
            dorkLiveErrors.value[idx] = res.data.error;
            dorkLiveResults.value[idx] = [];
        }
    } catch (err) {
        dorkLiveErrors.value[idx] = err?.response?.data?.message || 'Live dork scan failed or timed out.';
        dorkLiveResults.value[idx] = [];
    } finally {
        executingDorkIdx.value = null;
    }
};

const toggleDorkResults = (idx) => {
    expandedDorkResults.value[idx] = !expandedDorkResults.value[idx];
};

const copyDorkToClipboard = (text, idx) => {
    navigator.clipboard.writeText(text);
    copiedDorkIdx.value = idx;
    setTimeout(() => {
        copiedDorkIdx.value = null;
    }, 2500);
};

const handleBookmark = (payload) => {
    emit('bookmark', payload);
};

onUnmounted(() => {
    if (activeEventSource) {
        activeEventSource.close();
        activeEventSource = null;
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Activity Scout Input Console -->
        <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
            <form @submit.prevent="executeActivityScout" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-7 relative">
                    <MessageSquare class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                    <input 
                        v-model="activityTarget"
                        type="text"
                        required
                        placeholder="Target handle, username, or alias (e.g. saraichikawa, satoshi, octocat)..."
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition shadow-inner"
                    />
                </div>

                <div class="md:col-span-3 relative">
                    <input 
                        v-model="activityKeyword"
                        type="text"
                        placeholder="Optional context (e.g. crypto, exploit)"
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition shadow-inner"
                    />
                </div>

                <div class="md:col-span-2">
                    <button 
                        type="submit"
                        :disabled="isScoutingActivity || !activityTarget.trim()"
                        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase flex items-center justify-center space-x-1.5 cartoon-btn cursor-pointer shadow-lg shadow-amber-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="isScoutingActivity" class="w-4 h-4 animate-spin" />
                        <Sparkles v-else class="w-4 h-4" />
                        <span>{{ isScoutingActivity ? 'Harvesting...' : 'Scout Activity' }}</span>
                    </button>
                </div>
            </form>

            <!-- Error Alert -->
            <div v-if="activityError" class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-300 text-xs font-mono flex items-center gap-2">
                <AlertTriangle class="w-4 h-4 text-rose-400 shrink-0" />
                <span>{{ activityError }}</span>
            </div>

            <!-- Live Progressive SSE Stream Scan Progress Strip -->
            <div v-if="isScoutingActivity || (scanProgress > 0 && !scanComplete)" class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 space-y-2.5 font-mono text-xs shadow-inner">
                <div class="flex items-center justify-between text-slate-300">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-ping"></span>
                        <span class="font-bold uppercase tracking-wider text-amber-300">
                            {{ scanningPlatform ? `Scouring ${scanningPlatform}...` : 'Initializing Global Recon...' }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-slate-400 text-[11px]">Platform {{ scanIndex }} of {{ scanTotal }}</span>
                        <span class="text-amber-400 font-bold">{{ scanProgress }}%</span>
                    </div>
                </div>

                <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-800">
                    <div 
                        class="bg-gradient-to-r from-amber-500 via-cyan-400 to-emerald-400 h-2 rounded-full transition-all duration-300"
                        :style="{ width: `${scanProgress}%` }"
                    ></div>
                </div>

                <div class="flex flex-wrap items-center justify-between text-[11px] text-slate-400 pt-0.5">
                    <span class="flex items-center gap-1.5">
                        <Loader2 class="w-3 h-3 animate-spin text-amber-400" />
                        <span>Sequential Zero-API Stream</span>
                    </span>
                    <div class="flex items-center space-x-3">
                        <span class="text-emerald-400 font-bold">Findings: {{ allActivities.length }}</span>
                        <span class="text-slate-500">Networks: {{ platformsWithResults.length }} / {{ scanTotal }}</span>
                    </div>
                </div>
            </div>

            <!-- Activity Filter Tabs -->
            <div v-if="allActivities.length > 0 || generatedDorks.length > 0 || isScoutingActivity" class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80 text-xs font-mono">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        @click="selectedActivityFilter = 'all'"
                        type="button"
                        :class="[
                            selectedActivityFilter === 'all'
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-3 py-1.5 rounded-lg border transition text-xs flex items-center space-x-1.5 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>All Findings</span>
                        <span class="text-[10px] opacity-70">({{ allActivities.length }})</span>
                    </button>

                    <button
                        @click="selectedActivityFilter = 'comment'"
                        type="button"
                        :class="[
                            selectedActivityFilter === 'comment'
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-3 py-1.5 rounded-lg border transition text-xs flex items-center space-x-1.5 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>Comments & Replies</span>
                        <span class="text-[10px] opacity-70">({{ activityStats.comments }})</span>
                    </button>

                    <button
                        @click="selectedActivityFilter = 'discussion'"
                        type="button"
                        :class="[
                            selectedActivityFilter === 'discussion'
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-3 py-1.5 rounded-lg border transition text-xs flex items-center space-x-1.5 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>Forum & Web Mentions</span>
                        <span class="text-[10px] opacity-70">({{ activityStats.discussions }})</span>
                    </button>

                    <button
                        @click="selectedActivityFilter = 'code'"
                        type="button"
                        :class="[
                            selectedActivityFilter === 'code'
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-3 py-1.5 rounded-lg border transition text-xs flex items-center space-x-1.5 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>Code & Commits</span>
                        <span class="text-[10px] opacity-70">({{ activityStats.code }})</span>
                    </button>

                    <button
                        @click="selectedActivityFilter = 'dorks'"
                        type="button"
                        :class="[
                            selectedActivityFilter === 'dorks'
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-3 py-1.5 rounded-lg border transition text-xs flex items-center space-x-1.5 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>Surgical Dorks</span>
                        <span class="text-[10px] opacity-70">({{ generatedDorks.length }})</span>
                    </button>
                </div>

                <div class="text-slate-500 text-[11px] flex items-center gap-2">
                    <span>Target: <span class="text-amber-400 font-bold">@{{ scoutedTarget }}</span></span>
                    <span v-if="platformsWithResults.length > 0" class="text-slate-400">
                        • {{ platformsWithResults.length }} active networks
                    </span>
                </div>
            </div>
        </div>

        <!-- 1. ACTIVE VIEW: GROUPED PLATFORM STREAM -->
        <div v-if="selectedActivityFilter !== 'dorks' && (allActivities.length > 0 || scoutedTarget || isScoutingActivity)" class="space-y-6">
            <!-- Grouped Platform Accordion Cards -->
            <div v-if="platformsWithResults.length > 0" class="space-y-4">
                <div 
                    v-for="pGroup in platformsWithResults" 
                    :key="pGroup.id"
                    class="rounded-2xl bg-slate-900/90 border border-slate-800 transition-all cartoon-card overflow-hidden shadow-lg"
                >
                    <!-- Platform Header (Collapsible Toggle) -->
                    <button 
                        @click="togglePlatformCollapse(pGroup.id)"
                        type="button"
                        class="w-full p-4 sm:p-5 flex items-center justify-between gap-3 text-left hover:bg-slate-850/50 transition cursor-pointer select-none"
                    >
                        <div class="flex items-center space-x-3">
                            <span 
                                :class="[
                                    getPlatformColors(pGroup.id).bg,
                                    getPlatformColors(pGroup.id).border,
                                    getPlatformColors(pGroup.id).text,
                                    'px-3 py-1 rounded-lg text-xs font-mono font-bold uppercase border tracking-wider flex items-center space-x-1.5'
                                ]"
                            >
                                <span class="w-2 h-2 rounded-full" :class="pGroup.count > 0 ? 'bg-emerald-400' : 'bg-slate-600'"></span>
                                <span>{{ pGroup.name }}</span>
                            </span>

                            <span class="text-xs font-mono font-semibold px-2.5 py-0.5 rounded-full bg-slate-950 border border-slate-800 text-slate-300">
                                {{ pGroup.count }} {{ pGroup.count === 1 ? 'finding' : 'findings' }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-2 text-slate-400 text-xs font-mono">
                            <span class="hidden sm:inline text-slate-500">{{ collapsedPlatforms[pGroup.id] ? 'Expand' : 'Collapse' }}</span>
                            <ChevronUp v-if="!collapsedPlatforms[pGroup.id]" class="w-4 h-4 text-slate-400" />
                            <ChevronDown v-else class="w-4 h-4 text-slate-400" />
                        </div>
                    </button>

                    <!-- Platform Activities List -->
                    <div v-if="!collapsedPlatforms[pGroup.id]" class="p-4 sm:p-5 pt-0 border-t border-slate-800/80 space-y-3 bg-slate-950/40">
                        <div 
                            v-for="act in (selectedActivityFilter === 'all' ? pGroup.activities : pGroup.activities.filter(a => selectedActivityFilter === 'comment' ? a.category === 'comment' : selectedActivityFilter === 'code' ? a.category === 'code' : ['discussion', 'post', 'web_mention'].includes(a.category)))" 
                            :key="act.id"
                            class="p-4 sm:p-5 rounded-xl bg-slate-900/90 border border-slate-800 hover:border-amber-500/40 transition-all space-y-3 mt-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center space-x-2">
                                    <span class="text-[9px] font-mono uppercase px-2 py-0.5 rounded-full bg-slate-950 border border-slate-800 text-amber-400/80 font-semibold">
                                        {{ act.category }}
                                    </span>

                                    <span v-if="act.author" class="text-xs font-mono text-slate-400">
                                        by <span class="text-amber-300 font-semibold">@{{ act.author }}</span>
                                    </span>
                                </div>

                                <div v-if="act.date" class="flex items-center space-x-1 text-slate-500 text-[11px] font-mono">
                                    <Clock class="w-3 h-3" />
                                    <span>{{ act.date }}</span>
                                </div>
                            </div>

                            <div>
                                <a 
                                    :href="act.url" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="font-serif font-bold text-sm sm:text-base text-slate-100 hover:text-amber-300 transition inline-flex items-center space-x-1.5 group"
                                >
                                    <span>{{ act.title }}</span>
                                    <ExternalLink class="w-3.5 h-3.5 text-slate-500 group-hover:text-amber-400 transition shrink-0" />
                                </a>
                            </div>

                            <div class="p-3.5 sm:p-4 rounded-xl bg-slate-950/90 border border-slate-800/90 text-xs sm:text-sm text-slate-200 font-sans leading-relaxed whitespace-pre-wrap selection:bg-amber-500/30">
                                {{ act.content }}
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-800/70 text-xs font-mono">
                                <div class="text-[11px] text-cyan-400 truncate max-w-md font-mono">
                                    {{ act.url }}
                                </div>

                                <div class="flex items-center space-x-1.5">
                                    <button
                                        @click="copyActivityContent(act.content, act.id)"
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-amber-300 flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                        title="Copy comment text"
                                    >
                                        <Check v-if="copiedActivityId === act.id" class="w-3 h-3 text-emerald-400" />
                                        <Copy v-else class="w-3 h-3" />
                                        <span>{{ copiedActivityId === act.id ? 'Copied' : 'Copy' }}</span>
                                    </button>

                                    <button
                                        @click="handleBookmark({ title: `[${act.platform}] ${act.title}`, url: act.url, snippet: act.content, category: act.category })"
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-amber-300 flex items-center space-x-1 transition cartoon-btn cursor-pointer"
                                        title="Bookmark to Case Dossier"
                                    >
                                        <Bookmark class="w-3 h-3" />
                                        <span>Bookmark</span>
                                    </button>

                                    <Link
                                        :href="`/scraper?target=${encodeURIComponent(act.url)}`"
                                        class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-cyan-400 text-slate-300 hover:text-cyan-300 flex items-center space-x-1 transition cartoon-btn"
                                        title="Deep scrape target thread"
                                    >
                                        <Globe class="w-3 h-3" />
                                        <span>Deep Scrape</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clean / Empty Monitored Networks Strip -->
            <div v-if="platformsEmpty.length > 0" class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3 font-mono text-xs">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="font-bold text-slate-300 uppercase tracking-wider text-[11px]">
                        Inspected Networks with Zero Public Citations ({{ platformsEmpty.length }})
                    </span>
                    <span class="text-emerald-400 text-[10px]">Clean Audit</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span 
                        v-for="ep in platformsEmpty" 
                        :key="ep.id"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 text-[11px] flex items-center space-x-1"
                    >
                        <CheckCircle2 class="w-3 h-3 text-emerald-500/70" />
                        <span>{{ ep.name }}</span>
                    </span>
                </div>
            </div>

            <!-- No Activity Found on Any Monitored Network -->
            <div v-else-if="!isScoutingActivity && allActivities.length === 0 && scoutedTarget && scanComplete" class="p-8 rounded-3xl bg-slate-900/60 border border-slate-800 text-center space-y-4">
                <AlertTriangle class="w-10 h-10 text-amber-400 mx-auto" />
                <div class="space-y-1">
                    <div class="font-serif font-bold text-lg text-slate-200">
                        No Direct Comments Indexed for <span class="text-amber-300">@{{ scoutedTarget }}</span>
                    </div>
                    <div class="text-xs text-slate-400 font-mono max-w-lg mx-auto leading-relaxed">
                        Scanned 20 major social networks and public forums without direct comment hits. Use the Surgical Dork Launchers below to execute live searches on external clearnet indices.
                    </div>
                </div>

                <!-- 1-Click Search Engine Pivot Buttons -->
                <div class="pt-2 flex flex-wrap items-center justify-center gap-2 font-mono text-xs">
                    <a 
                        :href="getDiscussionSearchUrl('google', scoutedTarget)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-3.5 py-2 rounded-xl bg-blue-950/40 border border-blue-500/50 hover:border-blue-400 text-blue-300 font-bold flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <span>Google Discussion Dork</span>
                        <ExternalLink class="w-3 h-3" />
                    </a>

                    <a 
                        :href="getDiscussionSearchUrl('duckduckgo', scoutedTarget)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-3.5 py-2 rounded-xl bg-orange-950/40 border border-orange-500/50 hover:border-orange-400 text-orange-300 font-bold flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <span>DuckDuckGo Dork</span>
                        <ExternalLink class="w-3 h-3" />
                    </a>

                    <a 
                        :href="getDiscussionSearchUrl('bing', scoutedTarget)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-3.5 py-2 rounded-xl bg-cyan-950/40 border border-cyan-500/50 hover:border-cyan-400 text-cyan-300 font-bold flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <span>Bing Dork</span>
                        <ExternalLink class="w-3 h-3" />
                    </a>

                    <button
                        @click="selectedActivityFilter = 'dorks'"
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-amber-500/15 border border-amber-500/50 hover:border-amber-400 text-amber-300 font-bold flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                    >
                        <Zap class="w-3 h-3 text-amber-400" />
                        <span>View 21 Surgical Dorks</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. SURGICAL DORK MATRIX -->
        <div v-if="selectedActivityFilter === 'dorks' && generatedDorks.length > 0" class="space-y-4">
            <!-- Dork Sub-Categories -->
            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-xs font-mono">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="cat in dorkCategories"
                        :key="cat.id"
                        @click="selectedDorkCategory = cat.id"
                        type="button"
                        :class="[
                            selectedDorkCategory === cat.id
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/50 font-bold'
                                : 'bg-slate-950/60 text-slate-400 border-slate-800 hover:text-slate-200 hover:border-slate-700',
                            'px-2.5 py-1 rounded-lg border transition text-xs flex items-center space-x-1 cartoon-btn cursor-pointer'
                        ]"
                    >
                        <span>{{ cat.label }}</span>
                        <span class="text-[10px] opacity-70">
                            ({{ cat.id === 'all' ? generatedDorks.length : generatedDorks.filter(d => d.category === cat.id).length }})
                        </span>
                    </button>
                </div>

                <div class="text-slate-500 text-[11px]">
                    Showing {{ filteredDorks.length }} of {{ generatedDorks.length }} dorks
                </div>
            </div>

            <!-- Dork Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div 
                    v-for="(dork, idx) in filteredDorks" 
                    :key="idx"
                    class="p-5 rounded-2xl bg-slate-900/90 border border-slate-800 hover:border-amber-500/50 transition-all cartoon-card flex flex-col justify-between space-y-4"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="font-serif font-bold text-sm text-white">{{ dork.platform }}</span>
                            </div>
                            <span class="text-[9px] font-mono uppercase px-2 py-0.5 rounded-full bg-slate-950 border border-slate-800 text-amber-400/80 font-semibold">
                                {{ dork.category }}
                            </span>
                        </div>

                        <div class="text-xs text-slate-400 font-sans leading-relaxed">
                            {{ dork.description }}
                        </div>

                        <!-- Query Block -->
                        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs text-amber-300 break-all select-all flex items-center justify-between gap-2 group/code">
                            <span class="truncate">{{ dork.query }}</span>
                            <button
                                @click="copyDorkToClipboard(dork.query, idx)"
                                type="button"
                                class="p-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-amber-300 transition shrink-0 cursor-pointer"
                                title="Copy syntax"
                            >
                                <Check v-if="copiedDorkIdx === idx" class="w-3.5 h-3.5 text-emerald-400" />
                                <Copy v-else class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Action Toolbar: Live Scan + Direct Engine Launchers -->
                    <div class="space-y-2.5 pt-1 border-t border-slate-800/80 text-xs font-mono">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <button
                                @click="runLiveDorkScan(dork, idx)"
                                :disabled="executingDorkIdx === idx"
                                type="button"
                                class="px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/50 hover:border-amber-400 text-amber-300 font-bold flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                                title="Execute dork query in real-time"
                            >
                                <Loader2 v-if="executingDorkIdx === idx" class="w-3.5 h-3.5 animate-spin text-amber-400" />
                                <Zap v-else class="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                                <span>{{ executingDorkIdx === idx ? 'Scanning Index...' : 'Live Scan' }}</span>
                                <span v-if="dorkLiveResults[idx] && dorkLiveResults[idx].length" class="ml-1 px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px]">
                                    {{ dorkLiveResults[idx].length }}
                                </span>
                            </button>

                            <!-- Direct Search Engine Links -->
                            <div class="flex items-center space-x-1.5">
                                <a 
                                    :href="dork.google_url" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="px-2 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-blue-500/60 text-slate-300 hover:text-blue-300 flex items-center space-x-1 transition cartoon-btn"
                                    title="Execute search on Google"
                                >
                                    <span>Google</span>
                                    <ExternalLink class="w-2.5 h-2.5 text-slate-500" />
                                </a>

                                <a 
                                    :href="dork.duckduckgo_url" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="px-2 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-orange-500/60 text-slate-300 hover:text-orange-300 flex items-center space-x-1 transition cartoon-btn"
                                    title="Execute search on DuckDuckGo"
                                >
                                    <span>DDG</span>
                                    <ExternalLink class="w-2.5 h-2.5 text-slate-500" />
                                </a>

                                <a 
                                    :href="dork.bing_url" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="px-2 py-1.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-cyan-500/60 text-slate-300 hover:text-cyan-300 flex items-center space-x-1 transition cartoon-btn"
                                    title="Execute search on Bing"
                                >
                                    <span>Bing</span>
                                    <ExternalLink class="w-2.5 h-2.5 text-slate-500" />
                                </a>

                                <Link 
                                    :href="dork.darkdump_search_url"
                                    class="px-2 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:border-amber-400 flex items-center space-x-1 transition cartoon-btn"
                                    title="Search in Darkdump Multi-Engine"
                                >
                                    <span>Darkdump</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Live Scan Results Drawer -->
                        <div v-if="expandedDorkResults[idx]" class="mt-3 pt-3 border-t border-slate-800 space-y-2">
                            <div v-if="executingDorkIdx === idx" class="p-4 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center space-x-2 text-slate-400">
                                <Loader2 class="w-4 h-4 text-amber-400 animate-spin" />
                                <span>Harvesting live indexed findings...</span>
                            </div>

                            <div v-else-if="dorkLiveErrors[idx]" class="p-3 rounded-xl bg-rose-950/30 border border-rose-500/30 text-rose-300 text-xs">
                                {{ dorkLiveErrors[idx] }}
                            </div>

                            <div v-else-if="dorkLiveResults[idx] && dorkLiveResults[idx].length > 0" class="space-y-2">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span class="font-bold text-emerald-400">{{ dorkLiveResults[idx].length }} Live Findings Discovered</span>
                                    <button @click="toggleDorkResults(idx)" class="hover:text-white transition">Hide</button>
                                </div>

                                <div 
                                    v-for="(res, rIdx) in dorkLiveResults[idx]" 
                                    :key="rIdx"
                                    class="p-3 rounded-xl bg-slate-950 border border-slate-800/90 hover:border-slate-700 space-y-1.5"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <a 
                                            :href="res.url" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            class="font-bold text-slate-200 hover:text-amber-300 text-xs leading-snug truncate hover:underline"
                                        >
                                            {{ res.title }}
                                        </a>

                                        <div class="flex items-center space-x-1 shrink-0">
                                            <button
                                                @click="handleBookmark({ title: res.title, url: res.url, snippet: res.description })"
                                                class="p-1 rounded bg-slate-900 border border-slate-800 hover:border-amber-400 text-slate-400 hover:text-amber-400 transition"
                                                title="Bookmark to Case Dossier"
                                            >
                                                <Bookmark class="w-3 h-3" />
                                            </button>
                                            <Link
                                                :href="`/scraper?target=${encodeURIComponent(res.url)}`"
                                                class="p-1 rounded bg-slate-900 border border-slate-800 hover:border-cyan-400 text-slate-400 hover:text-cyan-400 transition"
                                                title="Deep scrape finding"
                                            >
                                                <Globe class="w-3 h-3" />
                                            </Link>
                                        </div>
                                    </div>

                                    <div class="text-[10px] text-cyan-400 font-mono truncate">
                                        {{ res.url }}
                                    </div>

                                    <p v-if="res.description" class="text-[11px] text-slate-400 font-sans line-clamp-2 leading-tight">
                                        {{ res.description }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="dorkLiveResults[idx] && dorkLiveResults[idx].length === 0" class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-center space-y-1">
                                <div class="text-xs text-slate-400">No hits returned on this engine.</div>
                                <div class="text-[10px] text-slate-500">
                                    Click the <span class="text-blue-400 font-bold">Google</span> or <span class="text-orange-400 font-bold">DDG</span> buttons above to view external indexed profiles.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!isScoutingActivity && allActivities.length === 0 && !scoutedTarget" class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-4">
            <MessageSquare class="w-10 h-10 text-slate-600 mx-auto" />
            <div class="space-y-1">
                <div class="font-serif font-bold text-lg text-slate-300 uppercase">Target Activity & Comment Scout</div>
                <div class="text-xs text-slate-500 font-mono max-w-md mx-auto leading-relaxed">
                    Enter an online handle (e.g. saraichikawa) to intercept public discussion comments, forum replies, developer commits, and thread activity across the clearweb.
                </div>
            </div>

            <!-- Quick Example Target Chips -->
            <div class="pt-2 flex flex-wrap items-center justify-center gap-2 text-xs font-mono">
                <span class="text-slate-500 text-[11px]">Quick Samples:</span>
                <button
                    @click="setActivityExample('saraichikawa')"
                    type="button"
                    class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-amber-500/50 text-slate-300 hover:text-amber-300 transition cartoon-btn cursor-pointer"
                >
                    saraichikawa
                </button>
                <button
                    @click="setActivityExample('satoshi', 'bitcoin')"
                    type="button"
                    class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-amber-500/50 text-slate-300 hover:text-amber-300 transition cartoon-btn cursor-pointer"
                >
                    satoshi
                </button>
                <button
                    @click="setActivityExample('octocat', 'developer')"
                    type="button"
                    class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-amber-500/50 text-slate-300 hover:text-amber-300 transition cartoon-btn cursor-pointer"
                >
                    octocat
                </button>
            </div>
        </div>
    </div>
</template>

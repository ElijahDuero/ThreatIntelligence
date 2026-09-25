<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import {
    Send,
    Compass,
    Loader2,
    ExternalLink,
    Bookmark,
    Check,
    Copy,
    Shield,
    Coins,
    Mail,
    Key,
    Filter,
    FileText,
    ArrowDown,
} from 'lucide-vue-next';

const emit = defineEmits(['bookmark']);

// Target modes: 'scrape' (Direct Channel Harvest) | 'discovery' (Keyword Channel Discovery)
const telegramMode = ref('scrape');

const telegramChannel = ref('');
const telegramQuery = ref('');
const isScrapingTelegram = ref(false);
const isLoadingMoreTelegram = ref(false);
const telegramLimit = ref(50);
const telegramForceTor = ref(true);
const telegramExtractIocs = ref(true);
const telegramData = ref(null);
const telegramError = ref(null);
const telegramFilterIocsOnly = ref(false);
const copiedIocValue = ref(null);

// Keyword Channel Discovery state
const telegramSearchKeyword = ref('');
const telegramSearchLimit = ref(25);
const isSearchingTelegramChannels = ref(false);
const discoveredChannels = ref([]);
const discoveryError = ref(null);

const executeTelegramChannelSearch = async () => {
    if (!telegramSearchKeyword.value.trim() || isSearchingTelegramChannels.value) return;

    isSearchingTelegramChannels.value = true;
    discoveryError.value = null;
    discoveredChannels.value = [];

    try {
        const res = await axios.post('/api/social-recon/telegram/search', {
            keyword: telegramSearchKeyword.value.trim(),
            limit: telegramSearchLimit.value,
            force_tor: telegramForceTor.value,
        });

        if (res.data?.channels) {
            discoveredChannels.value = res.data.channels;
        }
        if (res.data?.error) {
            discoveryError.value = res.data.error;
        }
    } catch (err) {
        discoveryError.value = err?.response?.data?.message || 'Failed to search Telegram channels.';
    } finally {
        isSearchingTelegramChannels.value = false;
    }
};

const copyIocText = (val) => {
    if (!val) return;
    navigator.clipboard.writeText(val);
    copiedIocValue.value = val;
    setTimeout(() => {
        if (copiedIocValue.value === val) {
            copiedIocValue.value = null;
        }
    }, 2000);
};

const executeTelegramScrape = async (overrideChannel = null) => {
    if (typeof overrideChannel === 'string') {
        telegramChannel.value = overrideChannel;
    }
    if (!telegramChannel.value.trim() || isScrapingTelegram.value) return;

    isScrapingTelegram.value = true;
    telegramError.value = null;
    telegramData.value = null;
    telegramFilterIocsOnly.value = false;

    try {
        const res = await axios.post('/api/social-recon/telegram', {
            channel: telegramChannel.value.trim(),
            query: telegramQuery.value.trim() || null,
            limit: telegramLimit.value,
            extract_iocs: telegramExtractIocs.value,
            force_tor: telegramForceTor.value,
        });

        if (res.data?.channel) {
            telegramData.value = res.data;
        }
        if (res.data?.error) {
            telegramError.value = res.data.error;
        }
    } catch (err) {
        telegramError.value = err?.response?.data?.message || 'Failed to scrape Telegram channel.';
    } finally {
        isScrapingTelegram.value = false;
    }
};

const inspectAndScrapeChannel = (handle) => {
    if (!handle) return;
    telegramMode.value = 'scrape';
    telegramChannel.value = handle;
    executeTelegramScrape(handle);
};

const loadMoreTelegramMessages = async () => {
    const oldestId = telegramData.value?.pagination?.oldest_id;
    if (!oldestId || isLoadingMoreTelegram.value) return;

    isLoadingMoreTelegram.value = true;
    try {
        const res = await axios.post('/api/social-recon/telegram', {
            channel: telegramChannel.value.trim(),
            query: telegramQuery.value.trim() || null,
            limit: telegramLimit.value,
            before_id: oldestId,
            extract_iocs: telegramExtractIocs.value,
            force_tor: telegramForceTor.value,
        });

        if (res.data?.messages?.length) {
            const existingIds = new Set(telegramData.value.messages.map(m => m.id));
            const newMessages = res.data.messages.filter(m => !existingIds.has(m.id));
            telegramData.value.messages.push(...newMessages);

            if (res.data.pagination) {
                telegramData.value.pagination = res.data.pagination;
            }

            if (res.data.iocs_summary && telegramData.value.iocs_summary) {
                const s = telegramData.value.iocs_summary;
                const ns = res.data.iocs_summary;
                s.emails = Array.from(new Set([...(s.emails || []), ...(ns.emails || [])]));
                s.crypto.btc = Array.from(new Set([...(s.crypto?.btc || []), ...(ns.crypto?.btc || [])]));
                s.crypto.eth = Array.from(new Set([...(s.crypto?.eth || []), ...(ns.crypto?.eth || [])]));
                s.crypto.xmr = Array.from(new Set([...(s.crypto?.xmr || []), ...(ns.crypto?.xmr || [])]));
                s.crypto.usdt = Array.from(new Set([...(s.crypto?.usdt || []), ...(ns.crypto?.usdt || [])]));

                const hashVals = new Set((s.hashes || []).map(h => h.value));
                (ns.hashes || []).forEach(h => {
                    if (!hashVals.has(h.value)) {
                        s.hashes.push(h);
                        hashVals.add(h.value);
                    }
                });

                s.leak_patterns = Array.from(new Set([...(s.leak_patterns || []), ...(ns.leak_patterns || [])]));
                s.total = s.emails.length + s.crypto.btc.length + s.crypto.eth.length + s.crypto.xmr.length + s.crypto.usdt.length + s.hashes.length + s.leak_patterns.length;
            }
        } else {
            if (telegramData.value?.pagination) {
                telegramData.value.pagination.has_more = false;
            }
        }
    } catch (err) {
        telegramError.value = err?.response?.data?.message || 'Failed to load older messages.';
    } finally {
        isLoadingMoreTelegram.value = false;
    }
};

const filteredTelegramMessages = computed(() => {
    if (!telegramData.value?.messages) return [];
    if (!telegramFilterIocsOnly.value) return telegramData.value.messages;
    return telegramData.value.messages.filter(m => m.has_iocs);
});

const handleBookmark = (payload) => {
    emit('bookmark', payload);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Target Mode Navigation Tabs -->
        <div class="flex items-center space-x-2 border-b border-slate-800 pb-3">
            <button 
                type="button"
                @click="telegramMode = 'scrape'"
                :class="telegramMode === 'scrape' ? 'bg-amber-500/20 text-amber-400 border-amber-500/50' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                class="px-4 py-2 rounded-xl border font-mono text-xs font-bold flex items-center space-x-2 cartoon-btn cursor-pointer transition"
            >
                <Send class="w-3.5 h-3.5" />
                <span>Direct Channel Harvest</span>
            </button>
            <button 
                type="button"
                @click="telegramMode = 'discovery'"
                :class="telegramMode === 'discovery' ? 'bg-amber-500/20 text-amber-400 border-amber-500/50' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                class="px-4 py-2 rounded-xl border font-mono text-xs font-bold flex items-center space-x-2 cartoon-btn cursor-pointer transition"
            >
                <Compass class="w-3.5 h-3.5" />
                <span>Keyword Channel Discovery</span>
            </button>
        </div>

        <!-- Mode 1: Direct Channel Harvest -->
        <div v-if="telegramMode === 'scrape'" class="space-y-6">
            <!-- Channel Input & Tactical Controls -->
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
                <form @submit.prevent="executeTelegramScrape()" class="space-y-4">
                    <div class="flex flex-col lg:flex-row gap-3">
                        <div class="relative flex-1">
                            <Send class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                            <input 
                                v-model="telegramChannel"
                                type="text"
                                required
                                placeholder="Channel handle or link (e.g. durov or t.me/s/cyberthreats)..."
                                class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition"
                            />
                        </div>

                        <div class="relative lg:w-64">
                            <input 
                                v-model="telegramQuery"
                                type="text"
                                placeholder="Optional message filter..."
                                class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition"
                            />
                        </div>

                        <button 
                            type="submit"
                            :disabled="isScrapingTelegram || !telegramChannel.trim()"
                            class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase flex items-center justify-center space-x-1.5 cartoon-btn cursor-pointer shrink-0 disabled:opacity-50"
                        >
                            <Loader2 v-if="isScrapingTelegram" class="w-4 h-4 animate-spin" />
                            <Send v-else class="w-4 h-4" />
                            <span>{{ isScrapingTelegram ? 'Scraping...' : 'Harvest Channel' }}</span>
                        </button>
                    </div>

                    <!-- Secondary Tactical Options -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/80 text-xs font-mono text-slate-400">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <span class="text-slate-500">Crawl Depth:</span>
                                <div class="inline-flex rounded-lg bg-slate-950 p-0.5 border border-slate-800">
                                    <button 
                                        v-for="depth in [25, 50, 100, 250]"
                                        :key="depth"
                                        type="button"
                                        @click="telegramLimit = depth"
                                        :class="telegramLimit === depth ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                                        class="px-2.5 py-1 rounded-md border text-[11px] font-bold transition"
                                    >
                                        {{ depth }}
                                    </button>
                                </div>
                            </div>

                            <label class="flex items-center space-x-2 cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    v-model="telegramExtractIocs"
                                    class="rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-0 focus:ring-offset-0"
                                />
                                <span class="text-slate-300">Extract Threat IOCs & Leaks</span>
                            </label>
                        </div>

                        <!-- Tor Proxy Routing Status -->
                        <div class="flex items-center space-x-2 px-3 py-1 rounded-lg bg-slate-950 border border-slate-800 text-emerald-400">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span>Tor SOCKS5 Active (Port 9050)</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Error Warning -->
            <div v-if="telegramError" class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/50 text-rose-300 font-mono text-xs flex items-center justify-between">
                <span>{{ telegramError }}</span>
                <button @click="telegramError = null" class="text-rose-400 hover:text-rose-200 ml-4 font-bold">&times;</button>
            </div>

            <!-- Channel Metadata Header -->
            <div v-if="telegramData?.channel" class="p-5 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <img 
                        v-if="telegramData.channel.avatar" 
                        :src="telegramData.channel.avatar" 
                        alt="Avatar" 
                        class="w-14 h-14 rounded-full border-2 border-amber-500/40 object-cover shadow-lg"
                    />
                    <div v-else class="w-14 h-14 rounded-full bg-slate-950 border border-slate-800 flex items-center justify-center">
                        <Send class="w-6 h-6 text-amber-400" />
                    </div>

                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="font-serif font-bold text-lg text-white">{{ telegramData.channel.title }}</h2>
                            <span v-if="telegramData.channel.verified" class="px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-400 border border-sky-500/30 text-[10px] font-mono">VERIFIED</span>
                        </div>
                        <div class="text-xs font-mono text-slate-400 flex items-center gap-2 mt-0.5">
                            <span>@{{ telegramData.channel.username }}</span>
                            <span>•</span>
                            <span class="text-amber-400 font-bold">{{ telegramData.channel.subscribers }} Subscribers</span>
                            <span>•</span>
                            <span class="text-cyan-400">{{ telegramData.messages?.length || 0 }} Messages Harvested</span>
                        </div>
                        <p v-if="telegramData.channel.description" class="text-xs text-slate-400 mt-1.5 line-clamp-2 max-w-2xl font-sans">
                            {{ telegramData.channel.description }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 shrink-0">
                    <button 
                        v-if="telegramData.messages?.length > 0"
                        @click="handleBookmark({ title: `Telegram Channel: @${telegramData.channel.username}`, url: telegramData.channel.url, text: telegramData.channel.description })"
                        class="px-3.5 py-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-xs font-mono text-slate-300 hover:text-amber-400 flex items-center space-x-1.5 cartoon-btn cursor-pointer"
                    >
                        <Bookmark class="w-3.5 h-3.5" />
                        <span>Bookmark Channel</span>
                    </button>
                    <a 
                        :href="telegramData.channel.url" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="px-3.5 py-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-400 text-xs font-mono text-slate-300 hover:text-white flex items-center space-x-1.5"
                    >
                        <span>Open Channel</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>

            <!-- Discovered Threat Intelligence & IOC Summary Hub -->
            <div v-if="telegramData?.iocs_summary && telegramData.iocs_summary.total > 0" class="p-5 rounded-2xl bg-slate-900/95 border border-amber-500/30 shadow-[0_0_20px_rgba(245,158,11,0.05)] space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <Shield class="w-4 h-4 text-amber-400" />
                        <h3 class="font-mono font-bold text-xs uppercase tracking-wider text-amber-400">
                            Discovered Threat Intelligence & IOC Footprint ({{ telegramData.iocs_summary.total }})
                        </h3>
                    </div>

                    <button 
                        @click="telegramFilterIocsOnly = !telegramFilterIocsOnly"
                        :class="telegramFilterIocsOnly ? 'bg-amber-500 text-slate-950 border-amber-400' : 'bg-slate-950 text-slate-300 border-slate-800 hover:border-amber-500/50'"
                        class="px-3 py-1 rounded-lg border text-xs font-mono flex items-center space-x-1.5 cartoon-btn cursor-pointer"
                    >
                        <Filter class="w-3.5 h-3.5" />
                        <span>{{ telegramFilterIocsOnly ? 'Showing IOCs Only' : 'Filter: IOCs Only' }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs font-mono">
                    <!-- Crypto Wallets -->
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5"><Coins class="w-3.5 h-3.5 text-amber-400" /> Crypto Wallets</span>
                            <span class="text-amber-400 font-bold">{{ (telegramData.iocs_summary.crypto?.btc?.length || 0) + (telegramData.iocs_summary.crypto?.eth?.length || 0) + (telegramData.iocs_summary.crypto?.xmr?.length || 0) + (telegramData.iocs_summary.crypto?.usdt?.length || 0) }}</span>
                        </div>
                        <div class="space-y-1 max-h-28 overflow-y-auto pr-1">
                            <div v-for="addr in [...(telegramData.iocs_summary.crypto?.btc || []), ...(telegramData.iocs_summary.crypto?.eth || []), ...(telegramData.iocs_summary.crypto?.xmr || []), ...(telegramData.iocs_summary.crypto?.usdt || [])].slice(0, 8)" :key="addr" class="flex items-center justify-between bg-slate-900 px-2 py-1 rounded border border-slate-800">
                                <span class="truncate max-w-[170px] text-slate-300 font-mono text-[11px]">{{ addr }}</span>
                                <button @click="copyIocText(addr)" class="text-slate-400 hover:text-amber-400 ml-1">
                                    <Check v-if="copiedIocValue === addr" class="w-3 h-3 text-emerald-400" />
                                    <Copy v-else class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Harvested Emails -->
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5"><Mail class="w-3.5 h-3.5 text-cyan-400" /> Harvested Emails</span>
                            <span class="text-cyan-400 font-bold">{{ telegramData.iocs_summary.emails?.length || 0 }}</span>
                        </div>
                        <div class="space-y-1 max-h-28 overflow-y-auto pr-1">
                            <div v-for="email in (telegramData.iocs_summary.emails || []).slice(0, 8)" :key="email" class="flex items-center justify-between bg-slate-900 px-2 py-1 rounded border border-slate-800">
                                <span class="truncate max-w-[170px] text-slate-300 font-mono text-[11px]">{{ email }}</span>
                                <button @click="copyIocText(email)" class="text-slate-400 hover:text-cyan-400 ml-1">
                                    <Check v-if="copiedIocValue === email" class="w-3 h-3 text-emerald-400" />
                                    <Copy v-else class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hashes & Leak Patterns -->
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5"><Key class="w-3.5 h-3.5 text-rose-400" /> Hashes & Leaks</span>
                            <span class="text-rose-400 font-bold">{{ (telegramData.iocs_summary.hashes?.length || 0) + (telegramData.iocs_summary.leak_patterns?.length || 0) }}</span>
                        </div>
                        <div class="space-y-1 max-h-28 overflow-y-auto pr-1">
                            <div v-for="h in (telegramData.iocs_summary.hashes || []).slice(0, 5)" :key="h.value" class="flex items-center justify-between bg-slate-900 px-2 py-1 rounded border border-slate-800">
                                <span class="text-amber-400 text-[10px]">{{ h.type }}</span>
                                <span class="truncate max-w-[130px] text-slate-300 font-mono text-[11px]">{{ h.value }}</span>
                                <button @click="copyIocText(h.value)" class="text-slate-400 hover:text-rose-400 ml-1">
                                    <Check v-if="copiedIocValue === h.value" class="w-3 h-3 text-emerald-400" />
                                    <Copy v-else class="w-3 h-3" />
                                </button>
                            </div>
                            <div v-for="lp in (telegramData.iocs_summary.leak_patterns || []).slice(0, 3)" :key="lp" class="text-[10px] text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20 truncate">
                                {{ lp }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Harvested Messages -->
            <div v-if="filteredTelegramMessages.length > 0" class="space-y-3">
                <div 
                    v-for="msg in filteredTelegramMessages" 
                    :key="msg.id || msg.url"
                    class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-amber-500/40 transition-all cartoon-card space-y-3"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-mono text-slate-400">
                        <div class="flex items-center space-x-2">
                            <span class="text-amber-400 font-bold">Post #{{ msg.id || 'Web' }}</span>
                            <span class="text-slate-600">•</span>
                            <span>{{ msg.datetime ? new Date(msg.datetime).toLocaleString() : 'Recent' }}</span>
                            <span class="text-slate-600">•</span>
                            <span class="text-cyan-400">{{ msg.views }} views</span>

                            <!-- Forwarded From Badge with Pivot Link -->
                            <template v-if="msg.forwarded_from">
                                <span class="text-slate-600">•</span>
                                <button 
                                    @click="executeTelegramScrape(msg.forwarded_from.handle || msg.forwarded_from.name)"
                                    class="inline-flex items-center space-x-1 px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 hover:bg-indigo-500/30 transition text-[11px]"
                                    :title="'Pivot to forwarded source: @' + (msg.forwarded_from.handle || msg.forwarded_from.name)"
                                >
                                    <span>Fwd: @{{ msg.forwarded_from.handle || msg.forwarded_from.name }}</span>
                                </button>
                            </template>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button 
                                @click="copyIocText(msg.text)"
                                class="p-1.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-amber-400 transition cartoon-btn cursor-pointer"
                                title="Copy Message Text"
                            >
                                <Check v-if="copiedIocValue === msg.text" class="w-3.5 h-3.5 text-emerald-400" />
                                <Copy v-else class="w-3.5 h-3.5" />
                            </button>
                            <button 
                                @click="handleBookmark({ title: `Telegram: @${msg.channel} (#${msg.id})`, url: msg.url, text: msg.text })"
                                class="p-1.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-amber-400 transition cartoon-btn cursor-pointer"
                                title="Bookmark to Case Dossier"
                            >
                                <Bookmark class="w-3.5 h-3.5" />
                            </button>
                            <a 
                                :href="msg.url" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="p-1.5 rounded-lg bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-400 text-slate-400 hover:text-white transition"
                                title="Open post on Telegram"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>

                    <!-- Media Attachment Previews -->
                    <div v-if="msg.media && msg.media.length > 0" class="flex flex-wrap gap-2 pt-1">
                        <template v-for="(item, mIdx) in msg.media" :key="mIdx">
                            <a 
                                v-if="item.type === 'photo'"
                                :href="item.url" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="block relative rounded-xl overflow-hidden border border-slate-800 hover:border-amber-400 transition max-w-xs"
                            >
                                <img :src="item.thumbnail" alt="Telegram Image" class="max-h-48 object-cover rounded-xl" />
                            </a>
                            <div 
                                v-else-if="item.type === 'document'"
                                class="flex items-center space-x-2 px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono text-slate-300"
                            >
                                <FileText class="w-4 h-4 text-amber-400" />
                                <span class="font-bold text-white">{{ item.filename }}</span>
                                <span v-if="item.filesize" class="text-slate-500">({{ item.filesize }})</span>
                            </div>
                        </template>
                    </div>

                    <!-- Message Text -->
                    <p v-if="msg.text" class="text-xs sm:text-sm text-slate-200 font-sans leading-relaxed whitespace-pre-wrap">
                        {{ msg.text }}
                    </p>

                    <!-- Discovered IOC Tags in Post -->
                    <div v-if="msg.has_iocs" class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-800/60 text-[11px] font-mono">
                        <span v-for="em in msg.iocs?.emails || []" :key="em" class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">
                            ✉ {{ em }}
                        </span>
                        <span v-for="addr in [...(msg.iocs?.crypto?.btc || []), ...(msg.iocs?.crypto?.eth || []), ...(msg.iocs?.crypto?.xmr || []), ...(msg.iocs?.crypto?.usdt || [])]" :key="addr" class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/30">
                            🪙 {{ addr }}
                        </span>
                        <span v-for="h in msg.iocs?.hashes || []" :key="h.value" class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30">
                            # {{ h.type }}: {{ h.value.substring(0, 10) }}...
                        </span>
                        <span v-for="lp in msg.iocs?.leak_patterns || []" :key="lp" class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-300 border border-rose-500/30">
                            🚨 {{ lp }}
                        </span>
                    </div>
                </div>

                <!-- Deep Pagination / Load More -->
                <div v-if="telegramData?.pagination?.has_more" class="p-4 text-center">
                    <button 
                        @click="loadMoreTelegramMessages"
                        :disabled="isLoadingMoreTelegram"
                        class="px-8 py-3 rounded-xl bg-slate-950 hover:bg-slate-900 border border-slate-800 hover:border-amber-400 text-slate-300 hover:text-white font-mono text-xs uppercase flex items-center justify-center space-x-2 mx-auto cartoon-btn cursor-pointer transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isLoadingMoreTelegram" class="w-4 h-4 animate-spin text-amber-400" />
                        <ArrowDown v-else class="w-4 h-4 text-amber-400" />
                        <span>{{ isLoadingMoreTelegram ? 'Harvesting Older Messages...' : `Load Older Messages (+${telegramLimit})` }}</span>
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!isScrapingTelegram && !telegramData" class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-3">
                <Send class="w-10 h-10 text-slate-600 mx-auto" />
                <div class="font-serif font-bold text-lg text-slate-300 uppercase">Telegram Threat Reconnaissance Monitor</div>
                <div class="text-xs text-slate-500 font-mono max-w-md mx-auto">
                    Harvest public intelligence feeds, extract leaked credentials & crypto addresses, and map cross-channel forward networks through anonymous Tor SOCKS5 routing.
                </div>
            </div>
        </div>

        <!-- Mode 2: Keyword Channel Discovery -->
        <div v-else-if="telegramMode === 'discovery'" class="space-y-6">
            <!-- Keyword Discovery Input Console -->
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
                <form @submit.prevent="executeTelegramChannelSearch" class="space-y-4">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Compass class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                            <input 
                                v-model="telegramSearchKeyword"
                                type="text"
                                required
                                placeholder="Search topic or threat keyword (e.g. ransomware, threat intel, crypto leaks)..."
                                class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition"
                            />
                        </div>

                        <button 
                            type="submit"
                            :disabled="isSearchingTelegramChannels || !telegramSearchKeyword.trim()"
                            class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase flex items-center justify-center space-x-1.5 cartoon-btn cursor-pointer shrink-0 disabled:opacity-50"
                        >
                            <Loader2 v-if="isSearchingTelegramChannels" class="w-4 h-4 animate-spin" />
                            <Compass v-else class="w-4 h-4" />
                            <span>{{ isSearchingTelegramChannels ? 'Searching...' : 'Discover Channels' }}</span>
                        </button>
                    </div>

                    <!-- Options & Tor Status -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/80 text-xs font-mono text-slate-400">
                        <div class="flex items-center space-x-2">
                            <span class="text-slate-500">Max Results:</span>
                            <div class="inline-flex rounded-lg bg-slate-950 p-0.5 border border-slate-800">
                                <button 
                                    v-for="lim in [10, 25, 50]"
                                    :key="lim"
                                    type="button"
                                    @click="telegramSearchLimit = lim"
                                    :class="telegramSearchLimit === lim ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                                    class="px-2.5 py-1 rounded-md border text-[11px] font-bold transition"
                                >
                                    {{ lim }}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 px-3 py-1 rounded-lg bg-slate-950 border border-slate-800 text-emerald-400">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span>Tor SOCKS5 Active (Port 9050)</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Discovery Error -->
            <div v-if="discoveryError" class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/50 text-rose-300 font-mono text-xs flex items-center justify-between">
                <span>{{ discoveryError }}</span>
                <button @click="discoveryError = null" class="text-rose-400 hover:text-rose-200 ml-4 font-bold">&times;</button>
            </div>

            <!-- Discovered Channels Grid -->
            <div v-if="discoveredChannels.length > 0" class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h3 class="font-mono font-bold text-xs uppercase tracking-wider text-slate-400">
                        Discovered Channels & Groups matching "{{ telegramSearchKeyword }}" ({{ discoveredChannels.length }})
                    </h3>
                    <span class="text-xs font-mono text-amber-400">Click "Inspect & Scrape" to pull message feed</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div 
                        v-for="ch in discoveredChannels" 
                        :key="ch.handle"
                        class="p-5 rounded-2xl bg-slate-900/85 border border-slate-800 hover:border-amber-500/50 transition-all cartoon-card flex flex-col justify-between space-y-4"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-serif font-bold text-base text-white truncate">{{ ch.title }}</h4>
                                    <div class="text-xs font-mono text-amber-400 font-bold truncate">@{{ ch.handle }}</div>
                                </div>

                                <span 
                                    :class="{
                                        'bg-sky-500/15 text-sky-400 border-sky-500/30': ch.type === 'channel',
                                        'bg-indigo-500/15 text-indigo-400 border-indigo-500/30': ch.type === 'group',
                                        'bg-purple-500/15 text-purple-400 border-purple-500/30': ch.type === 'bot'
                                    }"
                                    class="px-2 py-0.5 rounded border text-[10px] font-mono font-bold uppercase shrink-0"
                                >
                                    {{ ch.type }}
                                </span>
                            </div>

                            <p v-if="ch.description" class="text-xs text-slate-400 font-sans line-clamp-3 leading-relaxed">
                                {{ ch.description }}
                            </p>
                            <p v-else class="text-xs text-slate-600 font-sans italic">
                                No channel biography provided.
                            </p>
                        </div>

                        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2">
                            <button 
                                @click="inspectAndScrapeChannel(ch.handle)"
                                class="flex-1 px-3 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-mono font-bold text-xs uppercase flex items-center justify-center space-x-1.5 cartoon-btn cursor-pointer shadow"
                                title="Harvest live messages and IOCs from this channel"
                            >
                                <Send class="w-3.5 h-3.5" />
                                <span>Inspect & Scrape</span>
                            </button>

                            <button 
                                @click="handleBookmark({ title: `Telegram: @${ch.handle}`, url: ch.url, text: ch.description })"
                                class="p-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-amber-400 transition cartoon-btn cursor-pointer shrink-0"
                                title="Bookmark to Case Dossier"
                            >
                                <Bookmark class="w-3.5 h-3.5" />
                            </button>

                            <a 
                                :href="ch.url" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="p-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-400 text-slate-400 hover:text-white transition shrink-0"
                                title="Open on Telegram"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discovery Empty State -->
            <div v-else-if="!isSearchingTelegramChannels && discoveredChannels.length === 0" class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-3">
                <Compass class="w-10 h-10 text-slate-600 mx-auto" />
                <div class="font-serif font-bold text-lg text-slate-300 uppercase">Telegram Channel Keyword Discovery</div>
                <div class="text-xs text-slate-500 font-mono max-w-md mx-auto">
                    Search topics, breach actor names, threat keywords, or crypto tokens to locate indexed public Telegram channels, groups, and bots across global directories.
                </div>
            </div>
        </div>
    </div>
</template>

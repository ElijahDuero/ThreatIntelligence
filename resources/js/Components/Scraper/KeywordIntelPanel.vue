<script setup>
import { ref, computed } from 'vue';
import { 
    Crosshair, 
    Layers, 
    ArrowRight 
} from 'lucide-vue-next';

const props = defineProps({
    intel: {
        type: Object,
        default: null,
    },
    customKeywords: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['rescrape-subpage']);

const selectedKeywordFilter = ref('all');

const escapeRegex = (string) => {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
};

const formatSnippet = (snippet, keyword) => {
    if (!snippet) return '';
    if (!keyword) return snippet;
    const escaped = escapeRegex(keyword);
    const regex = new RegExp(`(${escaped})`, 'gi');
    return snippet.replace(regex, '<mark class="bg-rose-950/90 text-rose-300 font-bold px-1 py-0.5 rounded border border-rose-500/40">$1</mark>');
};

const filteredKeywordMatches = computed(() => {
    const matches = props.intel?.keyword_matches || [];
    if (selectedKeywordFilter.value === 'all') return matches;
    return matches.filter(m => m.keyword.toLowerCase() === selectedKeywordFilter.value.toLowerCase());
});
</script>

<template>
    <div class="space-y-6 font-mono">
        <!-- Intelligence Overview Banner -->
        <div class="p-6 rounded-3xl bg-slate-900/80 border border-slate-800 space-y-4 cartoon-card">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <Crosshair class="w-5 h-5 text-rose-400" />
                        <h3 class="text-base font-serif font-bold text-white uppercase">
                            Target Keyword Intelligence Report
                        </h3>
                    </div>
                    <p class="text-xs font-sans text-slate-400">
                        {{ intel?.summary || 'Scanned for custom keywords across HTML, meta elements, documents, and child links.' }}
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-500 block uppercase">TOTAL MATCHES</span>
                        <span class="text-lg font-bold text-rose-400">{{ intel?.total_matches_count ?? 0 }}</span>
                    </div>
                    <div class="px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-500 block uppercase">CRAWLED PAGES</span>
                        <span class="text-lg font-bold text-cyan-400">{{ intel?.crawled_pages_count ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Filter / Keyword pills selector -->
            <div v-if="intel?.keyword_matches?.length > 0" class="pt-3 border-t border-slate-800/80 flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-500 text-[11px] uppercase font-bold">Filter By Keyword:</span>
                <button 
                    type="button"
                    @click="selectedKeywordFilter = 'all'"
                    :class="[
                        selectedKeywordFilter === 'all' ? 'bg-rose-500/20 border-rose-500/60 text-rose-300 font-bold' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white',
                        'px-3 py-1 rounded-xl border text-[11px] transition cartoon-btn cursor-pointer'
                    ]"
                >
                    ALL ({{ intel.total_matches_count }})
                </button>
                <button 
                    v-for="km in intel.keyword_matches" 
                    :key="km.keyword"
                    type="button"
                    @click="selectedKeywordFilter = km.keyword"
                    :class="[
                        selectedKeywordFilter.toLowerCase() === km.keyword.toLowerCase() ? 'bg-rose-500/20 border-rose-500/60 text-rose-300 font-bold' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white',
                        'px-3 py-1 rounded-xl border text-[11px] flex items-center space-x-1.5 transition cartoon-btn cursor-pointer'
                    ]"
                >
                    <span>{{ km.keyword }}</span>
                    <span class="px-1.5 py-0.2 rounded bg-slate-900 border border-slate-700 text-[10px] text-rose-400 font-bold">
                        {{ km.hit_count }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Keyword Matches Detail Cards -->
        <div v-if="filteredKeywordMatches.length > 0" class="space-y-4">
            <div 
                v-for="km in filteredKeywordMatches" 
                :key="km.keyword"
                class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition space-y-4 cartoon-card"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800/80">
                    <div class="flex items-center space-x-3">
                        <span class="px-2.5 py-1 rounded-lg bg-rose-950 border border-rose-500/40 text-rose-300 text-xs font-bold uppercase tracking-wider">
                            KEYWORD
                        </span>
                        <h4 class="text-sm font-bold text-white font-mono">
                            "{{ km.keyword }}"
                        </h4>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 text-[11px]">
                            Primary Hits: <strong class="text-white">{{ km.primary_hits }}</strong>
                        </span>
                        <span v-if="km.subpage_hits > 0" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/40 text-cyan-300 text-[11px]">
                            Subpage Hits: <strong class="text-cyan-200">{{ km.subpage_hits }}</strong>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-rose-950/80 border border-rose-500/50 text-rose-300 text-[11px] font-bold">
                            Total: {{ km.hit_count }} Hits
                        </span>
                    </div>
                </div>

                <!-- Sources Tags -->
                <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span class="text-slate-500 font-bold uppercase mr-1">Discovered In:</span>
                    <span 
                        v-for="source in km.sources" 
                        :key="source"
                        class="px-2 py-0.5 rounded-md bg-slate-950 border border-slate-800 text-cyan-400 font-bold uppercase text-[10px]"
                    >
                        {{ source }}
                    </span>
                </div>

                <!-- Context Excerpt Snippets -->
                <div v-if="km.snippets && km.snippets.length > 0" class="space-y-2">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">
                        Contextual Excerpts & Forensic Evidence:
                    </span>
                    <div class="space-y-2">
                        <div 
                            v-for="(snippet, sIdx) in km.snippets" 
                            :key="sIdx"
                            class="p-3 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-300 text-xs font-sans leading-relaxed break-words"
                        >
                            <span v-html="formatSnippet(snippet, km.keyword)"></span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-xs text-slate-500 italic py-2">
                    No explicit context snippets found for this keyword.
                </div>
            </div>
        </div>

        <!-- Zero Matches Found Notice -->
        <div 
            v-else-if="customKeywords && customKeywords.length > 0" 
            class="p-8 text-center rounded-3xl bg-slate-900/40 border border-slate-800 text-slate-400 space-y-2 font-mono text-xs"
        >
            <Crosshair class="w-8 h-8 text-slate-600 mx-auto" />
            <h4 class="text-sm font-bold text-slate-300">No Keyword Matches Discovered</h4>
            <p class="text-slate-500 max-w-md mx-auto font-sans">
                Scanned target page and child links for: <span class="text-white font-mono">{{ customKeywords.join(', ') }}</span>, but no occurrences were found.
            </p>
        </div>

        <!-- No Custom Keywords Targeted Notice -->
        <div 
            v-else 
            class="p-8 text-center rounded-3xl bg-slate-900/40 border border-slate-800 text-slate-400 space-y-2 font-mono text-xs"
        >
            <Crosshair class="w-8 h-8 text-slate-600 mx-auto" />
            <h4 class="text-sm font-bold text-slate-300">No Custom Keywords Targeted</h4>
            <p class="text-slate-500 max-w-md mx-auto font-sans">
                Enter target keywords in the discovery console above (e.g. <span class="text-cyan-400 font-mono">bitcoin, wallet, credentials</span>) to enable deep keyword crawling and intelligence extraction.
            </p>
        </div>

        <!-- Targeted Crawled Subpages Section -->
        <div v-if="intel?.crawled_subpages?.length > 0" class="space-y-4 pt-4 border-t border-slate-800/80">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center space-x-2">
                    <Layers class="w-4 h-4 text-cyan-400" />
                    <span>Targeted Crawled Subpages ({{ intel.crawled_subpages.length }})</span>
                </h4>
                <span class="text-[11px] text-slate-500 font-sans">Child endpoints crawled in depth</span>
            </div>

            <div class="space-y-3">
                <div 
                    v-for="(subpage, pIdx) in intel.crawled_subpages" 
                    :key="pIdx"
                    class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-slate-700/80 transition space-y-3 cartoon-card"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="space-y-0.5 overflow-hidden">
                            <div class="flex items-center space-x-2">
                                <span 
                                    :class="[
                                        subpage.status_code === 200 ? 'bg-emerald-950 border-emerald-500/40 text-emerald-400' : 'bg-rose-950 border-rose-500/40 text-rose-400',
                                        'px-2 py-0.5 rounded text-[10px] font-bold border'
                                    ]"
                                >
                                    {{ subpage.status_code || 'ERR' }}
                                </span>
                                <h5 class="text-xs font-bold text-white truncate font-sans" :title="subpage.title">
                                    {{ subpage.title }}
                                </h5>
                            </div>
                            <div class="text-[11px] text-cyan-400 hover:underline truncate" :title="subpage.url">
                                {{ subpage.url }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span v-if="subpage.hit_count > 0" class="px-2.5 py-1 rounded-lg bg-rose-950/80 border border-rose-500/40 text-rose-300 text-[11px] font-bold">
                                {{ subpage.hit_count }} Matches
                            </span>
                            <button 
                                type="button"
                                @click="$emit('rescrape-subpage', subpage.url)"
                                class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-cyan-300 text-xs flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                                title="Deep scrape this subpage as primary target"
                            >
                                <ArrowRight class="w-3.5 h-3.5" />
                                <span>Scrape Target</span>
                            </button>
                        </div>
                    </div>

                    <!-- Matched keywords tags -->
                    <div v-if="subpage.matched_keywords && subpage.matched_keywords.length > 0" class="flex flex-wrap items-center gap-1.5 text-[10px]">
                        <span class="text-slate-500 font-bold uppercase">Matched:</span>
                        <span 
                            v-for="kw in subpage.matched_keywords" 
                            :key="kw"
                            class="px-2 py-0.5 rounded bg-rose-950/60 border border-rose-500/40 text-rose-300 font-bold"
                        >
                            {{ kw }}
                        </span>
                    </div>

                    <!-- Subpage snippets -->
                    <div v-if="subpage.snippets && subpage.snippets.length > 0" class="space-y-1.5">
                        <div 
                            v-for="(snip, sIdx) in subpage.snippets" 
                            :key="sIdx"
                            class="p-2.5 rounded-lg bg-slate-950 text-slate-300 text-xs font-sans leading-relaxed"
                        >
                            {{ snip }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

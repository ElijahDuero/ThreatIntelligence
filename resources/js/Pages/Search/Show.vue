<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Download, ArrowLeft, ExternalLink, ShieldAlert, Globe, Copy, Check } from 'lucide-vue-next';

const props = defineProps({
    search: Object,
});

const copiedUrl = ref(null);

const copyToClipboard = (url) => {
    navigator.clipboard.writeText(url);
    copiedUrl.value = url;
    setTimeout(() => {
        copiedUrl.value = null;
    }, 2000);
};
</script>

<template>
    <div class="space-y-8 reveal-item is-revealed">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
                <div class="flex items-center space-x-3">
                    <Link href="/search" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-white transition cartoon-btn">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center space-x-2 font-mono text-xs text-slate-400">
                            <span>DOSSIER RECORD #{{ search.id }}</span>
                            <span>•</span>
                            <span class="uppercase text-amber-400 font-bold font-mono">{{ search.engine }}</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-serif font-black text-white mt-1 uppercase">"{{ search.query }}"</h1>
                    </div>
                </div>

                <div class="flex items-center space-x-2 shrink-0">
                    <a 
                        :href="`/export/${search.id}?format=json`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>JSON</span>
                    </a>
                    <a 
                        :href="`/export/${search.id}?format=csv`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>CSV</span>
                    </a>
                    <a 
                        :href="`/export/${search.id}?format=markdown`"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>MD REPORT</span>
                    </a>
                </div>
            </div>

            <!-- Summary Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 font-mono text-xs shadow-xl cartoon-card">
                    <span class="text-slate-400 block uppercase">RESULTS INDEXED</span>
                    <span class="text-2xl font-bold text-amber-400 mt-1 block">{{ search.results.length }} items</span>
                </div>
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 font-mono text-xs shadow-xl cartoon-card">
                    <span class="text-slate-400 block uppercase">EXECUTION TIME</span>
                    <span class="text-2xl font-bold text-emerald-400 mt-1 block">{{ search.execution_time_seconds || '0.00' }}s</span>
                </div>
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 font-mono text-xs shadow-xl cartoon-card">
                    <span class="text-slate-400 block uppercase">TOR ROUTED</span>
                    <span class="text-2xl font-bold text-white mt-1 block">{{ search.use_tor ? 'YES' : 'NO' }}</span>
                </div>
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 font-mono text-xs shadow-xl cartoon-card">
                    <span class="text-slate-400 block uppercase">RECORDED DATE</span>
                    <span class="text-xs text-slate-300 mt-2 block">{{ new Date(search.created_at).toLocaleString() }}</span>
                </div>
            </div>

            <!-- Results List -->
            <div class="space-y-4">
                <div 
                    v-for="(item, idx) in search.results" 
                    :key="item.id"
                    class="bg-slate-950/90 border border-slate-800 hover:border-amber-500/50 rounded-2xl p-6 transition-all shadow-xl cartoon-card space-y-3"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center space-x-2.5">
                                <span class="text-xs font-mono font-black text-amber-400 bg-amber-500/10 border border-amber-500/30 px-2 py-0.5 rounded-md">
                                    #{{ item.position || (idx + 1) }}
                                </span>
                                <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                            </div>
                            <div class="text-xs font-mono text-cyan-400 break-all pt-0.5">
                                <a :href="item.url" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center space-x-1">
                                    <span>{{ item.url }}</span>
                                    <ExternalLink class="w-3 h-3 inline-block" />
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <span v-if="item.is_onion" class="px-2.5 py-1 rounded-lg bg-purple-950/60 border border-purple-500/40 text-purple-300 font-mono text-[10px] font-bold">
                                .ONION
                            </span>
                            <span v-if="item.is_blacklisted" class="px-2.5 py-1 rounded-lg bg-red-950/60 border border-red-500/40 text-red-400 font-mono text-[10px] font-bold flex items-center space-x-1">
                                <ShieldAlert class="w-3 h-3" />
                                <span>BLACKLISTED</span>
                            </span>
                        </div>
                    </div>

                    <p v-if="item.description" class="text-xs text-slate-300 font-sans leading-relaxed">
                        {{ item.description }}
                    </p>

                    <div class="flex items-center justify-end space-x-4 pt-3 border-t border-slate-800/80 text-xs font-mono">
                        <button 
                            @click="copyToClipboard(item.url)"
                            class="text-slate-400 hover:text-amber-400 flex items-center space-x-1 transition cartoon-btn"
                        >
                            <Check v-if="copiedUrl === item.url" class="w-3.5 h-3.5 text-emerald-400" />
                            <Copy v-else class="w-3.5 h-3.5" />
                            <span>{{ copiedUrl === item.url ? 'Copied' : 'Copy URL' }}</span>
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
</template>

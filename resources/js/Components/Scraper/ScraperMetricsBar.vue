<script setup>
import { 
    ShieldCheck, 
    Crosshair, 
    Camera, 
    AlertTriangle 
} from 'lucide-vue-next';

defineProps({
    scrapeResult: {
        type: Object,
        required: true,
    },
});

defineEmits(['change-tab']);
</script>

<template>
    <div class="space-y-4">
        <!-- Ahmia Abuse Blacklist Alert -->
        <div v-if="scrapeResult.is_blacklisted" class="p-4 rounded-2xl bg-rose-950/60 border border-rose-500/80 text-rose-200 text-xs font-mono flex items-center space-x-3 shadow-lg shadow-rose-950/40 cartoon-card">
            <AlertTriangle class="w-5 h-5 text-rose-400 shrink-0 animate-pulse" />
            <div>
                <span class="font-bold text-rose-300 uppercase">Ahmia Abuse Blacklist Match:</span>
                This target hidden service is actively listed on Ahmia's public abuse registry. Exercise forensic caution.
            </div>
        </div>

        <!-- Meta Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card">
                <span class="text-slate-400 block uppercase text-[11px]">HTTP STATUS</span>
                <span class="text-lg font-bold text-emerald-400 mt-1 block flex items-center space-x-1.5">
                    <ShieldCheck class="w-4 h-4 text-emerald-400 shrink-0" />
                    <span>{{ scrapeResult.status_code }} OK</span>
                </span>
            </div>

            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card">
                <span class="text-slate-400 block uppercase text-[11px]">SERVER BANNER</span>
                <span class="text-xs font-bold text-white mt-2 block truncate" :title="scrapeResult.server">
                    {{ scrapeResult.server || 'Unknown' }}
                </span>
            </div>

            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card">
                <span class="text-slate-400 block uppercase text-[11px]">RESPONSE LATENCY</span>
                <span class="text-lg font-bold text-cyan-400 mt-1 block">{{ scrapeResult.response_time_seconds }}s</span>
            </div>

            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card">
                <span class="text-slate-400 block uppercase text-[11px]">LINKS TOPOLOGY</span>
                <span class="text-lg font-bold text-white mt-1 block">
                    {{ scrapeResult.internal_links_count }} int / {{ scrapeResult.external_links_count }} ext
                </span>
            </div>

            <div 
                @click="$emit('change-tab', 'keyword-intel')" 
                class="bg-slate-950/80 border border-slate-800 hover:border-rose-500/50 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card cursor-pointer transition"
            >
                <div class="flex items-center justify-between text-slate-400 uppercase text-[11px]">
                    <span>KEYWORD INTEL</span>
                    <Crosshair class="w-3.5 h-3.5 text-rose-400" />
                </div>
                <span 
                    :class="[
                        (scrapeResult.keyword_intel?.total_matches_count ?? 0) > 0 ? 'text-rose-400 font-black' : 'text-slate-400 font-bold',
                        'text-lg mt-1 block flex items-center space-x-1.5'
                    ]"
                >
                    <span>{{ scrapeResult.keyword_intel?.total_matches_count ?? 0 }} Matches</span>
                </span>
                <span class="text-[10px] text-slate-500 block truncate mt-0.5">
                    {{ scrapeResult.custom_keywords?.length ? scrapeResult.custom_keywords.length + ' targets queried' : 'No filter set' }}
                </span>
            </div>

            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono text-xs shadow-xl cartoon-card">
                <span class="text-slate-400 block uppercase text-[11px]">FORENSIC EVIDENCE</span>
                <button 
                    type="button"
                    @click="$emit('change-tab', 'screenshot')" 
                    class="text-left mt-1 block group w-full cursor-pointer"
                >
                    <span v-if="scrapeResult.screenshot?.public_url" class="text-xs font-bold text-emerald-400 flex items-center space-x-1">
                        <ShieldCheck class="w-3.5 h-3.5" />
                        <span class="truncate">SHA-256 Captured</span>
                    </span>
                    <span v-else class="text-xs font-bold text-cyan-400 group-hover:text-cyan-300 transition flex items-center space-x-1">
                        <Camera class="w-3.5 h-3.5" />
                        <span>Capture PNG</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

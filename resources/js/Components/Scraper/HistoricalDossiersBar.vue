<script setup>
import { Clock } from 'lucide-vue-next';

defineProps({
    recentScrapes: {
        type: Array,
        default: () => [],
    },
    currentUrl: {
        type: String,
        default: '',
    },
});

defineEmits(['select-target']);
</script>

<template>
    <div v-if="recentScrapes && recentScrapes.length > 0" class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 font-mono text-xs space-y-2.5 reveal-item is-revealed">
        <div class="flex items-center justify-between text-[11px] text-slate-400">
            <span class="uppercase tracking-wider font-bold flex items-center gap-1.5">
                <Clock class="w-3.5 h-3.5 text-cyan-400" />
                <span>RECENT TARGET DOSSIERS</span>
            </span>
            <span class="text-slate-500">{{ recentScrapes.length }} indexed in database</span>
        </div>
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
            <button 
                v-for="target in recentScrapes" 
                :key="target.id"
                type="button"
                @click="$emit('select-target', target)"
                :class="[
                    currentUrl === target.url ? 'bg-cyan-500/20 border-cyan-500/60 text-cyan-300' : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700 hover:text-white',
                    'px-3 py-1.5 rounded-xl border text-[11px] flex items-center space-x-2 shrink-0 transition cartoon-btn cursor-pointer'
                ]"
            >
                <span :class="target.url.includes('.onion') ? 'text-purple-400' : 'text-slate-400'">●</span>
                <span class="font-bold truncate max-w-[140px]" :title="target.url">{{ target.title || target.url }}</span>
                <span v-if="target.screenshot_path" class="px-1.5 py-0.2 rounded bg-emerald-950 border border-emerald-500/40 text-emerald-300 text-[9px] uppercase font-bold">PNG</span>
            </button>
        </div>
    </div>
</template>

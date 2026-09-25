<script setup>
import { ref } from 'vue';
import { 
    Clock, 
    Copy, 
    Check 
} from 'lucide-vue-next';

const props = defineProps({
    domainResult: {
        type: Object,
        required: true,
    },
});

const copiedKey = ref(null);

const copyToClipboard = (text, key) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = null;
    }, 2000);
};
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">PRIMARY IP</span>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs font-bold text-sky-400 truncate">{{ domainResult.primary_ip || 'Unresolved' }}</span>
                <button 
                    v-if="domainResult.primary_ip"
                    type="button"
                    @click="copyToClipboard(domainResult.primary_ip, 'ip')" 
                    class="text-slate-400 hover:text-white cursor-pointer"
                    title="Copy IP"
                >
                    <Check v-if="copiedKey === 'ip'" class="w-3 h-3 text-emerald-400" />
                    <Copy v-else class="w-3 h-3" />
                </button>
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">GEO / COUNTRY</span>
            <div class="text-xs font-bold text-emerald-400 truncate mt-1">
                {{ domainResult.summary?.country || 'Unknown' }}
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">HOSTING & ASN</span>
            <div class="text-xs font-bold text-amber-400 truncate mt-1" :title="domainResult.summary?.hosting_org">
                {{ domainResult.summary?.hosting_org || 'N/A' }}
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">SUBDOMAINS (CRT.SH)</span>
            <div class="text-xs font-bold text-purple-400 mt-1">
                {{ domainResult.summary?.total_subdomains || 0 }} Discovered
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">DNS RECORDS</span>
            <div class="text-xs font-bold text-cyan-400 mt-1">
                {{ domainResult.summary?.total_dns_records || 0 }} Records
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 font-mono">
            <span class="text-[10px] uppercase text-slate-400 block">PROBE DURATION</span>
            <div class="text-xs font-bold text-slate-300 mt-1 flex items-center space-x-1">
                <Clock class="w-3 h-3 text-slate-400" />
                <span>{{ domainResult.execution_time }}s</span>
            </div>
        </div>
    </div>
</template>

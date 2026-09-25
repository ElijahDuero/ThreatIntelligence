<script setup>
import { ref, computed } from 'vue';
import { 
    Copy, 
    Check 
} from 'lucide-vue-next';

const props = defineProps({
    dns: {
        type: Object,
        default: () => ({ records: [], counts: {} }),
    },
});

defineEmits(['inspect-ip']);

const dnsFilter = ref('ALL');
const copiedKey = ref(null);

const copyToClipboard = (text, key) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = null;
    }, 2000);
};

const filteredDnsRecords = computed(() => {
    const list = props.dns?.records || [];
    if (dnsFilter.value === 'ALL') return list;
    return list.filter(r => r.type === dnsFilter.value);
});

const dnsTypeCounts = computed(() => {
    return props.dns?.counts || {};
});
</script>

<template>
    <div class="space-y-4 font-mono text-xs">
        <!-- DNS Record Type Filter Bar -->
        <div class="flex flex-wrap items-center gap-1.5 bg-slate-900/60 p-2.5 rounded-xl border border-slate-800">
            <span class="text-slate-400 text-[11px] mr-1">RECORD TYPE:</span>
            <button
                type="button"
                @click="dnsFilter = 'ALL'"
                :class="dnsFilter === 'ALL' ? 'bg-sky-500/20 border-sky-500/50 text-sky-300 font-bold' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'"
                class="px-2.5 py-1 rounded-lg border transition text-[11px] cursor-pointer"
            >
                ALL ({{ dns.records?.length || 0 }})
            </button>
            <button
                v-for="(count, type) in dnsTypeCounts"
                :key="type"
                type="button"
                @click="dnsFilter = type"
                :class="dnsFilter === type ? 'bg-sky-500/20 border-sky-500/50 text-sky-300 font-bold' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'"
                class="px-2.5 py-1 rounded-lg border transition text-[11px] cursor-pointer"
            >
                {{ type }} ({{ count }})
            </button>
        </div>

        <!-- DNS Records Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/70">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-[10px] text-slate-400 uppercase bg-slate-950">
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Host</th>
                        <th class="py-2.5 px-3">Value / Target</th>
                        <th class="py-2.5 px-3">TTL</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    <tr 
                        v-for="(rec, idx) in filteredDnsRecords" 
                        :key="idx"
                        class="hover:bg-slate-800/40 transition"
                    >
                        <td class="py-2.5 px-3">
                            <span 
                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                :class="[
                                    rec.type === 'A' ? 'bg-cyan-950 text-cyan-300 border border-cyan-500/40' :
                                    rec.type === 'AAAA' ? 'bg-indigo-950 text-indigo-300 border border-indigo-500/40' :
                                    rec.type === 'MX' ? 'bg-purple-950 text-purple-300 border border-purple-500/40' :
                                    rec.type === 'NS' ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/40' :
                                    rec.type === 'TXT' ? 'bg-amber-950 text-amber-300 border border-amber-500/40' :
                                    'bg-rose-950 text-rose-300 border border-rose-500/40'
                                ]"
                            >
                                {{ rec.type }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-slate-300">{{ rec.host }}</td>
                        <td class="py-2.5 px-3 text-slate-100 font-mono break-all">
                            <div class="flex items-center space-x-2">
                                <span>{{ rec.value }}</span>
                                <span v-if="rec.extra?.priority !== undefined" class="text-[10px] text-purple-400 font-bold">
                                    (Pri: {{ rec.extra.priority }})
                                </span>
                            </div>
                        </td>
                        <td class="py-2.5 px-3 text-slate-400">{{ rec.ttl }}s</td>
                        <td class="py-2.5 px-3 text-right">
                            <div class="flex items-center justify-end space-x-1.5">
                                <button
                                    v-if="rec.type === 'A'"
                                    type="button"
                                    @click="$emit('inspect-ip', rec.value)"
                                    class="px-1.5 py-0.5 rounded bg-sky-950 border border-sky-500/40 text-sky-300 hover:text-white text-[10px] cursor-pointer"
                                    title="Inspect IP directly"
                                >
                                    IP Intel
                                </button>
                                <button
                                    type="button"
                                    @click="copyToClipboard(rec.value, 'dns_' + idx)"
                                    class="p-1 rounded bg-slate-950 hover:bg-slate-800 text-slate-400 hover:text-white cursor-pointer"
                                    title="Copy Value"
                                >
                                    <Check v-if="copiedKey === 'dns_' + idx" class="w-3 h-3 text-emerald-400" />
                                    <Copy v-else class="w-3 h-3" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

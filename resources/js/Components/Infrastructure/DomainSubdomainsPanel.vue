<script setup>
import { ref, computed } from 'vue';
import { 
    Layers, 
    Search, 
    Copy, 
    Check, 
    ArrowRight 
} from 'lucide-vue-next';

const props = defineProps({
    subdomains: {
        type: Object,
        default: () => ({ subdomains: [], total_found: 0 }),
    },
    targetDomain: {
        type: String,
        default: '',
    },
});

defineEmits(['inspect-subdomain']);

const subdomainSearch = ref('');
const copiedKey = ref(null);

const copyToClipboard = (text, key) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = null;
    }, 2000);
};

const filteredSubdomains = computed(() => {
    const list = props.subdomains?.subdomains || [];
    const query = subdomainSearch.value.trim().toLowerCase();
    if (!query) return list;
    return list.filter(item => 
        (item.subdomain || '').toLowerCase().includes(query) || 
        (item.issuer || '').toLowerCase().includes(query)
    );
});
</script>

<template>
    <div class="space-y-4 font-mono text-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/60 p-3 rounded-xl border border-slate-800">
            <div class="flex items-center space-x-2 text-xs font-mono text-slate-300">
                <Layers class="w-4 h-4 text-purple-400" />
                <span>Certificate Transparency Logs for <strong>*.{{ targetDomain }}</strong></span>
                <span class="text-slate-400">({{ subdomains.total_found || 0 }} indexed)</span>
            </div>
            <div class="w-full sm:w-64 relative">
                <input
                    v-model="subdomainSearch"
                    type="text"
                    placeholder="Filter subdomains..."
                    class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-xs font-mono text-slate-200 placeholder-slate-400 focus:outline-none focus:border-purple-500/60"
                />
                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" />
            </div>
        </div>

        <div v-if="filteredSubdomains.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 font-mono text-xs">
            <div 
                v-for="(sub, idx) in filteredSubdomains" 
                :key="idx"
                class="p-3.5 rounded-xl bg-slate-900 border border-slate-800/90 hover:border-purple-500/50 transition group flex flex-col justify-between space-y-2.5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="overflow-hidden">
                        <span class="text-xs font-bold text-slate-100 block truncate group-hover:text-purple-300 transition" :title="sub.subdomain">
                            {{ sub.subdomain }}
                        </span>
                        <span class="text-[10px] text-slate-400 block truncate mt-0.5" :title="sub.issuer">
                            CA: {{ sub.issuer }}
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="copyToClipboard(sub.subdomain, 'sub_' + idx)"
                        class="p-1 rounded bg-slate-950 hover:bg-slate-800 text-slate-400 hover:text-white transition shrink-0 cursor-pointer"
                        title="Copy Subdomain"
                    >
                        <Check v-if="copiedKey === 'sub_' + idx" class="w-3 h-3 text-emerald-400" />
                        <Copy v-else class="w-3 h-3" />
                    </button>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-800/80 text-[10px] text-slate-400">
                    <span>Expires: {{ sub.valid_until ? sub.valid_until.split('T')[0] : 'N/A' }}</span>
                    <button
                        type="button"
                        @click="$emit('inspect-subdomain', sub.subdomain)"
                        class="text-sky-400 hover:text-sky-300 hover:underline flex items-center space-x-1 cursor-pointer"
                        title="Inspect this subdomain as target"
                    >
                        <span>Probe</span>
                        <ArrowRight class="w-2.5 h-2.5" />
                    </button>
                </div>
            </div>
        </div>
        <div v-else class="p-8 text-center rounded-xl bg-slate-900/40 border border-slate-800 font-mono text-xs text-slate-400">
            No subdomains matching "{{ subdomainSearch }}" found in Certificate Transparency logs.
        </div>
    </div>
</template>

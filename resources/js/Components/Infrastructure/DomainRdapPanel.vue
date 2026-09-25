<script setup>
import { ref } from 'vue';
import { 
    Globe, 
    Server, 
    Copy, 
    Check 
} from 'lucide-vue-next';

const props = defineProps({
    rdap: {
        type: Object,
        default: () => ({}),
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
    <div class="space-y-4 font-mono text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Registrar Details -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-amber-400 uppercase tracking-wide flex items-center space-x-2">
                    <Globe class="w-4 h-4 text-amber-400" />
                    <span>Registrar & Lifecycle</span>
                </h3>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Target Domain:</span>
                        <span class="text-white font-bold">{{ rdap?.domain || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Registrar Organization:</span>
                        <span class="text-amber-300 font-bold">{{ rdap?.registrar || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Registered On:</span>
                        <span class="text-slate-200">{{ rdap?.registration_date || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Registry Expiration:</span>
                        <span class="text-emerald-400 font-bold">{{ rdap?.expiration_date || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-400">Last Changed:</span>
                        <span class="text-slate-300">{{ rdap?.last_changed_date || 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Status & Authoritative Nameservers -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-sky-400 uppercase tracking-wide flex items-center space-x-2">
                    <Server class="w-4 h-4 text-sky-400" />
                    <span>Status & Nameservers</span>
                </h3>

                <!-- Status Badges -->
                <div>
                    <span class="text-[10px] text-slate-400 uppercase block mb-1.5">Domain Status Flags</span>
                    <div class="flex flex-wrap gap-1.5">
                        <span 
                            v-for="(st, idx) in (rdap?.status || [])" 
                            :key="idx"
                            class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-300 text-[10px]"
                        >
                            {{ st }}
                        </span>
                        <span v-if="!rdap?.status || rdap.status.length === 0" class="text-slate-400 text-xs">
                            No specific status flags reported.
                        </span>
                    </div>
                </div>

                <!-- Nameservers list -->
                <div class="pt-2 border-t border-slate-800">
                    <span class="text-[10px] text-slate-400 uppercase block mb-1.5">Authoritative Nameservers</span>
                    <div class="space-y-1">
                        <div 
                            v-for="(ns, idx) in (rdap?.nameservers || [])" 
                            :key="idx"
                            class="flex items-center justify-between p-1.5 rounded bg-slate-950 border border-slate-800 text-xs text-slate-200"
                        >
                            <span>{{ ns }}</span>
                            <button
                                type="button"
                                @click="copyToClipboard(ns, 'rdap_ns_' + idx)"
                                class="text-slate-400 hover:text-white p-0.5 cursor-pointer"
                                title="Copy NS"
                            >
                                <Check v-if="copiedKey === 'rdap_ns_' + idx" class="w-3 h-3 text-emerald-400" />
                                <Copy v-else class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

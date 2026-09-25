<script setup>
import { 
    Cpu, 
    Network 
} from 'lucide-vue-next';
import PortsAndCvePanel from '@/Components/Infrastructure/PortsAndCvePanel.vue';

defineProps({
    ipResult: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-4 font-mono text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- IP Routing & Location -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-amber-400 uppercase tracking-wide flex items-center space-x-2">
                    <Cpu class="w-4 h-4 text-amber-400" />
                    <span>IP Routing & Location</span>
                </h3>

                <div class="space-y-2">
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Target IP:</span>
                        <span class="text-white font-bold">{{ ipResult.ip }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Reverse DNS PTR:</span>
                        <span class="text-sky-300 font-bold truncate">{{ ipResult.reverse_dns || 'None' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Country:</span>
                        <span class="text-emerald-300 font-bold">{{ ipResult.country }} ({{ ipResult.country_code }})</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">City / Region:</span>
                        <span class="text-slate-200">{{ ipResult.city }}, {{ ipResult.region }}</span>
                    </div>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-400">Timezone:</span>
                        <span class="text-slate-300">{{ ipResult.timezone }}</span>
                    </div>
                </div>
            </div>

            <!-- Autonomous System & Provider -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-sky-400 uppercase tracking-wide flex items-center space-x-2">
                    <Network class="w-4 h-4 text-sky-400" />
                    <span>Autonomous System & Provider</span>
                </h3>

                <div class="space-y-2">
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Organization:</span>
                        <span class="text-amber-300 font-bold">{{ ipResult.org }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">ISP:</span>
                        <span class="text-slate-200">{{ ipResult.isp }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">ASN:</span>
                        <span class="text-cyan-300 font-bold">{{ ipResult.as }}</span>
                    </div>
                    <div v-if="ipResult.lat && ipResult.lon" class="flex justify-between py-1.5">
                        <span class="text-slate-400">Coordinates:</span>
                        <span class="text-emerald-400 font-bold">{{ ipResult.lat }}, {{ ipResult.lon }}</span>
                    </div>
                </div>
            </div>

            <!-- Standalone IP: Open Ports & CVE Intel -->
            <div v-if="ipResult.ports_intel" class="col-span-1 md:col-span-2">
                <PortsAndCvePanel 
                    :ports-intel="ipResult.ports_intel"
                    :host-label="ipResult.ip"
                    :show-summary-cards="false"
                />
            </div>
        </div>
    </div>
</template>

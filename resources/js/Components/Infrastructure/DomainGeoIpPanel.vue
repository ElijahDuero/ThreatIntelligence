<script setup>
import { 
    MapPin, 
    Cpu 
} from 'lucide-vue-next';

defineProps({
    ipIntel: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div v-if="ipIntel" class="space-y-4 font-mono text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Geolocation Data -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-emerald-400 uppercase tracking-wide flex items-center space-x-2">
                    <MapPin class="w-4 h-4 text-emerald-400" />
                    <span>Geolocation Data</span>
                </h3>

                <div class="space-y-2">
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Host IP:</span>
                        <span class="text-sky-300 font-bold">{{ ipIntel.ip }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Reverse DNS:</span>
                        <span class="text-slate-200 truncate">{{ ipIntel.reverse_dns || 'No PTR Record' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Country:</span>
                        <span class="text-emerald-300 font-bold">{{ ipIntel.country }} ({{ ipIntel.country_code }})</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Region / City:</span>
                        <span class="text-slate-200">{{ ipIntel.region }} / {{ ipIntel.city }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Timezone:</span>
                        <span class="text-slate-300">{{ ipIntel.timezone }}</span>
                    </div>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-400">Coordinates:</span>
                        <span class="text-cyan-300 font-bold">
                            {{ ipIntel.lat }}, {{ ipIntel.lon }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Network & Autonomous System -->
            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
                <h3 class="font-serif font-bold text-sm text-cyan-400 uppercase tracking-wide flex items-center space-x-2">
                    <Cpu class="w-4 h-4 text-cyan-400" />
                    <span>Network & Autonomous System</span>
                </h3>

                <div class="space-y-2">
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Organization:</span>
                        <span class="text-white font-bold">{{ ipIntel.org }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">ISP Provider:</span>
                        <span class="text-slate-200">{{ ipIntel.isp }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-800">
                        <span class="text-slate-400">Autonomous System:</span>
                        <span class="text-amber-300 font-bold">{{ ipIntel.as }}</span>
                    </div>
                    <div v-if="ipIntel.network_block" class="space-y-1 pt-2">
                        <span class="text-[10px] text-slate-400 uppercase block">RDAP Network Allocation</span>
                        <div class="p-2 rounded bg-slate-950 border border-slate-800 text-[11px] text-slate-300 space-y-1">
                            <div><strong>NetName:</strong> {{ ipIntel.network_block.name }}</div>
                            <div><strong>Handle:</strong> {{ ipIntel.network_block.handle }}</div>
                            <div><strong>Range:</strong> {{ ipIntel.network_block.start_address }} - {{ ipIntel.network_block.end_address }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

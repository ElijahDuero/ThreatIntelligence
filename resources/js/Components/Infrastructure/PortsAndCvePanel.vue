<script setup>
import { ref } from 'vue';
import { 
    Server, 
    ShieldAlert, 
    CheckCircle2, 
    Copy, 
    Check, 
    ExternalLink 
} from 'lucide-vue-next';

const props = defineProps({
    portsIntel: {
        type: Object,
        default: () => ({
            ports: [],
            cves: [],
            cpes: [],
            tags: [],
            total_ports: 0,
            total_cves: 0,
            has_vulnerabilities: false,
        }),
    },
    hostLabel: {
        type: String,
        default: '',
    },
    showSummaryCards: {
        type: Boolean,
        default: true,
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
        <!-- Top HUD Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/60 p-3 rounded-xl border border-slate-800">
            <div class="flex items-center space-x-2 text-xs font-mono text-slate-300">
                <Server class="w-4 h-4 text-indigo-400" />
                <span>Passive Ports & Exploit Surface: <strong class="text-white">{{ hostLabel }}</strong></span>
                <span class="text-slate-400">(Shodan InternetDB Feed)</span>
            </div>
            <div class="flex items-center space-x-2">
                <span 
                    class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider"
                    :class="portsIntel?.has_vulnerabilities || portsIntel?.total_cves > 0 ? 'bg-rose-950 text-rose-300 border border-rose-500/60 animate-pulse' : 'bg-emerald-950 text-emerald-300 border border-emerald-500/40'"
                >
                    {{ (portsIntel?.has_vulnerabilities || portsIntel?.total_cves > 0) ? (portsIntel?.total_cves || portsIntel?.cves?.length || 0) + ' VULNERABILITIES DETECTED' : 'NO KNOWN EXPLOITS INDEXED' }}
                </span>
            </div>
        </div>

        <!-- Ports & Exploit Summary Grid -->
        <div v-if="showSummaryCards" class="grid grid-cols-1 md:grid-cols-3 gap-3 font-mono">
            <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
                <span class="text-[10px] uppercase text-slate-400 block">EXPOSED PORTS</span>
                <div class="text-base font-bold text-sky-400 mt-1">
                    {{ portsIntel?.total_ports ?? portsIntel?.ports?.length ?? 0 }} Services Detected
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
                <span class="text-[10px] uppercase text-slate-400 block">REPORTED CVEs</span>
                <div 
                    class="text-base font-bold mt-1"
                    :class="(portsIntel?.total_cves || portsIntel?.cves?.length || 0) > 0 ? 'text-rose-400' : 'text-emerald-400'"
                >
                    {{ portsIntel?.total_cves ?? portsIntel?.cves?.length ?? 0 }} Known Vulnerabilities
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
                <span class="text-[10px] uppercase text-slate-400 block">SOFTWARE PACKAGES (CPE)</span>
                <div class="text-base font-bold text-amber-400 mt-1">
                    {{ portsIntel?.cpes?.length || 0 }} Identified Fingerprints
                </div>
            </div>
        </div>

        <!-- Discovered Open Ports Matrix -->
        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
            <h3 class="font-serif font-bold text-sm text-sky-400 uppercase tracking-wide flex items-center space-x-2">
                <Server class="w-4 h-4 text-sky-400" />
                <span>Passive Open Ports ({{ portsIntel?.total_ports ?? portsIntel?.ports?.length ?? 0 }})</span>
            </h3>

            <div v-if="portsIntel?.ports && portsIntel.ports.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
                <div 
                    v-for="p in portsIntel.ports" 
                    :key="p.port"
                    class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-sky-500/50 transition flex flex-col justify-between space-y-1.5"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-sky-300 font-mono">PORT {{ p.port }}</span>
                        <button 
                            type="button"
                            @click="copyToClipboard(p.port.toString(), 'port_' + p.port)"
                            class="text-slate-500 hover:text-white cursor-pointer"
                            title="Copy Port"
                        >
                            <Check v-if="copiedKey === 'port_' + p.port" class="w-3 h-3 text-emerald-400" />
                            <Copy v-else class="w-3 h-3" />
                        </button>
                    </div>
                    <span class="text-[10px] text-slate-300 truncate" :title="p.service">
                        {{ p.service }}
                    </span>
                    <div class="pt-1 border-t border-slate-800/80 flex items-center justify-between text-[9px] text-slate-400">
                        <span>TCP</span>
                        <span class="text-emerald-400 font-bold">PASSIVE</span>
                    </div>
                </div>
            </div>
            <div v-else class="p-6 text-center rounded-lg bg-slate-950/60 border border-slate-800 text-slate-400 text-xs">
                No open ports currently indexed for this host in passive Shodan telemetry.
            </div>
        </div>

        <!-- Vulnerabilities & CVE Catalog -->
        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="font-serif font-bold text-sm text-rose-400 uppercase tracking-wide flex items-center space-x-2">
                    <ShieldAlert class="w-4 h-4 text-rose-400" />
                    <span>Reported CVE Vulnerabilities ({{ portsIntel?.total_cves ?? portsIntel?.cves?.length ?? 0 }})</span>
                </h3>
                <span v-if="(portsIntel?.total_cves || portsIntel?.cves?.length || 0) > 0" class="text-[10px] text-slate-400">
                    Direct links to NIST NVD & MITRE CVE Database
                </span>
            </div>

            <div v-if="portsIntel?.cves && portsIntel.cves.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                <div 
                    v-for="cve in portsIntel.cves" 
                    :key="cve.id"
                    class="p-3 rounded-lg bg-slate-950 border border-rose-500/40 hover:border-rose-400 transition space-y-2 shadow-sm shadow-rose-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-rose-300 font-mono">{{ cve.id }}</span>
                        <button
                            type="button"
                            @click="copyToClipboard(cve.id, 'cve_' + cve.id)"
                            class="text-slate-500 hover:text-white cursor-pointer"
                            title="Copy CVE ID"
                        >
                            <Check v-if="copiedKey === 'cve_' + cve.id" class="w-3 h-3 text-emerald-400" />
                            <Copy v-else class="w-3 h-3" />
                        </button>
                    </div>
                    <div class="flex items-center space-x-2 pt-1.5 border-t border-slate-800 text-[10px]">
                        <a 
                            :href="cve.nvd_url" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="text-sky-400 hover:text-sky-300 underline flex items-center space-x-1"
                        >
                            <span>NIST NVD</span>
                            <ExternalLink class="w-2.5 h-2.5" />
                        </a>
                        <span class="text-slate-600">•</span>
                        <a 
                            :href="cve.mitre_url" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="text-amber-400 hover:text-amber-300 underline flex items-center space-x-1"
                        >
                            <span>MITRE</span>
                            <ExternalLink class="w-2.5 h-2.5" />
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="p-6 text-center rounded-lg bg-slate-950/60 border border-slate-800 text-slate-400 text-xs flex items-center justify-center space-x-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>No known vulnerabilities or active CVEs indexed for this host IP address.</span>
            </div>
        </div>

        <!-- Software Stacks & CPEs -->
        <div v-if="portsIntel?.cpes && portsIntel.cpes.length > 0" class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="text-[10px] text-slate-400 uppercase font-bold block">Fingerprinted Software CPEs</span>
            <div class="flex flex-wrap gap-1.5">
                <span 
                    v-for="(cpe, idx) in portsIntel.cpes" 
                    :key="idx"
                    class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-300 text-[10px] font-mono"
                >
                    {{ cpe }}
                </span>
            </div>
        </div>
    </div>
</template>

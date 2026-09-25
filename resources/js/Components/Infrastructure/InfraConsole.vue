<script setup>
import { 
    Server, 
    Globe, 
    Cpu, 
    Search, 
    Loader2, 
    AlertTriangle 
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    activeMode: {
        type: String,
        default: 'domain',
    },
    isAnalyzing: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: null,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:activeMode',
    'analyze',
    'preset-selected',
]);

const domainPresets = ['cloudflare.com', 'torproject.org', 'proton.me', 'github.com', 'wikipedia.org'];
const ipPresets = ['1.1.1.1', '8.8.8.8', '9.9.9.9', '149.154.167.99'];

const setMode = (mode) => {
    emit('update:activeMode', mode);
};

const selectPreset = (val, mode) => {
    emit('preset-selected', { value: val, mode });
};
</script>

<template>
    <div class="p-5 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Mode Switcher -->
            <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-slate-800 text-xs font-mono">
                <button
                    type="button"
                    @click="setMode('domain')"
                    :class="activeMode === 'domain' ? 'bg-sky-500/20 text-sky-300 border border-sky-500/40 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                    class="px-3 py-1.5 rounded-lg transition flex items-center space-x-1.5 cursor-pointer"
                >
                    <Globe class="w-3.5 h-3.5" />
                    <span>Domain & Subdomains</span>
                </button>
                <button
                    type="button"
                    @click="setMode('ip')"
                    :class="activeMode === 'ip' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40 font-bold' : 'text-slate-400 hover:text-slate-200 border-transparent'"
                    class="px-3 py-1.5 rounded-lg transition flex items-center space-x-1.5 cursor-pointer"
                >
                    <Cpu class="w-3.5 h-3.5" />
                    <span>Direct IP & ASN</span>
                </button>
            </div>

            <!-- Presets -->
            <div class="flex items-center space-x-1.5 overflow-x-auto text-[11px] font-mono">
                <span class="text-slate-400 text-xs mr-1 hidden sm:inline">PRESETS:</span>
                <template v-if="activeMode === 'domain'">
                    <button
                        v-for="preset in domainPresets"
                        :key="preset"
                        type="button"
                        @click="selectPreset(preset, 'domain')"
                        class="px-2 py-1 rounded bg-slate-950 hover:bg-slate-850 border border-slate-800 text-slate-300 hover:text-white transition cursor-pointer"
                    >
                        {{ preset }}
                    </button>
                </template>
                <template v-else>
                    <button
                        v-for="preset in ipPresets"
                        :key="preset"
                        type="button"
                        @click="selectPreset(preset, 'ip')"
                        class="px-2 py-1 rounded bg-slate-950 hover:bg-slate-850 border border-slate-800 text-slate-300 hover:text-white transition cursor-pointer"
                    >
                        {{ preset }}
                    </button>
                </template>
            </div>
        </div>

        <!-- Input Row -->
        <form @submit.prevent="$emit('analyze')" class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <Server v-if="activeMode === 'domain'" class="w-4 h-4 text-sky-400" />
                    <Cpu v-else class="w-4 h-4 text-amber-400" />
                </div>
                <input
                    :value="modelValue"
                    @input="$emit('update:modelValue', $event.target.value)"
                    type="text"
                    :placeholder="activeMode === 'domain' ? 'Enter target domain (e.g. cloudflare.com, torproject.org)...' : 'Enter target IPv4/IPv6 address (e.g. 1.1.1.1)...'"
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500/60 focus:ring-1 focus:ring-sky-500/30 text-xs font-mono transition"
                    :disabled="isAnalyzing"
                    required
                />
            </div>

            <button
                type="submit"
                :disabled="isAnalyzing || !modelValue.trim()"
                class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-mono font-bold text-xs flex items-center justify-center space-x-2 transition disabled:opacity-50 cursor-pointer shadow-lg shadow-sky-950/40 shrink-0"
            >
                <Loader2 v-if="isAnalyzing" class="w-4 h-4 animate-spin" />
                <Search v-else class="w-4 h-4" />
                <span>{{ isAnalyzing ? 'PROBING NETWORK...' : 'DISCOVER INFRASTRUCTURE' }}</span>
            </button>
        </form>

        <!-- Error Notice -->
        <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-950/60 border border-rose-500/60 text-rose-300 text-xs font-mono flex items-center space-x-2">
            <AlertTriangle class="w-4 h-4 text-rose-400 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>
    </div>
</template>

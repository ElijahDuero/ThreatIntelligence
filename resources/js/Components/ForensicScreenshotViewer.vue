<script setup>
import { ref } from 'vue';
import { 
    Camera, 
    ShieldCheck, 
    Copy, 
    Check, 
    Download, 
    Maximize2, 
    RotateCcw, 
    Loader2, 
    AlertCircle, 
    X, 
    Eye 
} from 'lucide-vue-next';

const props = defineProps({
    screenshot: {
        type: Object,
        default: null,
    },
    targetUrl: {
        type: String,
        required: true,
    },
    targetId: {
        type: Number,
        default: null,
    },
    useTor: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['recapture']);

const isCapturing = ref(false);
const copiedHash = ref(false);
const showLightbox = ref(false);
const captureError = ref(null);

const copySha256 = () => {
    if (!props.screenshot?.sha256) return;
    navigator.clipboard.writeText(props.screenshot.sha256);
    copiedHash.value = true;
    setTimeout(() => {
        copiedHash.value = false;
    }, 2000);
};

const triggerCapture = async () => {
    if (isCapturing.value) return;
    isCapturing.value = true;
    captureError.value = null;

    try {
        const res = await axios.post('/api/scraper/screenshot', {
            url: props.targetUrl,
            target_id: props.targetId,
            use_tor: props.useTor,
        });

        if (res.data?.success) {
            emit('recapture', res.data);
        } else {
            captureError.value = res.data?.error || 'Screenshot capture failed.';
        }
    } catch (err) {
        captureError.value = err.response?.data?.message || err.message || 'Error executing headless browser screenshot.';
    } finally {
        isCapturing.value = false;
    }
};

const formatDate = (isoString) => {
    if (!isoString) return 'Just now';
    try {
        return new Date(isoString).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return isoString;
    }
};
</script>

<template>
    <div class="space-y-6 font-sans">
        <!-- Error Banner -->
        <div v-if="captureError" class="p-4 rounded-2xl bg-rose-950/40 border border-rose-500/50 text-rose-300 text-xs font-mono flex items-center justify-between shadow-xl cartoon-card">
            <div class="flex items-center space-x-2.5">
                <AlertCircle class="w-4 h-4 text-rose-400 shrink-0" />
                <span>{{ captureError }}</span>
            </div>
            <button @click="captureError = null" class="text-rose-400 hover:text-white p-1">
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Capturing In-Progress State -->
        <div v-if="isCapturing" class="p-12 rounded-3xl bg-slate-950/90 border border-cyan-500/40 text-center space-y-4 shadow-2xl cartoon-card">
            <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-2 border-cyan-500/20 animate-ping"></div>
                <div class="w-12 h-12 rounded-2xl bg-cyan-950 border border-cyan-500/50 flex items-center justify-center">
                    <Loader2 class="w-6 h-6 text-cyan-400 animate-spin" />
                </div>
            </div>
            <div class="space-y-1">
                <h3 class="text-base font-serif font-black text-white uppercase tracking-wider">
                    Rendering Headless Browser Target
                </h3>
                <p class="text-xs font-mono text-slate-400 max-w-md mx-auto">
                    Spawning isolated Chromium process{{ useTor || targetUrl.includes('.onion') ? ' routed via Tor SOCKS5 proxy' : '' }}, executing virtual viewport budget, and capturing full-frame pixel evidence.
                </p>
            </div>
        </div>

        <!-- Populated Evidence State -->
        <div v-else-if="screenshot && screenshot.public_url" class="space-y-6">
            <!-- Metadata & Chain-of-Custody Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 font-mono text-xs">
                <!-- SHA-256 Card -->
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 shadow-xl cartoon-card md:col-span-2 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 flex items-center space-x-1.5 uppercase font-bold text-[11px]">
                            <ShieldCheck class="w-4 h-4 text-emerald-400" />
                            <span>SHA-256 FORENSIC HASH</span>
                        </span>
                        <button 
                            @click="copySha256"
                            class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 hover:border-cyan-400 text-slate-300 hover:text-cyan-300 flex items-center space-x-1 transition cartoon-btn text-[10px]"
                        >
                            <Check v-if="copiedHash" class="w-3 h-3 text-emerald-400" />
                            <Copy v-else class="w-3 h-3 text-cyan-400" />
                            <span>{{ copiedHash ? 'COPIED!' : 'COPY HASH' }}</span>
                        </button>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-900/90 border border-slate-850 text-cyan-300 font-mono text-xs break-all select-all">
                        {{ screenshot.sha256 }}
                    </div>
                </div>

                <!-- Capture Context Card -->
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 shadow-xl cartoon-card space-y-2">
                    <span class="text-slate-400 block uppercase font-bold text-[11px]">SPECIFICATIONS</span>
                    <div class="space-y-1.5 text-[11px] text-slate-300">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Dimensions:</span>
                            <span class="font-bold text-white">{{ screenshot.width || 1280 }} × {{ screenshot.height || 800 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">File Size:</span>
                            <span class="font-bold text-white">{{ screenshot.file_size_formatted || '48 KB' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Tor Proxy:</span>
                            <span :class="screenshot.routed_tor ? 'text-purple-400 font-bold' : 'text-slate-400'">
                                {{ screenshot.routed_tor ? 'SOCKS5 ROUTED' : 'CLEARNET DIRECT' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Captured:</span>
                            <span class="text-slate-400 text-[10px]">{{ formatDate(screenshot.captured_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Screenshot Render Preview Card -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl cartoon-card">
                <!-- Preview Toolbar -->
                <div class="px-5 py-3.5 border-b border-slate-800 bg-slate-900/90 flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
                    <div class="flex items-center space-x-2">
                        <Camera class="w-4 h-4 text-cyan-400" />
                        <span class="text-white font-bold uppercase">RENDERED VIEWPORT SNAPSHOT</span>
                        <span v-if="screenshot.filename" class="text-slate-500 text-[11px] hidden sm:inline">
                            ({{ screenshot.filename }})
                        </span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button 
                            @click="triggerCapture"
                            :disabled="isCapturing"
                            class="px-3 py-1.5 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-200 hover:text-white flex items-center space-x-1.5 transition cartoon-btn"
                            title="Recapture fresh screenshot"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-cyan-400" />
                            <span>RECAPTURE</span>
                        </button>

                        <a 
                            :href="screenshot.public_url" 
                            :download="screenshot.filename || 'evidence.png'"
                            class="px-3 py-1.5 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-200 hover:text-white flex items-center space-x-1.5 transition cartoon-btn"
                            title="Download evidence image"
                        >
                            <Download class="w-3.5 h-3.5 text-emerald-400" />
                            <span>DOWNLOAD PNG</span>
                        </a>

                        <button 
                            @click="showLightbox = true"
                            class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold flex items-center space-x-1.5 transition cartoon-btn"
                            title="Open full size"
                        >
                            <Maximize2 class="w-3.5 h-3.5" />
                            <span>FULL SIZE</span>
                        </button>
                    </div>
                </div>

                <!-- Image Canvas Container -->
                <div 
                    class="relative p-3 sm:p-6 bg-slate-950 flex items-center justify-center group cursor-pointer overflow-hidden"
                    @click="showLightbox = true"
                >
                    <img 
                        :src="screenshot.public_url" 
                        alt="Headless Target Screenshot" 
                        class="max-w-full h-auto rounded-2xl border border-slate-800 shadow-2xl group-hover:scale-[1.01] transition-transform duration-300"
                        loading="lazy"
                    />

                    <div class="absolute inset-0 bg-slate-950/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <div class="px-4 py-2 rounded-2xl bg-slate-900/90 border border-cyan-500/50 text-cyan-300 font-mono text-xs font-bold flex items-center space-x-2 shadow-2xl">
                            <Eye class="w-4 h-4" />
                            <span>CLICK TO ENLARGE EVIDENCE</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State: No Screenshot Captured Yet -->
        <div v-else class="p-10 sm:p-14 rounded-3xl bg-slate-950/70 border border-slate-800 text-center space-y-4 shadow-xl cartoon-card">
            <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto text-slate-600">
                <Camera class="w-7 h-7" />
            </div>
            <div class="space-y-1">
                <h3 class="text-base font-serif font-black text-white uppercase tracking-wider">
                    Visual Evidence Not Yet Captured
                </h3>
                <p class="text-xs font-mono text-slate-400 max-w-md mx-auto">
                    Capture a high-fidelity headless browser rendering of this target. Useful for documenting onion market storefronts, hidden service defacements, or phishing portals.
                </p>
            </div>
            <div class="pt-2">
                <button 
                    @click="triggerCapture"
                    :disabled="isCapturing"
                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-400 hover:to-cyan-500 text-slate-950 font-mono text-xs font-bold inline-flex items-center space-x-2 transition shadow-lg shadow-cyan-500/20 cartoon-btn"
                >
                    <Camera class="w-4 h-4" />
                    <span>CAPTURE HEADLESS SCREENSHOT NOW</span>
                </button>
            </div>
        </div>

        <!-- Fullscreen Lightbox Modal -->
        <Teleport to="body">
            <div 
                v-if="showLightbox && screenshot?.public_url" 
                class="fixed inset-0 z-[9999] bg-slate-950/95 backdrop-blur-md flex flex-col p-4 sm:p-6"
                @click="showLightbox = false"
            >
                <div class="flex items-center justify-between pb-4 font-mono text-xs border-b border-slate-800" @click.stop>
                    <div class="flex items-center space-x-3 text-slate-300">
                        <span class="px-2 py-0.5 rounded bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-bold uppercase">
                            FORENSIC EVIDENCE
                        </span>
                        <span class="text-white font-bold truncate max-w-md">{{ targetUrl }}</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <a 
                            :href="screenshot.public_url" 
                            :download="screenshot.filename || 'evidence.png'"
                            class="px-3 py-1.5 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-200 hover:text-white flex items-center space-x-1.5 transition cartoon-btn"
                        >
                            <Download class="w-3.5 h-3.5 text-emerald-400" />
                            <span>Download PNG</span>
                        </a>

                        <button 
                            @click="showLightbox = false"
                            class="p-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.stop>
                    <img 
                        :src="screenshot.public_url" 
                        alt="Full Size Screenshot" 
                        class="max-w-full max-h-[82vh] object-contain rounded-2xl shadow-2xl border border-slate-800"
                    />
                </div>

                <div class="pt-3 border-t border-slate-800 font-mono text-xs flex flex-wrap items-center justify-between text-slate-400 gap-2" @click.stop>
                    <div class="flex items-center gap-4 text-[11px]">
                        <span>SHA-256: <code class="text-cyan-300">{{ screenshot.sha256 }}</code></span>
                        <span>Size: {{ screenshot.file_size_formatted }}</span>
                    </div>
                    <button 
                        @click="showLightbox = false"
                        class="px-4 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs cartoon-btn"
                    >
                        Close Preview (Esc)
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

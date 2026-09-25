<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
    Image as ImageIcon, 
    Eye, 
    Copy, 
    Check, 
    ExternalLink, 
    X 
} from 'lucide-vue-next';

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
});

const selectedImage = ref(null);
const imageFilter = ref('all');
const copiedImageUrl = ref(null);
const failedImages = ref(new Set());

const filteredImages = computed(() => {
    const list = props.images || [];
    if (imageFilter.value === 'all') return list;
    if (imageFilter.value === 'jpg') {
        return list.filter(i => ['jpg', 'jpeg'].includes(i.extension?.toLowerCase()));
    }
    if (imageFilter.value === 'png') {
        return list.filter(i => ['png', 'webp', 'avif'].includes(i.extension?.toLowerCase()));
    }
    if (imageFilter.value === 'svg') {
        return list.filter(i => ['svg', 'ico'].includes(i.extension?.toLowerCase()));
    }
    if (imageFilter.value === 'meta') {
        return list.filter(i => i.source_type === 'meta_og' || i.source_type === 'favicon');
    }
    if (imageFilter.value === 'bg') {
        return list.filter(i => i.source_type === 'background_css' || i.source_type === 'linked_media');
    }
    return list;
});

const imageTypeCounts = computed(() => {
    const list = props.images || [];
    return {
        all: list.length,
        jpg: list.filter(i => ['jpg', 'jpeg'].includes(i.extension?.toLowerCase())).length,
        png: list.filter(i => ['png', 'webp', 'avif'].includes(i.extension?.toLowerCase())).length,
        svg: list.filter(i => ['svg', 'ico'].includes(i.extension?.toLowerCase())).length,
        meta: list.filter(i => i.source_type === 'meta_og' || i.source_type === 'favicon').length,
        bg: list.filter(i => i.source_type === 'background_css' || i.source_type === 'linked_media').length,
    };
});

const copyImageUrl = (url) => {
    navigator.clipboard.writeText(url);
    copiedImageUrl.value = url;
    setTimeout(() => {
        copiedImageUrl.value = null;
    }, 2000);
};

const handleImageError = (url) => {
    failedImages.value.add(url);
};

const openLightbox = (img) => {
    selectedImage.value = img;
};

const closeLightbox = () => {
    selectedImage.value = null;
};

const onKeyHandler = (e) => {
    if (e.key === 'Escape' && selectedImage.value) {
        selectedImage.value = null;
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeyHandler);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyHandler);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Filter Bar & Stats -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-slate-800/80">
            <div class="flex items-center space-x-1.5 text-xs font-mono overflow-x-auto no-scrollbar pb-1">
                <button 
                    type="button"
                    @click="imageFilter = 'all'"
                    :class="[imageFilter === 'all' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    ALL ({{ imageTypeCounts.all }})
                </button>
                <button 
                    type="button"
                    @click="imageFilter = 'jpg'"
                    :class="[imageFilter === 'jpg' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    JPG / PHOTOS ({{ imageTypeCounts.jpg }})
                </button>
                <button 
                    type="button"
                    @click="imageFilter = 'png'"
                    :class="[imageFilter === 'png' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    PNG / WEBP ({{ imageTypeCounts.png }})
                </button>
                <button 
                    type="button"
                    @click="imageFilter = 'svg'"
                    :class="[imageFilter === 'svg' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    SVG / ICONS ({{ imageTypeCounts.svg }})
                </button>
                <button 
                    type="button"
                    @click="imageFilter = 'meta'"
                    :class="[imageFilter === 'meta' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    OPENGRAPH ({{ imageTypeCounts.meta }})
                </button>
                <button 
                    type="button"
                    @click="imageFilter = 'bg'"
                    :class="[imageFilter === 'bg' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50 font-bold' : 'text-slate-400 hover:text-white bg-slate-900 border-slate-800', 'px-3 py-1.5 rounded-xl border transition-all text-[11px] cartoon-btn cursor-pointer']"
                >
                    CSS / LINKED ({{ imageTypeCounts.bg }})
                </button>
            </div>

            <div class="text-[11px] font-mono text-slate-400 flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                <span>Showing {{ filteredImages.length }} of {{ images.length }} assets</span>
            </div>
        </div>

        <!-- Image Grid -->
        <div v-if="filteredImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <div 
                v-for="(img, idx) in filteredImages" 
                :key="idx"
                class="rounded-2xl bg-slate-900 border border-slate-800 overflow-hidden group hover:border-cyan-400/80 transition-all cartoon-card flex flex-col justify-between"
            >
                <!-- Thumbnail Container with Hover Controls -->
                <div class="relative aspect-square bg-slate-950 flex items-center justify-center overflow-hidden cursor-pointer" @click="openLightbox(img)">
                    <!-- Real Image with proxy support -->
                    <img 
                        v-if="!failedImages.has(img.url)"
                        :src="img.preview_url || img.url" 
                        :alt="img.alt"
                        @error="handleImageError(img.url)"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                    />
                    <!-- Fallback when image fails to load -->
                    <div v-else class="flex flex-col items-center justify-center p-3 text-center text-slate-600 space-y-1">
                        <ImageIcon class="w-8 h-8 opacity-40" />
                        <span class="text-[9px] font-mono uppercase">Preview Offline</span>
                    </div>

                    <!-- Source Tag Badge (IMG, OG, CSS, LINK) -->
                    <div class="absolute top-2 left-2 flex items-center gap-1">
                        <span class="px-1.5 py-0.5 rounded bg-slate-950/85 border border-slate-700/80 text-[9px] font-mono font-bold uppercase text-cyan-300">
                            {{ img.extension || 'img' }}
                        </span>
                        <span v-if="img.is_onion" class="px-1.5 py-0.5 rounded bg-purple-950/80 border border-purple-500/40 text-[9px] font-mono font-bold uppercase text-purple-300">
                            ONION
                        </span>
                    </div>

                    <!-- Hover Action Overlay -->
                    <div class="absolute inset-0 bg-slate-950/70 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <button 
                            type="button"
                            @click.stop="openLightbox(img)" 
                            class="p-2 rounded-xl bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-500 hover:text-slate-950 transition cartoon-btn cursor-pointer"
                            title="View Full Size"
                        >
                            <Eye class="w-4 h-4" />
                        </button>
                        <button 
                            type="button"
                            @click.stop="copyImageUrl(img.url)" 
                            class="p-2 rounded-xl bg-slate-850 text-slate-200 border border-slate-700 hover:border-cyan-400 hover:text-cyan-300 transition cartoon-btn cursor-pointer"
                            title="Copy Direct URL"
                        >
                            <Copy class="w-4 h-4" />
                        </button>
                        <a 
                            :href="img.url" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            @click.stop
                            class="p-2 rounded-xl bg-slate-850 text-slate-200 border border-slate-700 hover:border-cyan-400 hover:text-cyan-300 transition cartoon-btn"
                            title="Open in new tab"
                        >
                            <ExternalLink class="w-4 h-4" />
                        </a>
                    </div>
                </div>

                <!-- Bottom Info -->
                <div class="p-2.5 bg-slate-900/90 border-t border-slate-800/80 space-y-1">
                    <div class="text-[10px] font-mono text-slate-300 font-bold truncate" :title="img.alt || img.filename">
                        {{ img.filename }}
                    </div>
                    <div class="flex items-center justify-between text-[9px] font-mono text-slate-500">
                        <span class="uppercase">{{ img.source_type }}</span>
                        <button 
                            type="button"
                            @click="copyImageUrl(img.url)" 
                            class="text-cyan-400 hover:underline flex items-center space-x-0.5 cursor-pointer"
                        >
                            <Check v-if="copiedImageUrl === img.url" class="w-2.5 h-2.5 text-emerald-400" />
                            <span v-else>Copy</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="text-xs font-mono text-slate-500 py-12 text-center bg-slate-950/40 rounded-2xl border border-slate-800/60">
            No visual assets matching the selected filter.
        </div>

        <!-- Image Lightbox Modal -->
        <Teleport to="body">
            <div 
                v-if="selectedImage" 
                class="fixed inset-0 z-[110] bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
                @click="closeLightbox"
            >
                <div 
                    class="relative max-w-4xl w-full bg-slate-900 border border-slate-700 rounded-3xl overflow-hidden shadow-2xl cartoon-modal"
                    @click.stop
                >
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-950/80 font-mono text-xs">
                        <div class="flex items-center space-x-3 truncate">
                            <span class="px-2 py-0.5 rounded bg-cyan-950 border border-cyan-500/40 text-cyan-300 text-[10px] uppercase font-bold">
                                {{ selectedImage.extension || 'IMG' }}
                            </span>
                            <span class="text-slate-200 truncate font-bold">{{ selectedImage.filename }}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button 
                                type="button"
                                @click="copyImageUrl(selectedImage.url)" 
                                class="px-3 py-1.5 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                            >
                                <Copy class="w-3.5 h-3.5 text-cyan-400" />
                                <span>{{ copiedImageUrl === selectedImage.url ? 'Copied!' : 'Copy URL' }}</span>
                            </button>
                            <a 
                                :href="selectedImage.url" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold flex items-center space-x-1.5 transition cartoon-btn"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                                <span>Open Full</span>
                            </a>
                            <button 
                                type="button"
                                @click="closeLightbox"
                                class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                            >
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body: Image Preview -->
                    <div class="bg-slate-950 max-h-[65vh] flex items-center justify-center p-4 overflow-hidden">
                        <img 
                            :src="selectedImage.preview_url || selectedImage.url" 
                            :alt="selectedImage.alt" 
                            class="max-w-full max-h-[60vh] object-contain rounded-xl shadow-lg" 
                        />
                    </div>

                    <!-- Modal Footer: Metadata Details -->
                    <div class="px-6 py-4 border-t border-slate-800 bg-slate-950/80 font-mono text-xs flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-0.5 text-[11px]">
                            <div class="text-slate-400 truncate max-w-xl">
                                Source: <span class="text-cyan-300 underline">{{ selectedImage.url }}</span>
                            </div>
                            <div class="text-slate-500 flex items-center gap-4">
                                <span>Origin: {{ selectedImage.source_type }}</span>
                                <span v-if="selectedImage.alt">Alt: {{ selectedImage.alt }}</span>
                                <span v-if="selectedImage.is_onion" class="text-purple-400 font-bold">● Dark Web Onion Proxy Routed</span>
                            </div>
                        </div>
                        <button 
                            type="button"
                            @click="closeLightbox" 
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs cartoon-btn cursor-pointer"
                        >
                            Close Preview (Esc)
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

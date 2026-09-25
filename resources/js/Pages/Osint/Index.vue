<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    Compass, 
    UserCheck, 
    Mail, 
    Globe, 
    Network, 
    Share2, 
    EyeOff, 
    ArrowRight, 
    ShieldCheck, 
    FolderGit2,
    GripVertical,
    RotateCcw,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    categories: Array,
    investigations: Array,
    metrics: Object,
});

const STORAGE_KEY = 'darkdump_osint_hub_tools_order';

const orderedCategories = ref([...props.categories]);
const draggedIndex = ref(null);
const dragOverIndex = ref(null);
const isReordered = ref(false);
const resetToast = ref(false);
const animationCompleted = ref(false);

const getCategoryIcon = (iconName) => {
    switch (iconName) {
        case 'UserCheck': return UserCheck;
        case 'Mail': return Mail;
        case 'Globe': return Globe;
        case 'Network': return Network;
        case 'Share2': return Share2;
        case 'EyeOff': return EyeOff;
        default: return Compass;
    }
};

const checkIfReordered = () => {
    if (orderedCategories.value.length !== props.categories.length) return true;
    return orderedCategories.value.some((cat, idx) => cat.id !== props.categories[idx].id);
};

const loadSavedOrder = () => {
    try {
        const savedRaw = localStorage.getItem(STORAGE_KEY);
        if (!savedRaw) return;
        const savedIds = JSON.parse(savedRaw);
        if (!Array.isArray(savedIds)) return;

        const map = new Map(props.categories.map(c => [c.id, c]));
        const reordered = [];

        savedIds.forEach(id => {
            if (map.has(id)) {
                reordered.push(map.get(id));
                map.delete(id);
            }
        });

        // Append any categories that weren't present in saved IDs
        map.forEach(cat => reordered.push(cat));

        orderedCategories.value = reordered;
        isReordered.value = checkIfReordered();
    } catch (e) {
        console.warn('Failed to load saved OSINT tools order:', e);
    }
};

const saveOrder = () => {
    try {
        const ids = orderedCategories.value.map(c => c.id);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        isReordered.value = checkIfReordered();
    } catch (e) {
        console.warn('Failed to save OSINT tools order:', e);
    }
};

const resetOrder = () => {
    try {
        localStorage.removeItem(STORAGE_KEY);
        orderedCategories.value = [...props.categories];
        isReordered.value = false;
        resetToast.value = true;
        setTimeout(() => {
            resetToast.value = false;
        }, 2500);
    } catch (e) {
        console.warn('Failed to reset OSINT tools order:', e);
    }
};

// Drag and Drop Event Handlers
const onDragStart = (event, index) => {
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onDragOver = (event, index) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverIndex.value = index;
};

const onDragEnter = (event, index) => {
    event.preventDefault();
    dragOverIndex.value = index;
};

const onDragLeave = (event, index) => {
    if (dragOverIndex.value === index) {
        dragOverIndex.value = null;
    }
};

const onDrop = (event, targetIndex) => {
    event.preventDefault();
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) {
        draggedIndex.value = null;
        dragOverIndex.value = null;
        return;
    }

    const item = orderedCategories.value.splice(draggedIndex.value, 1)[0];
    orderedCategories.value.splice(targetIndex, 0, item);

    saveOrder();
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

const onDragEnd = () => {
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

onMounted(() => {
    loadSavedOrder();
    const totalDuration = 200 + (props.categories?.length || 4) * 75 + 600;
    setTimeout(() => {
        animationCompleted.value = true;
    }, totalDuration);
});
</script>

<template>
    <div class="space-y-6 w-full">
            <!-- Navigation Breadcrumb & Header -->
            <div class="border-b border-slate-800/80 pb-5 reveal-item is-revealed">
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500 mb-2">
                    <span class="text-teal-400/90 font-semibold">OSINT Framework</span>
                    <span>/</span>
                    <span class="text-slate-400">Directory Hub</span>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-serif font-black tracking-tight text-white uppercase flex items-center gap-2.5">
                            <Compass class="w-6 h-6 text-teal-400" />
                            <span>OSINT Framework Directory</span>
                        </h1>
                        <p class="text-slate-400 text-xs font-mono mt-0.5">
                            Curated intelligence taxonomy, alias fingerprinting, automated search engines &amp; live reconnaissance trees.
                        </p>
                    </div>

                    <!-- Workstation Telemetry Badges -->
                    <div class="flex flex-wrap items-center gap-2.5 font-mono text-xs">
                        <div class="px-2.5 py-1 rounded-lg bg-slate-900/80 border border-slate-800 flex items-center gap-2">
                            <span class="text-slate-500">Global Targets:</span>
                            <span class="text-slate-200 font-bold">{{ metrics?.total_targets || 94 }}+ Services</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-teal-950/40 border border-teal-800/50 text-teal-300 flex items-center gap-1.5 font-bold">
                            <ShieldCheck class="w-3.5 h-3.5 text-teal-400" />
                            <span>Active Telemetry Engine</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-slate-800/70 border border-slate-700/60 text-slate-300 flex items-center gap-1.5">
                            <FolderGit2 class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ investigations?.length || 0 }} Active Cases</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ultra-Compact SOC Telemetry Highlight Strip -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-mono text-xs w-full reveal-item is-revealed reveal-delay-100">
                <div class="p-4 sm:p-5 rounded-xl bg-slate-900/70 border border-slate-800 shadow-sm flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-teal-950/50 border border-teal-800/50 flex items-center justify-center text-teal-400 shrink-0">
                        <UserCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-200 uppercase">75+ Live Platforms</div>
                        <div class="text-[11px] text-slate-400">Concurrent SSE Username Probing</div>
                    </div>
                </div>

                <div class="p-4 sm:p-5 rounded-xl bg-slate-900/70 border border-slate-800 shadow-sm flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-cyan-950/50 border border-cyan-800/50 flex items-center justify-center text-cyan-400 shrink-0">
                        <Globe class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-200 uppercase">105+ Framework Tools</div>
                        <div class="text-[11px] text-slate-400">Username, Email &amp; IP/MAC Recon</div>
                    </div>
                </div>

                <div class="p-4 sm:p-5 rounded-xl bg-slate-900/70 border border-slate-800 shadow-sm flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-center justify-center text-slate-300 shrink-0">
                        <FolderGit2 class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-200 uppercase">{{ investigations?.length || 0 }} Open Cases</div>
                        <div class="text-[11px] text-slate-400">One-Click Dossier Evidence Persistence</div>
                    </div>
                </div>
            </div>

            <!-- Framework Directory Section Header & Drag Controls -->
            <div class="space-y-3.5 w-full reveal-item is-revealed reveal-delay-150">
                <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-pulse"></span>
                        <h2 class="text-xs font-mono font-bold text-slate-200 uppercase tracking-wide">
                            Framework Directory Categories
                        </h2>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400 hidden sm:inline-flex items-center gap-1">
                            <GripVertical class="w-3 h-3 text-teal-400" />
                            <span>Draggable Matrix</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2.5 font-mono text-[11px]">
                        <button
                            v-if="isReordered"
                            type="button"
                            @click="resetOrder"
                            class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition text-xs shadow-sm cursor-pointer"
                            title="Reset to default framework order"
                        >
                            <RotateCcw class="w-3 h-3 text-amber-400" />
                            <span>Reset Order</span>
                        </button>

                        <span v-if="resetToast" class="text-teal-400 font-bold flex items-center gap-1">
                            <Check class="w-3.5 h-3.5" />
                            <span>Default Restored</span>
                        </span>

                        <span class="text-slate-500 text-[10px] hidden md:inline">Drag cards to reorder workstation</span>
                    </div>
                </div>

                <!-- Draggable Categories Grid with TransitionGroup Animation -->
                <TransitionGroup 
                    name="tool-card" 
                    tag="div" 
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 w-full items-stretch"
                >
                    <div 
                        v-for="(cat, index) in orderedCategories" 
                        :key="cat.id"
                        draggable="true"
                        @dragstart="onDragStart($event, index)"
                        @dragover="onDragOver($event, index)"
                        @dragenter="onDragEnter($event, index)"
                        @dragleave="onDragLeave($event, index)"
                        @drop="onDrop($event, index)"
                        @dragend="onDragEnd"
                        class="min-w-0 p-5 sm:p-6 rounded-xl bg-slate-900/80 border transition-all duration-200 flex flex-col justify-between group shadow-sm select-none relative cursor-grab active:cursor-grabbing cartoon-card"
                        :class="[
                            !animationCompleted ? 'reveal-item is-revealed' : '',
                            draggedIndex === index
                                ? 'opacity-40 scale-[0.98] border-teal-500 shadow-[0_0_25px_rgba(20,184,166,0.3)] ring-2 ring-teal-400/40'
                                : dragOverIndex === index
                                    ? 'border-teal-400 ring-2 ring-teal-400 ring-offset-2 ring-offset-slate-950 bg-slate-900 shadow-xl scale-[1.01]'
                                    : 'border-slate-800/90 hover:border-teal-500/40 hover:shadow-lg'
                        ]"
                        :style="!animationCompleted ? { animationDelay: `${200 + index * 75}ms` } : {}"
                    >
                        <div class="space-y-2.5">
                            <!-- Card Top Header -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <!-- Tactical Drag Handle Grip -->
                                    <div 
                                        class="p-1 rounded bg-slate-950/70 border border-slate-800/80 text-slate-500 group-hover:text-teal-400 transition shrink-0"
                                        title="Click and drag to reorder module"
                                    >
                                        <GripVertical class="w-3.5 h-3.5 pointer-events-none" />
                                    </div>

                                    <div class="w-7 h-7 rounded-lg bg-teal-950/40 border border-teal-800/50 flex items-center justify-center text-teal-400 group-hover:text-teal-300 transition-colors shrink-0">
                                        <component :is="getCategoryIcon(cat.icon)" class="w-4 h-4 pointer-events-none" />
                                    </div>
                                    <h3 class="text-xs font-mono font-bold text-slate-200 group-hover:text-teal-300 transition-colors">
                                        {{ cat.title }}
                                    </h3>
                                </div>

                                <span 
                                    class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase tracking-wider border shrink-0"
                                    :class="[
                                        cat.badge_color === 'teal' || cat.badge_color === 'emerald' 
                                            ? 'bg-teal-950/80 text-teal-300 border-teal-800/60' 
                                             : cat.badge_color === 'cyan' 
                                                ? 'bg-cyan-950/80 text-cyan-300 border-cyan-800/60' 
                                                : 'bg-slate-900 text-slate-500 border border-slate-800'
                                    ]"
                                >
                                    {{ cat.badge }}
                                </span>
                            </div>

                            <!-- Description -->
                            <p class="text-[11px] text-slate-400 font-mono leading-relaxed">
                                {{ cat.description }}
                            </p>

                            <!-- Branches Preview -->
                            <div class="pt-2 border-t border-slate-900">
                                <div class="text-[9px] font-mono uppercase text-slate-500 font-semibold mb-1.5 tracking-wider">Sub-Branches:</div>
                                <div class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="branch in cat.branches" 
                                        :key="branch"
                                        class="px-1.5 py-0.5 rounded text-[9px] font-mono bg-slate-950 border border-slate-800/80 text-slate-400 group-hover:border-slate-700 transition-colors"
                                    >
                                        {{ branch }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Button -->
                        <div class="pt-3 mt-3 border-t border-slate-900 font-mono">
                            <Link 
                                v-if="cat.url" 
                                :href="cat.url"
                                class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700/80 text-teal-300 hover:text-teal-200 font-mono text-xs font-semibold uppercase tracking-wider flex items-center justify-between transition shadow group-hover:border-teal-500/40 cartoon-btn"
                            >
                                <span>Launch {{ cat.title }}</span>
                                <ArrowRight class="w-3 h-3 text-teal-400" />
                            </Link>
                            <div 
                                v-else 
                                class="w-full px-2.5 py-1.5 rounded-lg bg-slate-950/60 border border-slate-800/50 text-slate-500 font-mono text-xs flex items-center justify-between cursor-not-allowed"
                            >
                                <span>Module in Development</span>
                                <span class="text-[10px] text-slate-600">v5.2</span>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>
</template>

<style scoped>
.tool-card-move {
    transition: transform 0.35s cubic-bezier(0.2, 0, 0, 1);
}
</style>

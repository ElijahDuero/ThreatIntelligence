<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { 
    Users, 
    MessageSquare, 
    Send, 
    Sparkles 
} from 'lucide-vue-next';

import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';
import PersonaMatrixPanel from '@/Components/SocialRecon/PersonaMatrixPanel.vue';
import RedditReconPanel from '@/Components/SocialRecon/RedditReconPanel.vue';
import TelegramReconPanel from '@/Components/SocialRecon/TelegramReconPanel.vue';
import SocialDorkerPanel from '@/Components/SocialRecon/SocialDorkerPanel.vue';

const props = defineProps({
    catalog: {
        type: Array,
        default: () => [],
    },
    investigations: {
        type: Array,
        default: () => [],
    },
    totalPlatforms: {
        type: Number,
        default: 0,
    },
});

// Active Mission Tab: 'persona' | 'reddit' | 'telegram' | 'dorker'
const activeTab = ref('persona');

// Case dossier bookmarking
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);

const openBookmarkModal = (item) => {
    openBookmark({
        title: item.title || item.name || 'Social Finding',
        url: item.url || '',
        notes: (item.category ? `Category: ${item.category}\n` : '') + (item.snippet || item.text || item.description || ''),
        severity: item.severity || 'medium',
        investigation_id: props.investigations?.[0]?.id || null,
    });
};
</script>

<template>
    <div class="space-y-6 w-full">
        <Head title="Social Recon & Persona Matrix | Darkdump OSINT" />
            
        <!-- Hero header & metrics strip -->
        <section class="relative overflow-hidden rounded-3xl border border-slate-800/90 bg-gradient-to-b from-slate-950 via-slate-900/60 to-slate-950 p-6 sm:p-8 lg:p-10 shadow-2xl">
            <!-- Radial Ambient Background Glows -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 right-1/4 w-[500px] h-[300px] bg-amber-500/10 blur-[130px] rounded-full"></div>
                <div class="absolute bottom-0 left-10 w-[450px] h-[250px] bg-emerald-600/10 blur-[120px] rounded-full"></div>
            </div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-3">
                    <!-- Authority Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900/90 border border-amber-500/30 text-amber-300 text-xs font-mono uppercase tracking-wider font-semibold select-none shadow-inner">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Social Intelligence Core v5</span>
                        <span class="text-slate-600">|</span>
                        <span class="text-slate-300 font-sans font-normal">Entity & Discussion Scraper</span>
                    </div>

                    <!-- Title -->
                    <h1 class="font-serif font-black text-2xl sm:text-3xl lg:text-4xl text-white uppercase tracking-tight leading-tight">
                        Social Recon & Persona Matrix
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 font-sans max-w-2xl leading-relaxed">
                        Probe digital personas across 50+ networks, extract real-time Reddit discussion comments, harvest public Telegram leak channels, and deploy automated search engine syntax.
                    </p>
                </div>

                <!-- Telemetry Metric Badges -->
                <div class="flex items-center gap-3 font-mono text-xs select-none shrink-0">
                    <div class="p-3 rounded-2xl bg-slate-950/80 border border-slate-800 text-center min-w-[100px]">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Networks</div>
                        <div class="text-lg font-bold text-amber-400">{{ totalPlatforms }}+</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-950/80 border border-slate-800 text-center min-w-[100px]">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Mode</div>
                        <div class="text-xs font-bold text-emerald-400 mt-1 uppercase">Zero-API</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-950/80 border border-slate-800 text-center min-w-[100px]">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Audit</div>
                        <div class="text-xs font-bold text-cyan-400 mt-1 uppercase">Clean IP</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission console tabs -->
        <div class="flex items-center space-x-2 border-b border-slate-800 pb-3 overflow-x-auto no-scrollbar font-mono text-xs select-none">
            <button
                @click="activeTab = 'persona'"
                :class="[
                    activeTab === 'persona' 
                        ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20' 
                        : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50',
                    'px-4 py-2.5 rounded-xl border transition-all flex items-center space-x-2 cartoon-btn cursor-pointer whitespace-nowrap'
                ]"
            >
                <Users class="w-4 h-4 text-amber-400" />
                <span>Persona Footprint</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">{{ totalPlatforms }}</span>
            </button>

            <button
                @click="activeTab = 'reddit'"
                :class="[
                    activeTab === 'reddit' 
                        ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20' 
                        : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50',
                    'px-4 py-2.5 rounded-xl border transition-all flex items-center space-x-2 cartoon-btn cursor-pointer whitespace-nowrap'
                ]"
            >
                <MessageSquare class="w-4 h-4 text-cyan-400" />
                <span>Reddit Intel</span>
            </button>

            <button
                @click="activeTab = 'telegram'"
                :class="[
                    activeTab === 'telegram' 
                        ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20' 
                        : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50',
                    'px-4 py-2.5 rounded-xl border transition-all flex items-center space-x-2 cartoon-btn cursor-pointer whitespace-nowrap'
                ]"
            >
                <Send class="w-4 h-4 text-sky-400" />
                <span>Telegram Channels</span>
            </button>

            <button
                @click="activeTab = 'dorker'"
                :class="[
                    activeTab === 'dorker' 
                        ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 font-bold shadow-sm shadow-amber-500/20' 
                        : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50',
                    'px-4 py-2.5 rounded-xl border transition-all flex items-center space-x-2 cartoon-btn cursor-pointer whitespace-nowrap'
                ]"
            >
                <Sparkles class="w-4 h-4 text-emerald-400" />
                <span>Activity & Comments</span>
            </button>
        </div>

        <!-- Tab 1: Persona footprinting (Google and 50+ network probe) -->
        <PersonaMatrixPanel 
            v-if="activeTab === 'persona'"
            :catalog="catalog"
            @bookmark="openBookmarkModal"
        />

        <!-- Tab 2: Reddit discussion and comment intel -->
        <RedditReconPanel 
            v-else-if="activeTab === 'reddit'"
            @bookmark="openBookmarkModal"
        />

        <!-- Tab 3: Telegram channel scraper & keyword discovery -->
        <TelegramReconPanel 
            v-else-if="activeTab === 'telegram'"
            @bookmark="openBookmarkModal"
        />

        <!-- Tab 4: Social activity and comment scout -->
        <SocialDorkerPanel 
            v-else-if="activeTab === 'dorker'"
            @bookmark="openBookmarkModal"
        />

        <!-- Evidence Bookmark Modal -->
        <EvidenceBookmarkModal
            v-model="showBookmarkModal"
            :target="bookmarkTarget"
            :investigations="investigations"
            source="social_recon"
        />
    </div>
</template>

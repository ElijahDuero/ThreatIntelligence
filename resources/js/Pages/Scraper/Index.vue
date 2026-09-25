<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { 
    Loader2, 
    AlertCircle, 
    Share2, 
    Camera, 
    Mail, 
    FileText, 
    Tag, 
    Code, 
    Image as ImageIcon, 
    Link as LinkIcon, 
    Server, 
    Crosshair 
} from 'lucide-vue-next';
import LinkTopologyGraph from '@/Components/LinkTopologyGraph.vue';
import ForensicScreenshotViewer from '@/Components/ForensicScreenshotViewer.vue';
import ScraperConsole from '@/Components/Scraper/ScraperConsole.vue';
import HistoricalDossiersBar from '@/Components/Scraper/HistoricalDossiersBar.vue';
import ScraperMetricsBar from '@/Components/Scraper/ScraperMetricsBar.vue';
import KeywordIntelPanel from '@/Components/Scraper/KeywordIntelPanel.vue';
import ImageGalleryPanel from '@/Components/Scraper/ImageGalleryPanel.vue';
import HarvestedArtifactsPanel from '@/Components/Scraper/HarvestedArtifactsPanel.vue';

const props = defineProps({
    recentScrapes: {
        type: Array,
        default: () => [],
    },
});

const targetUrl = ref('');
const collectImages = ref(true);
const useTor = ref(false);
const captureScreenshot = ref(true);
const customKeywords = ref('');
const crawlSubpages = ref(true);

const isScraping = ref(false);
const scrapeResult = ref(null);
const errorMessage = ref(null);
const errorDiagnostic = ref(null);
const showRawError = ref(false);
const activeTab = ref('topology');

const executeScrape = async () => {
    if (!targetUrl.value.trim() || isScraping.value) return;

    isScraping.value = true;
    scrapeResult.value = null;
    errorMessage.value = null;
    errorDiagnostic.value = null;
    showRawError.value = false;

    try {
        const res = await axios.post('/api/scraper/scrape', {
            url: targetUrl.value,
            collect_images: collectImages.value,
            use_tor: useTor.value,
            capture_screenshot: captureScreenshot.value,
            custom_keywords: customKeywords.value,
            crawl_subpages: crawlSubpages.value,
        });

        if (res.data.status === 'error') {
            errorMessage.value = res.data.error || 'Failed to reach or parse target site.';
            errorDiagnostic.value = res.data.diagnostic || null;
        } else {
            scrapeResult.value = res.data;
            if ((res.data.keyword_intel?.total_matches_count > 0) || (customKeywords.value.trim() && res.data.keyword_intel?.query_keywords?.length > 0)) {
                activeTab.value = 'keyword-intel';
            } else {
                activeTab.value = 'topology';
            }
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message || e.message || 'Scrape request failed.';
        errorDiagnostic.value = e.response?.data?.diagnostic || null;
    } finally {
        isScraping.value = false;
    }
};

const onGraphSelectTarget = (url) => {
    targetUrl.value = url;
    if (url.includes('.onion')) {
        useTor.value = true;
    }
    executeScrape();
};

const rescrapeSubpage = (url) => {
    targetUrl.value = url;
    if (url.includes('.onion')) {
        useTor.value = true;
    }
    executeScrape();
};

const onScreenshotRecaptured = (screenshotData) => {
    if (scrapeResult.value) {
        scrapeResult.value.screenshot = screenshotData;
    }
};

const loadHistoricalTarget = (target) => {
    targetUrl.value = target.url;
    if (target.url.includes('.onion')) {
        useTor.value = true;
    }
    customKeywords.value = (target.custom_keywords || []).join(', ');
    scrapeResult.value = {
        status: 'success',
        url: target.url,
        title: target.title,
        status_code: target.status_code,
        is_blacklisted: target.is_blacklisted,
        response_time_seconds: target.response_time_seconds,
        server: target.server,
        headers: target.headers,
        metadata: target.metadata || {},
        keywords: target.keywords || [],
        custom_keywords: target.custom_keywords || [],
        keyword_intel: target.keyword_intel || null,
        sentiment: target.sentiment || {},
        emails: target.emails || [],
        documents: target.documents || [],
        images: target.images || [],
        internal_links: target.internal_links || [],
        external_links: target.external_links || [],
        internal_links_count: (target.internal_links || []).length,
        external_links_count: (target.external_links || []).length,
        raw_text_sample: target.raw_text_sample,
        target_id: target.id,
        screenshot: target.screenshot_metadata || (target.screenshot_path ? {
            public_url: target.screenshot_url,
            storage_path: target.screenshot_path,
            sha256: target.screenshot_metadata?.sha256 || 'N/A',
            file_size_formatted: target.screenshot_metadata?.file_size_formatted || 'N/A',
            captured_at: target.screenshot_metadata?.captured_at || target.created_at,
            routed_tor: target.screenshot_metadata?.routed_tor ?? target.url.includes('.onion'),
            width: target.screenshot_metadata?.width || 1280,
            height: target.screenshot_metadata?.height || 800,
            filename: target.screenshot_metadata?.filename || 'evidence.png',
        } : null),
    };
    if (target.keyword_intel && target.keyword_intel.total_matches_count > 0) {
        activeTab.value = 'keyword-intel';
    } else {
        activeTab.value = 'topology';
    }
};

const onSelectTarget = ({ url, immediate }) => {
    targetUrl.value = url;
    if (url.includes('.onion')) {
        useTor.value = true;
    }
    if (immediate) {
        executeScrape();
    }
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const target = params.get('target');
    if (target) {
        targetUrl.value = target;
        if (target.includes('.onion')) {
            useTor.value = true;
        }
        executeScrape();
    }
});
</script>

<template>
    <div class="space-y-8">
            <!-- Header -->
            <div class="border-b border-slate-800/80 pb-6 reveal-item is-revealed">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-cyan-500/30 text-cyan-400 text-xs font-mono font-semibold uppercase mb-2">
                    <span>Target Crawling Engine</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                    Forensic Target Deep Scraper
                </h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 font-sans">
                    Extract emails, discover sensitive document files (.pdf, .sql, .kdbx), map link topology, and harvest visual assets.
                </p>
            </div>

            <!-- Scraper Input & Keyword Discovery Console -->
            <ScraperConsole 
                v-model="targetUrl"
                v-model:useTor="useTor"
                v-model:crawlSubpages="crawlSubpages"
                v-model:collectImages="collectImages"
                v-model:captureScreenshot="captureScreenshot"
                v-model:customKeywords="customKeywords"
                :is-scraping="isScraping"
                @scrape="executeScrape"
                @select-target="onSelectTarget"
            />

            <!-- Historical Scraped Targets Quick-Access -->
            <HistoricalDossiersBar 
                :recent-scrapes="recentScrapes"
                :current-url="scrapeResult?.url || targetUrl"
                @select-target="loadHistoricalTarget"
            />

            <!-- Loading State -->
            <div v-if="isScraping" class="py-20 text-center space-y-3 bg-slate-950/80 border border-slate-800 rounded-3xl">
                <Loader2 class="w-10 h-10 text-cyan-400 animate-spin mx-auto" />
                <h3 class="text-base font-serif font-bold text-white uppercase">Initiating Deep Crawl on Target</h3>
                <p class="text-xs font-mono text-slate-400 max-w-md mx-auto">
                    Routing connection, parsing DOM structure, harvesting document references, emails, and link topology.
                </p>
            </div>

            <!-- Forensic Error Banner -->
            <div v-if="errorMessage" class="p-6 rounded-3xl bg-rose-950/30 border border-rose-500/40 text-rose-300 font-mono text-xs shadow-2xl space-y-3 cartoon-card reveal-item is-revealed">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start space-x-3.5">
                        <AlertCircle class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-lg bg-rose-900/60 border border-rose-500/40 text-rose-300 text-[10px] font-bold uppercase tracking-wider">
                                    {{ errorDiagnostic?.type || 'PROBE_UNREACHABLE' }}
                                </span>
                                <span v-if="errorDiagnostic?.host" class="text-slate-400 text-[11px]">
                                    Target Host: <span class="text-white font-bold">{{ errorDiagnostic.host }}</span>
                                </span>
                                <span v-if="errorDiagnostic?.route_via_tor" class="px-2 py-0.5 rounded bg-purple-950/80 border border-purple-500/40 text-purple-300 text-[9px] font-bold">
                                    ROUTED VIA TOR
                                </span>
                            </div>
                            <h4 class="text-sm font-bold text-white font-sans">
                                {{ errorMessage }}
                            </h4>
                            <p v-if="errorDiagnostic?.suggestion" class="text-rose-200/90 text-xs leading-relaxed font-sans">
                                💡 <span class="text-slate-300">{{ errorDiagnostic.suggestion }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="errorDiagnostic?.detail" class="pt-3 border-t border-rose-900/40 text-[11px]">
                    <button 
                        type="button" 
                        @click="showRawError = !showRawError" 
                        class="text-slate-400 hover:text-white underline text-[10px] cursor-pointer flex items-center space-x-1"
                    >
                        <span>{{ showRawError ? 'Hide Technical Diagnostics' : 'View Raw Network Exception / cURL Code' }}</span>
                    </button>
                    <div v-if="showRawError" class="mt-2.5 p-3 bg-slate-950/90 rounded-xl border border-slate-800 text-slate-400 break-all font-mono text-[10px] leading-relaxed">
                        {{ errorDiagnostic.detail }}
                    </div>
                </div>
            </div>

            <!-- Scrape Result Details -->
            <div v-if="scrapeResult" class="space-y-6 reveal-item is-revealed">
                <!-- Meta Metrics -->
                <ScraperMetricsBar 
                    :scrape-result="scrapeResult" 
                    @change-tab="activeTab = $event" 
                />

                <!-- Tabs Container -->
                <div class="bg-slate-950/80 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
                    <div class="flex border-b border-slate-800 overflow-x-auto text-xs font-mono font-semibold p-2 gap-2 no-scrollbar">
                        <button 
                            type="button"
                            @click="activeTab = 'keyword-intel'"
                            :class="[activeTab === 'keyword-intel' ? 'bg-rose-500/15 border border-rose-500/40 text-rose-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Crosshair class="w-4 h-4 text-rose-400" />
                            <span>KEYWORD INTEL ({{ scrapeResult.keyword_intel?.total_matches_count ?? 0 }})</span>
                            <span v-if="(scrapeResult.keyword_intel?.total_matches_count ?? 0) > 0" class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'topology'"
                            :class="[activeTab === 'topology' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Share2 class="w-4 h-4 text-amber-400" />
                            <span>TOPOLOGY GRAPH</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'screenshot'"
                            :class="[activeTab === 'screenshot' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Camera class="w-4 h-4 text-emerald-400" />
                            <span>VISUAL EVIDENCE</span>
                            <span v-if="scrapeResult.screenshot?.public_url" class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'emails'"
                            :class="[activeTab === 'emails' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Mail class="w-4 h-4" />
                            <span>EMAILS ({{ scrapeResult.emails?.length || 0 }})</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'documents'"
                            :class="[activeTab === 'documents' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <FileText class="w-4 h-4" />
                            <span>DOCUMENTS ({{ scrapeResult.documents?.length || 0 }})</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'keywords'"
                            :class="[activeTab === 'keywords' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Tag class="w-4 h-4" />
                            <span>KEYWORDS & NLP ({{ scrapeResult.keywords?.length || 0 }})</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'metadata'"
                            :class="[activeTab === 'metadata' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Code class="w-4 h-4" />
                            <span>META & OG ({{ Object.keys(scrapeResult.metadata || {}).length }})</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'images'"
                            :class="[activeTab === 'images' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <ImageIcon class="w-4 h-4" />
                            <span>IMAGES ({{ scrapeResult.images?.length || 0 }})</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'links'"
                            :class="[activeTab === 'links' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <LinkIcon class="w-4 h-4" />
                            <span>TOPOLOGY LINKS</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'headers'"
                            :class="[activeTab === 'headers' ? 'bg-cyan-500/15 border border-cyan-500/40 text-cyan-300 font-bold' : 'text-slate-400 hover:text-white', 'px-4 py-2 rounded-xl flex items-center space-x-2 transition cartoon-btn shrink-0 cursor-pointer']"
                        >
                            <Server class="w-4 h-4" />
                            <span>HEADERS & BODY</span>
                        </button>
                    </div>

                    <div class="p-6 sm:p-8">
                        <KeywordIntelPanel 
                            v-if="activeTab === 'keyword-intel'" 
                            :intel="scrapeResult.keyword_intel"
                            :custom-keywords="scrapeResult.custom_keywords"
                            @rescrape-subpage="rescrapeSubpage"
                        />

                        <div v-else-if="activeTab === 'topology'" class="space-y-4">
                            <LinkTopologyGraph 
                                :root-url="scrapeResult.url"
                                :root-title="scrapeResult.title"
                                :internal-links="scrapeResult.internal_links || []"
                                :external-links="scrapeResult.external_links || []"
                                :emails="scrapeResult.emails || []"
                                :documents="scrapeResult.documents || []"
                                @select-target="onGraphSelectTarget"
                            />
                        </div>

                        <div v-else-if="activeTab === 'screenshot'" class="space-y-4">
                            <ForensicScreenshotViewer 
                                :screenshot="scrapeResult.screenshot"
                                :target-url="scrapeResult.url"
                                :target-id="scrapeResult.target_id || null"
                                :use-tor="useTor || scrapeResult.url?.includes('.onion')"
                                @recapture="onScreenshotRecaptured"
                            />
                        </div>

                        <ImageGalleryPanel 
                            v-else-if="activeTab === 'images'" 
                            :images="scrapeResult.images || []"
                        />

                        <HarvestedArtifactsPanel 
                            v-else 
                            :tab="activeTab"
                            :scrape-result="scrapeResult"
                        />
                    </div>
                </div>
            </div>
        </div>
</template>

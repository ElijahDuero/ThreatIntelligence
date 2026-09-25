<script setup>
import { ref } from 'vue';
import { 
    Mail, 
    Check, 
    FileText, 
    Download, 
    Tag, 
    ExternalLink, 
    Server, 
    Code 
} from 'lucide-vue-next';

const props = defineProps({
    tab: {
        type: String,
        required: true,
    },
    scrapeResult: {
        type: Object,
        required: true,
    },
});

const copiedEmail = ref(null);

const copyEmail = (email) => {
    navigator.clipboard.writeText(email);
    copiedEmail.value = email;
    setTimeout(() => {
        copiedEmail.value = null;
    }, 2000);
};
</script>

<template>
    <div>
        <!-- Emails Tab -->
        <div v-if="tab === 'emails'">
            <div v-if="scrapeResult.emails && scrapeResult.emails.length > 0" class="flex flex-wrap gap-2.5">
                <button 
                    v-for="email in scrapeResult.emails" 
                    :key="email"
                    type="button"
                    @click="copyEmail(email)"
                    class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700/80 hover:border-cyan-400 font-mono text-xs text-slate-200 hover:text-cyan-300 flex items-center space-x-2 transition cartoon-btn cursor-pointer"
                >
                    <Mail class="w-3.5 h-3.5 text-cyan-400" />
                    <span>{{ email }}</span>
                    <Check v-if="copiedEmail === email" class="w-3 h-3 text-emerald-400" />
                </button>
            </div>
            <div v-else class="text-xs font-mono text-slate-500 py-8 text-center">
                No email addresses discovered in page HTML.
            </div>
        </div>

        <!-- Documents Tab -->
        <div v-if="tab === 'documents'">
            <div v-if="scrapeResult.documents && scrapeResult.documents.length > 0" class="divide-y divide-slate-800">
                <div 
                    v-for="(doc, idx) in scrapeResult.documents" 
                    :key="idx"
                    class="py-3.5 flex items-center justify-between font-mono text-xs gap-3"
                >
                    <div class="flex items-center space-x-3 overflow-hidden">
                        <span class="px-2.5 py-1 rounded-lg bg-cyan-950/60 border border-cyan-500/40 text-cyan-300 text-[10px] uppercase font-bold shrink-0">
                            {{ doc.extension }}
                        </span>
                        <span v-if="doc.category" class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-400 text-[9px] uppercase font-bold shrink-0">
                            {{ doc.category }}
                        </span>
                        <span class="text-white truncate font-sans" :title="doc.name">{{ doc.name }}</span>
                    </div>
                    <a 
                        :href="doc.url" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-cyan-400 text-cyan-400 text-xs flex items-center space-x-1.5 shrink-0 transition cartoon-btn"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download</span>
                    </a>
                </div>
            </div>
            <div v-else class="text-xs font-mono text-slate-500 py-8 text-center">
                No document files (.pdf, .sql, .kdbx, .zip, etc.) found linked on this target.
            </div>
        </div>

        <!-- Keywords & NLP Tab -->
        <div v-if="tab === 'keywords'" class="space-y-6">
            <!-- Sentiment & Threat Indicators -->
            <div v-if="scrapeResult.sentiment" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 font-mono text-xs cartoon-card">
                    <span class="text-slate-400 block uppercase">THREAT ASSESSMENT</span>
                    <div class="mt-2 flex items-center space-x-2">
                        <span 
                            :class="[
                                (scrapeResult.sentiment.label || '').includes('High Risk') ? 'text-rose-400 bg-rose-950/60 border-rose-500/40' : 
                                scrapeResult.sentiment.label === 'Positive' ? 'text-emerald-400 bg-emerald-950/60 border-emerald-500/40' : 
                                scrapeResult.sentiment.label === 'Negative' ? 'text-amber-400 bg-amber-950/60 border-amber-500/40' : 
                                'text-cyan-400 bg-cyan-950/60 border-cyan-500/40',
                                'px-2.5 py-1 rounded-lg border text-xs font-bold'
                            ]"
                        >
                            {{ scrapeResult.sentiment.label }}
                        </span>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 font-mono text-xs cartoon-card">
                    <span class="text-slate-400 block uppercase">POLARITY SCORE</span>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <span class="text-lg font-bold" :class="scrapeResult.sentiment.polarity < 0 ? 'text-rose-400' : 'text-emerald-400'">
                            {{ scrapeResult.sentiment.polarity > 0 ? '+' : '' }}{{ scrapeResult.sentiment.polarity }}
                        </span>
                        <span class="text-[10px] text-slate-500">(-1.0 to +1.0 valence)</span>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 font-mono text-xs cartoon-card">
                    <span class="text-slate-400 block uppercase">SUBJECTIVITY INDEX</span>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <span class="text-lg font-bold text-cyan-400">
                            {{ ((scrapeResult.sentiment.subjectivity || 0) * 100).toFixed(0) }}%
                        </span>
                        <span class="text-[10px] text-slate-500">(evaluative density)</span>
                    </div>
                </div>
            </div>

            <!-- Extracted Keywords (Top 18 from original darkdump) -->
            <div class="space-y-3">
                <h4 class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-2">
                    <Tag class="w-3.5 h-3.5 text-cyan-400" />
                    <span>TOP 18 INTELLIGENCE KEYWORDS (NLTK STOPWORD FILTERED)</span>
                </h4>
                <div v-if="scrapeResult.keywords && scrapeResult.keywords.length > 0" class="flex flex-wrap gap-2">
                    <span 
                        v-for="(kw, idx) in scrapeResult.keywords" 
                        :key="kw"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700/80 hover:border-cyan-500/60 font-mono text-xs text-cyan-300 flex items-center space-x-1.5 transition cartoon-btn"
                    >
                        <span class="text-[10px] text-slate-500 font-sans">#{{ idx + 1 }}</span>
                        <span class="font-bold">{{ kw }}</span>
                    </span>
                </div>
                <div v-else class="text-xs font-mono text-slate-500 py-6 text-center">
                    No prominent keywords identified.
                </div>
            </div>

            <!-- Top Recurring Words Frequency (from analyze_text) -->
            <div v-if="scrapeResult.sentiment?.top_words && scrapeResult.sentiment.top_words.length > 0" class="space-y-3">
                <h4 class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">
                    LEXICAL FREQUENCY DISTRIBUTION
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div 
                        v-for="item in scrapeResult.sentiment.top_words" 
                        :key="item.word"
                        class="p-2.5 rounded-xl bg-slate-900/60 border border-slate-800 text-xs font-mono flex items-center justify-between"
                    >
                        <span class="text-slate-200 truncate">{{ item.word }}</span>
                        <span class="px-1.5 py-0.5 rounded bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-bold text-[10px]">
                            {{ item.count }}×
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metadata & OpenGraph Tab -->
        <div v-if="tab === 'metadata'" class="space-y-4">
            <div v-if="scrapeResult.metadata && Object.keys(scrapeResult.metadata).length > 0" class="divide-y divide-slate-800">
                <div 
                    v-for="(val, key) in scrapeResult.metadata" 
                    :key="key"
                    class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 font-mono text-xs"
                >
                    <span class="text-cyan-400 font-bold sm:w-1/3 truncate" :title="key">{{ key }}</span>
                    <span class="text-slate-300 sm:w-2/3 break-all font-sans">{{ val }}</span>
                </div>
            </div>
            <div v-else class="text-xs font-mono text-slate-500 py-8 text-center">
                No HTML meta attributes found on target page.
            </div>
        </div>

        <!-- Links Tab -->
        <div v-if="tab === 'links'" class="space-y-6">
            <div>
                <h4 class="text-xs font-mono font-bold text-slate-400 mb-3">
                    EXTERNAL DESTINATIONS ({{ scrapeResult.external_links?.length || 0 }})
                </h4>
                <div class="max-h-52 overflow-y-auto space-y-1.5 font-mono text-xs text-cyan-400 no-scrollbar">
                    <div v-for="l in scrapeResult.external_links" :key="l" class="truncate">
                        <a :href="l" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center space-x-1">
                            <span>{{ l }}</span>
                            <ExternalLink class="w-3 h-3 inline-block shrink-0" />
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-mono font-bold text-slate-400 mb-3">
                    INTERNAL SITE ENDPOINTS ({{ scrapeResult.internal_links?.length || 0 }})
                </h4>
                <div class="max-h-52 overflow-y-auto space-y-1.5 font-mono text-xs text-slate-300 no-scrollbar">
                    <div v-for="l in scrapeResult.internal_links" :key="l" class="truncate">
                        {{ l }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Headers Tab -->
        <div v-if="tab === 'headers'" class="space-y-4 font-mono text-xs">
            <div>
                <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">RESPONSE HEADERS</h4>
                <pre class="bg-slate-900 p-4 rounded-xl text-slate-300 overflow-x-auto no-scrollbar">{{ scrapeResult.headers }}</pre>
            </div>
            <div>
                <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">PAGE TEXT SAMPLE</h4>
                <div class="bg-slate-900 p-4 rounded-xl text-slate-300 leading-relaxed font-sans text-xs">
                    {{ scrapeResult.raw_text_sample || 'No readable text content extracted.' }}
                </div>
            </div>
        </div>
    </div>
</template>

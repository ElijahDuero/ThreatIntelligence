<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { 
    MessageSquare, 
    Search, 
    Loader2, 
    Bookmark, 
    ExternalLink 
} from 'lucide-vue-next';

const emit = defineEmits(['bookmark']);

const redditQuery = ref('');
const redditSubreddit = ref('');
const redditSort = ref('relevance');
const isSearchingReddit = ref(false);
const redditResults = ref([]);
const redditSearchError = ref(null);

const executeRedditSearch = async () => {
    if (!redditQuery.value.trim() || isSearchingReddit.value) return;

    isSearchingReddit.value = true;
    redditSearchError.value = null;
    redditResults.value = [];

    try {
        const res = await axios.post('/api/social-recon/reddit', {
            query: redditQuery.value.trim(),
            subreddit: redditSubreddit.value.trim() || null,
            sort: redditSort.value,
        });

        if (res.data?.results) {
            redditResults.value = res.data.results;
        }
        if (res.data?.error) {
            redditSearchError.value = res.data.error;
        }
    } catch (err) {
        redditSearchError.value = err?.response?.data?.message || 'Failed to search Reddit.';
    } finally {
        isSearchingReddit.value = false;
    }
};

const handleBookmark = (payload) => {
    emit('bookmark', payload);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Search Inputs -->
        <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl space-y-4">
            <form @submit.prevent="executeRedditSearch" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-6 relative">
                    <MessageSquare class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                    <input 
                        v-model="redditQuery"
                        type="text"
                        required
                        placeholder="Search keyword, exact phrase, or entity discussion..."
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition"
                    />
                </div>

                <div class="md:col-span-3 relative">
                    <input 
                        v-model="redditSubreddit"
                        type="text"
                        placeholder="Optional subreddit (e.g. netsec)"
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono transition"
                    />
                </div>

                <div class="md:col-span-3 flex gap-2">
                    <select 
                        v-model="redditSort"
                        class="bg-slate-950 border border-slate-700/80 rounded-xl px-3 py-3.5 text-xs text-slate-300 font-mono focus:outline-none focus:border-amber-400"
                    >
                        <option value="relevance">Relevance</option>
                        <option value="new">Newest</option>
                        <option value="top">Top Score</option>
                        <option value="comments">Most Comments</option>
                    </select>

                    <button 
                        type="submit"
                        :disabled="isSearchingReddit || !redditQuery.trim()"
                        class="flex-1 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase flex items-center justify-center space-x-1.5 cartoon-btn cursor-pointer"
                    >
                        <Loader2 v-if="isSearchingReddit" class="w-4 h-4 animate-spin" />
                        <Search v-else class="w-4 h-4" />
                        <span>Search</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Error Warning -->
        <div v-if="redditSearchError" class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/50 text-rose-300 font-mono text-xs">
            {{ redditSearchError }}
        </div>

        <!-- Results List -->
        <div v-if="redditResults.length > 0" class="space-y-3">
            <div class="text-xs font-mono text-slate-400 flex items-center justify-between">
                <span>Found {{ redditResults.length }} threads & discussion snippets</span>
                <span>Source: Public Reddit JSON API</span>
            </div>

            <div 
                v-for="post in redditResults" 
                :key="post.id"
                class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-amber-500/40 transition-all cartoon-card space-y-3"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs font-mono">
                            <span class="text-amber-400 font-bold">{{ post.subreddit }}</span>
                            <span class="text-slate-600">•</span>
                            <span class="text-slate-400">u/{{ post.author }}</span>
                            <span class="text-slate-600">•</span>
                            <span class="text-slate-500">Score: {{ post.score }}</span>
                            <span class="text-slate-600">•</span>
                            <span class="text-slate-500">{{ post.num_comments }} comments</span>
                        </div>
                        <h3 class="text-base font-serif font-bold text-white leading-snug">
                            {{ post.title }}
                        </h3>
                    </div>

                    <div class="flex items-center space-x-2 shrink-0">
                        <button
                            @click="handleBookmark({ title: post.title, url: post.url, snippet: post.snippet })"
                            class="p-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-amber-400 transition cartoon-btn cursor-pointer"
                            title="Bookmark to Case Dossier"
                        >
                            <Bookmark class="w-4 h-4" />
                        </button>
                        <a 
                            :href="post.url" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            class="p-2 rounded-xl bg-slate-950 hover:bg-slate-850 border border-slate-800 hover:border-amber-400 text-slate-400 hover:text-white transition"
                            title="Open thread"
                        >
                            <ExternalLink class="w-4 h-4" />
                        </a>
                    </div>
                </div>

                <p v-if="post.snippet" class="text-xs text-slate-300 font-sans leading-relaxed bg-slate-950/60 p-3 rounded-xl border border-slate-800/60">
                    {{ post.snippet }}
                </p>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!isSearchingReddit" class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-3">
            <MessageSquare class="w-10 h-10 text-slate-600 mx-auto" />
            <div class="font-serif font-bold text-lg text-slate-300 uppercase">Reddit Live Intel</div>
            <div class="text-xs text-slate-500 font-mono max-w-md mx-auto">
                Search any topic, breach keyword, or user handle to harvest public Reddit discussions without API limits.
            </div>
        </div>
    </div>
</template>

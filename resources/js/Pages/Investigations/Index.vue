<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { 
    FolderGit2, 
    Plus, 
    Bookmark, 
    Trash2, 
    ExternalLink, 
    FolderPlus,
    X,
    ArrowRight
} from 'lucide-vue-next';

const props = defineProps({
    investigations: Array,
    bookmarks: Array,
});

const showCreateModal = ref(false);

const form = useForm({
    title: '',
    description: '',
    priority: 'medium',
});

const submitCase = () => {
    form.post('/investigations', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const deleteBookmark = async (id) => {
    if (!confirm('Remove this saved bookmark?')) return;
    try {
        await axios.delete(`/api/bookmarks/${id}`);
        window.location.reload();
    } catch (e) {
        alert('Failed to delete bookmark');
    }
};
</script>

<template>
    <div class="space-y-10">
            <!-- Header (DigitalManagement Style) -->
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-6 reveal-item is-revealed">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-indigo-500/30 text-indigo-400 text-xs font-mono font-semibold uppercase mb-2">
                        <span>Forensic Case Management</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                        Investigation Case Dossiers
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-1 font-sans">
                        Group search results, deep-scraped targets, and sensitive findings into persistent investigative dossiers.
                    </p>
                </div>

                <button 
                    @click="showCreateModal = true"
                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-mono text-xs font-bold flex items-center space-x-2 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer shrink-0"
                >
                    <Plus class="w-4 h-4" />
                    <span>NEW CASE DOSSIER</span>
                </button>
            </div>

            <!-- Case Dossiers Grid -->
            <div class="reveal-item is-revealed reveal-delay-100">
                <h2 class="text-xs font-serif font-bold text-slate-400 tracking-wider uppercase mb-4">ACTIVE CASE DOSSIERS</h2>
                <div v-if="investigations && investigations.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Link 
                        v-for="c in investigations" 
                        :key="c.id"
                        :href="`/investigations/${c.id}`"
                        class="group bg-slate-950/80 border border-slate-800 hover:border-amber-500/60 rounded-2xl p-6 transition-all shadow-xl cartoon-card space-y-4 flex flex-col justify-between block cursor-pointer hover:shadow-amber-500/10"
                    >
                        <div class="space-y-4">
                            <div class="flex items-start justify-between">
                                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider"
                                    :class="[
                                        c.priority === 'critical' ? 'bg-red-950/80 border border-red-500 text-red-300' : '',
                                        c.priority === 'high' ? 'bg-orange-950/80 border border-orange-500 text-orange-300' : '',
                                        c.priority === 'medium' ? 'bg-indigo-950/80 border border-indigo-500 text-indigo-300' : 'bg-slate-900 text-slate-400',
                                    ]"
                                >
                                    {{ c.priority }}
                                </span>
                                <span class="text-[10px] font-mono text-slate-400">{{ new Date(c.created_at).toLocaleDateString() }}</span>
                            </div>

                            <h3 class="font-serif font-bold text-base text-white uppercase group-hover:text-amber-400 transition-colors">{{ c.title }}</h3>
                            <p class="text-xs text-slate-400 leading-relaxed font-sans line-clamp-3">
                                {{ c.description || 'No case objectives entered.' }}
                            </p>
                        </div>

                        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-mono text-slate-400">
                            <span class="flex items-center space-x-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                <span>{{ c.bookmarks_count || 0 }} findings</span>
                            </span>
                            <span class="text-amber-400 font-bold uppercase flex items-center space-x-1 group-hover:translate-x-1 transition-transform">
                                <span>OPEN DOSSIER</span>
                                <ArrowRight class="w-3.5 h-3.5" />
                            </span>
                        </div>
                    </Link>
                </div>
                <div v-else class="text-center py-12 bg-slate-950/80 border border-slate-800 rounded-2xl text-xs font-mono text-slate-400">
                    No investigation dossiers created yet. Click "New Case Dossier" to group your intelligence findings!
                </div>
            </div>

            <!-- Bookmarked Findings Table -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl reveal-item is-revealed reveal-delay-200">
                <div class="flex items-center space-x-2.5 mb-5">
                    <Bookmark class="w-4 h-4 text-amber-400" />
                    <h2 class="text-xs font-serif font-bold text-white tracking-wider uppercase">SAVED FINDINGS & ARTEFACTS ({{ bookmarks.length }})</h2>
                </div>

                <div v-if="bookmarks && bookmarks.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="border-b border-slate-800 text-slate-400">
                            <tr>
                                <th class="pb-3">TITLE / SITE</th>
                                <th class="pb-3">URL</th>
                                <th class="pb-3">NOTES</th>
                                <th class="pb-3">RECORDED</th>
                                <th class="pb-3 text-right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr v-for="b in bookmarks" :key="b.id" class="hover:bg-slate-900/40 transition">
                                <td class="py-3.5 font-semibold text-white max-w-xs truncate">{{ b.title }}</td>
                                <td class="py-3.5 text-cyan-400 max-w-sm truncate">
                                    <a :href="b.url" target="_blank" class="hover:underline flex items-center space-x-1">
                                        <span class="truncate">{{ b.url }}</span>
                                        <ExternalLink class="w-3 h-3 shrink-0" />
                                    </a>
                                </td>
                                <td class="py-3.5 text-slate-400">{{ b.notes || '—' }}</td>
                                <td class="py-3.5 text-slate-400 text-[11px]">{{ new Date(b.created_at).toLocaleDateString() }}</td>
                                <td class="py-3.5 text-right space-x-2">
                                    <Link 
                                        :href="`/scraper?target=${encodeURIComponent(b.url)}`" 
                                        class="text-cyan-400 hover:text-cyan-300 font-bold cartoon-btn"
                                    >
                                        Scrape
                                    </Link>
                                    <button 
                                        @click="deleteBookmark(b.id)" 
                                        class="text-red-400 hover:text-red-300 ml-2 font-bold cartoon-btn"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-10 text-xs font-mono text-slate-400">
                    No bookmarked items yet. Bookmark targets from search results to collect them here!
                </div>
            </div>

            <!-- Create Case Modal -->
            <Teleport to="body">
                <div v-if="showCreateModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-5 shadow-2xl cartoon-modal">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 class="font-serif font-bold text-base text-white flex items-center space-x-2 uppercase">
                                <FolderPlus class="w-4 h-4 text-amber-400" />
                                <span>Create Case Dossier</span>
                            </h3>
                            <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <form @submit.prevent="submitCase" class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-mono text-slate-400 block uppercase tracking-wider">CASE TITLE</label>
                                <input 
                                    v-model="form.title"
                                    type="text"
                                    required
                                    placeholder="e.g. Operation Sovereign, Threat Actor X"
                                    class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white focus:outline-none focus:border-amber-400"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-mono text-slate-400 block uppercase tracking-wider">PRIORITY</label>
                                <select 
                                    v-model="form.priority"
                                    class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white focus:outline-none focus:border-amber-400"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-mono text-slate-400 block uppercase tracking-wider">DESCRIPTION / OBJECTIVES</label>
                                <textarea 
                                    v-model="form.description"
                                    rows="3"
                                    placeholder="Scope, target entities, investigation notes..."
                                    class="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white focus:outline-none focus:border-amber-400"
                                ></textarea>
                            </div>

                            <div class="pt-3 flex justify-end space-x-2.5">
                                <button 
                                    @click="showCreateModal = false"
                                    type="button"
                                    class="px-4 py-2.5 rounded-xl bg-slate-800 text-slate-300 font-mono text-xs hover:bg-slate-700 transition cartoon-btn"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-mono text-xs font-bold transition shadow-lg shadow-amber-500/25 cartoon-btn"
                                >
                                    Save Dossier
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import EntityLinkGraph from '@/Components/EntityLinkGraph.vue';
import { 
    ArrowLeft, 
    Share2, 
    Download, 
    Trash2, 
    Plus, 
    Bookmark, 
    Search, 
    FileText, 
    ExternalLink, 
    Link as LinkIcon, 
    Check, 
    X,
    Edit3,
    Tag
} from 'lucide-vue-next';

const props = defineProps({
    investigation: {
        type: Object,
        required: true,
    },
    bookmarks: {
        type: [Array, Object],
        default: () => [],
    },
    queries: {
        type: [Array, Object],
        default: () => [],
    },
    graph: {
        type: Object,
        default: () => ({ nodes: [], edges: [], stats: {} }),
    },
});

const bookmarkList = computed(() => Array.isArray(props.bookmarks) ? props.bookmarks : (props.bookmarks?.data || []));
const bookmarkTotal = computed(() => Array.isArray(props.bookmarks) ? props.bookmarks.length : (props.bookmarks?.total ?? bookmarkList.value.length));
const bookmarkPagination = computed(() => Array.isArray(props.bookmarks) ? null : props.bookmarks);

const queryList = computed(() => Array.isArray(props.queries) ? props.queries : (props.queries?.data || []));
const queryTotal = computed(() => Array.isArray(props.queries) ? props.queries.length : (props.queries?.total ?? queryList.value.length));
const queryPagination = computed(() => Array.isArray(props.queries) ? null : props.queries);

const activeTab = ref('graph'); // graph, evidence, queries, brief
const currentGraph = ref(JSON.parse(JSON.stringify(props.graph)));
const statusMsg = ref('');
const isSaving = ref(false);

// Modals
const showNodeModal = ref(false);
const showEdgeModal = ref(false);
const showEditDossierModal = ref(false);

// Edit Dossier Form
const editForm = ref({
    title: props.investigation.title,
    description: props.investigation.description || '',
    priority: props.investigation.priority || 'medium',
    status: props.investigation.status || 'active',
});

// Custom Node Form
const newNodeForm = ref({
    label: '',
    type: 'person',
    subtitle: '',
    notes: '',
});

// Custom Edge Form
const newEdgeForm = ref({
    source: '',
    target: '',
    label: 'associated_with',
    notes: '',
});

const nodeTypeOptions = [
    { value: 'person', label: 'Person / Handle / Alias' },
    { value: 'onion', label: 'Dark Web Onion Service' },
    { value: 'channel', label: 'Telegram Public Channel' },
    { value: 'email', label: 'Email Account / Leak' },
    { value: 'wallet', label: 'Cryptocurrency Wallet' },
    { value: 'domain', label: 'Clearnet Domain / Server' },
    { value: 'forum', label: 'Forum / Reddit Thread' },
    { value: 'developer', label: 'Code Repository' },
    { value: 'generic', label: 'Generic Asset Node' },
];

const relationshipOptions = [
    'operates',
    'controls_wallet',
    'mirror_of',
    'leaked_by',
    'mentions_entity',
    'associated_with',
    'communicates_via',
    'funds_address',
    'infrastructure_of',
];

const showToast = (msg) => {
    statusMsg.value = msg;
    setTimeout(() => {
        statusMsg.value = '';
    }, 3500);
};

// Save Graph Data (Nodes & Edges)
const saveGraph = async (graphPayload) => {
    isSaving.value = true;
    try {
        const res = await axios.put(`/api/investigations/${props.investigation.id}/graph`, graphPayload);
        if (res.data.success) {
            currentGraph.value = res.data.graph;
            showToast('Entity graph layout & custom nodes synchronized.');
        }
    } catch (e) {
        showToast('Error syncing graph: ' + (e.response?.data?.message || e.message));
    } finally {
        isSaving.value = false;
    }
};

// Add Custom Node
const handleAddCustomNode = () => {
    newNodeForm.value = {
        label: '',
        type: 'person',
        subtitle: '',
        notes: '',
    };
    showNodeModal.value = true;
};

const submitCustomNode = async () => {
    if (!newNodeForm.value.label.trim()) return;

    const id = 'custom_' + Date.now();
    const node = {
        id,
        label: newNodeForm.value.label.trim(),
        type: newNodeForm.value.type,
        subtitle: newNodeForm.value.subtitle.trim() || 'Manual Entity',
        notes: newNodeForm.value.notes.trim(),
        metadata: {},
        is_auto: false,
    };

    const updatedNodes = [...(currentGraph.value.nodes || []), node];
    const payload = {
        nodes: updatedNodes,
        edges: currentGraph.value.edges || [],
    };

    await saveGraph(payload);
    showNodeModal.value = false;
    showToast(`Entity node "${node.label}" deployed.`);
};

// Add Custom Edge
const handleAddCustomEdge = (preselectedSourceId = null) => {
    newEdgeForm.value = {
        source: preselectedSourceId || (currentGraph.value.nodes[0]?.id || ''),
        target: currentGraph.value.nodes[1]?.id || '',
        label: 'associated_with',
        notes: '',
    };
    showEdgeModal.value = true;
};

const submitCustomEdge = async () => {
    if (!newEdgeForm.value.source || !newEdgeForm.value.target) return;
    if (newEdgeForm.value.source === newEdgeForm.value.target) {
        alert('Source and target must be distinct entities.');
        return;
    }

    const edge = {
        id: 'edge_' + Date.now(),
        source: newEdgeForm.value.source,
        target: newEdgeForm.value.target,
        label: newEdgeForm.value.label,
        type: 'custom',
        notes: newEdgeForm.value.notes.trim(),
    };

    const updatedEdges = [...(currentGraph.value.edges || []), edge];
    const payload = {
        nodes: currentGraph.value.nodes || [],
        edges: updatedEdges,
    };

    await saveGraph(payload);
    showEdgeModal.value = false;
    showToast(`Link established between entities.`);
};

// Delete Custom Node
const handleDeleteNode = async (nodeId) => {
    if (!confirm('Permanently remove this custom entity from the dossier graph?')) return;

    const updatedNodes = currentGraph.value.nodes.filter(n => n.id !== nodeId);
    const updatedEdges = currentGraph.value.edges.filter(e => e.source !== nodeId && e.target !== nodeId);

    await saveGraph({ nodes: updatedNodes, edges: updatedEdges });
    showToast('Custom entity removed.');
};

// Update Case Metadata
const updateDossier = async () => {
    try {
        const res = await axios.put(`/api/investigations/${props.investigation.id}`, editForm.value);
        if (res.data.success) {
            props.investigation.title = editForm.value.title;
            props.investigation.description = editForm.value.description;
            props.investigation.priority = editForm.value.priority;
            props.investigation.status = editForm.value.status;
            showEditDossierModal.value = false;
            showToast('Dossier metadata updated.');
        }
    } catch (e) {
        alert('Failed to update case: ' + (e.response?.data?.message || e.message));
    }
};

// Delete Bookmark
const deleteBookmark = async (id) => {
    if (!confirm('Remove this finding from the dossier evidence catalog?')) return;
    try {
        await axios.delete(`/api/bookmarks/${id}`);
        router.reload({ only: ['bookmarks', 'graph'] });
        showToast('Evidence item removed.');
    } catch (e) {
        alert('Failed to delete bookmark.');
    }
};

// Delete Case
const deleteInvestigation = () => {
    if (!confirm(`Are you sure you want to permanently delete Case #${props.investigation.id}: "${props.investigation.title}"?`)) return;
    router.delete(`/investigations/${props.investigation.id}`);
};
</script>

<template>
    <div class="space-y-6">
        <Head :title="`[CASE #${investigation.id}] ${investigation.title} | Darkdump Dossier`" />

        <!-- Toast Notification -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-4 opacity-0"
            >
                <div 
                    v-if="statusMsg"
                    class="fixed top-20 right-6 z-50 bg-slate-900 border border-cyan-500/50 text-cyan-300 px-4 py-2.5 rounded-xl shadow-2xl font-mono text-xs flex items-center space-x-2 backdrop-blur"
                >
                    <Check class="w-4 h-4 text-emerald-400" />
                    <span>{{ statusMsg }}</span>
                </div>
            </transition>

            <!-- Dossier Header Section -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-3">
                        <!-- Breadcrumb & Classification -->
                        <div class="flex flex-wrap items-center gap-2 text-xs font-mono">
                            <Link 
                                href="/investigations"
                                class="text-slate-400 hover:text-amber-400 flex items-center space-x-1 transition"
                            >
                                <ArrowLeft class="w-3.5 h-3.5" />
                                <span>CASE DOSSIERS</span>
                            </Link>
                            <span class="text-slate-600">/</span>
                            <span class="text-cyan-400 font-bold">CASE #{{ investigation.id }}</span>
                            <span class="text-slate-600">|</span>
                            <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[10px] font-bold">
                                TLP:AMBER • THREAT INTELLIGENCE
                            </span>
                        </div>

                        <!-- Title & Priority -->
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                                {{ investigation.title }}
                            </h1>

                            <!-- Priority Badge -->
                            <span 
                                class="px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold uppercase tracking-wider"
                                :class="[
                                    investigation.priority === 'critical' ? 'bg-red-950/80 border border-red-500 text-red-300' : '',
                                    investigation.priority === 'high' ? 'bg-orange-950/80 border border-orange-500 text-orange-300' : '',
                                    investigation.priority === 'medium' ? 'bg-indigo-950/80 border border-indigo-500 text-indigo-300' : 'bg-slate-900 text-slate-400',
                                ]"
                            >
                                {{ investigation.priority }} PRIORITY
                            </span>

                            <!-- Status Badge -->
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold uppercase tracking-wider bg-slate-900 border border-slate-700 text-amber-400">
                                {{ investigation.status }}
                            </span>
                        </div>

                        <!-- Description Summary -->
                        <p class="text-xs sm:text-sm text-slate-400 font-sans max-w-3xl leading-relaxed">
                            {{ investigation.description || 'No specific operational objectives provided for this dossier.' }}
                        </p>

                        <!-- Case Tags -->
                        <div v-if="investigation.tags && investigation.tags.length > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span 
                                v-for="(tag, idx) in investigation.tags" 
                                :key="idx"
                                class="px-2 py-0.5 rounded-md bg-slate-900 border border-slate-800 text-[11px] font-mono text-slate-400 flex items-center space-x-1"
                            >
                                <Tag class="w-3 h-3 text-slate-500" />
                                <span>{{ tag }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Actions Bar -->
                    <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                        <button 
                            @click="showEditDossierModal = true"
                            class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-mono text-xs font-bold flex items-center space-x-1.5 transition cursor-pointer"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                            <span>Edit Case</span>
                        </button>

                        <!-- Export Dropdown -->
                        <div class="relative group">
                            <button 
                                class="px-3.5 py-2 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-mono text-xs font-bold flex items-center space-x-1.5 transition cursor-pointer shadow-lg shadow-cyan-500/10"
                            >
                                <Download class="w-3.5 h-3.5" />
                                <span>Export Brief</span>
                            </button>
                            <div class="absolute right-0 top-full mt-1.5 w-44 bg-slate-950 border border-slate-800 rounded-xl p-1 shadow-2xl hidden group-hover:block z-30 font-mono text-xs">
                                <a 
                                    :href="`/investigations/${investigation.id}/export-report?format=markdown`" 
                                    target="_blank"
                                    class="block px-3 py-2 rounded-lg hover:bg-slate-900 text-slate-300 hover:text-white transition"
                                >
                                    Markdown Brief (.md)
                                </a>
                                <a 
                                    :href="`/investigations/${investigation.id}/export-report?format=json`" 
                                    target="_blank"
                                    class="block px-3 py-2 rounded-lg hover:bg-slate-900 text-slate-300 hover:text-white transition"
                                >
                                    Structured JSON (.json)
                                </a>
                            </div>
                        </div>

                        <button 
                            @click="deleteInvestigation"
                            class="px-3.5 py-2 rounded-xl bg-red-950/40 hover:bg-red-900/40 text-red-400 border border-red-500/30 font-mono text-xs font-bold flex items-center space-x-1.5 transition cursor-pointer"
                            title="Delete entire case dossier"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center space-x-2 border-t border-slate-800/80 pt-4 mt-6 overflow-x-auto">
                    <button 
                        @click="activeTab = 'graph'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer shrink-0"
                        :class="activeTab === 'graph' ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/40 shadow-lg shadow-cyan-500/10' : 'text-slate-400 hover:text-white'"
                    >
                        <Share2 class="w-3.5 h-3.5" />
                        <span>Entity Link Graph ({{ currentGraph.nodes?.length || 0 }})</span>
                    </button>

                    <button 
                        @click="activeTab = 'evidence'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer shrink-0"
                        :class="activeTab === 'evidence' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/40' : 'text-slate-400 hover:text-white'"
                    >
                        <Bookmark class="w-3.5 h-3.5" />
                        <span>Evidence Catalog ({{ bookmarkTotal }})</span>
                    </button>

                    <button 
                        @click="activeTab = 'queries'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer shrink-0"
                        :class="activeTab === 'queries' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/40' : 'text-slate-400 hover:text-white'"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>Search Leads ({{ queryTotal }})</span>
                    </button>

                    <button 
                        @click="activeTab = 'brief'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer shrink-0"
                        :class="activeTab === 'brief' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/40' : 'text-slate-400 hover:text-white'"
                    >
                        <FileText class="w-3.5 h-3.5" />
                        <span>Operational Brief</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: Entity Link Graph Canvas -->
            <div v-show="activeTab === 'graph'" class="space-y-4">
                <EntityLinkGraph 
                    :initial-nodes="currentGraph.nodes"
                    :initial-edges="currentGraph.edges"
                    :case-title="investigation.title"
                    @save-graph="saveGraph"
                    @add-custom-node="handleAddCustomNode"
                    @add-custom-edge="handleAddCustomEdge"
                    @delete-node="handleDeleteNode"
                />

                <!-- Graph Telemetry Summary -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Identified Entities</div>
                        <div class="text-xl font-bold text-cyan-400 mt-1">{{ currentGraph.nodes?.length || 0 }}</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Relational Edges</div>
                        <div class="text-xl font-bold text-sky-400 mt-1">{{ currentGraph.edges?.length || 0 }}</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Bookmarked Findings</div>
                        <div class="text-xl font-bold text-amber-400 mt-1">{{ bookmarkTotal }}</div>
                    </div>
                    <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Connected Queries</div>
                        <div class="text-xl font-bold text-purple-400 mt-1">{{ queryTotal }}</div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Evidence Catalog (Bookmarks) -->
            <div v-show="activeTab === 'evidence'" class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center space-x-2.5">
                        <Bookmark class="w-4 h-4 text-amber-400" />
                        <h2 class="text-xs font-serif font-bold text-white tracking-wider uppercase">EVIDENCE ARTEFACTS & FINDINGS ({{ bookmarkTotal }})</h2>
                    </div>
                </div>

                <div v-if="bookmarkList && bookmarkList.length > 0" class="overflow-x-auto space-y-4">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="border-b border-slate-800 text-slate-400">
                            <tr>
                                <th class="pb-3">TITLE / ASSET</th>
                                <th class="pb-3">URL / ENDPOINT</th>
                                <th class="pb-3">SEVERITY</th>
                                <th class="pb-3">OPERATOR NOTES</th>
                                <th class="pb-3">LOGGED AT</th>
                                <th class="pb-3 text-right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr v-for="b in bookmarkList" :key="b.id" class="hover:bg-slate-900/40 transition">
                                <td class="py-3.5 font-semibold text-white max-w-xs truncate">{{ b.title }}</td>
                                <td class="py-3.5 text-cyan-400 max-w-sm truncate">
                                    <a :href="b.url" target="_blank" class="hover:underline flex items-center space-x-1">
                                        <span class="truncate">{{ b.url }}</span>
                                        <ExternalLink class="w-3 h-3 shrink-0" />
                                    </a>
                                </td>
                                <td class="py-3.5">
                                    <span 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                        :class="[
                                            b.severity === 'CRITICAL' ? 'bg-red-950/80 border border-red-500 text-red-300' : '',
                                            b.severity === 'HIGH' ? 'bg-orange-950/80 border border-orange-500 text-orange-300' : '',
                                            b.severity === 'MEDIUM' ? 'bg-amber-950/80 border border-amber-500 text-amber-300' : 'bg-slate-900 text-slate-400 border border-slate-800',
                                        ]"
                                    >
                                        {{ b.severity || 'INFO' }}
                                    </span>
                                </td>
                                <td class="py-3.5 text-slate-400 max-w-xs truncate">{{ b.notes || '—' }}</td>
                                <td class="py-3.5 text-slate-500">{{ new Date(b.created_at).toLocaleDateString() }}</td>
                                <td class="py-3.5 text-right">
                                    <button 
                                        @click="deleteBookmark(b.id)"
                                        class="text-red-400 hover:text-red-300 p-1 rounded hover:bg-slate-800 transition cursor-pointer"
                                        title="Remove bookmark"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Bookmark Pagination -->
                    <div v-if="bookmarkPagination && bookmarkPagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-800/80 font-mono text-xs">
                        <div class="text-slate-400">
                            Showing <span class="text-white font-bold">{{ bookmarkPagination.from }}</span> to <span class="text-white font-bold">{{ bookmarkPagination.to }}</span> of <span class="text-amber-400 font-bold">{{ bookmarkPagination.total }}</span> findings
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <template v-for="(link, i) in bookmarkPagination.links" :key="i">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    class="px-3 py-1.5 rounded-lg border transition cursor-pointer"
                                    :class="link.active ? 'bg-amber-500 text-slate-950 border-amber-500 font-bold shadow-lg shadow-amber-500/20' : 'bg-slate-900 text-slate-300 border-slate-800 hover:border-slate-700'"
                                    v-html="link.label"
                                />
                                <span 
                                    v-else
                                    class="px-3 py-1.5 rounded-lg border border-slate-900 bg-slate-950 text-slate-600 opacity-50"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-slate-500 font-mono text-xs">
                    No evidence items bookmarked for this dossier yet.
                </div>
            </div>

            <!-- TAB 3: Search Leads & Queries -->
            <div v-show="activeTab === 'queries'" class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card">
                <div class="flex items-center space-x-2.5 mb-5">
                    <Search class="w-4 h-4 text-indigo-400" />
                    <h2 class="text-xs font-serif font-bold text-white tracking-wider uppercase">CONNECTED SEARCH LEADS ({{ queryTotal }})</h2>
                </div>

                <div v-if="queryList && queryList.length > 0" class="space-y-4">
                    <div class="space-y-3">
                        <div 
                            v-for="q in queryList" 
                            :key="q.id"
                            class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-slate-900/60 border border-slate-800/80 gap-3"
                        >
                            <div class="space-y-1 font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-bold text-white">{{ q.query }}</span>
                                    <span class="px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[10px] uppercase">
                                        {{ q.engine }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Yielded {{ q.results_count }} hits • Execution: {{ q.execution_time_seconds || 0 }}s
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 text-xs font-mono">
                                <span class="text-slate-500">{{ new Date(q.created_at).toLocaleDateString() }}</span>
                                <Link 
                                    :href="`/search/${q.id}`"
                                    class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 transition"
                                >
                                    View Results
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Queries Pagination -->
                    <div v-if="queryPagination && queryPagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-800/80 font-mono text-xs">
                        <div class="text-slate-400">
                            Showing <span class="text-white font-bold">{{ queryPagination.from }}</span> to <span class="text-white font-bold">{{ queryPagination.to }}</span> of <span class="text-indigo-400 font-bold">{{ queryPagination.total }}</span> search leads
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <template v-for="(link, i) in queryPagination.links" :key="i">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    class="px-3 py-1.5 rounded-lg border transition cursor-pointer"
                                    :class="link.active ? 'bg-indigo-500 text-white border-indigo-500 font-bold shadow-lg shadow-indigo-500/20' : 'bg-slate-900 text-slate-300 border-slate-800 hover:border-slate-700'"
                                    v-html="link.label"
                                />
                                <span 
                                    v-else
                                    class="px-3 py-1.5 rounded-lg border border-slate-900 bg-slate-950 text-slate-600 opacity-50"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-slate-500 font-mono text-xs">
                    No search queries linked directly to this case dossier.
                </div>
            </div>

            <!-- TAB 4: Case Operational Brief -->
            <div v-show="activeTab === 'brief'" class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card space-y-6">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="flex items-center space-x-2.5">
                        <FileText class="w-4 h-4 text-purple-400" />
                        <h2 class="text-xs font-serif font-bold text-white tracking-wider uppercase">OPERATIONAL DOSSIER SUMMARY</h2>
                    </div>
                    <button 
                        @click="showEditDossierModal = true"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-mono text-xs font-bold flex items-center space-x-1.5 transition cursor-pointer"
                    >
                        <Edit3 class="w-3.5 h-3.5" />
                        <span>Edit Objectives</span>
                    </button>
                </div>

                <div class="space-y-4 font-sans text-xs sm:text-sm text-slate-300 leading-relaxed max-w-4xl">
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 space-y-2">
                        <h4 class="font-mono text-xs font-bold text-amber-400 uppercase tracking-wider">Mission Objectives</h4>
                        <p>{{ investigation.description || 'No detailed objectives established yet.' }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 font-mono text-xs">
                        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 space-y-1">
                            <div class="text-slate-400">Created At</div>
                            <div class="text-white font-bold">{{ new Date(investigation.created_at).toLocaleString() }}</div>
                        </div>
                        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 space-y-1">
                            <div class="text-slate-400">Last Modified</div>
                            <div class="text-white font-bold">{{ new Date(investigation.updated_at).toLocaleString() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 1: Add Custom Entity Node -->
        <div v-if="showNodeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-950 border border-cyan-500/50 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl cartoon-card space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-serif font-black text-lg text-white uppercase flex items-center space-x-2">
                        <Plus class="w-5 h-5 text-cyan-400" />
                        <span>Deploy Custom Entity Node</span>
                    </h3>
                    <button @click="showNodeModal = false" class="text-slate-400 hover:text-white">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Entity Classification</label>
                        <select 
                            v-model="newNodeForm.type"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                        >
                            <option v-for="opt in nodeTypeOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Entity Identifier / Label *</label>
                        <input 
                            v-model="newNodeForm.label"
                            type="text" 
                            placeholder="e.g. Satoshi_99, breachleaks.onion, admin@threat.org"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Role / Subtitle</label>
                        <input 
                            v-model="newNodeForm.subtitle"
                            type="text" 
                            placeholder="e.g. Suspected Operator, Drop Wallet, C2 Server"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Investigative Notes</label>
                        <textarea 
                            v-model="newNodeForm.notes"
                            rows="3"
                            placeholder="Add forensic context, attribution hints, or leak cross-references..."
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 font-sans"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800">
                    <button 
                        @click="showNodeModal = false"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white font-mono text-xs transition"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="submitCustomNode"
                        class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-mono text-xs font-bold transition shadow-lg shadow-cyan-500/20"
                    >
                        Deploy Node
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 2: Connect Entities (Add Edge) -->
        <div v-if="showEdgeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-950 border border-amber-500/50 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl cartoon-card space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-serif font-black text-lg text-white uppercase flex items-center space-x-2">
                        <LinkIcon class="w-5 h-5 text-amber-400" />
                        <span>Establish Relational Link</span>
                    </h3>
                    <button @click="showEdgeModal = false" class="text-slate-400 hover:text-white">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Source Entity *</label>
                        <select 
                            v-model="newEdgeForm.source"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                        >
                            <option v-for="node in currentGraph.nodes" :key="node.id" :value="node.id">
                                [{{ node.type }}] {{ node.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Target Entity *</label>
                        <select 
                            v-model="newEdgeForm.target"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                        >
                            <option v-for="node in currentGraph.nodes" :key="node.id" :value="node.id">
                                [{{ node.type }}] {{ node.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Relationship Descriptor</label>
                        <select 
                            v-model="newEdgeForm.label"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500 mb-2"
                        >
                            <option v-for="r in relationshipOptions" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </select>
                        <input 
                            v-model="newEdgeForm.label"
                            type="text"
                            placeholder="Or type custom relationship..."
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Link Justification / Notes</label>
                        <textarea 
                            v-model="newEdgeForm.notes"
                            rows="2"
                            placeholder="Why are these entities connected? Evidence reference..."
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 font-sans"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800">
                    <button 
                        @click="showEdgeModal = false"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white font-mono text-xs transition"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="submitCustomEdge"
                        class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-mono text-xs font-bold transition shadow-lg shadow-amber-500/20"
                    >
                        Connect Nodes
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: Edit Case Dossier Details -->
        <div v-if="showEditDossierModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-950 border border-slate-700 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl cartoon-card space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-serif font-black text-lg text-white uppercase flex items-center space-x-2">
                        <Edit3 class="w-5 h-5 text-amber-400" />
                        <span>Edit Case Dossier</span>
                    </h3>
                    <button @click="showEditDossierModal = false" class="text-slate-400 hover:text-white">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Case Title *</label>
                        <input 
                            v-model="editForm.title"
                            type="text" 
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 uppercase mb-1">Priority</label>
                            <select 
                                v-model="editForm.priority"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-400 uppercase mb-1">Status</label>
                            <select 
                                v-model="editForm.status"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-cyan-500"
                            >
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Mission Description</label>
                        <textarea 
                            v-model="editForm.description"
                            rows="4"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-cyan-500 font-sans"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800">
                    <button 
                        @click="showEditDossierModal = false"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white font-mono text-xs transition"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="updateDossier"
                        class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-mono text-xs font-bold transition shadow-lg shadow-cyan-500/20"
                    >
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
</template>

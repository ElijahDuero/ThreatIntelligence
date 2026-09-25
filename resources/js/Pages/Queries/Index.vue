<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Terminal,
    Search,
    Shield,
    Globe,
    Clock,
    Download,
    Trash2,
    RotateCcw,
    ExternalLink,
    Filter,
    CheckSquare,
    Square,
    Copy,
    Check,
    AlertCircle,
    ArrowUpDown,
    FileSpreadsheet,
    FileCode,
    X,
    FolderGit2,
    Loader2
} from 'lucide-vue-next';

const props = defineProps({
    queries: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({
            total_queries: 0,
            total_results: 0,
            tor_queries: 0,
            clearnet_queries: 0,
            avg_execution_time: 0,
            failed_queries: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            engine: '',
            routing: '',
            status: '',
            sort: 'newest',
        }),
    },
    availableEngines: {
        type: Array,
        default: () => [],
    },
});

// Reactive Filter State
const searchInput = ref(props.filters.search || '');
const filterEngine = ref(props.filters.engine || '');
const filterRouting = ref(props.filters.routing || '');
const filterStatus = ref(props.filters.status || '');
const filterSort = ref(props.filters.sort || 'newest');

// Selection state for batch operations
const selectedIds = ref([]);
const isBulkDeleting = ref(false);
const deletingId = ref(null);
const showClearModal = ref(false);
const isClearingAll = ref(false);
const copiedQueryId = ref(null);
const showExportMenu = ref(false);

// Debounce timer for search input
let searchDebounceTimer = null;

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 350);
};

const applyFilters = () => {
    router.get('/queries', {
        search: searchInput.value || undefined,
        engine: filterEngine.value || undefined,
        routing: filterRouting.value || undefined,
        status: filterStatus.value || undefined,
        sort: filterSort.value !== 'newest' ? filterSort.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchInput.value = '';
    filterEngine.value = '';
    filterRouting.value = '';
    filterStatus.value = '';
    filterSort.value = 'newest';
    router.get('/queries', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const hasActiveFilters = computed(() => {
    return Boolean(
        searchInput.value ||
        filterEngine.value ||
        filterRouting.value ||
        filterStatus.value ||
        (filterSort.value && filterSort.value !== 'newest')
    );
});

// Selection Helpers
const allOnPageSelected = computed(() => {
    if (!props.queries.data || props.queries.data.length === 0) return false;
    return props.queries.data.every(q => selectedIds.value.includes(q.id));
});

const toggleSelectAll = () => {
    if (allOnPageSelected.value) {
        const pageIds = props.queries.data.map(q => q.id);
        selectedIds.value = selectedIds.value.filter(id => !pageIds.includes(id));
    } else {
        const pageIds = props.queries.data.map(q => q.id);
        const combined = new Set([...selectedIds.value, ...pageIds]);
        selectedIds.value = Array.from(combined);
    }
};

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// Clipboard helper
const copyQuery = (query, id) => {
    navigator.clipboard.writeText(query);
    copiedQueryId.value = id;
    setTimeout(() => {
        if (copiedQueryId.value === id) {
            copiedQueryId.value = null;
        }
    }, 2000);
};

// Delete single query
const deleteQuery = async (id) => {
    if (!confirm('Permanently delete this query record and its indexed results?')) return;
    deletingId.value = id;
    try {
        await axios.delete(`/api/queries/${id}`);
        selectedIds.value = selectedIds.value.filter(itemId => itemId !== id);
        router.reload({ preserveScroll: true });
    } catch (err) {
        alert('Failed to delete query record.');
    } finally {
        deletingId.value = null;
    }
};

// Bulk delete queries
const bulkDelete = async () => {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`Permanently delete ${selectedIds.value.length} selected query records?`)) return;
    isBulkDeleting.value = true;
    try {
        await axios.post('/api/queries/bulk-delete', { ids: selectedIds.value });
        selectedIds.value = [];
        router.reload({ preserveScroll: true });
    } catch (err) {
        alert('Failed to bulk delete selected queries.');
    } finally {
        isBulkDeleting.value = false;
    }
};

// Clear all queries
const clearAllQueries = async () => {
    isClearingAll.value = true;
    try {
        await axios.post('/api/queries/clear');
        showClearModal.value = false;
        selectedIds.value = [];
        router.reload({ preserveScroll: true });
    } catch (err) {
        alert('Failed to clear query registry.');
    } finally {
        isClearingAll.value = false;
    }
};

// Format dates
const formatDate = (isoString) => {
    if (!isoString) return '—';
    const date = new Date(isoString);
    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getEngineBadgeClass = (engine) => {
    const e = (engine || '').toLowerCase();
    if (e.includes('ahmia')) {
        return 'bg-purple-950/80 border-purple-500/50 text-purple-300';
    }
    if (e.includes('duckduckgo') || e.includes('ddg')) {
        return 'bg-orange-950/80 border-orange-500/50 text-orange-300';
    }
    if (e.includes('tordex')) {
        return 'bg-cyan-950/80 border-cyan-500/50 text-cyan-300';
    }
    if (e.includes('torch')) {
        return 'bg-red-950/80 border-red-500/50 text-red-300';
    }
    return 'bg-slate-900 border-slate-700 text-slate-300';
};
</script>

<template>
    <div class="space-y-8">
            <!-- 1. Header Section -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 border-b border-slate-800/80 pb-6 reveal-item is-revealed">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-amber-500/30 text-amber-400 text-xs font-mono font-semibold uppercase mb-2 select-none">
                        <Terminal class="w-3.5 h-3.5 text-amber-400" />
                        <span>Investigation Query Registry & Audit Trail</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                        Investigation Query Registry
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-1 font-sans max-w-3xl">
                        Comprehensive ledger of all darknet and clearnet recon queries, execution latencies, engine distributions, and archived intelligence results.
                    </p>
                </div>

                <!-- Action Controls: New Search, Export & Clear -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <Link
                        href="/search"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-mono text-xs font-bold flex items-center space-x-2 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer shrink-0"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>NEW SEARCH</span>
                    </Link>

                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button
                            @click="showExportMenu = !showExportMenu"
                            type="button"
                            class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-xs font-mono text-slate-300 hover:text-amber-300 flex items-center space-x-2 transition cartoon-btn cursor-pointer"
                        >
                            <Download class="w-3.5 h-3.5 text-amber-400" />
                            <span>EXPORT</span>
                        </button>

                        <div
                            v-if="showExportMenu"
                            class="fixed inset-0 z-40 bg-transparent cursor-default"
                            @click="showExportMenu = false"
                        ></div>

                        <div
                            v-if="showExportMenu"
                            class="absolute right-0 mt-2 w-48 bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl py-1.5 z-50 font-mono text-xs cartoon-modal backdrop-blur-md"
                        >
                            <div class="px-3 py-1 text-[10px] uppercase font-bold text-slate-400 border-b border-slate-800">
                                Export Format
                            </div>
                            <a
                                href="/queries/export?format=csv"
                                @click="showExportMenu = false"
                                class="w-full text-left px-3 py-2 text-slate-300 hover:bg-amber-500/10 hover:text-amber-300 flex items-center space-x-2 transition"
                            >
                                <FileSpreadsheet class="w-3.5 h-3.5 text-emerald-400" />
                                <span>Export as CSV</span>
                            </a>
                            <a
                                href="/queries/export?format=json"
                                @click="showExportMenu = false"
                                class="w-full text-left px-3 py-2 text-slate-300 hover:bg-amber-500/10 hover:text-amber-300 flex items-center space-x-2 transition"
                            >
                                <FileCode class="w-3.5 h-3.5 text-cyan-400" />
                                <span>Export as JSON</span>
                            </a>
                        </div>
                    </div>

                    <!-- Clear Registry Button -->
                    <button
                        @click="showClearModal = true"
                        type="button"
                        class="px-3 py-2 rounded-xl bg-slate-900 border border-red-900/50 hover:border-red-500/70 text-xs font-mono text-red-400 hover:text-red-300 flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                        title="Clear all recorded query history"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">CLEAR ALL</span>
                    </button>
                </div>
            </div>

            <!-- 2. Metrics & KPI Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 reveal-item is-revealed reveal-delay-100">
                <!-- Total Queries -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Total Queries</span>
                        <Terminal class="w-3.5 h-3.5 text-amber-400" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold text-white tracking-tight">
                        {{ stats.total_queries.toLocaleString() }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        Logged in database
                    </div>
                </div>

                <!-- Total Results Discovered -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Indexed Results</span>
                        <Globe class="w-3.5 h-3.5 text-cyan-400" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold text-amber-400 tracking-tight">
                        {{ stats.total_results.toLocaleString() }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        Onion & web targets
                    </div>
                </div>

                <!-- Tor Routed Queries -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-emerald-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Tor / Onion</span>
                        <Shield class="w-3.5 h-3.5 text-emerald-400" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold text-emerald-400 tracking-tight">
                        {{ stats.tor_queries.toLocaleString() }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        {{ stats.total_queries > 0 ? Math.round((stats.tor_queries / stats.total_queries) * 100) : 0 }}% of searches
                    </div>
                </div>

                <!-- Clearnet Routed Queries -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Clearnet</span>
                        <Globe class="w-3.5 h-3.5 text-slate-400" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold text-slate-200 tracking-tight">
                        {{ stats.clearnet_queries.toLocaleString() }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        Direct routing
                    </div>
                </div>

                <!-- Avg Latency -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Avg Latency</span>
                        <Clock class="w-3.5 h-3.5 text-amber-400" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold text-amber-300 tracking-tight">
                        {{ stats.avg_execution_time || 0 }}s
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        Per investigation
                    </div>
                </div>

                <!-- Failed Searches -->
                <div class="bg-slate-950/80 border border-slate-800 hover:border-red-500/40 rounded-2xl p-4 transition-all shadow-xl cartoon-card">
                    <div class="flex items-center justify-between text-slate-400 font-mono text-[10px] uppercase">
                        <span>Failed</span>
                        <AlertCircle class="w-3.5 h-3.5" :class="stats.failed_queries > 0 ? 'text-red-400' : 'text-slate-500'" />
                    </div>
                    <div class="mt-2 text-xl sm:text-2xl font-mono font-bold tracking-tight" :class="stats.failed_queries > 0 ? 'text-red-400' : 'text-slate-400'">
                        {{ stats.failed_queries.toLocaleString() }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                        Timeouts / Errors
                    </div>
                </div>
            </div>

            <!-- 3. Filter & Search Toolbar -->
            <div class="bg-slate-950/90 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4 reveal-item is-revealed reveal-delay-200">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            v-model="searchInput"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Filter queries by target string, domain, hash, or keywords..."
                            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl pl-10 pr-10 py-2.5 text-xs sm:text-sm font-mono text-white placeholder-slate-500 focus:outline-none focus:border-amber-500/80 focus:ring-1 focus:ring-amber-500/30 transition shadow-inner"
                        />
                        <button
                            v-if="searchInput"
                            @click="searchInput = ''; applyFilters();"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white p-1"
                            title="Clear search"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Dropdowns & Filters -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Engine Selector -->
                        <div class="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Engine:</span>
                            <select
                                v-model="filterEngine"
                                @change="applyFilters"
                                class="bg-transparent text-xs font-mono text-white focus:outline-none cursor-pointer pr-2"
                            >
                                <option value="" class="bg-slate-900 text-white">All Engines</option>
                                <option v-for="eng in availableEngines" :key="eng" :value="eng" class="bg-slate-900 text-white uppercase">
                                    {{ eng }}
                                </option>
                            </select>
                        </div>

                        <!-- Routing Filter -->
                        <div class="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Route:</span>
                            <select
                                v-model="filterRouting"
                                @change="applyFilters"
                                class="bg-transparent text-xs font-mono text-white focus:outline-none cursor-pointer pr-2"
                            >
                                <option value="" class="bg-slate-900 text-white">All Routes</option>
                                <option value="tor" class="bg-slate-900 text-white">Tor / Onion Only</option>
                                <option value="clearnet" class="bg-slate-900 text-white">Clearnet Only</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Status:</span>
                            <select
                                v-model="filterStatus"
                                @change="applyFilters"
                                class="bg-transparent text-xs font-mono text-white focus:outline-none cursor-pointer pr-2"
                            >
                                <option value="" class="bg-slate-900 text-white">All Statuses</option>
                                <option value="completed" class="bg-slate-900 text-white">Completed</option>
                                <option value="failed" class="bg-slate-900 text-white">Failed</option>
                                <option value="running" class="bg-slate-900 text-white">Running</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5">
                            <ArrowUpDown class="w-3 h-3 text-slate-400" />
                            <select
                                v-model="filterSort"
                                @change="applyFilters"
                                class="bg-transparent text-xs font-mono text-white focus:outline-none cursor-pointer pr-2"
                            >
                                <option value="newest" class="bg-slate-900 text-white">Newest First</option>
                                <option value="oldest" class="bg-slate-900 text-white">Oldest First</option>
                                <option value="results_desc" class="bg-slate-900 text-white">Most Results</option>
                                <option value="results_asc" class="bg-slate-900 text-white">Fewest Results</option>
                                <option value="time_desc" class="bg-slate-900 text-white">Longest Latency</option>
                                <option value="time_asc" class="bg-slate-900 text-white">Shortest Latency</option>
                            </select>
                        </div>

                        <!-- Reset Filters Button -->
                        <button
                            v-if="hasActiveFilters"
                            @click="resetFilters"
                            type="button"
                            class="px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20 text-xs font-mono flex items-center space-x-1.5 transition cartoon-btn cursor-pointer"
                            title="Reset all active search filters"
                        >
                            <RotateCcw class="w-3 h-3" />
                            <span>Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions Bar (Visible when items selected) -->
                <div
                    v-if="selectedIds.length > 0"
                    class="bg-amber-500/10 border border-amber-500/30 rounded-2xl px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs font-mono text-amber-300 animate-fadeIn"
                >
                    <div class="flex items-center space-x-2">
                        <span class="font-bold">{{ selectedIds.length }}</span>
                        <span>queries selected for batch action</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button
                            @click="selectedIds = []"
                            class="px-2.5 py-1 rounded-lg bg-slate-900 text-slate-400 hover:text-white border border-slate-700 transition cursor-pointer"
                        >
                            Deselect All
                        </button>
                        <button
                            @click="bulkDelete"
                            :disabled="isBulkDeleting"
                            class="px-3 py-1 rounded-lg bg-red-950/90 text-red-300 hover:bg-red-900 border border-red-500/50 flex items-center space-x-1.5 font-bold transition cursor-pointer"
                        >
                            <Loader2 v-if="isBulkDeleting" class="w-3.5 h-3.5 animate-spin" />
                            <Trash2 v-else class="w-3.5 h-3.5" />
                            <span>Delete Selected</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. Queries Table Registry -->
            <div class="bg-slate-950/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 reveal-item is-revealed reveal-delay-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-2.5">
                        <Clock class="w-4 h-4 text-amber-400" />
                        <h2 class="font-serif text-sm font-bold tracking-wide text-white uppercase">
                            Logged Investigation Queries
                        </h2>
                        <span class="px-2 py-0.5 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-[10px] font-mono">
                            {{ queries.total }} records
                        </span>
                    </div>

                    <!-- Quick Page Stats -->
                    <div class="text-xs font-mono text-slate-400">
                        Showing {{ queries.from || 0 }} - {{ queries.to || 0 }} of {{ queries.total }}
                    </div>
                </div>

                <!-- Table Content -->
                <div v-if="queries.data && queries.data.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="border-b border-slate-800 text-slate-400 select-none">
                            <tr>
                                <th class="pb-3 w-10 text-center">
                                    <button
                                        @click="toggleSelectAll"
                                        type="button"
                                        class="p-1 hover:text-amber-400 text-slate-500 transition cursor-pointer"
                                        title="Select all on this page"
                                    >
                                        <CheckSquare v-if="allOnPageSelected" class="w-4 h-4 text-amber-400" />
                                        <Square v-else class="w-4 h-4" />
                                    </button>
                                </th>
                                <th class="pb-3 font-semibold min-w-[220px]">QUERY STRING</th>
                                <th class="pb-3 font-semibold">ENGINE</th>
                                <th class="pb-3 font-semibold">ROUTING</th>
                                <th class="pb-3 font-semibold text-center">RESULTS</th>
                                <th class="pb-3 font-semibold text-center">LATENCY</th>
                                <th class="pb-3 font-semibold text-center">STATUS</th>
                                <th class="pb-3 font-semibold">RECORDED AT</th>
                                <th class="pb-3 text-right font-semibold min-w-[180px]">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr
                                v-for="q in queries.data"
                                :key="q.id"
                                class="hover:bg-slate-900/40 transition group"
                                :class="selectedIds.includes(q.id) ? 'bg-amber-500/5' : ''"
                            >
                                <!-- Checkbox -->
                                <td class="py-3.5 text-center">
                                    <button
                                        @click="toggleSelect(q.id)"
                                        type="button"
                                        class="p-1 text-slate-500 hover:text-amber-400 transition cursor-pointer"
                                    >
                                        <CheckSquare v-if="selectedIds.includes(q.id)" class="w-4 h-4 text-amber-400" />
                                        <Square v-else class="w-4 h-4" />
                                    </button>
                                </td>

                                <!-- Query String -->
                                <td class="py-3.5">
                                    <div class="flex items-center space-x-2">
                                        <Link
                                            :href="`/search/${q.id}`"
                                            class="font-semibold text-white hover:text-amber-400 hover:underline transition max-w-sm truncate"
                                            :title="q.query"
                                        >
                                            {{ q.query }}
                                        </Link>

                                        <!-- Copy Query Button -->
                                        <button
                                            @click="copyQuery(q.query, q.id)"
                                            class="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-amber-300 transition shrink-0"
                                            title="Copy query to clipboard"
                                        >
                                            <Check v-if="copiedQueryId === q.id" class="w-3 h-3 text-emerald-400" />
                                            <Copy v-else class="w-3 h-3" />
                                        </button>

                                        <!-- Associated Investigation Badge -->
                                        <span
                                            v-if="q.investigation"
                                            class="inline-flex items-center gap-1 text-[9px] px-1.5 py-0.5 rounded bg-indigo-950/80 border border-indigo-500/40 text-indigo-300 shrink-0"
                                            :title="`Part of case: ${q.investigation.title}`"
                                        >
                                            <FolderGit2 class="w-2.5 h-2.5" />
                                            <span class="max-w-[80px] truncate">{{ q.investigation.title }}</span>
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        ID #{{ q.id }}
                                        <span v-if="q.scan_type && q.scan_type !== 'quick'">• Scan: {{ q.scan_type }}</span>
                                    </div>
                                </td>

                                <!-- Engine -->
                                <td class="py-3.5 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border select-none"
                                        :class="getEngineBadgeClass(q.engine)"
                                    >
                                        {{ q.engine }}
                                    </span>
                                </td>

                                <!-- Routing -->
                                <td class="py-3.5 whitespace-nowrap">
                                    <span
                                        v-if="q.use_tor"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 font-bold select-none"
                                        title="Routed via Tor SOCKS5 circuit"
                                    >
                                        <Shield class="w-3 h-3 text-emerald-400" />
                                        <span>TOR / ONION</span>
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-slate-900 border border-slate-700 text-slate-400 font-medium select-none"
                                        title="Standard clearnet direct routing"
                                    >
                                        <Globe class="w-3 h-3 text-slate-400" />
                                        <span>CLEARNET</span>
                                    </span>
                                </td>

                                <!-- Results Count -->
                                <td class="py-3.5 text-center whitespace-nowrap">
                                    <span
                                        class="font-bold px-2 py-0.5 rounded-md border text-xs"
                                        :class="q.results_count > 0 ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-slate-900 border-slate-800 text-slate-400'"
                                    >
                                        {{ q.results_count }}
                                    </span>
                                </td>

                                <!-- Latency -->
                                <td class="py-3.5 text-center text-slate-400 whitespace-nowrap">
                                    {{ q.execution_time_seconds ? `${q.execution_time_seconds}s` : '—' }}
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 text-center whitespace-nowrap">
                                    <span
                                        v-if="q.status === 'completed'"
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950/60 text-emerald-400 border border-emerald-900/60"
                                    >
                                        COMPLETED
                                    </span>
                                    <span
                                        v-else-if="q.status === 'failed'"
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-red-950/60 text-red-400 border border-red-900/60"
                                        :title="q.error_message || 'Query execution encountered an error'"
                                    >
                                        FAILED
                                    </span>
                                    <span
                                        v-else
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-400 border border-amber-900/60 animate-pulse"
                                    >
                                        {{ (q.status || 'RUNNING').toUpperCase() }}
                                    </span>
                                </td>

                                <!-- Recorded At -->
                                <td class="py-3.5 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ formatDate(q.created_at) }}
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 text-right whitespace-nowrap space-x-1.5">
                                    <!-- Inspect Dossier -->
                                    <Link
                                        :href="`/search/${q.id}`"
                                        class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-amber-500/60 text-slate-300 hover:text-amber-300 text-[11px] font-mono transition inline-flex items-center space-x-1 cartoon-btn"
                                        title="View full result findings and screenshots"
                                    >
                                        <span>Dossier</span>
                                        <ExternalLink class="w-3 h-3 shrink-0" />
                                    </Link>

                                    <!-- Rerun Query -->
                                    <Link
                                        :href="`/search?q=${encodeURIComponent(q.query)}&engine=${q.engine}${q.use_tor ? '&use_tor=1' : ''}`"
                                        class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 hover:bg-amber-500/20 text-amber-400 text-[11px] font-mono transition inline-flex items-center space-x-1 cartoon-btn"
                                        title="Re-execute query in live search"
                                    >
                                        <RotateCcw class="w-3 h-3 shrink-0" />
                                        <span>Rerun</span>
                                    </Link>

                                    <!-- Delete Record -->
                                    <button
                                        @click="deleteQuery(q.id)"
                                        :disabled="deletingId === q.id"
                                        class="p-1 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-950/40 border border-transparent hover:border-red-900 transition inline-flex items-center cursor-pointer"
                                        title="Delete query record"
                                    >
                                        <Loader2 v-if="deletingId === q.id" class="w-3.5 h-3.5 animate-spin text-red-400" />
                                        <Trash2 v-else class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State (No records found or filters matched nothing) -->
                <div v-else class="text-center py-16 px-4 bg-slate-900/40 border border-slate-800/80 rounded-2xl space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mx-auto">
                        <Filter v-if="hasActiveFilters" class="w-6 h-6 text-amber-400" />
                        <Terminal v-else class="w-6 h-6 text-amber-400" />
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-base font-serif font-bold text-white uppercase">
                            <template v-if="hasActiveFilters">No matching queries found</template>
                            <template v-else>No queries recorded in registry yet</template>
                        </h3>
                        <p class="text-xs text-slate-400 max-w-md mx-auto">
                            <template v-if="hasActiveFilters">
                                No investigation search queries matched your current filter criteria. Try clearing filters or using broader search terms.
                            </template>
                            <template v-else>
                                Execute your first darknet or clearnet reconnaissance query from the Multi-Engine search suite to begin building your query archive.
                            </template>
                        </p>
                    </div>

                    <div>
                        <button
                            v-if="hasActiveFilters"
                            @click="resetFilters"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 font-mono text-xs font-bold transition cartoon-btn cursor-pointer"
                        >
                            Clear All Filters
                        </button>
                        <Link
                            v-else
                            href="/search"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-mono text-xs font-bold inline-flex items-center space-x-2 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer"
                        >
                            <Search class="w-3.5 h-3.5" />
                            <span>LAUNCH FIRST SEARCH</span>
                        </Link>
                    </div>
                </div>

                <!-- 5. Pagination Component -->
                <div
                    v-if="queries.links && queries.links.length > 3"
                    class="pt-4 border-t border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4 font-mono text-xs"
                >
                    <div class="text-slate-400 text-center sm:text-left">
                        Page <span class="font-bold text-white">{{ queries.current_page }}</span> of <span class="font-bold text-white">{{ queries.last_page }}</span> ({{ queries.total }} queries)
                    </div>

                    <div class="flex items-center justify-center flex-wrap gap-1">
                        <template v-for="(link, i) in queries.links" :key="i">
                            <span
                                v-if="!link.url"
                                class="px-3 py-1.5 rounded-lg border border-slate-800/50 text-slate-600 select-none"
                                v-html="link.label"
                            ></span>
                            <Link
                                v-else
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border transition cartoon-btn"
                                :class="[
                                    link.active
                                        ? 'bg-amber-500 border-amber-400 text-slate-950 font-bold shadow-md shadow-amber-500/20'
                                        : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white hover:border-slate-700'
                                ]"
                                v-html="link.label"
                                preserve-scroll
                            ></Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Clear All Confirmation Modal -->
            <div
                v-if="showClearModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
                @click.self="showClearModal = false"
            >
                <div class="bg-slate-900 border border-red-500/40 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 cartoon-modal">
                    <div class="w-12 h-12 rounded-2xl bg-red-950/80 border border-red-500/60 flex items-center justify-center mx-auto text-red-400">
                        <AlertCircle class="w-6 h-6" />
                    </div>

                    <div class="text-center space-y-2">
                        <h3 class="text-lg font-serif font-black text-white uppercase tracking-wide">
                            Wipe Entire Query Registry?
                        </h3>
                        <p class="text-xs text-slate-400 font-sans leading-relaxed">
                            This will permanently delete all <strong class="text-white">{{ stats.total_queries }}</strong> recorded investigation queries and all <strong class="text-white">{{ stats.total_results }}</strong> indexed search findings. This operation cannot be undone.
                        </p>
                    </div>

                    <div class="flex items-center space-x-3 pt-2">
                        <button
                            @click="showClearModal = false"
                            class="w-1/2 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs font-bold transition cartoon-btn cursor-pointer"
                        >
                            CANCEL
                        </button>
                        <button
                            @click="clearAllQueries"
                            :disabled="isClearingAll"
                            class="w-1/2 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white font-mono text-xs font-bold flex items-center justify-center space-x-2 transition shadow-lg shadow-red-600/30 cartoon-btn cursor-pointer"
                        >
                            <Loader2 v-if="isClearingAll" class="w-3.5 h-3.5 animate-spin" />
                            <Trash2 v-else class="w-3.5 h-3.5" />
                            <span>CONFIRM WIPE</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    FolderGit2, 
    CheckCircle2, 
    ExternalLink, 
    Loader2 
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    investigations: {
        type: Array,
        default: () => [],
    },
    candidateNodesAndEdges: {
        type: Object,
        default: () => ({ nodes: [], edges: [] }),
    },
    isDeploying: {
        type: Boolean,
        default: false,
    },
    deploySuccessResult: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'deploy']);

const selectedInvestigationId = ref(props.investigations[0]?.id || null);

const handleDeploy = () => {
    if (selectedInvestigationId.value) {
        emit('deploy', selectedInvestigationId.value);
    }
};
</script>

<template>
    <div 
        v-if="show" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 font-mono"
        @click="$emit('close')"
    >
        <div 
            class="w-full max-w-lg rounded-2xl bg-slate-950 border border-slate-800 shadow-2xl p-5 space-y-4 cartoon-modal"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center space-x-2">
                    <FolderGit2 class="w-5 h-5 text-indigo-400" />
                    <h3 class="font-serif font-bold text-white text-sm uppercase tracking-wide">
                        Deploy to Case Dossier Graph
                    </h3>
                </div>
                <button 
                    type="button"
                    @click="$emit('close')" 
                    class="text-slate-400 hover:text-white text-xs cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <p class="text-xs text-slate-300">
                Select a target Investigation to inject the discovered infrastructure entities (domains, subdomains, IPs, nameservers) and their relational edges into the interactive Link Graph.
            </p>

            <!-- Summary of Entities to be Injected -->
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 text-xs space-y-1.5">
                <div class="text-[10px] text-slate-400 uppercase font-bold">CANDIDATE NODES & EDGES:</div>
                <div class="flex items-center justify-between text-slate-200">
                    <span>Entities to deploy:</span>
                    <span class="font-bold text-indigo-300">{{ candidateNodesAndEdges.nodes.length }} Nodes</span>
                </div>
                <div class="flex items-center justify-between text-slate-200">
                    <span>Relational edges:</span>
                    <span class="font-bold text-purple-300">{{ candidateNodesAndEdges.edges.length }} Edges</span>
                </div>
            </div>

            <!-- Target Investigation Selector -->
            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 uppercase font-bold">Select Investigation Case:</label>
                <select
                    v-model="selectedInvestigationId"
                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-indigo-500"
                >
                    <option v-for="inv in investigations" :key="inv.id" :value="inv.id">
                        Case #{{ inv.id }} - {{ inv.title }} ({{ inv.status }})
                    </option>
                </select>
            </div>

            <!-- Success State -->
            <div v-if="deploySuccessResult" class="p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/60 text-emerald-300 text-xs space-y-2">
                <div class="flex items-center space-x-1.5 font-bold">
                    <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                    <span>Successfully Linked to Dossier #{{ deploySuccessResult.case_number }}!</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-300">Added {{ deploySuccessResult.added_nodes }} nodes & {{ deploySuccessResult.added_edges }} edges.</span>
                    <Link 
                        :href="`/investigations/${deploySuccessResult.investigation_id}`"
                        class="text-sky-400 hover:text-white underline font-bold flex items-center space-x-1"
                    >
                        <span>Open Case Graph</span>
                        <ExternalLink class="w-3 h-3" />
                    </Link>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-800">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs transition cursor-pointer"
                >
                    Close
                </button>
                <button
                    type="button"
                    @click="handleDeploy"
                    :disabled="isDeploying || candidateNodesAndEdges.nodes.length === 0"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition flex items-center space-x-1.5 disabled:opacity-50 cursor-pointer shadow-lg shadow-indigo-950/40"
                >
                    <Loader2 v-if="isDeploying" class="w-3.5 h-3.5 animate-spin" />
                    <FolderGit2 v-else class="w-3.5 h-3.5" />
                    <span>Inject into Graph</span>
                </button>
            </div>
        </div>
    </div>
</template>

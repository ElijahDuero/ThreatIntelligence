<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Bell, 
    Plus, 
    Play, 
    Pause, 
    RefreshCw, 
    Trash2, 
    ExternalLink, 
    Send, 
    MessageSquare, 
    Users, 
    Radio, 
    FolderGit2, 
    Bookmark, 
    Check, 
    X,
    Globe,
    Server
} from 'lucide-vue-next';

import EvidenceBookmarkModal from '@/Components/EvidenceBookmarkModal.vue';
import { useEvidenceBookmark } from '@/Composables/useEvidenceBookmark';

const props = defineProps({
    monitors: {
        type: Array,
        default: () => [],
    },
    recentAlerts: {
        type: Array,
        default: () => [],
    },
    investigations: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            active_monitors: 0,
            total_monitors: 0,
            total_findings: 0,
            unread_alerts: 0,
        }),
    },
});

const activeTab = ref('monitors'); // monitors, alerts
const showCreateModal = ref(false);
const { showBookmarkModal, bookmarkTarget, openBookmark } = useEvidenceBookmark(props.investigations);
const scanningMonitorId = ref(null);
const isScanningAll = ref(false);
const toastMsg = ref('');

const showToast = (msg) => {
    toastMsg.value = msg;
    setTimeout(() => {
        toastMsg.value = '';
    }, 3500);
};

// Form for New Monitor
const form = useForm({
    title: '',
    target_type: 'telegram',
    target_value: '',
    frequency: 'hourly',
    webhook_url: '',
    investigation_id: '',
});

const submitNewMonitor = () => {
    form.post('/monitors', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            showToast('Recon monitor deployed and baseline sweep initiated.');
        },
    });
};

// Toggle Monitor Active / Pause
const toggleMonitorActive = async (monitor) => {
    try {
        const nextState = !monitor.is_active;
        await axios.put(`/api/monitors/${monitor.id}`, { is_active: nextState });
        monitor.is_active = nextState;
        showToast(`Monitor "${monitor.title}" ${nextState ? 'resumed' : 'paused'}.`);
    } catch (e) {
        alert('Failed to toggle monitor.');
    }
};

// Scan Monitor Now
const scanMonitorNow = async (monitor) => {
    if (scanningMonitorId.value) return;
    scanningMonitorId.value = monitor.id;

    try {
        const res = await axios.post(`/api/monitors/${monitor.id}/poll`);
        if (res.data.success) {
            monitor.findings_count = res.data.monitor.findings_count;
            monitor.last_scanned_at = res.data.monitor.last_scanned_at;
            showToast(`Scan complete: ${res.data.new_alerts_count} new alerts detected.`);
            router.reload({ only: ['recentAlerts', 'stats'] });
        } else {
            showToast(`Scan error: ${res.data.error}`);
        }
    } catch (e) {
        showToast('Scan request failed.');
    } finally {
        scanningMonitorId.value = null;
    }
};

// Scan All Monitors
const scanAllNow = async () => {
    if (isScanningAll.value) return;
    isScanningAll.value = true;

    try {
        const res = await axios.post('/api/monitors/poll-all');
        showToast(`Swept ${res.data.total_polled} monitors. Detected ${res.data.new_alerts_count} alerts.`);
        router.reload({ only: ['monitors', 'recentAlerts', 'stats'] });
    } catch (e) {
        showToast('Batch sweep failed.');
    } finally {
        isScanningAll.value = false;
    }
};

// Delete Monitor
const deleteMonitor = (id) => {
    if (!confirm('Permanently remove this reconnaissance monitor and all logged alerts?')) return;
    router.delete(`/monitors/${id}`);
};

// Mark Alert Read
const markAlertRead = async (alert) => {
    try {
        await axios.post(`/api/alerts/${alert.id}/read`);
        alert.is_read = true;
    } catch (e) {}
};

// Mark All Alerts Read
const markAllAlertsRead = async () => {
    try {
        await axios.post('/api/alerts/mark-all-read');
        props.recentAlerts.forEach(a => a.is_read = true);
        showToast('All alerts marked as read.');
    } catch (e) {}
};

// Open Bookmark Modal
const openBookmarkModal = (alert) => {
    openBookmark({
        title: alert.title,
        url: alert.external_url || (`#alert-${alert.id}`),
        notes: alert.summary || '',
        severity: (alert.severity || 'medium').toLowerCase(),
        investigation_id: alert.investigation_id || (props.investigations?.[0]?.id || null),
    });
};
</script>

<template>
    <div class="space-y-6 w-full">
        <Head title="Recon Monitors & Threat Alerts | Darkdump OSINT" />

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
                    v-if="toastMsg"
                    class="fixed top-20 right-6 z-50 bg-slate-900 border border-rose-500/50 text-rose-300 px-4 py-2.5 rounded-xl shadow-2xl font-mono text-xs flex items-center space-x-2 backdrop-blur"
                >
                    <Check class="w-4 h-4 text-rose-400" />
                    <span>{{ toastMsg }}</span>
                </div>
            </transition>

            <!-- Header Section -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                            <span class="text-xs font-mono text-rose-400 uppercase tracking-wider font-bold">AUTOMATED RECONNAISSANCE & SURVEILLANCE</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-serif font-black tracking-tight text-white uppercase">
                            Recon Mission Monitors
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 font-sans max-w-3xl leading-relaxed">
                            Deploy autonomous scheduled sweeps across Telegram leak channels, Reddit discussions, and persona handles. Receive instant in-app alerts and external webhook dispatch upon new activity.
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <button 
                            @click="scanAllNow"
                            :disabled="isScanningAll || monitors.length === 0"
                            class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 font-mono text-xs font-bold flex items-center space-x-2 transition cursor-pointer disabled:opacity-50"
                        >
                            <RefreshCw class="w-4 h-4 text-cyan-400" :class="{ 'animate-spin': isScanningAll }" />
                            <span>{{ isScanningAll ? 'SWEEPING...' : 'SWEEP ALL NOW' }}</span>
                        </button>

                        <button 
                            @click="showCreateModal = true"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-400 hover:to-rose-500 text-white font-mono text-xs font-bold flex items-center space-x-2 transition shadow-lg shadow-rose-500/25 cartoon-btn cursor-pointer"
                        >
                            <Plus class="w-4 h-4" />
                            <span>DEPLOY MONITOR</span>
                        </button>
                    </div>
                </div>

                <!-- Telemetry Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 pt-6 mt-6 border-t border-slate-800/80">
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Active Surveillance</div>
                        <div class="text-xl font-bold text-rose-400 mt-1">
                            {{ stats.active_monitors }} <span class="text-xs text-slate-500 font-normal">/ {{ stats.total_monitors }}</span>
                        </div>
                    </div>
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Cumulative Findings</div>
                        <div class="text-xl font-bold text-cyan-400 mt-1">{{ stats.total_findings }}</div>
                    </div>
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Unread Cyber Alerts</div>
                        <div class="text-xl font-bold text-amber-400 mt-1">{{ stats.unread_alerts }}</div>
                    </div>
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 font-mono">
                        <div class="text-[10px] text-slate-400 uppercase">Surveillance Engine</div>
                        <div class="text-xs font-bold text-emerald-400 mt-2 flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>CRON DAEMON ONLINE</span>
                        </div>
                    </div>
                </div>

                <!-- Tab Controls -->
                <div class="flex items-center space-x-2 pt-6 mt-6 border-t border-slate-800/80">
                    <button 
                        @click="activeTab = 'monitors'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer"
                        :class="activeTab === 'monitors' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/40 shadow-lg shadow-rose-500/10' : 'text-slate-400 hover:text-white'"
                    >
                        <Radio class="w-3.5 h-3.5" />
                        <span>Active Monitors ({{ monitors.length }})</span>
                    </button>

                    <button 
                        @click="activeTab = 'alerts'"
                        class="px-4 py-2 rounded-xl text-xs font-mono font-bold flex items-center space-x-2 transition cursor-pointer"
                        :class="activeTab === 'alerts' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/40 shadow-lg shadow-amber-500/10' : 'text-slate-400 hover:text-white'"
                    >
                        <Bell class="w-3.5 h-3.5" />
                        <span>Threat Alert Feed ({{ recentAlerts.length }})</span>
                        <span v-if="stats.unread_alerts > 0" class="px-1.5 py-0.2 rounded-full bg-rose-600 text-white text-[10px]">
                            {{ stats.unread_alerts }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: Monitors Grid -->
            <div v-show="activeTab === 'monitors'" class="space-y-4">
                <div v-if="monitors && monitors.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div 
                        v-for="m in monitors" 
                        :key="m.id"
                        class="bg-slate-950/80 border rounded-2xl p-6 transition-all shadow-xl cartoon-card space-y-4 flex flex-col justify-between"
                        :class="m.is_active ? 'border-slate-800 hover:border-rose-500/50' : 'border-slate-800/50 opacity-60'"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-2">
                                    <span 
                                        class="p-2 rounded-xl border"
                                        :class="[
                                            m.target_type === 'telegram' ? 'bg-sky-500/10 border-sky-500/30 text-sky-400' :
                                            m.target_type === 'reddit' ? 'bg-orange-500/10 border-orange-500/30 text-orange-400' :
                                            m.target_type === 'onion' ? 'bg-rose-500/10 border-rose-500/30 text-rose-400' :
                                            (m.target_type === 'infrastructure' || m.target_type === 'domain' || m.target_type === 'ip') ? 'bg-purple-500/10 border-purple-500/30 text-purple-400' :
                                            'bg-cyan-500/10 border-cyan-500/30 text-cyan-400'
                                        ]"
                                    >
                                        <Send v-if="m.target_type === 'telegram'" class="w-4 h-4" />
                                        <MessageSquare v-else-if="m.target_type === 'reddit'" class="w-4 h-4" />
                                        <Globe v-else-if="m.target_type === 'onion'" class="w-4 h-4" />
                                        <Server v-else-if="m.target_type === 'infrastructure' || m.target_type === 'domain' || m.target_type === 'ip'" class="w-4 h-4" />
                                        <Users v-else class="w-4 h-4" />
                                    </span>
                                    <div>
                                        <span class="text-[10px] font-mono text-slate-500 uppercase">{{ m.target_type }} Monitor</span>
                                        <h3 class="font-serif font-bold text-white text-sm uppercase leading-tight">{{ m.title }}</h3>
                                    </div>
                                </div>

                                <span 
                                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                                    :class="m.is_active ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/40' : 'bg-slate-800 text-slate-400 border border-slate-700'"
                                >
                                    {{ m.is_active ? 'ACTIVE' : 'PAUSED' }}
                                </span>
                            </div>

                            <!-- Target String Box -->
                            <div class="bg-slate-900/80 border border-slate-800/80 rounded-xl p-2.5 font-mono text-xs">
                                <div class="text-[10px] text-slate-500 uppercase">Target Value</div>
                                <div class="text-white font-bold truncate mt-0.5">
                                    {{ m.target_type === 'telegram' ? '@' + m.target_value : m.target_value }}
                                </div>
                            </div>

                            <!-- Meta details -->
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono text-slate-400">
                                <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800">
                                    Frequency: {{ m.frequency }}
                                </span>
                                <span v-if="m.webhook_url" class="px-2 py-0.5 rounded bg-purple-950/60 border border-purple-500/30 text-purple-300">
                                    Webhook Active
                                </span>
                                <Link 
                                    v-if="m.investigation" 
                                    :href="`/investigations/${m.investigation.id}`"
                                    class="px-2 py-0.5 rounded bg-indigo-950/60 border border-indigo-500/30 text-indigo-300 hover:underline flex items-center space-x-1"
                                >
                                    <FolderGit2 class="w-3 h-3" />
                                    <span class="truncate max-w-[120px]">{{ m.investigation.title }}</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between font-mono text-xs">
                            <div class="text-[10px] text-slate-400">
                                <div>Findings: <span class="text-cyan-400 font-bold">{{ m.findings_count }}</span></div>
                                <div class="text-slate-500 text-[9px]">
                                    {{ m.last_scanned_at ? new Date(m.last_scanned_at).toLocaleTimeString() : 'Never scanned' }}
                                </div>
                            </div>

                            <div class="flex items-center space-x-1.5">
                                <button 
                                    @click="scanMonitorNow(m)"
                                    :disabled="scanningMonitorId === m.id"
                                    class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-cyan-400 border border-slate-700 transition cursor-pointer"
                                    title="Scan this target now"
                                >
                                    <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': scanningMonitorId === m.id }" />
                                </button>
                                <button 
                                    @click="toggleMonitorActive(m)"
                                    class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 transition cursor-pointer"
                                    :class="m.is_active ? 'text-amber-400 border border-amber-500/30' : 'text-emerald-400 border border-emerald-500/30'"
                                    :title="m.is_active ? 'Pause Monitor' : 'Resume Monitor'"
                                >
                                    <Pause v-if="m.is_active" class="w-3.5 h-3.5" />
                                    <Play v-else class="w-3.5 h-3.5" />
                                </button>
                                <button 
                                    @click="deleteMonitor(m.id)"
                                    class="p-2 rounded-xl bg-red-950/40 hover:bg-red-900/40 text-red-400 border border-red-500/30 transition cursor-pointer"
                                    title="Delete monitor"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-16 bg-slate-950/80 border border-slate-800 rounded-3xl text-slate-400 font-mono text-xs space-y-3">
                    <Radio class="w-8 h-8 text-rose-500/50 mx-auto" />
                    <div>No surveillance monitors configured yet.</div>
                    <button 
                        @click="showCreateModal = true"
                        class="px-4 py-2 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40 font-bold hover:bg-rose-500/30 transition cursor-pointer"
                    >
                        Deploy First Monitor
                    </button>
                </div>
            </div>

            <!-- TAB 2: Threat Alert Feed -->
            <div v-show="activeTab === 'alerts'" class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl cartoon-card space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="flex items-center space-x-2">
                        <Bell class="w-4 h-4 text-amber-400" />
                        <h2 class="font-serif font-bold text-xs text-white uppercase tracking-wider">
                            RECENT SURVEILLANCE FINDINGS ({{ recentAlerts.length }})
                        </h2>
                    </div>

                    <button 
                        v-if="stats.unread_alerts > 0"
                        @click="markAllAlertsRead"
                        class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-mono text-xs font-bold transition cursor-pointer"
                    >
                        Mark All Read
                    </button>
                </div>

                <div v-if="recentAlerts && recentAlerts.length > 0" class="space-y-3">
                    <div 
                        v-for="a in recentAlerts" 
                        :key="a.id"
                        class="p-4 rounded-2xl border transition flex flex-col sm:flex-row sm:items-center justify-between gap-4 font-mono text-xs"
                        :class="a.is_read ? 'bg-slate-900/40 border-slate-800/70 opacity-80' : 'bg-slate-900/90 border-rose-500/40 shadow-sm shadow-rose-500/10'"
                    >
                        <div class="space-y-1.5 max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <span 
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                    :class="[
                                        a.severity === 'critical' ? 'bg-red-950 text-red-300 border border-red-500' :
                                        a.severity === 'high' ? 'bg-orange-950 text-orange-300 border border-orange-500' :
                                        a.severity === 'medium' ? 'bg-sky-950 text-sky-300 border border-sky-500' :
                                        'bg-slate-800 text-slate-400'
                                    ]"
                                >
                                    {{ a.severity }}
                                </span>

                                <span v-if="a.monitor" class="text-slate-400 text-[11px]">
                                    via [{{ a.monitor.target_type }}] {{ a.monitor.title }}
                                </span>

                                <span class="text-slate-500 text-[10px]">• {{ new Date(a.created_at).toLocaleString() }}</span>
                            </div>

                            <h4 class="font-bold text-sm text-white">{{ a.title }}</h4>
                            <p class="text-slate-300 font-sans text-xs leading-relaxed">{{ a.summary }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2 shrink-0">
                            <a 
                                v-if="a.external_url" 
                                :href="a.external_url" 
                                target="_blank"
                                class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 flex items-center space-x-1 transition"
                            >
                                <span>Inspect</span>
                                <ExternalLink class="w-3 h-3" />
                            </a>

                            <button 
                                @click="openBookmarkModal(a)"
                                class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 flex items-center space-x-1 transition cursor-pointer"
                                title="Add to Case Dossier"
                            >
                                <Bookmark class="w-3 h-3" />
                                <span>Dossier</span>
                            </button>

                            <button 
                                v-if="!a.is_read"
                                @click="markAlertRead(a)"
                                class="p-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-emerald-400 border border-slate-700 transition cursor-pointer"
                                title="Mark as read"
                            >
                                <Check class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-slate-500 font-mono text-xs">
                    No threat alerts recorded yet. Sweeps will post findings here.
                </div>
            </div>

        <!-- MODAL: Deploy New Recon Monitor -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-950 border border-rose-500/50 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl cartoon-card space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-serif font-black text-lg text-white uppercase flex items-center space-x-2">
                        <Radio class="w-5 h-5 text-rose-400" />
                        <span>Deploy Recon Monitor</span>
                    </h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitNewMonitor" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase mb-1.5">Target Vector *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <label 
                                class="p-2 rounded-xl border text-center cursor-pointer transition flex flex-col items-center justify-center space-y-1"
                                :class="form.target_type === 'telegram' ? 'bg-sky-500/20 border-sky-500 text-sky-300 font-bold' : 'bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <input type="radio" v-model="form.target_type" value="telegram" class="sr-only" />
                                <Send class="w-3.5 h-3.5" />
                                <span class="text-[11px]">Telegram</span>
                            </label>

                            <label 
                                class="p-2 rounded-xl border text-center cursor-pointer transition flex flex-col items-center justify-center space-y-1"
                                :class="form.target_type === 'reddit' ? 'bg-orange-500/20 border-orange-500 text-orange-300 font-bold' : 'bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <input type="radio" v-model="form.target_type" value="reddit" class="sr-only" />
                                <MessageSquare class="w-3.5 h-3.5" />
                                <span class="text-[11px]">Reddit</span>
                            </label>

                            <label 
                                class="p-2 rounded-xl border text-center cursor-pointer transition flex flex-col items-center justify-center space-y-1"
                                :class="form.target_type === 'persona' ? 'bg-cyan-500/20 border-cyan-500 text-cyan-300 font-bold' : 'bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <input type="radio" v-model="form.target_type" value="persona" class="sr-only" />
                                <Users class="w-3.5 h-3.5" />
                                <span class="text-[11px]">Persona</span>
                            </label>

                            <label 
                                class="p-2 rounded-xl border text-center cursor-pointer transition flex flex-col items-center justify-center space-y-1"
                                :class="form.target_type === 'onion' ? 'bg-rose-500/20 border-rose-500 text-rose-300 font-bold' : 'bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <input type="radio" v-model="form.target_type" value="onion" class="sr-only" />
                                <Globe class="w-3.5 h-3.5" />
                                <span class="text-[11px]">.Onion</span>
                            </label>

                            <label 
                                class="p-2 rounded-xl border text-center cursor-pointer transition flex flex-col items-center justify-center space-y-1 col-span-2 sm:col-span-1"
                                :class="form.target_type === 'infrastructure' ? 'bg-purple-500/20 border-purple-500 text-purple-300 font-bold' : 'bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <input type="radio" v-model="form.target_type" value="infrastructure" class="sr-only" />
                                <Server class="w-3.5 h-3.5" />
                                <span class="text-[11px]">Host / IP</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Monitor Mission Label *</label>
                        <input 
                            v-model="form.title"
                            type="text" 
                            placeholder="e.g. Ransomware Leak Channel Monitor"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-rose-500"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">
                            {{ 
                                form.target_type === 'telegram' ? 'Telegram Channel Handle *' : 
                                form.target_type === 'reddit' ? 'Keyword / Topic Phrase *' : 
                                form.target_type === 'persona' ? 'Target Username / Alias *' :
                                form.target_type === 'onion' ? 'Onion Service URL or Address *' :
                                'Target Domain or IPv4 Address *'
                            }}
                        </label>
                        <input 
                            v-model="form.target_value"
                            type="text" 
                            :placeholder="
                                form.target_type === 'telegram' ? 'e.g. durov or cyberthreats' : 
                                form.target_type === 'reddit' ? 'e.g. database dump leaked' : 
                                form.target_type === 'persona' ? 'e.g. satoshi_nakamoto' :
                                form.target_type === 'onion' ? 'e.g. expyuzv7y53i2...onion' :
                                'e.g. threat-intel.org or 198.51.100.25'
                            "
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-rose-500"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 uppercase mb-1">Sweep Frequency</label>
                            <select 
                                v-model="form.frequency"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500"
                            >
                                <option value="15m">Every 15 Minutes</option>
                                <option value="hourly">Hourly Sweep</option>
                                <option value="daily">Daily Sweep</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-400 uppercase mb-1">Link to Case Dossier</label>
                            <select 
                                v-model="form.investigation_id"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500"
                            >
                                <option value="">No Dossier (Global)</option>
                                <option v-for="inv in investigations" :key="inv.id" :value="inv.id">
                                    [#{{ inv.id }}] {{ inv.title }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase mb-1">Webhook URL (Discord / Slack / Generic)</label>
                        <input 
                            v-model="form.webhook_url"
                            type="url" 
                            placeholder="https://discord.com/api/webhooks/..."
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-rose-500 font-sans"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800">
                        <button 
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white font-bold transition shadow-lg shadow-rose-500/20"
                        >
                            {{ form.processing ? 'Deploying...' : 'Deploy Monitor' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Evidence Bookmark Modal -->
        <EvidenceBookmarkModal
            v-model="showBookmarkModal"
            :target="bookmarkTarget"
            :investigations="investigations"
            source="recon_monitors"
        />
    </div>
</template>

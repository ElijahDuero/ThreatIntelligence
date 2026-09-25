<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { 
    FolderGit2, 
    X, 
    Loader2, 
    Check, 
    AlertTriangle 
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    show: {
        type: Boolean,
        default: undefined,
    },
    target: {
        type: Object,
        default: () => ({}),
    },
    investigations: {
        type: Array,
        default: () => [],
    },
    source: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'update:show', 'saved', 'close']);

const isOpen = computed(() => {
    return props.show !== undefined ? props.show : props.modelValue;
});

const form = ref({
    title: '',
    url: '',
    notes: '',
    severity: 'medium',
    investigation_id: null,
});

const isSaving = ref(false);
const saveSuccess = ref(false);
const showToast = ref(false);
const errorMessage = ref(null);

watch(
    () => [props.target, isOpen.value],
    () => {
        if (isOpen.value) {
            errorMessage.value = null;
            saveSuccess.value = false;
            form.value = {
                title: props.target?.title || '',
                url: props.target?.url || '',
                notes: props.target?.notes || props.target?.snippet || '',
                severity: props.target?.severity || 'medium',
                investigation_id: props.target?.investigation_id ?? (props.investigations?.[0]?.id || null),
            };
        }
    },
    { immediate: true, deep: true }
);

const close = () => {
    emit('update:modelValue', false);
    emit('update:show', false);
    emit('close');
};

const submit = async () => {
    if (!form.value.url || isSaving.value) return;

    isSaving.value = true;
    errorMessage.value = null;

    try {
        const payload = {
            title: form.value.title,
            url: form.value.url,
            notes: form.value.notes,
            severity: form.value.severity,
            investigation_id: form.value.investigation_id,
        };

        if (props.source) {
            payload.source = props.source;
        }

        const res = await axios.post('/api/bookmarks', payload);

        if (res.data?.success || res.status === 200 || res.status === 201) {
            saveSuccess.value = true;
            emit('saved', res.data);

            setTimeout(() => {
                close();
                saveSuccess.value = false;
                showToast.value = true;

                setTimeout(() => {
                    showToast.value = false;
                }, 3500);
            }, 600);
        }
    } catch (err) {
        console.error('Failed to save evidence bookmark:', err);
        errorMessage.value = err.response?.data?.message || 'Failed to save evidence to Case Dossier.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div>
        <!-- Modal Backdrop & Dialog -->
        <div 
            v-if="isOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            @click.self="close"
        >
            <div class="w-full max-w-lg p-6 rounded-2xl bg-slate-900 border border-slate-700 shadow-2xl font-mono space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2 text-rose-400 font-bold text-sm">
                        <FolderGit2 class="w-4 h-4" />
                        <span>Save Evidence to Case Dossier</span>
                    </div>
                    <button 
                        type="button"
                        @click="close" 
                        class="text-slate-400 hover:text-white transition"
                        title="Close modal"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200 text-xs flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 shrink-0 text-rose-400" />
                    <span>{{ errorMessage }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase text-slate-400 font-bold mb-1">Target Evidence Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:border-rose-500 focus:outline-none"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-slate-400 font-bold mb-1">Direct Target URL</label>
                        <input
                            v-model="form.url"
                            type="text"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:border-rose-500 focus:outline-none"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs uppercase text-slate-400 font-bold mb-1">Severity Rating</label>
                            <select
                                v-model="form.severity"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:border-rose-500 focus:outline-none uppercase"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs uppercase text-slate-400 font-bold mb-1">Assign to Investigation</label>
                            <select
                                v-model="form.investigation_id"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:border-rose-500 focus:outline-none"
                            >
                                <option :value="null">-- General Evidence Pool --</option>
                                <option 
                                    v-for="inv in investigations" 
                                    :key="inv.id" 
                                    :value="inv.id"
                                >
                                    Case #{{ inv.id }}: {{ inv.title }} [{{ inv.priority }}]
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-slate-400 font-bold mb-1">Investigative Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Add analyst observations or correlation context..."
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:border-rose-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                        <button
                            type="button"
                            @click="close"
                            class="px-4 py-2 rounded-xl border border-slate-800 hover:bg-slate-800 text-xs text-slate-400 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white text-xs font-bold uppercase flex items-center gap-2 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="isSaving" class="w-3.5 h-3.5 animate-spin" />
                            <Check v-else-if="saveSuccess" class="w-3.5 h-3.5 text-emerald-300" />
                            <span>{{ saveSuccess ? 'Evidence Linked!' : 'Save Evidence' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Global Toast Notification -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="transform translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform translate-y-2 opacity-0"
            >
                <div 
                    v-if="showToast" 
                    class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-950/95 border border-emerald-500/50 text-emerald-200 text-xs font-mono shadow-2xl backdrop-blur-md"
                >
                    <Check class="w-4 h-4 text-emerald-400" />
                    <span>Evidence finding successfully linked to Case Dossier!</span>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

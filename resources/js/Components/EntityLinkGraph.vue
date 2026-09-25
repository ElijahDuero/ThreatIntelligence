<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { 
    Maximize2, 
    Minimize2, 
    RotateCcw, 
    Pause, 
    Play, 
    ZoomIn, 
    ZoomOut, 
    Search, 
    Download, 
    Share2, 
    Plus, 
    Link as LinkIcon, 
    Trash2, 
    ExternalLink, 
    X
} from 'lucide-vue-next';

const props = defineProps({
    initialNodes: {
        type: Array,
        default: () => [],
    },
    initialEdges: {
        type: Array,
        default: () => [],
    },
    caseTitle: {
        type: String,
        default: 'Case Dossier',
    },
});

const emit = defineEmits(['save-graph', 'add-custom-node', 'add-custom-edge', 'delete-node']);

const containerRef = ref(null);
const canvasRef = ref(null);

// Dimensions & Transform
const width = ref(1000);
const height = ref(650);
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const startPanX = ref(0);
const startPanY = ref(0);
const isFullscreen = ref(false);

// Physics & Simulation
const isFrozen = ref(false);
let animFrameId = null;
const draggedNode = ref(null);
const hoveredNode = ref(null);
const selectedNode = ref(null);
const searchQuery = ref('');
const activeTypeFilter = ref('all');

// Node Palette & Styling
const ENTITY_CONFIG = {
    case: { stroke: '#fbbf24', fill: '#78350f', glow: 'rgba(251, 191, 36, 0.45)', text: '#fde68a', radius: 24, label: 'Case Dossier' },
    person: { stroke: '#06b6d4', fill: '#164e63', glow: 'rgba(6, 182, 212, 0.45)', text: '#a5f3fc', radius: 18, label: 'Person / Persona' },
    onion: { stroke: '#a855f7', fill: '#581c87', glow: 'rgba(168, 85, 247, 0.45)', text: '#e9d5ff', radius: 18, label: 'Dark Web Onion' },
    channel: { stroke: '#38bdf8', fill: '#0c4a6e', glow: 'rgba(56, 189, 248, 0.45)', text: '#bae6fd', radius: 17, label: 'Telegram Channel' },
    email: { stroke: '#10b981', fill: '#064e3b', glow: 'rgba(16, 185, 129, 0.45)', text: '#a7f3d0', radius: 16, label: 'Email Account' },
    wallet: { stroke: '#f59e0b', fill: '#78350f', glow: 'rgba(245, 158, 11, 0.45)', text: '#fde68a', radius: 17, label: 'Crypto Wallet' },
    domain: { stroke: '#6366f1', fill: '#312e81', glow: 'rgba(99, 102, 241, 0.4)', text: '#c7d2fe', radius: 16, label: 'Clearnet Domain' },
    forum: { stroke: '#f97316', fill: '#7c2d12', glow: 'rgba(249, 115, 22, 0.4)', text: '#fed7aa', radius: 16, label: 'Forum / Thread' },
    developer: { stroke: '#14b8a6', fill: '#134e4a', glow: 'rgba(20, 184, 166, 0.4)', text: '#99f6e4', radius: 16, label: 'Code Repository' },
    tag: { stroke: '#64748b', fill: '#1e293b', glow: 'rgba(100, 116, 139, 0.3)', text: '#cbd5e1', radius: 14, label: 'Case Tag' },
    generic: { stroke: '#94a3b8', fill: '#334155', glow: 'rgba(148, 163, 184, 0.3)', text: '#e2e8f0', radius: 15, label: 'Asset Node' },
};

// Simulation State
const simNodes = ref([]);
const simEdges = ref([]);

const initGraphData = () => {
    const w = width.value || 800;
    const h = height.value || 600;
    const cx = w / 2;
    const cy = h / 2;

    const rawNodes = props.initialNodes || [];
    const count = rawNodes.length;

    simNodes.value = rawNodes.map((n, i) => {
        let x = n.x;
        let y = n.y;

        if (x === undefined || y === undefined) {
            if (n.type === 'case') {
                x = cx;
                y = cy;
            } else {
                const angle = (i / Math.max(1, count - 1)) * 2 * Math.PI;
                const dist = 120 + (i % 3) * 60;
                x = cx + Math.cos(angle) * dist + (Math.random() - 0.5) * 30;
                y = cy + Math.sin(angle) * dist + (Math.random() - 0.5) * 30;
            }
        }

        return {
            ...n,
            x,
            y,
            vx: 0,
            vy: 0,
            radius: ENTITY_CONFIG[n.type]?.radius || 16,
        };
    });

    simEdges.value = (props.initialEdges || []).map(e => ({
        ...e,
        sourceId: typeof e.source === 'object' ? e.source.id : e.source,
        targetId: typeof e.target === 'object' ? e.target.id : e.target,
    }));
};

watch(() => [props.initialNodes, props.initialEdges], () => {
    initGraphData();
}, { deep: true });

// Filtered Nodes
const visibleNodes = computed(() => {
    if (activeTypeFilter.value === 'all') {
        return simNodes.value;
    }
    return simNodes.value.filter(n => n.type === activeTypeFilter.value || n.type === 'case');
});

const visibleEdges = computed(() => {
    const nodeIds = new Set(visibleNodes.value.map(n => n.id));
    return simEdges.value.filter(e => nodeIds.has(e.sourceId) && nodeIds.has(e.targetId));
});

// Physics Step Simulation
const stepPhysics = () => {
    if (isFrozen.value) return;

    const nodes = visibleNodes.value;
    const edges = visibleEdges.value;
    const n = nodes.length;
    if (n === 0) return;

    const nodeMap = new Map();
    nodes.forEach(node => nodeMap.set(node.id, node));

    const cx = width.value / 2;
    const cy = height.value / 2;

    // Repulsion between nodes
    const repulsionK = 2800;
    for (let i = 0; i < n; i++) {
        const n1 = nodes[i];
        for (let j = i + 1; j < n; j++) {
            const n2 = nodes[j];
            const dx = n2.x - n1.x;
            const dy = n2.y - n1.y;
            const distSq = dx * dx + dy * dy || 1;
            const dist = Math.sqrt(distSq);

            if (dist < 380) {
                const force = repulsionK / distSq;
                const fx = (dx / dist) * force;
                const fy = (dy / dist) * force;

                if (draggedNode.value !== n1 && n1.type !== 'case') {
                    n1.vx -= fx;
                    n1.vy -= fy;
                }
                if (draggedNode.value !== n2 && n2.type !== 'case') {
                    n2.vx += fx;
                    n2.vy += fy;
                }
            }
        }

        // Center gravity
        const gDx = cx - n1.x;
        const gDy = cy - n1.y;
        const gDist = Math.sqrt(gDx * gDx + gDy * gDy) || 1;
        const gForce = 0.04;
        if (draggedNode.value !== n1 && n1.type !== 'case') {
            n1.vx += gDx * gForce;
            n1.vy += gDy * gForce;
        }
    }

    // Spring forces along edges
    const springLength = 110;
    const springK = 0.05;
    edges.forEach(edge => {
        const s = nodeMap.get(edge.sourceId);
        const t = nodeMap.get(edge.targetId);
        if (!s || !t) return;

        const dx = t.x - s.x;
        const dy = t.y - s.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 1;
        const delta = dist - springLength;
        const fx = (dx / dist) * delta * springK;
        const fy = (dy / dist) * delta * springK;

        if (draggedNode.value !== s && s.type !== 'case') {
            s.vx += fx;
            s.vy += fy;
        }
        if (draggedNode.value !== t && t.type !== 'case') {
            t.vx -= fx;
            t.vy -= fy;
        }
    });

    // Velocity integration & damping
    const damping = 0.82;
    nodes.forEach(node => {
        if (draggedNode.value === node) return;
        if (node.type === 'case') {
            // Keep root gently centered
            node.x = cx;
            node.y = cy;
            return;
        }
        node.vx *= damping;
        node.vy *= damping;
        node.x += node.vx;
        node.y += node.vy;
    });
};

// Canvas Renderer
const render = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    stepPhysics();

    const dpr = window.devicePixelRatio || 1;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.save();
    ctx.scale(dpr, dpr);

    // Apply pan & zoom
    ctx.translate(panX.value, panY.value);
    ctx.scale(zoom.value, zoom.value);

    // Background Cyber Grid
    drawGrid(ctx);

    const nodes = visibleNodes.value;
    const edges = visibleEdges.value;
    const nodeMap = new Map();
    nodes.forEach(n => nodeMap.set(n.id, n));

    const isMatch = (node) => {
        if (!searchQuery.value) return true;
        const q = searchQuery.value.toLowerCase();
        return (node.label && node.label.toLowerCase().includes(q)) ||
               (node.type && node.type.toLowerCase().includes(q)) ||
               (node.subtitle && node.subtitle.toLowerCase().includes(q));
    };

    // Draw Edges
    edges.forEach(edge => {
        const s = nodeMap.get(edge.sourceId);
        const t = nodeMap.get(edge.targetId);
        if (!s || !t) return;

        const isHighlighted = selectedNode.value && 
            (selectedNode.value.id === s.id || selectedNode.value.id === t.id);

        ctx.beginPath();
        ctx.moveTo(s.x, s.y);
        ctx.lineTo(t.x, t.y);

        if (isHighlighted) {
            ctx.strokeStyle = '#38bdf8';
            ctx.lineWidth = 2.5;
            ctx.shadowColor = 'rgba(56, 189, 248, 0.8)';
            ctx.shadowBlur = 8;
        } else {
            ctx.strokeStyle = edge.type === 'custom' ? 'rgba(251, 191, 36, 0.35)' : 'rgba(51, 65, 85, 0.55)';
            ctx.lineWidth = edge.type === 'custom' ? 1.8 : 1.2;
            ctx.shadowBlur = 0;
        }
        ctx.stroke();
        ctx.shadowBlur = 0;

        // Draw Edge Label if highlighted or hovered
        if (isHighlighted || (hoveredNode.value && (hoveredNode.value.id === s.id || hoveredNode.value.id === t.id))) {
            const midX = (s.x + t.x) / 2;
            const midY = (s.y + t.y) / 2;

            ctx.font = '9px "JetBrains Mono", monospace';
            ctx.fillStyle = isHighlighted ? '#38bdf8' : '#94a3b8';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            const txt = edge.label || 'links';
            const textWidth = ctx.measureText(txt).width;
            ctx.fillStyle = '#020617';
            ctx.fillRect(midX - textWidth / 2 - 4, midY - 6, textWidth + 8, 12);
            ctx.fillStyle = isHighlighted ? '#38bdf8' : '#cbd5e1';
            ctx.fillText(txt, midX, midY);
        }
    });

    // Draw Nodes
    nodes.forEach(node => {
        const cfg = ENTITY_CONFIG[node.type] || ENTITY_CONFIG.generic;
        const isSel = selectedNode.value && selectedNode.value.id === node.id;
        const isHov = hoveredNode.value && hoveredNode.value.id === node.id;
        const matched = isMatch(node);
        const radius = node.radius || cfg.radius;

        ctx.save();
        ctx.translate(node.x, node.y);

        // Selection / Hover Glow Ring
        if (isSel || isHov) {
            ctx.beginPath();
            ctx.arc(0, 0, radius + 7, 0, Math.PI * 2);
            ctx.fillStyle = cfg.glow;
            ctx.fill();
            ctx.strokeStyle = cfg.stroke;
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        // Search Match Ring
        if (searchQuery.value && matched) {
            ctx.beginPath();
            ctx.arc(0, 0, radius + 4, 0, Math.PI * 2);
            ctx.strokeStyle = '#fbbf24';
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        // Node Circle Base
        ctx.beginPath();
        ctx.arc(0, 0, radius, 0, Math.PI * 2);
        ctx.fillStyle = matched ? cfg.fill : '#0f172a';
        ctx.fill();
        ctx.strokeStyle = matched ? cfg.stroke : '#334155';
        ctx.lineWidth = isSel ? 2.5 : 1.8;
        ctx.stroke();

        // Node Icon Glyph / Initial
        ctx.font = 'bold 10px sans-serif';
        ctx.fillStyle = matched ? cfg.text : '#64748b';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        const glyph = node.type === 'case' ? '★' : 
                      node.type === 'person' ? 'P' :
                      node.type === 'onion' ? 'O' :
                      node.type === 'channel' ? 'T' :
                      node.type === 'email' ? '@' :
                      node.type === 'wallet' ? '₿' :
                      node.type === 'forum' ? 'R' :
                      node.type === 'developer' ? '<>' :
                      node.type === 'tag' ? '#' : '•';
        ctx.fillText(glyph, 0, 0);

        // Node Text Label (Below)
        ctx.font = '10px "JetBrains Mono", monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        const labelText = node.label.length > 22 ? node.label.substring(0, 20) + '..' : node.label;
        ctx.fillStyle = isSel ? '#ffffff' : (matched ? cfg.text : '#475569');
        ctx.fillText(labelText, 0, radius + 4);

        if (node.subtitle && (isSel || isHov)) {
            ctx.font = '8px sans-serif';
            ctx.fillStyle = '#94a3b8';
            ctx.fillText(node.subtitle, 0, radius + 16);
        }

        ctx.restore();
    });

    ctx.restore();

    animFrameId = requestAnimationFrame(render);
};

const drawGrid = (ctx) => {
    const gridSize = 40;
    const startX = -panX.value / zoom.value;
    const startY = -panY.value / zoom.value;
    const endX = startX + width.value / zoom.value;
    const endY = startY + height.value / zoom.value;

    ctx.strokeStyle = 'rgba(30, 41, 59, 0.4)';
    ctx.lineWidth = 0.5;

    ctx.beginPath();
    for (let x = Math.floor(startX / gridSize) * gridSize; x < endX; x += gridSize) {
        ctx.moveTo(x, startY);
        ctx.lineTo(x, endY);
    }
    for (let y = Math.floor(startY / gridSize) * gridSize; y < endY; y += gridSize) {
        ctx.moveTo(startX, y);
        ctx.lineTo(endX, y);
    }
    ctx.stroke();
};

// Canvas Interaction Coordinates
const getCanvasCoords = (e) => {
    const canvas = canvasRef.value;
    if (!canvas) return { x: 0, y: 0 };
    const rect = canvas.getBoundingClientRect();
    const clientX = e.clientX ?? e.touches?.[0]?.clientX ?? 0;
    const clientY = e.clientY ?? e.touches?.[0]?.clientY ?? 0;

    const screenX = clientX - rect.left;
    const screenY = clientY - rect.top;

    return {
        x: (screenX - panX.value) / zoom.value,
        y: (screenY - panY.value) / zoom.value,
        screenX,
        screenY
    };
};

const findNodeAt = (x, y) => {
    const nodes = visibleNodes.value;
    for (let i = nodes.length - 1; i >= 0; i--) {
        const n = nodes[i];
        const dx = n.x - x;
        const dy = n.y - y;
        const r = (n.radius || 16) + 4;
        if (dx * dx + dy * dy <= r * r) {
            return n;
        }
    }
    return null;
};

// Mouse Event Handlers
const onMouseDown = (e) => {
    if (e.button !== 0) return;
    const { x, y, screenX, screenY } = getCanvasCoords(e);
    const hit = findNodeAt(x, y);

    if (hit) {
        draggedNode.value = hit;
        selectedNode.value = hit;
    } else {
        isPanning.value = true;
        startPanX.value = screenX - panX.value;
        startPanY.value = screenY - panY.value;
    }
};

const onMouseMove = (e) => {
    const { x, y, screenX, screenY } = getCanvasCoords(e);

    if (draggedNode.value) {
        draggedNode.value.x = x;
        draggedNode.value.y = y;
        draggedNode.value.vx = 0;
        draggedNode.value.vy = 0;
        return;
    }

    if (isPanning.value) {
        panX.value = screenX - startPanX.value;
        panY.value = screenY - startPanY.value;
        return;
    }

    hoveredNode.value = findNodeAt(x, y);
};

const onMouseUp = () => {
    if (draggedNode.value) {
        draggedNode.value = null;
    }
    isPanning.value = false;
};

const onWheel = (e) => {
    e.preventDefault();
    const { screenX, screenY } = getCanvasCoords(e);
    const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
    const newZoom = Math.min(Math.max(0.3, zoom.value * zoomFactor), 3.0);

    panX.value = screenX - (screenX - panX.value) * (newZoom / zoom.value);
    panY.value = screenY - (screenY - panY.value) * (newZoom / zoom.value);
    zoom.value = newZoom;
};

// Controls
const zoomIn = () => {
    zoom.value = Math.min(zoom.value * 1.25, 3.0);
};

const zoomOut = () => {
    zoom.value = Math.max(zoom.value * 0.8, 0.3);
};

const resetView = () => {
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
    draggedNode.value = null;
    selectedNode.value = null;
};

const fitView = () => {
    const nodes = visibleNodes.value;
    if (nodes.length === 0) return;

    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    nodes.forEach(n => {
        minX = Math.min(minX, n.x);
        maxX = Math.max(maxX, n.x);
        minY = Math.min(minY, n.y);
        maxY = Math.max(maxY, n.y);
    });

    const graphW = maxX - minX + 160;
    const graphH = maxY - minY + 160;
    const scaleX = width.value / graphW;
    const scaleY = height.value / graphH;
    const fitScale = Math.min(scaleX, scaleY, 1.4);

    zoom.value = Math.max(0.4, fitScale);
    panX.value = (width.value - (minX + maxX) * zoom.value) / 2;
    panY.value = (height.value - (minY + maxY) * zoom.value) / 2;
};

const togglePhysics = () => {
    isFrozen.value = !isFrozen.value;
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    nextTick(() => {
        handleResize();
    });
};

const exportPng = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const link = document.createElement('a');
    link.download = `dossier-graph-${Date.now()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
};

const handleResize = () => {
    if (!containerRef.value || !canvasRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    width.value = rect.width;
    height.value = isFullscreen.value ? window.innerHeight : Math.max(560, rect.height || 560);

    const dpr = window.devicePixelRatio || 1;
    canvasRef.value.width = width.value * dpr;
    canvasRef.value.height = height.value * dpr;
};

onMounted(() => {
    handleResize();
    window.addEventListener('resize', handleResize);
    initGraphData();
    animFrameId = requestAnimationFrame(render);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    if (animFrameId) {
        cancelAnimationFrame(animFrameId);
    }
});

const getNeighbors = (nodeId) => {
    if (!nodeId) return [];
    const neighbors = [];
    simEdges.value.forEach(e => {
        if (e.sourceId === nodeId) {
            const target = simNodes.value.find(n => n.id === e.targetId);
            if (target) neighbors.push({ node: target, label: e.label, direction: 'outgoing' });
        } else if (e.targetId === nodeId) {
            const source = simNodes.value.find(n => n.id === e.sourceId);
            if (source) neighbors.push({ node: source, label: e.label, direction: 'incoming' });
        }
    });
    return neighbors;
};

const savePositions = () => {
    const nodesData = simNodes.value.map(n => ({
        id: n.id,
        label: n.label,
        type: n.type,
        subtitle: n.subtitle,
        notes: n.notes,
        metadata: n.metadata,
        is_auto: n.is_auto,
        x: Math.round(n.x),
        y: Math.round(n.y),
    }));

    const edgesData = simEdges.value.map(e => ({
        id: e.id,
        source: e.sourceId,
        target: e.targetId,
        label: e.label,
        type: e.type,
        notes: e.notes,
    }));

    emit('save-graph', { nodes: nodesData, edges: edgesData });
};
</script>

<template>
    <div 
        ref="containerRef"
        class="relative w-full rounded-2xl overflow-hidden border border-slate-800 bg-[#020617] select-none"
        :class="isFullscreen ? 'fixed inset-0 z-50 rounded-none' : 'h-[620px]'"
    >
        <!-- Canvas Element -->
        <canvas 
            ref="canvasRef"
            class="w-full h-full cursor-grab active:cursor-grabbing"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @mouseup="onMouseUp"
            @wheel="onWheel"
        ></canvas>

        <!-- Top Graph HUD Bar -->
        <div class="absolute top-3.5 left-3.5 right-3.5 flex flex-wrap items-center justify-between gap-2 pointer-events-none">
            <!-- Left: Search & Type Filter -->
            <div class="flex items-center space-x-2 pointer-events-auto">
                <div class="relative">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Highlight entities in graph..."
                        class="bg-slate-900/90 border border-slate-700/80 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 w-52 sm:w-64 backdrop-blur font-mono"
                    />
                </div>

                <div class="hidden sm:flex items-center bg-slate-900/90 border border-slate-700/80 rounded-xl p-1 backdrop-blur space-x-1 text-[11px] font-mono">
                    <button 
                        @click="activeTypeFilter = 'all'"
                        class="px-2.5 py-1 rounded-lg transition"
                        :class="activeTypeFilter === 'all' ? 'bg-cyan-500/20 text-cyan-400 font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        All ({{ simNodes.length }})
                    </button>
                    <button 
                        @click="activeTypeFilter = 'person'"
                        class="px-2 py-1 rounded-lg transition"
                        :class="activeTypeFilter === 'person' ? 'bg-cyan-500/20 text-cyan-400 font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        Person
                    </button>
                    <button 
                        @click="activeTypeFilter = 'onion'"
                        class="px-2 py-1 rounded-lg transition"
                        :class="activeTypeFilter === 'onion' ? 'bg-purple-500/20 text-purple-400 font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        Onion
                    </button>
                    <button 
                        @click="activeTypeFilter = 'channel'"
                        class="px-2 py-1 rounded-lg transition"
                        :class="activeTypeFilter === 'channel' ? 'bg-sky-500/20 text-sky-400 font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        Telegram
                    </button>
                    <button 
                        @click="activeTypeFilter = 'wallet'"
                        class="px-2 py-1 rounded-lg transition"
                        :class="activeTypeFilter === 'wallet' ? 'bg-amber-500/20 text-amber-400 font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        Crypto
                    </button>
                </div>
            </div>

            <!-- Right: Action & Layout Controls -->
            <div class="flex items-center space-x-2 pointer-events-auto">
                <button 
                    @click="$emit('add-custom-node')"
                    class="px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-mono text-xs font-bold flex items-center space-x-1.5 transition backdrop-blur cursor-pointer shadow-lg shadow-cyan-500/10"
                    title="Add manual entity node to graph"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Add Node</span>
                </button>

                <button 
                    @click="$emit('add-custom-edge')"
                    class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 font-mono text-xs font-bold flex items-center space-x-1.5 transition backdrop-blur cursor-pointer"
                    title="Connect two entities with a relational edge"
                >
                    <LinkIcon class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Connect Nodes</span>
                </button>

                <button 
                    @click="savePositions"
                    class="px-3 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 font-mono text-xs font-bold flex items-center space-x-1.5 transition backdrop-blur cursor-pointer"
                    title="Save current layout positions"
                >
                    <Share2 class="w-3.5 h-3.5" />
                    <span>Save Layout</span>
                </button>

                <div class="flex items-center bg-slate-900/90 border border-slate-700/80 rounded-xl p-1 backdrop-blur space-x-0.5">
                    <button 
                        @click="togglePhysics" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        :title="isFrozen ? 'Resume Physics' : 'Pause Physics'"
                    >
                        <Play v-if="isFrozen" class="w-3.5 h-3.5 text-amber-400" />
                        <Pause v-else class="w-3.5 h-3.5" />
                    </button>
                    <button 
                        @click="zoomIn" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        title="Zoom In"
                    >
                        <ZoomIn class="w-3.5 h-3.5" />
                    </button>
                    <button 
                        @click="zoomOut" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        title="Zoom Out"
                    >
                        <ZoomOut class="w-3.5 h-3.5" />
                    </button>
                    <button 
                        @click="fitView" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        title="Fit Graph"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                    </button>
                    <button 
                        @click="exportPng" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        title="Export Graph Image (PNG)"
                    >
                        <Download class="w-3.5 h-3.5" />
                    </button>
                    <button 
                        @click="toggleFullscreen" 
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
                        title="Toggle Fullscreen"
                    >
                        <Minimize2 v-if="isFullscreen" class="w-3.5 h-3.5 text-cyan-400" />
                        <Maximize2 v-else class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Legend & Telemetry Bar -->
        <div class="absolute bottom-3 left-3 flex flex-wrap items-center gap-3 text-[10px] font-mono text-slate-400 bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-1.5 backdrop-blur pointer-events-none">
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-amber-400"></span><span>Case Root</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-cyan-400"></span><span>Person</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-purple-400"></span><span>Onion</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-sky-400"></span><span>Telegram</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span>Crypto</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-emerald-400"></span><span>Email</span></span>
            <span class="flex items-center space-x-1"><span class="w-2 h-2 rounded-full bg-indigo-400"></span><span>Domain</span></span>
            <span class="text-slate-600">|</span>
            <span>Zoom: {{ Math.round(zoom * 100) }}%</span>
            <span>Nodes: {{ visibleNodes.length }}</span>
            <span>Edges: {{ visibleEdges.length }}</span>
        </div>

        <!-- Node Inspector Drawer (Right Sidebar) -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div 
                v-if="selectedNode"
                class="absolute top-16 right-3.5 bottom-12 w-80 sm:w-96 bg-slate-950/95 border border-slate-800 rounded-2xl p-5 shadow-2xl backdrop-blur flex flex-col justify-between overflow-y-auto z-20 cartoon-card"
            >
                <div class="space-y-4">
                    <!-- Drawer Header -->
                    <div class="flex items-start justify-between border-b border-slate-800/80 pb-3">
                        <div>
                            <span 
                                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider"
                                :style="{
                                    backgroundColor: (ENTITY_CONFIG[selectedNode.type]?.fill || '#1e293b'),
                                    color: (ENTITY_CONFIG[selectedNode.type]?.text || '#cbd5e1'),
                                    border: '1px solid ' + (ENTITY_CONFIG[selectedNode.type]?.stroke || '#475569')
                                }"
                            >
                                {{ ENTITY_CONFIG[selectedNode.type]?.label || selectedNode.type }}
                            </span>
                            <span v-if="!selectedNode.is_auto" class="ml-2 text-[10px] font-mono text-amber-400 border border-amber-500/30 px-1.5 py-0.5 rounded bg-amber-500/10">
                                CUSTOM
                            </span>
                            <h3 class="text-base font-serif font-bold text-white uppercase mt-2 break-all">
                                {{ selectedNode.label }}
                            </h3>
                            <p v-if="selectedNode.subtitle" class="text-xs text-slate-400 font-sans mt-0.5">
                                {{ selectedNode.subtitle }}
                            </p>
                        </div>
                        <button 
                            @click="selectedNode = null"
                            class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-900 transition"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Direct URL Link -->
                    <div v-if="selectedNode.metadata?.url" class="space-y-1">
                        <label class="text-[10px] font-mono text-slate-400 uppercase">Target Address / Endpoint</label>
                        <a 
                            :href="selectedNode.metadata.url" 
                            target="_blank" 
                            class="text-xs font-mono text-cyan-400 hover:underline flex items-center space-x-1 break-all bg-slate-900/60 p-2 rounded-xl border border-slate-800/80"
                        >
                            <span class="truncate">{{ selectedNode.metadata.url }}</span>
                            <ExternalLink class="w-3 h-3 shrink-0" />
                        </a>
                    </div>

                    <!-- Notes -->
                    <div v-if="selectedNode.notes || selectedNode.metadata?.notes" class="space-y-1">
                        <label class="text-[10px] font-mono text-slate-400 uppercase">Investigative Notes</label>
                        <p class="text-xs font-sans text-slate-300 leading-relaxed bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/80">
                            {{ selectedNode.notes || selectedNode.metadata?.notes }}
                        </p>
                    </div>

                    <!-- Relational Neighbors List -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-mono text-slate-400 uppercase">Connected Entities</label>
                            <span class="text-[10px] font-mono text-cyan-400 font-bold">
                                {{ getNeighbors(selectedNode.id).length }} links
                            </span>
                        </div>
                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                            <div 
                                v-for="(n, idx) in getNeighbors(selectedNode.id)" 
                                :key="idx"
                                @click="selectedNode = n.node"
                                class="flex items-center justify-between p-2 rounded-xl bg-slate-900/80 hover:bg-slate-800/80 border border-slate-800 transition cursor-pointer text-xs font-mono"
                            >
                                <div class="flex items-center space-x-2 truncate">
                                    <span 
                                        class="w-2 h-2 rounded-full shrink-0"
                                        :style="{ backgroundColor: ENTITY_CONFIG[n.node.type]?.stroke || '#94a3b8' }"
                                    ></span>
                                    <span class="truncate text-slate-200">{{ n.node.label }}</span>
                                </div>
                                <span class="text-[10px] text-cyan-400/80 px-1.5 py-0.5 rounded bg-cyan-950/60 shrink-0">
                                    {{ n.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Drawer Actions Footer -->
                <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between gap-2">
                    <button 
                        @click="$emit('add-custom-edge', selectedNode.id)"
                        class="flex-1 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 font-mono text-xs font-bold transition flex items-center justify-center space-x-1 cursor-pointer"
                    >
                        <LinkIcon class="w-3.5 h-3.5" />
                        <span>Link Entity</span>
                    </button>
                    <button 
                        v-if="!selectedNode.is_auto"
                        @click="$emit('delete-node', selectedNode.id)"
                        class="py-1.5 px-3 rounded-xl bg-red-950/60 hover:bg-red-900/60 text-red-400 border border-red-500/30 font-mono text-xs font-bold transition flex items-center justify-center space-x-1 cursor-pointer"
                        title="Delete custom node"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>

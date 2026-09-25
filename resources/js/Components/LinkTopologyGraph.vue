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
    Copy, 
    Check, 
    Share2, 
    Globe, 
    Download, 
    X 
} from 'lucide-vue-next';

const props = defineProps({
    rootUrl: {
        type: String,
        required: true,
    },
    rootTitle: {
        type: String,
        default: '',
    },
    internalLinks: {
        type: Array,
        default: () => [],
    },
    externalLinks: {
        type: Array,
        default: () => [],
    },
    emails: {
        type: Array,
        default: () => [],
    },
    documents: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['select-target']);

const containerRef = ref(null);
const canvasRef = ref(null);

// Dimensions & Transform state
const width = ref(800);
const height = ref(560);
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const startPanX = ref(0);
const startPanY = ref(0);
const isFullscreen = ref(false);

// Simulation state
const isFrozen = ref(false);
let animFrameId = null;
const draggedNode = ref(null);
const hoveredNode = ref(null);
const selectedNode = ref(null);
const searchQuery = ref('');
const copiedText = ref(null);

// Category Visibility Filters
const filterRoot = ref(true);
const filterInternal = ref(true);
const filterOnion = ref(true);
const filterClearnet = ref(true);
const filterEmails = ref(true);
const filterDocuments = ref(true);

// Node Color Palette
const COLORS = {
    root: { stroke: '#fbbf24', fill: '#78350f', glow: 'rgba(251, 191, 36, 0.45)', text: '#fde68a' },
    internal: { stroke: '#06b6d4', fill: '#164e63', glow: 'rgba(6, 182, 212, 0.4)', text: '#a5f3fc' },
    onion: { stroke: '#a855f7', fill: '#581c87', glow: 'rgba(168, 85, 247, 0.45)', text: '#e9d5ff' },
    clearnet: { stroke: '#38bdf8', fill: '#0c4a6e', glow: 'rgba(56, 189, 248, 0.35)', text: '#bae6fd' },
    email: { stroke: '#10b981', fill: '#064e3b', glow: 'rgba(16, 185, 129, 0.4)', text: '#a7f3d0' },
    document: { stroke: '#f43f5e', fill: '#881337', glow: 'rgba(244, 63, 94, 0.4)', text: '#fecdd3' },
};

// Graph data holder
const nodes = ref([]);
const links = ref([]);

const cleanHost = (url) => {
    try {
        const parsed = new URL(url.startsWith('http') ? url : 'http://' + url);
        return parsed.hostname;
    } catch {
        return url;
    }
};

const buildGraphData = () => {
    const newNodes = [];
    const newLinks = [];
    const seenIds = new Set();

    // 1. Root Node
    const rootHost = cleanHost(props.rootUrl);
    const rootNode = {
        id: 'node_root',
        type: 'root',
        label: props.rootTitle ? (props.rootTitle.slice(0, 24) + (props.rootTitle.length > 24 ? '…' : '')) : rootHost,
        fullValue: props.rootUrl,
        category: 'Root Target',
        radius: 22,
        x: width.value / 2,
        y: height.value / 2,
        vx: 0,
        vy: 0,
        isPinned: false,
    };
    newNodes.push(rootNode);
    seenIds.add(rootNode.id);

    // 2. Internal Links (Limit to max 35 to maintain great rendering)
    const internals = (props.internalLinks || []).slice(0, 35);
    internals.forEach((link, idx) => {
        const id = `node_int_${idx}`;
        if (!seenIds.has(id)) {
            let label = link;
            try {
                const u = new URL(link);
                label = u.pathname || u.hostname;
                if (label.length > 20) label = '…' + label.slice(-18);
            } catch {
                if (label.length > 20) label = label.slice(0, 18) + '…';
            }
            const angle = (idx / Math.max(1, internals.length)) * 2 * Math.PI;
            const dist = 120 + Math.random() * 40;
            const n = {
                id,
                type: 'internal',
                label: label || '/endpoint',
                fullValue: link,
                category: 'Internal Endpoint',
                radius: 12,
                x: (width.value / 2) + Math.cos(angle) * dist,
                y: (height.value / 2) + Math.sin(angle) * dist,
                vx: 0,
                vy: 0,
            };
            newNodes.push(n);
            seenIds.add(id);
            newLinks.push({ source: rootNode.id, target: id, type: 'internal' });
        }
    });

    // 3. External Links (partition into .onion vs clearnet)
    const externals = (props.externalLinks || []).slice(0, 40);
    externals.forEach((link, idx) => {
        const isOnion = link.includes('.onion');
        const id = `node_ext_${idx}`;
        if (!seenIds.has(id)) {
            const host = cleanHost(link);
            const label = host.length > 22 ? host.slice(0, 20) + '…' : host;
            const angle = ((idx + 0.5) / Math.max(1, externals.length)) * 2 * Math.PI;
            const dist = 180 + Math.random() * 60;
            const n = {
                id,
                type: isOnion ? 'onion' : 'clearnet',
                label,
                fullValue: link,
                category: isOnion ? 'External Hidden Service' : 'Clearnet External Link',
                radius: isOnion ? 14 : 11,
                x: (width.value / 2) + Math.cos(angle) * dist,
                y: (height.value / 2) + Math.sin(angle) * dist,
                vx: 0,
                vy: 0,
            };
            newNodes.push(n);
            seenIds.add(id);
            newLinks.push({ source: rootNode.id, target: id, type: n.type });
        }
    });

    // 4. Harvested Emails
    const emailsList = (props.emails || []).slice(0, 20);
    emailsList.forEach((email, idx) => {
        const id = `node_mail_${idx}`;
        if (!seenIds.has(id)) {
            const label = email.length > 20 ? email.slice(0, 18) + '…' : email;
            const angle = (idx / Math.max(1, emailsList.length)) * 2 * Math.PI + 0.8;
            const dist = 140 + Math.random() * 50;
            const n = {
                id,
                type: 'email',
                label,
                fullValue: email,
                category: 'Harvested Email',
                radius: 12,
                x: (width.value / 2) + Math.cos(angle) * dist,
                y: (height.value / 2) + Math.sin(angle) * dist,
                vx: 0,
                vy: 0,
            };
            newNodes.push(n);
            seenIds.add(id);
            newLinks.push({ source: rootNode.id, target: id, type: 'email' });
        }
    });

    // 5. Sensitive Documents
    const docsList = (props.documents || []).slice(0, 25);
    docsList.forEach((doc, idx) => {
        const id = `node_doc_${idx}`;
        if (!seenIds.has(id)) {
            const docName = typeof doc === 'object' ? (doc.name || 'document') : doc;
            const ext = typeof doc === 'object' ? (doc.extension || 'file') : 'file';
            const label = docName.length > 18 ? docName.slice(0, 16) + '…' : docName;
            const angle = (idx / Math.max(1, docsList.length)) * 2 * Math.PI - 0.5;
            const dist = 160 + Math.random() * 60;
            const n = {
                id,
                type: 'document',
                label: `[${ext.toUpperCase()}] ${label}`,
                fullValue: typeof doc === 'object' ? (doc.url || docName) : docName,
                meta: doc,
                category: `Sensitive Document (${ext.toUpperCase()})`,
                radius: 13,
                x: (width.value / 2) + Math.cos(angle) * dist,
                y: (height.value / 2) + Math.sin(angle) * dist,
                vx: 0,
                vy: 0,
            };
            newNodes.push(n);
            seenIds.add(id);
            newLinks.push({ source: rootNode.id, target: id, type: 'document' });
        }
    });

    nodes.value = newNodes;
    links.value = newLinks;
};

// Filtered visible nodes
const visibleNodes = computed(() => {
    return nodes.value.filter(n => {
        if (n.type === 'root' && !filterRoot.value) return false;
        if (n.type === 'internal' && !filterInternal.value) return false;
        if (n.type === 'onion' && !filterOnion.value) return false;
        if (n.type === 'clearnet' && !filterClearnet.value) return false;
        if (n.type === 'email' && !filterEmails.value) return false;
        if (n.type === 'document' && !filterDocuments.value) return false;

        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase();
            return n.label.toLowerCase().includes(q) || n.fullValue.toLowerCase().includes(q) || n.type.includes(q);
        }
        return true;
    });
});

const visibleNodeIds = computed(() => new Set(visibleNodes.value.map(n => n.id)));

const visibleLinks = computed(() => {
    const ids = visibleNodeIds.value;
    return links.value.filter(l => ids.has(l.source) && ids.has(l.target));
});

// Category counts
const counts = computed(() => {
    const c = { root: 0, internal: 0, onion: 0, clearnet: 0, email: 0, document: 0, total: nodes.value.length };
    for (const n of nodes.value) {
        if (c[n.type] !== undefined) c[n.type]++;
    }
    return c;
});

// Simulation and physics engine
const runSimulationStep = () => {
    if (isFrozen.value) return;

    const list = visibleNodes.value;
    const nLen = list.length;
    if (nLen === 0) return;

    const kRepulsion = 1400;
    const centerPull = 0.015;
    const friction = 0.86;
    const cx = width.value / 2;
    const cy = height.value / 2;

    // 1. Repulsion between visible nodes
    for (let i = 0; i < nLen; i++) {
        const n1 = list[i];
        for (let j = i + 1; j < nLen; j++) {
            const n2 = list[j];
            let dx = n1.x - n2.x;
            let dy = n1.y - n2.y;
            let distSq = dx * dx + dy * dy;
            if (distSq < 1) distSq = 1;
            const dist = Math.sqrt(distSq);

            const minAllowed = n1.radius + n2.radius + 15;
            let force = (kRepulsion / distSq);
            if (dist < minAllowed) {
                force += (minAllowed - dist) * 0.12;
            }

            const fx = (dx / dist) * force;
            const fy = (dy / dist) * force;

            if (n1 !== draggedNode.value && n1.type !== 'root') {
                n1.vx += fx;
                n1.vy += fy;
            }
            if (n2 !== draggedNode.value && n2.type !== 'root') {
                n2.vx -= fx;
                n2.vy -= fy;
            }
        }
    }

    // 2. Spring force along links
    const nodeMap = new Map();
    for (const n of list) nodeMap.set(n.id, n);

    for (const link of visibleLinks.value) {
        const source = nodeMap.get(link.source);
        const target = nodeMap.get(link.target);
        if (!source || !target) continue;

        const dx = target.x - source.x;
        const dy = target.y - source.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 1;
        const desiredDist = target.type === 'internal' ? 95 : target.type === 'document' ? 120 : 150;
        const springForce = (dist - desiredDist) * 0.025;

        const fx = (dx / dist) * springForce;
        const fy = (dy / dist) * springForce;

        if (target !== draggedNode.value && target.type !== 'root') {
            target.vx -= fx;
            target.vy -= fy;
        }
        if (source !== draggedNode.value && source.type !== 'root') {
            source.vx += fx;
            source.vy += fy;
        }
    }

    // 3. Center gravity & Velocity damping
    for (const n of list) {
        if (n === draggedNode.value) continue;

        if (n.type === 'root') {
            // Keep root close to center
            n.x += (cx - n.x) * 0.1;
            n.y += (cy - n.y) * 0.1;
            continue;
        }

        n.vx += (cx - n.x) * centerPull;
        n.vy += (cy - n.y) * centerPull;

        n.vx *= friction;
        n.vy *= friction;

        // Speed limit
        const maxV = 10;
        const vLen = Math.sqrt(n.vx * n.vx + n.vy * n.vy);
        if (vLen > maxV) {
            n.vx = (n.vx / vLen) * maxV;
            n.vy = (n.vy / vLen) * maxV;
        }

        n.x += n.vx;
        n.y += n.vy;
    }
};

// Canvas rendering engine
const renderCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Device Pixel Ratio scaling for retina crispness
    const dpr = window.devicePixelRatio || 1;
    if (canvas.width !== width.value * dpr || canvas.height !== height.value * dpr) {
        canvas.width = width.value * dpr;
        canvas.height = height.value * dpr;
    }

    ctx.save();
    ctx.scale(dpr, dpr);

    // Background fill
    ctx.fillStyle = '#020617'; // slate-950
    ctx.fillRect(0, 0, width.value, height.value);

    // Cyber Grid lines
    ctx.save();
    ctx.translate(panX.value, panY.value);
    ctx.scale(zoom.value, zoom.value);

    drawCyberGrid(ctx);

    // Draw Links
    const nodeMap = new Map();
    for (const n of visibleNodes.value) nodeMap.set(n.id, n);

    for (const link of visibleLinks.value) {
        const source = nodeMap.get(link.source);
        const target = nodeMap.get(link.target);
        if (!source || !target) continue;

        const colorStyle = COLORS[link.type] || COLORS.internal;
        const isHighlighted = (hoveredNode.value && (hoveredNode.value.id === source.id || hoveredNode.value.id === target.id)) ||
                              (selectedNode.value && (selectedNode.value.id === source.id || selectedNode.value.id === target.id));

        ctx.beginPath();
        ctx.moveTo(source.x, source.y);
        ctx.lineTo(target.x, target.y);
        ctx.lineWidth = isHighlighted ? 2.5 : 1;
        ctx.strokeStyle = isHighlighted ? colorStyle.stroke : 'rgba(51, 65, 85, 0.4)'; // slate-700
        ctx.stroke();

        if (isHighlighted) {
            // Neon glow for highlighted connections
            ctx.strokeStyle = colorStyle.glow;
            ctx.lineWidth = 6;
            ctx.stroke();
        }
    }

    // Draw Nodes
    for (const n of visibleNodes.value) {
        drawNode(ctx, n);
    }

    ctx.restore();
    ctx.restore();
};

const drawCyberGrid = (ctx) => {
    const gridSize = 40;
    const worldW = width.value / zoom.value * 3;
    const worldH = height.value / zoom.value * 3;
    const startX = -worldW;
    const endX = worldW * 2;
    const startY = -worldH;
    const endY = worldH * 2;

    ctx.strokeStyle = 'rgba(30, 41, 59, 0.35)'; // slate-800
    ctx.lineWidth = 0.5;

    ctx.beginPath();
    for (let x = startX - (startX % gridSize); x <= endX; x += gridSize) {
        ctx.moveTo(x, startY);
        ctx.lineTo(x, endY);
    }
    for (let y = startY - (startY % gridSize); y <= endY; y += gridSize) {
        ctx.moveTo(startX, y);
        ctx.lineTo(endX, y);
    }
    ctx.stroke();

    // Subtle radar concentric circles at center
    const cx = width.value / 2;
    const cy = height.value / 2;
    ctx.strokeStyle = 'rgba(6, 182, 212, 0.08)'; // cyan faint
    ctx.lineWidth = 1;
    [100, 220, 360].forEach(radius => {
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, 2 * Math.PI);
        ctx.stroke();
    });
};

const drawNode = (ctx, node) => {
    const style = COLORS[node.type] || COLORS.internal;
    const isHovered = hoveredNode.value?.id === node.id;
    const isSelected = selectedNode.value?.id === node.id;
    const radius = node.radius * (isHovered || isSelected ? 1.25 : 1);

    // Glowing Aura for root or hovered
    if (node.type === 'root' || isHovered || isSelected) {
        ctx.save();
        ctx.beginPath();
        ctx.arc(node.x, node.y, radius + (node.type === 'root' ? 8 : 6), 0, 2 * Math.PI);
        ctx.fillStyle = style.glow;
        ctx.fill();
        ctx.restore();
    }

    // Node Body
    ctx.beginPath();
    ctx.arc(node.x, node.y, radius, 0, 2 * Math.PI);
    ctx.fillStyle = isSelected ? style.stroke : style.fill;
    ctx.fill();

    // Node Border
    ctx.lineWidth = isSelected ? 3 : 2;
    ctx.strokeStyle = style.stroke;
    ctx.stroke();

    // Special inner badge ring for Root node
    if (node.type === 'root') {
        ctx.beginPath();
        ctx.arc(node.x, node.y, radius * 0.6, 0, 2 * Math.PI);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }

    // Node Label
    ctx.font = `${isHovered || isSelected ? 'bold 11px' : '10px'} "JetBrains Mono", monospace`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    // Label background pill for readability
    const labelY = node.y + radius + 11;
    const textMetrics = ctx.measureText(node.label);
    const pillW = textMetrics.width + 10;
    const pillH = 14;

    ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
    ctx.fillRect(node.x - pillW / 2, labelY - pillH / 2, pillW, pillH);
    ctx.strokeStyle = isSelected ? style.stroke : 'rgba(51, 65, 85, 0.5)';
    ctx.lineWidth = 0.75;
    ctx.strokeRect(node.x - pillW / 2, labelY - pillH / 2, pillW, pillH);

    ctx.fillStyle = isHovered || isSelected ? '#ffffff' : style.text;
    ctx.fillText(node.label, node.x, labelY);
};

// Animation loop
const animate = () => {
    runSimulationStep();
    renderCanvas();
    animFrameId = requestAnimationFrame(animate);
};

// Coordinate transforms (screen <-> world)
const screenToWorld = (screenX, screenY) => {
    const rect = canvasRef.value.getBoundingClientRect();
    const xOnCanvas = screenX - rect.left;
    const yOnCanvas = screenY - rect.top;
    return {
        x: (xOnCanvas - panX.value) / zoom.value,
        y: (yOnCanvas - panY.value) / zoom.value,
    };
};

const findNodeAtPosition = (worldX, worldY) => {
    for (let i = visibleNodes.value.length - 1; i >= 0; i--) {
        const n = visibleNodes.value[i];
        const dx = worldX - n.x;
        const dy = worldY - n.y;
        if (Math.sqrt(dx * dx + dy * dy) <= n.radius + 6) {
            return n;
        }
    }
    return null;
};

// Mouse and touch event handlers
const handleMouseDown = (e) => {
    const world = screenToWorld(e.clientX, e.clientY);
    const hit = findNodeAtPosition(world.x, world.y);

    if (hit) {
        draggedNode.value = hit;
        selectedNode.value = hit;
    } else {
        isPanning.value = true;
        startPanX.value = e.clientX - panX.value;
        startPanY.value = e.clientY - panY.value;
    }
};

const handleMouseMove = (e) => {
    if (draggedNode.value) {
        const world = screenToWorld(e.clientX, e.clientY);
        draggedNode.value.x = world.x;
        draggedNode.value.y = world.y;
        draggedNode.value.vx = 0;
        draggedNode.value.vy = 0;
        return;
    }

    if (isPanning.value) {
        panX.value = e.clientX - startPanX.value;
        panY.value = e.clientY - startPanY.value;
        return;
    }

    const world = screenToWorld(e.clientX, e.clientY);
    hoveredNode.value = findNodeAtPosition(world.x, world.y);
};

const handleMouseUp = () => {
    draggedNode.value = null;
    isPanning.value = false;
};

const handleWheel = (e) => {
    e.preventDefault();
    const zoomFactor = e.deltaY < 0 ? 1.12 : 0.89;
    const newZoom = Math.min(3.5, Math.max(0.3, zoom.value * zoomFactor));

    const rect = canvasRef.value.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const mouseY = e.clientY - rect.top;

    // Zoom towards mouse cursor
    panX.value = mouseX - (mouseX - panX.value) * (newZoom / zoom.value);
    panY.value = mouseY - (mouseY - panY.value) * (newZoom / zoom.value);
    zoom.value = newZoom;
};

// Controls and actions
const resetView = () => {
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
    if (nodes.value.length > 0) {
        const root = nodes.value.find(n => n.type === 'root');
        if (root) {
            root.x = width.value / 2;
            root.y = height.value / 2;
        }
    }
};

const zoomIn = () => {
    zoom.value = Math.min(3.5, zoom.value * 1.25);
};

const zoomOut = () => {
    zoom.value = Math.max(0.3, zoom.value * 0.8);
};

const toggleFreeze = () => {
    isFrozen.value = !isFrozen.value;
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    nextTick(() => {
        updateCanvasDimensions();
    });
};

const updateCanvasDimensions = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    width.value = rect.width;
    height.value = isFullscreen.value ? window.innerHeight - 80 : 580;
};

const copyValue = (val) => {
    navigator.clipboard.writeText(val);
    copiedText.value = val;
    setTimeout(() => {
        copiedText.value = null;
    }, 2000);
};

const triggerDeepCrawl = (url) => {
    emit('select-target', url);
};

const downloadGraphSnapshot = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = `topology_graph_${cleanHost(props.rootUrl)}_${Date.now()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
};

// Lifecycle
onMounted(() => {
    window.addEventListener('resize', updateCanvasDimensions);
    updateCanvasDimensions();
    buildGraphData();
    animate();
});

onUnmounted(() => {
    window.removeEventListener('resize', updateCanvasDimensions);
    if (animFrameId) cancelAnimationFrame(animFrameId);
});

watch(
    () => [props.rootUrl, props.internalLinks, props.externalLinks, props.emails, props.documents],
    () => {
        buildGraphData();
        resetView();
    },
    { deep: true }
);
</script>

<template>
    <div 
        ref="containerRef"
        :class="[
            'relative bg-slate-950 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl transition-all duration-300 font-sans',
            isFullscreen ? 'fixed inset-4 z-[9999] border-cyan-500/50 shadow-cyan-950/60' : 'w-full'
        ]"
    >
        <!-- Top Toolbar Banner -->
        <div class="p-3 sm:p-4 border-b border-slate-800/80 bg-slate-900/90 backdrop-blur-md flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
            <div class="flex items-center space-x-2">
                <div class="p-1.5 rounded-lg bg-cyan-950 border border-cyan-500/40 text-cyan-400">
                    <Share2 class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-white font-bold tracking-wider uppercase">Visual Target Topology</span>
                    <span class="text-slate-400 text-[11px] ml-2 hidden sm:inline">
                        ({{ visibleNodes.length }} of {{ nodes.length }} nodes mapped)
                    </span>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                <button 
                    @click="filterInternal = !filterInternal"
                    :class="[
                        filterInternal ? 'bg-cyan-950/80 border-cyan-500/50 text-cyan-300' : 'bg-slate-900 border-slate-800 text-slate-500',
                        'px-2.5 py-1 rounded-lg border text-[11px] flex items-center space-x-1.5 transition cartoon-btn shrink-0'
                    ]"
                >
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span>Internal ({{ counts.internal }})</span>
                </button>

                <button 
                    @click="filterOnion = !filterOnion"
                    :class="[
                        filterOnion ? 'bg-purple-950/80 border-purple-500/50 text-purple-300' : 'bg-slate-900 border-slate-800 text-slate-500',
                        'px-2.5 py-1 rounded-lg border text-[11px] flex items-center space-x-1.5 transition cartoon-btn shrink-0'
                    ]"
                >
                    <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                    <span>Onion ({{ counts.onion }})</span>
                </button>

                <button 
                    @click="filterClearnet = !filterClearnet"
                    :class="[
                        filterClearnet ? 'bg-sky-950/80 border-sky-500/50 text-sky-300' : 'bg-slate-900 border-slate-800 text-slate-500',
                        'px-2.5 py-1 rounded-lg border text-[11px] flex items-center space-x-1.5 transition cartoon-btn shrink-0'
                    ]"
                >
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                    <span>Clearnet ({{ counts.clearnet }})</span>
                </button>

                <button 
                    @click="filterEmails = !filterEmails"
                    :class="[
                        filterEmails ? 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300' : 'bg-slate-900 border-slate-800 text-slate-500',
                        'px-2.5 py-1 rounded-lg border text-[11px] flex items-center space-x-1.5 transition cartoon-btn shrink-0'
                    ]"
                >
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Emails ({{ counts.email }})</span>
                </button>

                <button 
                    @click="filterDocuments = !filterDocuments"
                    :class="[
                        filterDocuments ? 'bg-rose-950/80 border-rose-500/50 text-rose-300' : 'bg-slate-900 border-slate-800 text-slate-500',
                        'px-2.5 py-1 rounded-lg border text-[11px] flex items-center space-x-1.5 transition cartoon-btn shrink-0'
                    ]"
                >
                    <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                    <span>Docs ({{ counts.document }})</span>
                </button>
            </div>

            <!-- View Action Controls -->
            <div class="flex items-center space-x-1.5">
                <button 
                    @click="toggleFreeze"
                    :title="isFrozen ? 'Resume Physics' : 'Freeze Nodes in Place'"
                    :class="[
                        isFrozen ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-slate-850 text-slate-300 border-slate-700 hover:text-white',
                        'p-2 rounded-xl border transition cartoon-btn'
                    ]"
                >
                    <Play v-if="isFrozen" class="w-3.5 h-3.5" />
                    <Pause v-else class="w-3.5 h-3.5" />
                </button>

                <button 
                    @click="zoomIn"
                    title="Zoom In"
                    class="p-2 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition cartoon-btn"
                >
                    <ZoomIn class="w-3.5 h-3.5" />
                </button>

                <button 
                    @click="zoomOut"
                    title="Zoom Out"
                    class="p-2 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition cartoon-btn"
                >
                    <ZoomOut class="w-3.5 h-3.5" />
                </button>

                <button 
                    @click="resetView"
                    title="Reset Coordinates"
                    class="p-2 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition cartoon-btn"
                >
                    <RotateCcw class="w-3.5 h-3.5" />
                </button>

                <button 
                    @click="downloadGraphSnapshot"
                    title="Export PNG Diagram"
                    class="p-2 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition cartoon-btn"
                >
                    <Download class="w-3.5 h-3.5" />
                </button>

                <button 
                    @click="toggleFullscreen"
                    :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen Canvas'"
                    class="p-2 rounded-xl bg-slate-850 hover:bg-slate-800 border border-slate-700 text-cyan-400 hover:text-cyan-300 transition cartoon-btn"
                >
                    <Minimize2 v-if="isFullscreen" class="w-3.5 h-3.5" />
                    <Maximize2 v-else class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <!-- Canvas Container -->
        <div class="relative w-full overflow-hidden select-none cursor-grab active:cursor-grabbing" :style="{ height: `${height}px` }">
            <canvas 
                ref="canvasRef"
                class="block w-full h-full"
                @mousedown="handleMouseDown"
                @mousemove="handleMouseMove"
                @mouseup="handleMouseUp"
                @mouseleave="handleMouseUp"
                @wheel="handleWheel"
            ></canvas>

            <!-- Bottom Left Quick Search -->
            <div class="absolute bottom-4 left-4 z-10 w-64">
                <div class="relative">
                    <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search mapped nodes..."
                        class="w-full bg-slate-900/90 backdrop-blur-md border border-slate-700/80 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 font-mono shadow-lg"
                    />
                    <button 
                        v-if="searchQuery" 
                        @click="searchQuery = ''"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                    >
                        <X class="w-3 h-3" />
                    </button>
                </div>
            </div>

            <!-- Bottom Right Legend -->
            <div class="absolute bottom-4 right-4 z-10 hidden sm:flex items-center gap-3 px-3.5 py-2 rounded-xl bg-slate-900/90 backdrop-blur-md border border-slate-800 text-[10px] font-mono shadow-xl">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span class="text-slate-300">Root</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                    <span class="text-slate-300">Internal</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                    <span class="text-slate-300">Onion</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                    <span class="text-slate-300">Clearnet</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <span class="text-slate-300">Email</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                    <span class="text-slate-300">Doc</span>
                </div>
            </div>

            <!-- Node Inspector Floating Card (Selected Node) -->
            <div 
                v-if="selectedNode"
                class="absolute top-4 right-4 z-20 max-w-sm w-full bg-slate-900/95 backdrop-blur-md border border-slate-700/90 rounded-2xl p-4 shadow-2xl space-y-3 cartoon-card font-mono text-xs"
            >
                <div class="flex items-start justify-between gap-2 border-b border-slate-800 pb-2.5">
                    <div class="flex items-center space-x-2">
                        <span 
                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                            :style="{ backgroundColor: COLORS[selectedNode.type]?.fill, color: COLORS[selectedNode.type]?.stroke, border: `1px solid ${COLORS[selectedNode.type]?.stroke}` }"
                        >
                            {{ selectedNode.category }}
                        </span>
                    </div>
                    <button 
                        @click="selectedNode = null"
                        class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-1">
                    <span class="text-[10px] text-slate-500 uppercase block font-bold">Target Value</span>
                    <div class="text-white text-xs break-all bg-slate-950 p-2 rounded-xl border border-slate-800 select-all">
                        {{ selectedNode.fullValue }}
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button 
                        @click="copyValue(selectedNode.fullValue)"
                        class="flex-1 py-1.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 hover:text-white flex items-center justify-center space-x-1.5 transition cartoon-btn text-[11px]"
                    >
                        <Check v-if="copiedText === selectedNode.fullValue" class="w-3.5 h-3.5 text-emerald-400" />
                        <Copy v-else class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ copiedText === selectedNode.fullValue ? 'Copied!' : 'Copy Value' }}</span>
                    </button>

                    <!-- If it's a URL or onion, offer deep crawl or external open -->
                    <button 
                        v-if="['internal', 'onion', 'clearnet'].includes(selectedNode.type)"
                        @click="triggerDeepCrawl(selectedNode.fullValue)"
                        class="flex-1 py-1.5 px-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold flex items-center justify-center space-x-1.5 transition cartoon-btn text-[11px]"
                    >
                        <Globe class="w-3.5 h-3.5" />
                        <span>Deep Crawl</span>
                    </button>

                    <a 
                        v-if="selectedNode.type === 'document' && selectedNode.meta?.url"
                        :href="selectedNode.meta.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex-1 py-1.5 px-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold flex items-center justify-center space-x-1.5 transition cartoon-btn text-[11px]"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

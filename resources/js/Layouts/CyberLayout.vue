<script>
import { ref } from 'vue';

// Module-level persistent state across Inertia page switches
const globalTorStatus = ref({
    is_tor: false,
    ip: 'Clearnet / Standby',
    status: 'disconnected',
    proxy: 'socks5h://127.0.0.1:9050'
});

let savedNavScrollLeft = null;

let torStatusFetchedAt = 0;
let isTorFetching = false;

async function checkGlobalTorStatus(force = false) {
    const now = Date.now();
    // Prevent refetching on every page mount; cache for 30 seconds
    if (!force && (now - torStatusFetchedAt < 30000 || isTorFetching)) {
        return;
    }
    isTorFetching = true;
    try {
        const res = await fetch('/api/tor-status');
        const data = await res.json();
        globalTorStatus.value = data;
        torStatusFetchedAt = Date.now();
    } catch (e) {
        globalTorStatus.value = {
            is_tor: false,
            ip: 'Offline / Clearnet',
            status: 'disconnected',
            proxy: 'socks5h://127.0.0.1:9050'
        };
    } finally {
        isTorFetching = false;
    }
}
</script>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Search, 
    Terminal, 
    FolderGit2, 
    Globe, 
    Cpu, 
    Compass, 
    ListFilter, 
    ChevronDown, 
    ChevronLeft, 
    ChevronRight, 
    ExternalLink, 
    Download, 
    GripVertical, 
    GripHorizontal, 
    Move, 
    Check, 
    X, 
    Activity, 
    ArrowRight, 
    Loader2, 
    Play, 
    Square, 
    LogOut, 
    User, 
    Radio, 
    Bell, 
    Server 
} from 'lucide-vue-next';

const showBanner = ref(false);
const showSearchModal = ref(false);
const spotlightQuery = ref('');
const burgerMenuOpen = ref(false);
const showUserMenu = ref(false);
const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Horizontal header navigation scroll with infinite virtual loop & acceleration-based momentum physics
const headerNavItems = [
    { id: 'dashboard', label: 'Dashboard', url: '/', icon: Cpu, iconColor: 'text-amber-400', title: 'Dashboard Hub' },
    { id: 'search', label: 'Multi-Engine', url: '/search', icon: Search, iconColor: 'text-amber-400', title: 'Multi-Engine Deep Web Search' },
    { id: 'scraper', label: 'Deep Scraper', url: '/scraper', icon: Globe, iconColor: 'text-cyan-400', title: 'Forensic Deep Scraper' },
    { id: 'social-recon', label: 'Social Recon', url: '/social-recon', icon: Radio, iconColor: 'text-emerald-400', title: 'Social Recon & Persona Matrix' },
    { id: 'infra-recon', label: 'Infra Recon', url: '/infra-recon', icon: Server, iconColor: 'text-sky-400', title: 'Infrastructure & DNS OSINT' },
    { id: 'osint', label: 'OSINT Hub', url: '/osint', icon: Compass, iconColor: 'text-rose-400', title: 'OSINT Framework Hub & Username Lookup' },
    { id: 'monitors', label: 'Monitors', url: '/monitors', icon: Bell, iconColor: 'text-rose-400', title: 'Automated Recon Monitors & Alerts' },
    { id: 'investigations', label: 'Case Dossiers', url: '/investigations', icon: FolderGit2, iconColor: 'text-indigo-400', title: 'Investigation Case Dossiers' },
    { id: 'queries', label: 'Query Registry', url: '/queries', icon: ListFilter, iconColor: 'text-amber-400', title: 'Investigation Query Registry' },
];

const infiniteNavSets = [0, 1, 2];

const NAV_KINETIC_CONFIG = {
    baseImpulse: 14,          // Base px/frame impulse on a single wheel tick (~120px total displacement)
    flickThresholdMs: 250,    // Rapid successive flicks window (ms)
    flickIncrement: 0.35,     // Acceleration multiplier increase per rapid tick (+35% speed per flick)
    maxMultiplier: 3.6,       // Cap on acceleration multiplier to prevent extreme overshoot
    friction: 0.89,           // Deceleration decay factor per 16.67ms frame
    minVelocity: 0.25,        // Threshold below which animation rests
};

const navScrollRef = ref(null);
const canScrollNavLeft = ref(true);
const canScrollNavRight = ref(true);
const navVelocity = ref(0);
const isNavKineticActive = ref(false);

let navResizeObserver = null;
let navRafId = null;
let lastNavFrameTime = 0;
let lastNavWheelTime = 0;
let navConsecutiveFlicks = 0;

const getSingleSetWidth = () => {
    if (!navScrollRef.value) return 0;
    const nav = navScrollRef.value;
    const item0 = nav.querySelector('[data-item-key="0-dashboard"]');
    const item1 = nav.querySelector('[data-item-key="1-dashboard"]');
    if (item0 && item1) {
        const diff = item1.offsetLeft - item0.offsetLeft;
        if (diff > 50) return diff;
    }
    return Math.max(0, nav.scrollWidth / 3);
};

const normalizeInfiniteScroll = () => {
    if (!navScrollRef.value) return;
    const el = navScrollRef.value;
    const setWidth = getSingleSetWidth();
    if (setWidth <= 20) return;

    // Center set is Set 1 (from setWidth to 2 * setWidth)
    if (el.scrollLeft >= 2 * setWidth) {
        el.scrollLeft -= setWidth;
    } else if (el.scrollLeft < setWidth) {
        el.scrollLeft += setWidth;
    }
};

const stopNavKineticLoop = () => {
    if (navRafId) {
        cancelAnimationFrame(navRafId);
        navRafId = null;
    }
    navVelocity.value = 0;
    isNavKineticActive.value = false;
};

const stepNavKinetic = (now) => {
    if (!navScrollRef.value) {
        stopNavKineticLoop();
        return;
    }

    const elapsed = Math.min(50, now - lastNavFrameTime);
    lastNavFrameTime = now;
    const frameRatio = elapsed / 16.67;

    const el = navScrollRef.value;

    // Apply displacement
    const step = navVelocity.value * frameRatio;
    el.scrollLeft += step;

    // Seamless Infinite Loop Wrapping
    normalizeInfiniteScroll();

    // Apply friction decay
    navVelocity.value *= Math.pow(NAV_KINETIC_CONFIG.friction, frameRatio);
    updateNavScrollState();

    // Stop if velocity drops below minimum threshold
    if (Math.abs(navVelocity.value) < NAV_KINETIC_CONFIG.minVelocity) {
        stopNavKineticLoop();
    } else {
        navRafId = requestAnimationFrame(stepNavKinetic);
    }
};

const startNavKineticLoop = () => {
    if (isNavKineticActive.value && navRafId) return;
    isNavKineticActive.value = true;
    lastNavFrameTime = performance.now();
    navRafId = requestAnimationFrame(stepNavKinetic);
};

const updateNavScrollState = () => {
    if (!navScrollRef.value) return;
    const el = navScrollRef.value;
    normalizeInfiniteScroll();

    // Both directions always available in infinite loop
    canScrollNavLeft.value = true;
    canScrollNavRight.value = true;
    savedNavScrollLeft = el.scrollLeft;

    try {
        const setWidth = getSingleSetWidth();
        const canonical = setWidth > 0 ? (el.scrollLeft % setWidth) : el.scrollLeft;
        sessionStorage.setItem('darkdump_header_scroll_left', String(canonical));
    } catch (e) {}
};

const restoreAndFocusNavScroll = (smooth = false) => {
    if (!navScrollRef.value) return;
    stopNavKineticLoop();

    const nav = navScrollRef.value;
    const setWidth = getSingleSetWidth();

    // 1. Restore exact scroll offset into center set (Set 1)
    let targetScroll = savedNavScrollLeft;
    if (targetScroll === null) {
        try {
            const stored = sessionStorage.getItem('darkdump_header_scroll_left');
            if (stored !== null && !isNaN(parseFloat(stored))) {
                targetScroll = setWidth > 0 ? (setWidth + (parseFloat(stored) % setWidth)) : parseFloat(stored);
            }
        } catch (e) {}
    }

    if (targetScroll !== null && !isNaN(targetScroll) && targetScroll > 0) {
        nav.scrollLeft = targetScroll;
    } else if (setWidth > 0) {
        nav.scrollLeft = setWidth;
    }

    // 2. Auto-focus active module tab closest to visible viewport center
    const currentUrl = page.url;
    const matchingLinks = Array.from(nav.querySelectorAll('a[data-item-key]')).filter(a => {
        const href = a.getAttribute('href');
        return href === currentUrl || (href && href !== '/' && currentUrl.startsWith(href));
    });

    if (matchingLinks.length > 0) {
        const navCenter = nav.scrollLeft + (nav.clientWidth / 2);
        let closestLink = matchingLinks[0];
        let minDiff = Infinity;

        matchingLinks.forEach(link => {
            const linkCenter = link.offsetLeft + (link.offsetWidth / 2);
            const diff = Math.abs(linkCenter - navCenter);
            if (diff < minDiff) {
                minDiff = diff;
                closestLink = link;
            }
        });

        const linkLeft = closestLink.offsetLeft;
        const linkWidth = closestLink.offsetWidth || closestLink.clientWidth;
        const navWidth = nav.clientWidth;

        const isVisible = (linkLeft >= nav.scrollLeft + 6) && 
                          (linkLeft + linkWidth <= nav.scrollLeft + navWidth - 6);

        if (!isVisible) {
            const ideal = linkLeft - (navWidth / 2) + (linkWidth / 2);
            if (smooth) {
                nav.scrollTo({ left: ideal, behavior: 'smooth' });
            } else {
                nav.scrollLeft = ideal;
            }
        }
    }

    updateNavScrollState();
};

const triggerNavScrollSync = (smooth = false) => {
    nextTick(() => {
        restoreAndFocusNavScroll(smooth);
        requestAnimationFrame(() => {
            setTimeout(() => {
                restoreAndFocusNavScroll(false);
            }, 60);
        });
    });
};

const handleNavWheel = (event) => {
    if (!navScrollRef.value) return;
    event.preventDefault();
    event.stopPropagation();

    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
    if (Math.abs(delta) < 0.5) return;

    const now = performance.now();
    const timeSinceLast = now - lastNavWheelTime;
    lastNavWheelTime = now;

    // Directional Braking: If user reverses wheel scroll while moving, instantly brake and zero out velocity
    if ((navVelocity.value > 0.5 && delta < 0) || (navVelocity.value < -0.5 && delta > 0)) {
        navVelocity.value = 0;
        navConsecutiveFlicks = 0;
    }

    // Acceleration Ramp: Rapid consecutive flicks accumulate velocity multiplier
    const isRapid = timeSinceLast < NAV_KINETIC_CONFIG.flickThresholdMs;
    navConsecutiveFlicks = isRapid ? Math.min(6, navConsecutiveFlicks + 1) : 0;
    const multiplier = Math.min(
        NAV_KINETIC_CONFIG.maxMultiplier,
        1 + navConsecutiveFlicks * NAV_KINETIC_CONFIG.flickIncrement
    );

    // Add kinetic impulse to current velocity
    const impulse = Math.sign(delta) * NAV_KINETIC_CONFIG.baseImpulse * multiplier;
    navVelocity.value += impulse;

    startNavKineticLoop();
};

const scrollNav = (direction) => {
    const sign = direction === 'left' ? -1 : 1;
    navVelocity.value = sign * 18;
    startNavKineticLoop();
};

// Subtle visual acceleration skew feedback
const navSkewTransform = computed(() => {
    if (!isNavKineticActive.value) return 'none';
    const vel = navVelocity.value;
    if (Math.abs(vel) < 0.4) return 'none';
    const angle = Math.max(-1.8, Math.min(1.8, vel * -0.09));
    return `skewX(${angle.toFixed(2)}deg)`;
});

const handleLogout = () => {
    router.post('/logout');
};

const torStatus = globalTorStatus;
const isTorStarting = ref(false);
const isTorStopping = ref(false);
const showTorMenu = ref(false);
const torNotification = ref(null);

const startTorDaemon = async () => {
    if (isTorStarting.value || isTorStopping.value) return;
    isTorStarting.value = true;
    showTorMenu.value = false;

    globalTorStatus.value = {
        ...globalTorStatus.value,
        status: 'starting',
        ip: 'Bootstrapping...',
    };

    torNotification.value = {
        type: 'info',
        message: 'Launching Tor SOCKS5 proxy daemon on 127.0.0.1:9050...'
    };

    try {
        const res = await axios.post('/api/tor/start');
        const data = res.data;
        if (data.status) {
            globalTorStatus.value = data.status;
        }
        torNotification.value = {
            type: data.success ? 'success' : 'error',
            message: data.message || (data.success ? 'Tor daemon started successfully!' : 'Tor launch failed.')
        };
        setTimeout(() => {
            if (torNotification.value?.message === data.message) {
                torNotification.value = null;
            }
        }, 5000);
    } catch (err) {
        torNotification.value = {
            type: 'error',
            message: err?.response?.data?.message || 'Failed to start Tor daemon.'
        };
        setTimeout(() => {
            torNotification.value = null;
        }, 5000);
        await checkGlobalTorStatus(true);
    } finally {
        isTorStarting.value = false;
    }
};

const stopTorDaemon = async () => {
    if (isTorStopping.value || isTorStarting.value) return;
    isTorStopping.value = true;
    showTorMenu.value = false;

    torNotification.value = {
        type: 'info',
        message: 'Terminating Tor daemon process...'
    };

    try {
        const res = await axios.post('/api/tor/stop');
        const data = res.data;
        if (data.status) {
            globalTorStatus.value = data.status;
        }
        torNotification.value = {
            type: 'info',
            message: data.message || 'Tor daemon stopped. Operating in Clearnet mode.'
        };
        setTimeout(() => {
            torNotification.value = null;
        }, 5000);
    } catch (err) {
        torNotification.value = {
            type: 'error',
            message: 'Failed to stop Tor process.'
        };
        setTimeout(() => {
            torNotification.value = null;
        }, 5000);
    } finally {
        isTorStopping.value = false;
    }
};

const handleTorProxyClick = () => {
    if (isTorStarting.value || isTorStopping.value) return;

    if (!torStatus.value.is_tor) {
        startTorDaemon();
    } else {
        showTorMenu.value = !showTorMenu.value;
    }
};

// Dock Position State: 'top' | 'bottom' | 'left' | 'right'
const dockPosition = ref('top');

// Dragging State
const isDragging = ref(false);
const dragHoverZone = ref(null); // 'top' | 'bottom' | 'left' | 'right' | null
const dragStartX = ref(0);
const dragStartY = ref(0);
const hasMoved = ref(false);
const showDockMenu = ref(false);

const isHorizontal = computed(() => dockPosition.value === 'top' || dockPosition.value === 'bottom');
const isVertical = computed(() => dockPosition.value === 'left' || dockPosition.value === 'right');

// Quick Navigation Modules for Liquid FAB Menu & Spotlight
const navModules = [
    { id: 'dashboard', label: 'Dashboard Hub', url: '/', icon: '⚡', badge: 'Overview', desc: 'Central metrics, recent queries, and system status' },
    { id: 'search', label: 'Multi-Engine', url: '/search', icon: '🔍', badge: 'Live SSE', desc: 'Stream darknet results via Ahmia, DuckDuckGo & TorDex' },
    { id: 'scraper', label: 'Deep Scraper', url: '/scraper', icon: '🌐', badge: 'Forensic', desc: 'Harvest emails, documents, topology & image galleries' },
    { id: 'social-recon', label: 'Social Recon', url: '/social-recon', icon: '📡', badge: 'Persona', desc: 'Username footprinting, Reddit comments, Telegram channels & social dorks' },
    { id: 'infra-recon', label: 'Infra Recon', url: '/infra-recon', icon: '🌐', badge: 'Network', desc: 'DNS records, SSL cert transparency, RDAP/WHOIS & GeoIP' },
    { id: 'osint', label: 'OSINT Hub', url: '/osint', icon: '🎯', badge: 'Framework', desc: 'OSINT Framework directory, username reconnaissance, and alias footprinting' },
    { id: 'monitors', label: 'Recon Monitors', url: '/monitors', icon: '🚨', badge: 'Alerts', desc: 'Automated periodic sweeps, target tracking & threat notifications' },
    { id: 'investigations', label: 'Case Dossiers', url: '/investigations', icon: '📁', badge: 'Cases', desc: 'Persistent case files, notes, and tagged findings' },
    { id: 'queries', label: 'Query Registry', url: '/queries', icon: '📋', badge: 'History', desc: 'Central log of darknet and clearnet search queries' },
];

// Cyber Threat Alerts State
const showAlertCenter = ref(false);
const unreadAlertsCount = ref(0);
const recentAlertsList = ref([]);
let alertsInterval = null;

const fetchAlerts = async () => {
    try {
        const res = await axios.get('/api/alerts');
        unreadAlertsCount.value = res.data.unread_count || 0;
        recentAlertsList.value = res.data.alerts || [];
    } catch (e) {
        // silent background failure
    }
};

const toggleAlertCenter = () => {
    showAlertCenter.value = !showAlertCenter.value;
    if (showAlertCenter.value) {
        fetchAlerts();
    }
};

const markAlertAsRead = async (id) => {
    try {
        await axios.post(`/api/alerts/${id}/read`);
        const a = recentAlertsList.value.find(item => item.id === id);
        if (a && !a.is_read) {
            a.is_read = true;
            unreadAlertsCount.value = Math.max(0, unreadAlertsCount.value - 1);
        }
    } catch (e) {}
};

const markAllAsRead = async () => {
    try {
        await axios.post('/api/alerts/mark-all-read');
        recentAlertsList.value.forEach(a => a.is_read = true);
        unreadAlertsCount.value = 0;
    } catch (e) {}
};

const filteredSpotlightModules = computed(() => {
    if (!spotlightQuery.value.trim()) return navModules;
    const q = spotlightQuery.value.toLowerCase();
    return navModules.filter(m => m.label.toLowerCase().includes(q) || m.desc.toLowerCase().includes(q) || m.badge.toLowerCase().includes(q));
});

const executeSpotlightSearch = () => {
    if (!spotlightQuery.value.trim()) return;
    showSearchModal.value = false;
    router.visit(`/search?q=${encodeURIComponent(spotlightQuery.value)}`);
};

const handleSpotlightSelect = (url) => {
    showSearchModal.value = false;
    router.visit(url);
};

const fetchTorStatus = () => {
    checkGlobalTorStatus(true);
};

// Global Keyboard Shortcut (Ctrl+K)
const onKeyDown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        showSearchModal.value = !showSearchModal.value;
    } else if (e.key === 'Escape') {
        showSearchModal.value = false;
        burgerMenuOpen.value = false;
        showTorMenu.value = false;
        showAlertCenter.value = false;
    }
};

// Prevent native HTML5 text-drag-and-drop on highlighted text.
const onDragStart = (e) => {
    if (!e.target.closest?.('[draggable="true"]')) {
        e.preventDefault();
    }
};

onMounted(() => {
    checkGlobalTorStatus(false);
    fetchAlerts();
    alertsInterval = setInterval(fetchAlerts, 45000);

    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('dragstart', onDragStart);
    window.addEventListener('resize', updateNavScrollState, { passive: true });

    triggerNavScrollSync(false);

    if (window.ResizeObserver && navScrollRef.value) {
        navResizeObserver = new ResizeObserver(() => {
            updateNavScrollState();
        });
        navResizeObserver.observe(navScrollRef.value);
    }

    // Restore saved dock position
    const saved = localStorage.getItem('darkdump_header_dock');
    if (saved && ['top', 'bottom', 'left', 'right'].includes(saved)) {
        dockPosition.value = saved;
    }
});

onUnmounted(() => {
    stopNavKineticLoop();
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('dragstart', onDragStart);
    window.removeEventListener('resize', updateNavScrollState);
    if (navResizeObserver) {
        navResizeObserver.disconnect();
        navResizeObserver = null;
    }
    if (alertsInterval) {
        clearInterval(alertsInterval);
    }
});

watch(() => page.url, () => {
    triggerNavScrollSync(true);
});

// Dragging Logic
const onHeaderMouseDown = (e) => {
    if (e.target.closest('button, a, input, select, textarea, .no-drag')) {
        return;
    }

    dragStartX.value = e.clientX;
    dragStartY.value = e.clientY;
    hasMoved.value = false;

    window.addEventListener('mousemove', onHeaderMouseMove);
    window.addEventListener('mouseup', onHeaderMouseUp);
};

const onHeaderMouseMove = (e) => {
    const dx = e.clientX - dragStartX.value;
    const dy = e.clientY - dragStartY.value;

    if (!hasMoved.value && Math.hypot(dx, dy) > 6) {
        hasMoved.value = true;
        isDragging.value = true;
        document.body.style.userSelect = 'none';
    }

    if (isDragging.value) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const dTop = e.clientY;
        const dBottom = h - e.clientY;
        const dLeft = e.clientX;
        const dRight = w - e.clientX;

        const min = Math.min(dTop, dBottom, dLeft, dRight);
        if (min === dTop) {
            dragHoverZone.value = 'top';
        } else if (min === dBottom) {
            dragHoverZone.value = 'bottom';
        } else if (min === dLeft) {
            dragHoverZone.value = 'left';
        } else {
            dragHoverZone.value = 'right';
        }
    }
};

const onHeaderMouseUp = () => {
    window.removeEventListener('mousemove', onHeaderMouseMove);
    window.removeEventListener('mouseup', onHeaderMouseUp);
    document.body.style.userSelect = '';

    if (isDragging.value && dragHoverZone.value) {
        setDockPosition(dragHoverZone.value);
    }

    isDragging.value = false;
    dragHoverZone.value = null;
    hasMoved.value = false;
};

const setDockPosition = (pos) => {
    dockPosition.value = pos;
    localStorage.setItem('darkdump_header_dock', pos);
    showDockMenu.value = false;
};

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('dragstart', onDragStart);
    window.removeEventListener('mousemove', onHeaderMouseMove);
    window.removeEventListener('mouseup', onHeaderMouseUp);
});
</script>

<template>
    <div 
        :class="[
            'min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-amber-500 selection:text-slate-950 relative',
            isVertical ? (dockPosition === 'left' ? 'flex flex-row' : 'flex flex-row-reverse') : 'flex flex-col'
        ]"
    >
        <!-- Ambient Chart Grid overlay -->
        <div class="fixed inset-0 chart-grid pointer-events-none z-0 opacity-40"></div>

        <!-- Header navigation dock -->
        <header 
            :class="[
                'bg-slate-900/90 border-slate-800 backdrop-blur-md z-40 transition-all duration-200 select-none relative',
                // Horizontal styles
                isHorizontal ? 'w-full sticky z-40 px-4 sm:px-6 lg:px-8' : '',
                dockPosition === 'top' ? 'top-0 border-b shadow-lg shadow-black/40' : '',
                dockPosition === 'bottom' ? 'bottom-0 border-t order-last shadow-[0_-8px_25px_rgba(0,0,0,0.6)]' : '',
                
                // Vertical styles (Left or Right Taskbar)
                isVertical ? 'w-64 h-screen sticky top-0 flex flex-col justify-between p-4 overflow-y-auto shrink-0 shadow-2xl no-scrollbar' : '',
                dockPosition === 'left' ? 'border-r' : '',
                dockPosition === 'right' ? 'border-l' : '',

                // Drag state
                isDragging ? 'opacity-85 ring-2 ring-amber-400 cursor-grabbing' : ''
            ]"
        >
            <!-- ------------------------------------------------------------- -->
            <!-- HORIZONTAL LAYOUT (TOP / BOTTOM) - OCCUPIES ENTIRE HEADER WIDTH -->
            <!-- ------------------------------------------------------------- -->
            <div v-if="isHorizontal" class="w-full min-h-[3.75rem] flex items-center justify-between gap-2 sm:gap-3 lg:gap-4 py-2">
                <!-- Left: Drag Grip Handle + Brand Logo -->
                <div class="flex items-center space-x-2.5 sm:space-x-3 shrink-0">
                    <!-- Grip Handle -->
                    <div 
                        @mousedown="onHeaderMouseDown"
                        class="hidden sm:flex p-1.5 rounded-lg bg-slate-950/80 border border-slate-800 text-amber-500/70 hover:text-amber-400 hover:border-amber-500/40 transition cursor-grab active:cursor-grabbing items-center justify-center group shrink-0"
                        title="Hold click & drag to any screen edge (Top, Bottom, Left, Right) to dock"
                    >
                        <GripVertical class="w-3.5 h-3.5 group-hover:scale-110 transition-transform pointer-events-none" />
                    </div>

                    <!-- Brand Lockup in DigitalManagement style (Full nowrap) -->
                    <Link href="/" class="flex items-center space-x-2 sm:space-x-3 group text-left cursor-pointer focus:outline-none cartoon-pill shrink-0 whitespace-nowrap">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-slate-900 border border-amber-500/40 flex items-center justify-center group-hover:scale-105 group-hover:border-amber-400 group-hover:shadow-[0_0_20px_rgba(245,158,11,0.25)] transition-all shrink-0">
                            <Terminal class="w-4 h-4 sm:w-5 sm:h-5 text-amber-400 group-hover:rotate-6 transition-transform" />
                        </div>
                        <div class="whitespace-nowrap flex flex-col justify-center">
                            <div class="font-serif font-bold text-sm sm:text-base tracking-wider text-amber-400 uppercase leading-snug group-hover:text-amber-300 transition-colors flex items-center gap-1.5 whitespace-nowrap">
                                <span>Darkdump</span>
                                <span class="hidden md:inline text-amber-400/80 font-mono text-[10px] font-semibold tracking-wider shrink-0">OSINT v5</span>
                            </div>
                            <div class="text-[10px] font-mono text-slate-400 tracking-wider uppercase whitespace-nowrap leading-tight hidden xl:block">
                                Deep Web Intelligence • Reconnaissance
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Center: Navigation Links with horizontal wheel scrolling & tiny indicator arrows -->
                <div class="hidden lg:flex items-center min-w-0 max-w-full shrink py-1">
                    <!-- Left Arrow Indicator Button -->
                    <button
                        v-show="canScrollNavLeft"
                        @click="scrollNav('left')"
                        type="button"
                        class="w-5 h-5 rounded-full bg-slate-950 border border-amber-500/50 text-amber-400 hover:text-amber-300 hover:border-amber-300 shadow-[0_0_8px_rgba(0,0,0,0.8)] flex items-center justify-center transition-all cursor-pointer shrink-0 mr-1.5 focus:outline-none focus:ring-1 focus:ring-amber-400/50 active:scale-90"
                        title="Scroll navigation left"
                        aria-label="Scroll navigation left"
                    >
                        <ChevronLeft class="w-3.5 h-3.5" />
                    </button>

                    <!-- OSINT Modules Nav Bar (Seamless Infinite Looping Ribbon) -->
                    <nav 
                        ref="navScrollRef"
                        @wheel.prevent.stop="handleNavWheel"
                        @scroll.passive="updateNavScrollState"
                        class="flex items-center space-x-1.5 font-mono text-xs overflow-x-auto no-scrollbar shrink min-w-0 max-w-full py-1 relative select-none"
                        :style="{ transform: navSkewTransform, transition: isNavKineticActive ? 'none' : 'transform 0.22s ease-out' }"
                        title="OSINT Modules Navigation (Scroll to pan)"
                    >
                        <!-- Repeated Virtual Sets (0, 1, 2) for Seamless Infinite Circular Looping -->
                        <template v-for="setIdx in infiniteNavSets" :key="setIdx">
                            <Link 
                                v-for="item in headerNavItems"
                                :key="`${setIdx}-${item.id}`"
                                :data-item-key="`${setIdx}-${item.id}`"
                                :href="item.url" 
                                :class="[
                                    (item.url === '/' ? page.url === '/' : page.url.startsWith(item.url))
                                        ? 'text-amber-300 bg-amber-500/15 border-amber-500/40 font-bold shadow-sm shadow-amber-500/20' 
                                        : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 border-transparent',
                                    'px-2.5 2xl:px-3 py-1.5 rounded-xl border transition-all flex items-center space-x-1.5 cartoon-btn whitespace-nowrap shrink-0'
                                ]"
                                :title="item.title"
                            >
                                <component :is="item.icon" class="w-3.5 h-3.5 shrink-0" :class="item.iconColor" />
                                <span class="whitespace-nowrap">{{ item.label }}</span>
                            </Link>
                        </template>
                    </nav>

                    <!-- Right Arrow Indicator Button -->
                    <button
                        v-show="canScrollNavRight"
                        @click="scrollNav('right')"
                        type="button"
                        class="w-5 h-5 rounded-full bg-slate-950 border border-amber-500/60 text-amber-400 hover:text-amber-300 hover:border-amber-300 shadow-[0_0_10px_rgba(245,158,11,0.3)] flex items-center justify-center transition-all cursor-pointer shrink-0 ml-1.5 animate-pulse focus:outline-none focus:ring-1 focus:ring-amber-400/50 active:scale-90"
                        title="Scroll navigation right"
                        aria-label="Scroll navigation right"
                    >
                        <ChevronRight class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Right Side: Spotlight Search Button + Alerts Bell + Tor Badge + Quick Dock + Liquid FAB Burger Menu -->
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <!-- SPOTLIGHT SEARCHBAR (Ctrl+K) - Exact DigitalManagement style -->
                    <button
                        @click="showSearchModal = true"
                        type="button"
                        class="flex items-center gap-1.5 p-2 2xl:px-3 2xl:py-1.5 rounded-xl bg-slate-950/90 border border-slate-800 hover:border-amber-500/60 text-slate-400 hover:text-slate-200 text-xs transition-all shadow-inner group cursor-pointer focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-500/30 cartoon-btn shrink-0"
                        title="Search OSINT tools and modules (Ctrl+K)"
                    >
                        <Search class="w-3.5 h-3.5 text-slate-500 group-hover:text-amber-400 transition-colors shrink-0" />
                        <span class="hidden 2xl:inline text-left truncate text-slate-400 group-hover:text-slate-300 max-w-[8rem]">Quick Command...</span>
                        <kbd class="hidden 2xl:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-mono font-bold text-slate-400 bg-slate-900 border border-slate-700/80 rounded shrink-0">
                            <span>Ctrl</span><span>K</span>
                        </kbd>
                    </button>

                    <!-- CYBER THREAT ALERTS BELL -->
                    <div class="relative shrink-0">
                        <div
                            v-if="showAlertCenter"
                            class="fixed inset-0 z-40 bg-transparent cursor-default"
                            @click.stop="showAlertCenter = false"
                        ></div>

                        <button
                            @click="toggleAlertCenter"
                            type="button"
                            class="relative p-2 2xl:px-2.5 2xl:py-1.5 rounded-xl bg-slate-950/90 border border-slate-800 hover:border-rose-500/60 text-slate-300 hover:text-white text-xs transition-all cartoon-btn cursor-pointer shrink-0 z-50 flex items-center space-x-1"
                            title="Cyber Threat Alert Center"
                        >
                            <Bell class="w-3.5 h-3.5 text-rose-400 shrink-0" />
                            <span 
                                v-if="unreadAlertsCount > 0"
                                class="px-1.5 py-0.2 rounded-full bg-rose-600 text-white text-[9px] font-mono font-bold animate-pulse"
                            >
                                {{ unreadAlertsCount > 9 ? '9+' : unreadAlertsCount }}
                            </span>
                        </button>

                        <!-- Threat Alert Center Popover -->
                        <div
                            v-if="showAlertCenter"
                            class="absolute right-0 mt-2 w-80 sm:w-96 bg-slate-950/95 border border-slate-800 rounded-2xl shadow-2xl p-4 z-50 font-mono text-xs cartoon-modal backdrop-blur-md space-y-3"
                        >
                            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                    <h4 class="font-serif font-bold text-white text-xs uppercase tracking-wider">THREAT ALERTS</h4>
                                    <span v-if="unreadAlertsCount > 0" class="px-1.5 py-0.2 rounded bg-rose-950 border border-rose-500 text-rose-300 text-[10px] font-bold">
                                        {{ unreadAlertsCount }} NEW
                                    </span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button 
                                        v-if="unreadAlertsCount > 0"
                                        @click="markAllAsRead"
                                        class="text-[10px] text-slate-400 hover:text-cyan-400 transition cursor-pointer"
                                    >
                                        Mark all read
                                    </button>
                                    <Link 
                                        href="/monitors" 
                                        @click="showAlertCenter = false"
                                        class="text-[10px] text-amber-400 hover:underline cursor-pointer"
                                    >
                                        View All
                                    </Link>
                                </div>
                            </div>

                            <div v-if="recentAlertsList.length > 0" class="space-y-2 max-h-80 overflow-y-auto pr-1">
                                <div 
                                    v-for="alert in recentAlertsList.slice(0, 10)" 
                                    :key="alert.id"
                                    class="p-2.5 rounded-xl border transition flex flex-col space-y-1.5"
                                    :class="alert.is_read ? 'bg-slate-900/40 border-slate-800/60 opacity-75' : 'bg-slate-900/90 border-rose-500/40 shadow-sm shadow-rose-500/10'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <span 
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase"
                                            :class="[
                                                alert.severity === 'critical' ? 'bg-red-950 text-red-300 border border-red-500' :
                                                alert.severity === 'high' ? 'bg-orange-950 text-orange-300 border border-orange-500' :
                                                alert.severity === 'medium' ? 'bg-sky-950 text-sky-300 border border-sky-500' :
                                                'bg-slate-800 text-slate-400'
                                            ]"
                                        >
                                            {{ alert.severity }}
                                        </span>
                                        <span class="text-[9px] text-slate-500">{{ new Date(alert.created_at).toLocaleTimeString() }}</span>
                                    </div>
                                    <div class="font-bold text-white text-[11px] leading-tight">{{ alert.title }}</div>
                                    <p class="text-[10px] text-slate-400 font-sans line-clamp-2">{{ alert.summary }}</p>
                                    <div class="flex items-center justify-between pt-1 border-t border-slate-800/60 text-[10px]">
                                        <a 
                                            v-if="alert.external_url" 
                                            :href="alert.external_url" 
                                            target="_blank" 
                                            class="text-cyan-400 hover:underline flex items-center space-x-1"
                                        >
                                            <span>Inspect Target</span>
                                            <ExternalLink class="w-2.5 h-2.5" />
                                        </a>
                                        <button 
                                            v-if="!alert.is_read"
                                            @click="markAlertAsRead(alert.id)"
                                            class="text-slate-400 hover:text-emerald-400 transition cursor-pointer"
                                        >
                                            Mark Read
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-6 text-slate-500 text-xs font-mono">
                                No active threat alerts triggered.
                            </div>
                        </div>
                    </div>

                    <!-- Tor Proxy Status & Control Badge -->
                    <div class="relative shrink-0">
                        <div
                            v-if="showTorMenu"
                            class="fixed inset-0 z-40 bg-transparent cursor-default"
                            @click.stop="showTorMenu = false"
                        ></div>

                        <button 
                            @click="handleTorProxyClick"
                            :title="isTorStarting ? 'Starting Tor SOCKS5 daemon...' : torStatus.is_tor ? 'Tor Active (Click for controls)' : 'Click to Launch Tor SOCKS5 Daemon'" 
                            :disabled="isTorStarting || isTorStopping"
                            class="flex items-center space-x-1.5 text-xs font-mono p-2 2xl:px-3 2xl:py-1.5 rounded-xl border transition-all cartoon-btn relative z-50 cursor-pointer shrink-0"
                            :class="[
                                isTorStarting 
                                    ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 animate-pulse cursor-wait' 
                                    : isTorStopping
                                        ? 'bg-rose-500/15 border-rose-500/60 text-rose-300 animate-pulse cursor-wait'
                                        : torStatus.is_tor 
                                            ? 'bg-slate-950/90 border-emerald-500/40 text-emerald-300 hover:border-emerald-400 shadow-sm shadow-emerald-500/20' 
                                            : 'bg-slate-950/90 border-amber-500/30 text-amber-300 hover:border-amber-400 hover:bg-amber-500/10 shadow-sm shadow-amber-500/20'
                            ]"
                        >
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span v-if="torStatus.is_tor || isTorStarting" class="animate-ping absolute inline-flex h-full w-full rounded-full" :class="isTorStarting ? 'bg-amber-400 opacity-75' : 'bg-emerald-400 opacity-75'"></span>
                                <span :class="isTorStarting ? 'bg-amber-400' : isTorStopping ? 'bg-rose-400' : torStatus.is_tor ? 'bg-emerald-400' : 'bg-amber-400'" class="relative inline-flex rounded-full h-2 w-2"></span>
                            </span>

                            <Loader2 v-if="isTorStarting || isTorStopping" class="w-3 h-3 animate-spin shrink-0" :class="isTorStarting ? 'text-amber-400' : 'text-rose-400'" />
                            <Play v-else-if="!torStatus.is_tor" class="w-3 h-3 text-amber-400 shrink-0" />

                            <span class="hidden 2xl:inline font-semibold text-[11px] whitespace-nowrap">
                                <template v-if="isTorStarting">STARTING TOR...</template>
                                <template v-else-if="isTorStopping">STOPPING...</template>
                                <template v-else-if="torStatus.is_tor">TOR ACTIVE</template>
                                <template v-else>START TOR</template>
                            </span>
                            <span class="text-[10px] opacity-75 hidden 2xl:inline whitespace-nowrap">({{ isTorStarting ? 'Bootstrapping' : torStatus.ip }})</span>

                            <ChevronDown v-if="torStatus.is_tor && !isTorStarting && !isTorStopping" class="w-3 h-3 text-emerald-400/80 hidden 2xl:inline shrink-0" />
                        </button>

                        <!-- Tor Active Control Menu Dropdown -->
                        <div 
                            v-if="showTorMenu && torStatus.is_tor" 
                            class="absolute right-0 mt-2 w-60 bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl py-2 z-50 font-mono text-xs cartoon-modal backdrop-blur-md"
                        >
                            <div class="px-3.5 py-1.5 border-b border-slate-800 text-[10px] text-emerald-400 font-bold uppercase tracking-wider flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Tor Daemon
                                </span>
                                <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-1.5 py-0.5 rounded text-[9px]">SOCKS5 ONLINE</span>
                            </div>
                            <div class="px-3.5 py-2 text-[11px] text-slate-300 border-b border-slate-800/80 space-y-1">
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-slate-400">Local Socket:</span>
                                    <span class="text-slate-200 font-mono">127.0.0.1:9050</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-slate-400">Exit IP:</span>
                                    <span class="text-emerald-300 font-mono truncate max-w-[120px]">{{ torStatus.ip }}</span>
                                </div>
                            </div>
                            <div class="p-1.5 space-y-1">
                                <button 
                                    @click="checkGlobalTorStatus(true); showTorMenu = false"
                                    class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white flex items-center space-x-2 transition cursor-pointer"
                                >
                                    <Activity class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                    <span>Refresh Connectivity</span>
                                </button>
                                <button 
                                    @click="stopTorDaemon"
                                    class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-rose-500/15 text-rose-400 hover:text-rose-300 flex items-center space-x-2 transition cursor-pointer"
                                >
                                    <Square class="w-3.5 h-3.5 text-rose-400 fill-rose-400/20 shrink-0" />
                                    <span>Stop Tor Daemon</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Operator Profile Badge & Dropdown -->
                    <div v-if="currentUser" class="relative shrink-0">
                        <div
                            v-if="showUserMenu"
                            class="fixed inset-0 z-40 bg-transparent cursor-default"
                            @click.stop="showUserMenu = false"
                        ></div>

                        <button
                            @click="showUserMenu = !showUserMenu"
                            class="flex items-center gap-1.5 p-2 2xl:px-3 2xl:py-1.5 rounded-xl bg-slate-950/90 border border-slate-800 hover:border-amber-500/50 text-slate-300 hover:text-amber-300 text-xs font-mono transition cartoon-btn relative z-50 cursor-pointer shrink-0"
                            :title="'Operator: ' + (currentUser.name || currentUser.email)"
                        >
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                            <span class="hidden 2xl:inline font-bold uppercase truncate max-w-[120px]">{{ currentUser.name || 'OPERATOR' }}</span>
                            <ChevronDown class="w-3 h-3 text-slate-400 hidden 2xl:inline shrink-0" />
                        </button>

                        <!-- User Profile Dropdown -->
                        <div
                            v-if="showUserMenu"
                            class="absolute right-0 mt-2 w-60 bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl py-2 z-50 font-mono text-xs cartoon-modal backdrop-blur-md"
                        >
                            <div class="px-3.5 py-2 border-b border-slate-800">
                                <div class="text-[10px] text-amber-400 font-bold uppercase tracking-wider">Active Operator</div>
                                <div class="text-slate-200 font-bold truncate mt-0.5">{{ currentUser.name }}</div>
                                <div class="text-[10px] text-slate-400 truncate">{{ currentUser.email }}</div>
                            </div>
                            <div class="p-1.5">
                                <button
                                    @click="handleLogout"
                                    class="w-full text-left px-3 py-2 rounded-lg hover:bg-rose-500/15 text-rose-400 hover:text-rose-300 flex items-center space-x-2 transition cursor-pointer"
                                >
                                    <LogOut class="w-3.5 h-3.5 shrink-0" />
                                    <span>Terminate Session</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Dock Switcher Button -->
                    <div class="relative">
                        <button 
                            @click="showDockMenu = !showDockMenu"
                            class="p-2 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-400 hover:text-amber-400 hover:border-amber-500/50 transition cartoon-btn flex items-center space-x-1 font-mono text-[11px]"
                            title="Dock position options"
                        >
                            <Move class="w-3.5 h-3.5 text-amber-400" />
                        </button>

                        <div v-if="showDockMenu" class="absolute right-0 mt-2 w-36 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl py-1 z-50 font-mono text-xs cartoon-modal">
                            <div class="px-3 py-1.5 text-[10px] text-amber-400 font-bold border-b border-slate-800 uppercase tracking-wider font-serif">
                                Taskbar Dock
                            </div>
                            <button 
                                v-for="pos in ['top', 'bottom', 'left', 'right']" 
                                :key="pos"
                                @click="setDockPosition(pos)"
                                class="w-full text-left px-3 py-2 hover:bg-amber-500/10 hover:text-amber-300 flex items-center justify-between capitalize transition"
                            >
                                <span>{{ pos }}</span>
                                <Check v-if="dockPosition === pos" class="w-3 h-3 text-amber-400" />
                            </button>
                        </div>
                    </div>

                    <!-- LIQUID GOOEY BURGER / FAB BUTTON (Mobile & Tablet Quick Navigation) -->
                    <div class="relative flex lg:hidden items-center justify-center">
                        <div
                            v-if="burgerMenuOpen"
                            class="fixed inset-0 z-30 bg-transparent cursor-default"
                            @click.stop="burgerMenuOpen = false"
                        ></div>

                        <!-- Revealed Quick Module Cards -->
                        <div
                            v-if="burgerMenuOpen"
                            class="absolute top-14 right-0 z-50 flex flex-col items-end gap-1.5 max-h-[440px] overflow-y-auto overflow-x-hidden p-1 select-none burger-menu-scroll cartoon-modal"
                        >
                            <div
                                v-for="(item, idx) in navModules"
                                :key="item.id"
                                class="transition-all shrink-0"
                            >
                                <Link
                                    :href="item.url"
                                    @click="burgerMenuOpen = false"
                                    class="px-3.5 py-2 rounded-xl border border-slate-700/80 bg-slate-900 hover:bg-slate-850 text-slate-200 hover:border-amber-500/60 shadow-lg flex items-center gap-2.5 group cursor-pointer text-left whitespace-nowrap transition-all duration-200 cartoon-btn font-mono"
                                >
                                    <span class="text-sm shrink-0">{{ item.icon }}</span>
                                    <span class="font-mono uppercase tracking-wider font-bold text-xs group-hover:text-amber-300 transition-colors">{{ item.label }}</span>
                                    <span class="text-[9px] font-mono px-1.5 py-0.5 rounded uppercase tracking-widest ml-1 bg-slate-800 text-slate-400 border border-slate-700">{{ item.badge }}</span>
                                </Link>
                            </div>

                            <!-- Mobile Sign Out Button -->
                            <div v-if="currentUser" class="pt-1 w-full shrink-0 border-t border-slate-800">
                                <button
                                    @click="burgerMenuOpen = false; handleLogout();"
                                    class="w-full px-3.5 py-2 rounded-xl border border-rose-500/40 bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 shadow-lg flex items-center gap-2.5 group cursor-pointer text-left whitespace-nowrap transition-all duration-200 cartoon-btn font-mono"
                                >
                                    <LogOut class="w-3.5 h-3.5 text-rose-400 shrink-0" />
                                    <span class="font-mono uppercase tracking-wider font-bold text-xs">Terminate Session</span>
                                </button>
                            </div>
                        </div>

                        <!-- Burger Button with Spring Easing -->
                        <button
                            @click="burgerMenuOpen = !burgerMenuOpen"
                            class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-slate-900 hover:bg-slate-800 border-2 border-slate-700 hover:border-amber-400 text-slate-100 flex items-center justify-center shadow-xl hover:scale-105 active:scale-95 transition-all cursor-pointer relative z-50 focus:outline-none"
                            :class="{ 'border-amber-400 shadow-amber-500/30 text-amber-400': burgerMenuOpen }"
                            aria-label="Toggle Quick Navigation Menu"
                        >
                            <div class="w-5 h-5 relative flex items-center justify-center pointer-events-none">
                                <span
                                    :class="burgerMenuOpen ? 'rotate-45 bg-amber-400 w-5' : 'rotate-0 bg-slate-200 w-4'"
                                    class="absolute h-0.5 rounded transition-all duration-300 ease-out"
                                ></span>
                                <span
                                    :class="burgerMenuOpen ? '-rotate-45 bg-amber-400 w-5' : 'rotate-90 bg-slate-200 w-4'"
                                    class="absolute h-0.5 rounded transition-all duration-300 ease-out"
                                ></span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------------------------- -->
            <!-- VERTICAL LAYOUT (LEFT / RIGHT TASKBAR) -->
            <!-- ------------------------------------------------------------- -->
            <div v-else class="h-full flex flex-col justify-between space-y-6">
                <!-- Top: Drag Handle + Brand -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div 
                            @mousedown="onHeaderMouseDown"
                            class="flex items-center space-x-2 cursor-grab active:cursor-grabbing select-none" 
                            title="Hold click & drag to dock taskbar"
                        >
                            <GripHorizontal class="w-4 h-4 text-amber-400 animate-pulse pointer-events-none" />
                            <span class="text-[10px] font-mono text-amber-400 font-bold uppercase tracking-wider font-serif pointer-events-none">TASKBAR ({{ dockPosition }})</span>
                        </div>

                        <!-- Dock Selector -->
                        <div class="relative">
                            <button 
                                @click="showDockMenu = !showDockMenu"
                                class="p-1 rounded text-slate-400 hover:text-white"
                                title="Change Dock Position"
                            >
                                <Move class="w-3.5 h-3.5" />
                            </button>

                            <div v-if="showDockMenu" class="absolute left-0 mt-2 w-32 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl py-1 z-50 font-mono text-xs cartoon-modal">
                                <button 
                                    v-for="pos in ['top', 'bottom', 'left', 'right']" 
                                    :key="pos"
                                    @click="setDockPosition(pos)"
                                    class="w-full text-left px-3 py-1.5 hover:bg-amber-500/10 hover:text-amber-300 flex items-center justify-between capitalize transition"
                                >
                                    <span>{{ pos }}</span>
                                    <Check v-if="dockPosition === pos" class="w-3 h-3 text-amber-400" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Brand Lockup -->
                    <Link href="/" class="flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500/20 to-slate-900 border border-amber-500/40 flex items-center justify-center group-hover:scale-105 group-hover:border-amber-400 transition-all shrink-0">
                            <Terminal class="w-5 h-5 text-amber-400" />
                        </div>
                        <div class="overflow-hidden">
                            <div class="font-serif font-bold text-sm tracking-wider text-amber-400 uppercase leading-tight">
                                Darkdump
                            </div>
                            <span class="text-[10px] text-slate-400 block font-mono truncate">OSINT Suite v5</span>
                        </div>
                    </Link>

                    <!-- Spotlight search launcher button -->
                    <button
                        @click="showSearchModal = true"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-500/50 text-slate-400 hover:text-slate-200 text-xs transition-all cartoon-btn font-mono"
                    >
                        <div class="flex items-center space-x-2">
                            <Search class="w-3.5 h-3.5 text-amber-400" />
                            <span>Quick Search</span>
                        </div>
                        <kbd class="text-[9px] px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700">Ctrl K</kbd>
                    </button>

                    <!-- Navigation Links Stacked Vertically -->
                    <nav class="space-y-1.5 font-mono text-xs pt-2">
                        <Link 
                            v-for="item in navModules"
                            :key="item.id"
                            :href="item.url"
                            :class="[
                                page.url === item.url || (item.url !== '/' && page.url.startsWith(item.url))
                                    ? 'text-amber-300 bg-amber-500/15 border-amber-500/40 font-bold shadow-sm shadow-amber-500/20' 
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 border-transparent',
                                'w-full px-3 py-2.5 rounded-xl border transition-all flex items-center justify-between cartoon-btn'
                            ]"
                        >
                            <div class="flex items-center space-x-2.5">
                                <span class="text-sm">{{ item.icon }}</span>
                                <span>{{ item.label }}</span>
                            </div>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700/60 text-slate-400">{{ item.badge }}</span>
                        </Link>
                    </nav>
                </div>

                <!-- Bottom of Vertical Sidebar: Tor Status & Banner -->
                <div class="space-y-2.5 pt-4 border-t border-slate-800/80">
                    <button 
                        @click="handleTorProxyClick"
                        :disabled="isTorStarting || isTorStopping"
                        :title="isTorStarting ? 'Starting Tor daemon...' : torStatus.is_tor ? 'Tor Active (Click to toggle)' : 'Click to Launch Tor SOCKS5 Daemon'"
                        class="w-full flex items-center justify-between text-xs font-mono p-2.5 rounded-xl border transition-all cartoon-btn cursor-pointer"
                        :class="[
                            isTorStarting 
                                ? 'bg-amber-500/15 border-amber-500/60 text-amber-300 animate-pulse' 
                                : isTorStopping
                                    ? 'bg-rose-500/15 border-rose-500/60 text-rose-300 animate-pulse'
                                    : torStatus.is_tor 
                                        ? 'bg-slate-950 border-emerald-500/40 text-emerald-300' 
                                        : 'bg-slate-950 border-amber-500/30 text-amber-300 hover:bg-amber-500/10'
                        ]"
                    >
                        <div class="flex items-center space-x-2">
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span v-if="torStatus.is_tor || isTorStarting" class="animate-ping absolute inline-flex h-full w-full rounded-full" :class="isTorStarting ? 'bg-amber-400 opacity-75' : 'bg-emerald-400 opacity-75'"></span>
                                <span :class="isTorStarting ? 'bg-amber-400' : isTorStopping ? 'bg-rose-400' : torStatus.is_tor ? 'bg-emerald-400' : 'bg-amber-400'" class="relative inline-flex rounded-full h-2 w-2"></span>
                            </span>
                            <Loader2 v-if="isTorStarting || isTorStopping" class="w-3.5 h-3.5 animate-spin" :class="isTorStarting ? 'text-amber-400' : 'text-rose-400'" />
                            <Play v-else-if="!torStatus.is_tor" class="w-3.5 h-3.5 text-amber-400" />
                            <span class="font-semibold text-[11px]">
                                <template v-if="isTorStarting">STARTING...</template>
                                <template v-else-if="isTorStopping">STOPPING...</template>
                                <template v-else-if="torStatus.is_tor">TOR ACTIVE</template>
                                <template v-else>START TOR</template>
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400 truncate max-w-[85px]">{{ isTorStarting ? '9050' : torStatus.ip }}</span>
                    </button>

                    <button 
                        @click="showBanner = !showBanner" 
                        class="w-full py-2 px-3 rounded-xl border border-slate-800 bg-slate-950 hover:border-amber-500/50 text-slate-400 hover:text-white flex items-center justify-center space-x-2 font-mono text-xs transition cartoon-btn"
                    >
                        <Terminal class="w-3.5 h-3.5 text-amber-400" />
                        <span>Toggle Cyber Banner</span>
                    </button>

                    <!-- Vertical Layout Operator Info & Sign Out -->
                    <div v-if="currentUser" class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] text-amber-400 font-bold uppercase tracking-wider">Operator</span>
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        </div>
                        <div class="text-[11px] text-slate-300 font-bold truncate">{{ currentUser.name }}</div>
                        <button 
                            @click="handleLogout"
                            class="w-full py-1.5 px-2.5 rounded-lg border border-rose-500/40 bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 flex items-center justify-center space-x-1.5 text-[11px] transition cartoon-btn cursor-pointer"
                        >
                            <LogOut class="w-3 h-3 text-rose-400" />
                            <span>Sign Out</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Drag and dock drop zone overlay -->
        <div v-if="isDragging" class="fixed inset-0 z-50 pointer-events-none transition-all">
            <!-- Center Drag Helper Toast -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-slate-950/95 border border-amber-500/80 px-7 py-3.5 rounded-2xl shadow-[0_0_40px_rgba(245,158,11,0.4)] text-center font-mono text-xs space-y-1 backdrop-blur-md">
                <div class="text-amber-400 font-bold flex items-center justify-center space-x-2 font-serif text-sm">
                    <Move class="w-4 h-4 animate-bounce text-amber-400" />
                    <span>DOCKING TASKBAR</span>
                </div>
                <div class="text-slate-300 text-[11px]">Move cursor to Top, Bottom, Left, or Right edge to dock</div>
                <div class="text-[11px] text-amber-300 font-bold uppercase pt-1 tracking-wider">
                    Snapping to: [ {{ dragHoverZone }} ]
                </div>
            </div>

            <!-- TOP DROP ZONE -->
            <div 
                :class="[
                    'absolute top-0 left-0 right-0 h-24 transition-all border-b-2 flex items-center justify-center font-mono text-xs font-bold tracking-widest backdrop-blur-sm',
                    dragHoverZone === 'top' 
                        ? 'bg-amber-500/25 border-amber-400 text-amber-300 shadow-[0_0_35px_rgba(245,158,11,0.5)]' 
                        : 'bg-slate-950/40 border-slate-800 text-slate-500'
                ]"
            >
                <span>▲ DOCK TASKBAR TO TOP ▲</span>
            </div>

            <!-- BOTTOM DROP ZONE -->
            <div 
                :class="[
                    'absolute bottom-0 left-0 right-0 h-24 transition-all border-t-2 flex items-center justify-center font-mono text-xs font-bold tracking-widest backdrop-blur-sm',
                    dragHoverZone === 'bottom' 
                        ? 'bg-amber-500/25 border-amber-400 text-amber-300 shadow-[0_0_35px_rgba(245,158,11,0.5)]' 
                        : 'bg-slate-950/40 border-slate-800 text-slate-500'
                ]"
            >
                <span>▼ DOCK TASKBAR TO BOTTOM (WINDOWS STYLE) ▼</span>
            </div>

            <!-- LEFT DROP ZONE -->
            <div 
                :class="[
                    'absolute top-0 bottom-0 left-0 w-52 transition-all border-r-2 flex items-center justify-center font-mono text-xs font-bold tracking-widest backdrop-blur-sm',
                    dragHoverZone === 'left' 
                        ? 'bg-amber-500/25 border-amber-400 text-amber-300 shadow-[0_0_35px_rgba(245,158,11,0.5)]' 
                        : 'bg-slate-950/40 border-slate-800 text-slate-500'
                ]"
            >
                <span>◄ DOCK TASKBAR TO LEFT ◄</span>
            </div>

            <!-- RIGHT DROP ZONE -->
            <div 
                :class="[
                    'absolute top-0 bottom-0 right-0 w-52 transition-all border-l-2 flex items-center justify-center font-mono text-xs font-bold tracking-widest backdrop-blur-sm',
                    dragHoverZone === 'right' 
                        ? 'bg-amber-500/25 border-amber-400 text-amber-300 shadow-[0_0_35px_rgba(245,158,11,0.5)]' 
                        : 'bg-slate-950/40 border-slate-800 text-slate-500'
                ]"
            >
                <span>► DOCK TASKBAR TO RIGHT ►</span>
            </div>
        </div>

        <!-- Spotlight search modal -->
        <Teleport to="body">
            <div 
                v-if="showSearchModal" 
                class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-start justify-center pt-20 sm:pt-28 px-4"
                @click.self="showSearchModal = false"
            >
                <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-2xl w-full p-4 sm:p-5 shadow-2xl cartoon-modal space-y-4">
                    <!-- Search Input Box -->
                    <div class="relative flex items-center">
                        <Search class="w-5 h-5 absolute left-3.5 text-amber-400" />
                        <input 
                            v-model="spotlightQuery"
                            @keydown.enter="executeSpotlightSearch"
                            type="text"
                            placeholder="Type an OSINT query, onion link, or search modules..."
                            class="w-full bg-slate-950 border border-slate-700/80 rounded-xl pl-11 pr-24 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition"
                            autofocus
                        />
                        <button 
                            @click="executeSpotlightSearch"
                            class="absolute right-2 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold font-mono text-xs flex items-center space-x-1 cartoon-btn"
                        >
                            <span>Search</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Navigation Modules Filter List -->
                    <div class="space-y-1">
                        <div class="px-2 py-1 text-[10px] font-mono font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                            <span>Navigation & Modules</span>
                            <span>Esc to close</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto space-y-1 no-scrollbar">
                            <button 
                                v-for="m in filteredSpotlightModules" 
                                :key="m.id"
                                @click="handleSpotlightSelect(m.url)"
                                class="w-full p-3 rounded-xl hover:bg-slate-800/80 border border-transparent hover:border-amber-500/30 flex items-center justify-between text-left transition group cartoon-btn"
                            >
                                <div class="flex items-center space-x-3">
                                    <span class="text-xl p-2 rounded-lg bg-slate-950 border border-slate-800 group-hover:border-amber-500/40 transition">{{ m.icon }}</span>
                                    <div>
                                        <div class="font-mono font-bold text-sm text-white group-hover:text-amber-300 transition-colors">{{ m.label }}</div>
                                        <div class="text-xs text-slate-400 font-sans">{{ m.desc }}</div>
                                    </div>
                                </div>
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400 group-hover:border-amber-500/40 group-hover:text-amber-300 transition">{{ m.badge }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Tor status toast -->
        <Teleport to="body">
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform -translate-y-4 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-4 opacity-0"
            >
                <div 
                    v-if="torNotification" 
                    class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] flex items-center gap-3 px-5 py-3 rounded-2xl shadow-2xl border font-mono text-xs backdrop-blur-xl pointer-events-auto cartoon-modal"
                    :class="[
                        torNotification.type === 'success' ? 'bg-slate-950/95 border-emerald-500/80 text-emerald-300 shadow-[0_0_30px_rgba(16,185,129,0.3)]' :
                        torNotification.type === 'error' ? 'bg-slate-950/95 border-rose-500/80 text-rose-300 shadow-[0_0_30px_rgba(244,63,94,0.3)]' :
                        'bg-slate-950/95 border-amber-500/80 text-amber-300 shadow-[0_0_30px_rgba(245,158,11,0.3)]'
                    ]"
                >
                    <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="torNotification.type === 'success' ? 'bg-emerald-400 animate-pulse' : torNotification.type === 'error' ? 'bg-rose-400 animate-ping' : 'bg-amber-400 animate-ping'"></div>
                    <span class="font-medium tracking-wide">{{ torNotification.message }}</span>
                    <button @click="torNotification = null" class="ml-2 text-slate-400 hover:text-white text-base leading-none cursor-pointer">&times;</button>
                </div>
            </transition>
        </Teleport>

        <!-- Main content area -->
        <div :class="['flex-1 flex flex-col min-w-0 min-h-screen overflow-x-hidden relative z-10', isHorizontal && dockPosition === 'bottom' ? 'order-first' : '']">
            <!-- Collapsible Terminal Banner -->
            <div v-if="showBanner" class="bg-slate-950/90 border-b border-amber-500/20 py-4 px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 2xl:px-16 overflow-x-auto font-mono text-[11px] text-amber-400">
                <div class="w-full flex items-start justify-between">
                    <pre class="leading-none select-none text-amber-400 font-bold">
   ____      ________   ____  ____  ____      ____________________
  / __ \    / /_  __/  / __ \/ __ \/ __ \    / / ____/ ____/_  __/
 / / / /_  / / / /    / /_/ / /_/ / / / /_  / / __/ / /     / /   
/ /_/ / /_/ / / /    / ____/ _, _/ /_/ / /_/ / /___/ /___  / /    
\____/\____/ /_/    /_/   /_/ |_|\____/\____/_____/\____/ /_/     
[ LARAVEL 11 + VUE 3 + INERTIA V2 + TAILWIND V3 + REDIS + MYSQL ]
                    </pre>
                    <button @click="showBanner = false" class="text-slate-500 hover:text-slate-300 text-xs">
                        [close]
                    </button>
                </div>
            </div>

            <!-- Main Content Slot (Occupies Entire Screen Width) -->
            <main class="flex-1 w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 2xl:px-16 py-8 sm:py-10">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="border-t border-slate-900/80 bg-slate-950/80 py-8 text-center text-xs text-slate-500 font-mono mt-auto">
                <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 2xl:px-16 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-2.5">
                        <span class="text-amber-500 font-bold">●</span>
                        <span class="text-slate-300 font-serif font-bold uppercase tracking-wider">Darkdump OSINT Suite</span>
                        <span class="text-slate-600">•</span>
                        <span class="text-slate-400">Deep Web Reconnaissance & Intelligence Matrix</span>
                    </div>
                    <div class="text-[11px] text-slate-400 max-w-md text-left sm:text-right font-sans">
                        <span>Strictly for verified security research and threat intelligence. Drag header to any edge to re-dock taskbar.</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

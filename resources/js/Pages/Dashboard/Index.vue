<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { 
    Search, 
    FolderGit2, 
    Terminal, 
    Radio, 
    Layers, 
    ArrowRight, 
    Clock, 
    Bookmark, 
    ExternalLink, 
    Zap,
    Globe,
    ShieldAlert,
    Eye
} from 'lucide-vue-next';

const props = defineProps({
    recentQueries: Array,
    recentBookmarks: Array,
    recentAlerts: Array,
    stats: Object,
    torStatus: Object,
});

const quickQuery = ref('');
const selectedEngine = ref('onionfind');

const launchSearch = () => {
    if (!quickQuery.value.trim()) return;
    router.visit(`/search?q=${encodeURIComponent(quickQuery.value)}&engine=${selectedEngine.value}`);
};
</script>

<template>
    <div class="space-y-10">
            <!-- HERO SECTION: 2-COLUMN SPLIT (DIGITAL MANAGEMENT STYLE) -->
            <section class="relative overflow-hidden rounded-3xl border border-slate-800/90 bg-gradient-to-b from-slate-950 via-slate-900/50 to-slate-950 p-6 sm:p-10 lg:p-14 xl:p-16 shadow-2xl reveal-item is-revealed">
                <!-- Radial Gold Ambient Glow -->
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute top-0 right-1/4 w-[600px] h-[350px] bg-amber-500/10 blur-[140px] rounded-full"></div>
                    <div class="absolute bottom-0 left-10 w-[500px] h-[300px] bg-blue-600/10 blur-[130px] rounded-full"></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 xl:gap-16 items-center relative z-10 w-full">
                    <!-- Left Column: Authority Copy, Titles & Search Input (7 cols) -->
                    <div class="lg:col-span-7 xl:col-span-7 space-y-6 sm:space-y-8 text-center lg:text-left">
                        <!-- Authority & Tor Live Status Pills -->
                        <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2.5">
                            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900/90 border border-amber-500/30 text-amber-300 text-xs font-semibold tracking-wider uppercase shadow-inner cartoon-pill select-none">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span class="font-mono">Darkdump Core v5</span>
                                <span class="text-slate-600 hidden sm:inline">|</span>
                                <span class="text-slate-300 font-normal hidden sm:inline font-sans">Multi-Engine OSINT Intelligence</span>
                            </div>

                            <div v-if="torStatus" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-mono border cartoon-pill" :class="torStatus.is_tor ? 'bg-emerald-950/80 border-emerald-500/40 text-emerald-300' : 'bg-slate-900/90 border-slate-700/80 text-slate-400'">
                                <span class="w-2 h-2 rounded-full" :class="torStatus.is_tor ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></span>
                                <span>TOR SOCKS5: {{ torStatus.is_tor ? 'CIRCUIT ACTIVE (' + torStatus.ip + ')' : (torStatus.status === 'connected' ? 'CIRCUIT READY' : 'STANDBY') }}</span>
                            </div>
                        </div>

                        <!-- Title Lockup -->
                        <div class="space-y-2">
                            <h1 class="font-serif font-black text-3xl sm:text-4xl md:text-5xl lg:text-5xl xl:text-6xl 2xl:text-7xl tracking-tight text-slate-100 uppercase leading-[1.08]">
                                Advanced
                                <span class="block text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 pt-1">
                                    Threat Intelligence
                                </span>
                            </h1>
                            <p class="text-xs sm:text-sm lg:text-base font-semibold tracking-widest text-amber-400/90 uppercase font-serif">
                                Automated Darknet Crawling • Forensic Harvesting • Target Discovery
                            </p>
                        </div>

                        <!-- Description Paragraph -->
                        <p class="text-sm sm:text-base lg:text-lg text-slate-300 font-light leading-relaxed max-w-3xl xl:max-w-4xl mx-auto lg:mx-0">
                            Query multiple dark web search engines simultaneously, deep-scrape onion endpoints for credentials, sensitive documents and emails, and organize intelligence findings into case dossiers.
                        </p>

                        <!-- Search Input Form -->
                        <form @submit.prevent="launchSearch" class="pt-2 flex flex-col sm:flex-row gap-2.5 max-w-3xl xl:max-w-4xl w-full">
                            <div class="relative flex-1">
                                <Search class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                                <input 
                                    v-model="quickQuery"
                                    type="text"
                                    placeholder="Enter target query, keyword, onion link, or email..."
                                    class="w-full bg-slate-950/90 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition shadow-inner"
                                />
                            </div>

                            <select 
                                v-model="selectedEngine"
                                class="bg-slate-950/90 border border-slate-700/80 rounded-xl px-3.5 py-3.5 text-xs text-slate-300 font-mono focus:outline-none focus:border-amber-400"
                            >
                                <option value="onionfind">OnionFind (Uncensored)</option>
                                <option value="vormweb">VormWeb (Uncensored)</option>
                                <option value="tordex">TorDex (Uncensored)</option>
                                <option value="onionland">OnionLand (Tor + I2P)</option>
                                <option value="ahmia">Ahmia (Filtered)</option>
                                <option value="duckduckgo">DuckDuckGo (Clearnet)</option>
                            </select>

                            <button 
                                type="submit"
                                class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs uppercase tracking-wider flex items-center justify-center space-x-2 transition shadow-lg shadow-amber-500/25 cartoon-btn cursor-pointer shrink-0 select-none"
                            >
                                <span>Launch Scan</span>
                                <ArrowRight class="w-4 h-4" />
                            </button>
                        </form>

                        <!-- Trust / Operational Verification Row -->
                        <div class="pt-3 border-t border-slate-800/80 flex flex-wrap items-center justify-center lg:justify-start gap-4 sm:gap-6 text-xs text-slate-400 font-medium font-mono select-none">
                            <div class="flex items-center gap-1.5">
                                <span class="text-amber-400 font-bold">✓</span>
                                <span>Ahmia Blacklist Automated Check</span>
                            </div>
                            <span class="text-slate-700 hidden sm:inline">•</span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-amber-400 font-bold">✓</span>
                                <span>SOCKS5 Proxy Tor Routing</span>
                            </div>
                            <span class="text-slate-700 hidden sm:inline">•</span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-amber-400 font-bold">✓</span>
                                <span>Real-Time SSE Streaming</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Majestic OSINT Cyber Seal (5 cols) -->
                    <div class="lg:col-span-5 xl:col-span-5 flex flex-col items-center justify-center relative py-4 lg:py-0 reveal-item-scale is-revealed reveal-delay-200 select-none">
                        <!-- Ambient Multi-Layer Radial Glow -->
                        <div class="absolute w-80 h-80 sm:w-96 sm:h-96 xl:w-[440px] xl:h-[440px] bg-gradient-to-tr from-amber-500/25 via-yellow-500/15 to-transparent rounded-full blur-3xl pointer-events-none -z-10 animate-core-glow"></div>
                        <div class="absolute w-60 h-60 sm:w-72 sm:h-72 bg-amber-400/10 rounded-full blur-2xl pointer-events-none -z-10"></div>

                        <!-- Outer Master Container with HUD Corner Brackets -->
                        <div class="relative w-64 h-64 sm:w-72 sm:h-72 md:w-80 md:h-80 lg:w-84 lg:h-84 xl:w-96 xl:h-96 2xl:w-[410px] 2xl:h-[410px] flex items-center justify-center p-3 sm:p-4 group cursor-crosshair">
                            
                            <!-- 1. Sci-Fi HUD Corner Brackets (Framing the matrix) -->
                            <div class="absolute -top-1 -left-1 w-5 h-5 border-t-2 border-l-2 border-amber-400/70 rounded-tl transition-all duration-300 group-hover:scale-110 group-hover:border-amber-300 pointer-events-none"></div>
                            <div class="absolute -top-1 -right-1 w-5 h-5 border-t-2 border-r-2 border-amber-400/70 rounded-tr transition-all duration-300 group-hover:scale-110 group-hover:border-amber-300 pointer-events-none"></div>
                            <div class="absolute -bottom-1 -left-1 w-5 h-5 border-b-2 border-l-2 border-amber-400/70 rounded-bl transition-all duration-300 group-hover:scale-110 group-hover:border-amber-300 pointer-events-none"></div>
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 border-b-2 border-r-2 border-amber-400/70 rounded-br transition-all duration-300 group-hover:scale-110 group-hover:border-amber-300 pointer-events-none"></div>

                            <!-- 2. Main Outer Glass Border & Ambient Halo -->
                            <div class="absolute inset-2 sm:inset-3 rounded-full border-2 border-amber-500/30 bg-slate-950/70 backdrop-blur-sm shadow-[0_0_50px_rgba(245,158,11,0.2)] group-hover:shadow-[0_0_80px_rgba(245,158,11,0.45)] transition-all duration-500 pointer-events-none"></div>

                            <!-- 3. Active Sonar / Radar Sweep Beam Cone -->
                            <div class="absolute inset-4 sm:inset-5 rounded-full overflow-hidden pointer-events-none z-10">
                                <div class="w-full h-full rounded-full animate-radar-sweep opacity-80 group-hover:opacity-100 transition-opacity" style="background: conic-gradient(from 0deg, transparent 0deg, transparent 270deg, rgba(245, 158, 11, 0.04) 300deg, rgba(245, 158, 11, 0.35) 360deg);"></div>
                            </div>

                            <!-- 4. SVG Multi-Layer Cyber Hologram Graphic -->
                            <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" viewBox="0 0 400 400">
                                <defs>
                                    <!-- Linear Gradient for Accents -->
                                    <linearGradient id="cyberGold" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.9" />
                                        <stop offset="50%" stop-color="#fbbf24" stop-opacity="1" />
                                        <stop offset="100%" stop-color="#d97706" stop-opacity="0.8" />
                                    </linearGradient>

                                    <!-- Circular Path for Rotating Text -->
                                    <path id="hudTextTrack" d="M 200,200 m -162,0 a 162,162 0 1,1 324,0 a 162,162 0 1,1 -324,0" fill="none" />
                                </defs>

                                <!-- Outer Cardinal Crosshair Ticks (Static frame) -->
                                <g stroke="#f59e0b" stroke-width="1.5" opacity="0.4">
                                    <line x1="200" y1="8" x2="200" y2="22" />
                                    <line x1="200" y1="378" x2="200" y2="392" />
                                    <line x1="8" y1="200" x2="22" y2="200" />
                                    <line x1="378" y1="200" x2="392" y2="200" />
                                </g>

                                <!-- Concentric Calibration Circle Rings -->
                                <circle cx="200" cy="200" r="190" fill="none" stroke="#f59e0b" stroke-width="0.75" stroke-dasharray="2 8" opacity="0.35" />
                                
                                <!-- Layer A: Slow Clockwise Rotating Dial with 24 Degree Ticks -->
                                <g class="animate-spin-slow origin-center">
                                    <circle cx="200" cy="200" r="178" fill="none" stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="4 12" opacity="0.5" />
                                    <circle cx="200" cy="200" r="174" fill="none" stroke="#fbbf24" stroke-width="2" stroke-dasharray="60 30 120 40" opacity="0.6" />
                                    <!-- Dial coordinate node blips -->
                                    <circle cx="200" cy="22" r="3" fill="#f59e0b" />
                                    <circle cx="378" cy="200" r="3" fill="#f59e0b" />
                                    <circle cx="200" cy="378" r="3" fill="#f59e0b" />
                                    <circle cx="22" cy="200" r="3" fill="#f59e0b" />
                                </g>

                                <!-- Layer B: Rotating Outer Telemetry Text Ring (Counter-Clockwise) -->
                                <g class="animate-spin-reverse-slow origin-center">
                                    <text class="text-[8.5px] font-mono tracking-[0.28em] fill-amber-400/80 uppercase font-bold">
                                        <textPath href="#hudTextTrack" startOffset="0%">
                                            ● DARKDUMP OSINT V5 ● RECON ENCRYPTED ● TOR ROUTING ACTIVE ● 256-BIT SOCKS5 ● ZERO FOOTPRINT ●
                                        </textPath>
                                    </text>
                                </g>

                                <!-- Layer C: Segmented Tachometer Arcs (Clockwise Medium) -->
                                <g class="animate-spin-medium origin-center">
                                    <circle cx="200" cy="200" r="146" fill="none" stroke="url(#cyberGold)" stroke-width="2.5" stroke-dasharray="110 40 80 50" stroke-linecap="round" opacity="0.75" />
                                    <circle cx="200" cy="200" r="141" fill="none" stroke="#f59e0b" stroke-width="0.8" stroke-dasharray="3 9" opacity="0.4" />
                                </g>

                                <!-- Layer D: Inner Fast Reverse Arcs & Data Hash Ring -->
                                <g class="animate-spin-reverse-medium origin-center">
                                    <circle cx="200" cy="200" r="126" fill="none" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="40 80 60 70" stroke-linecap="round" opacity="0.5" />
                                    <circle cx="200" cy="200" r="122" fill="none" stroke="#f59e0b" stroke-width="0.5" stroke-dasharray="1 6" opacity="0.3" />
                                </g>
                            </svg>

                            <!-- 5. Interactive Darknet Radar Targets / Blips (Positioned over radar plane) -->
                            <div class="absolute inset-0 pointer-events-none z-20">
                                <!-- Target 1: Top-Right (Active Onion Node) -->
                                <div class="absolute top-[22%] right-[22%] -translate-x-1/2 -translate-y-1/2 flex items-center gap-1.5">
                                    <div class="relative flex items-center justify-center">
                                        <span class="absolute w-6 h-6 rounded-full bg-amber-400/40 animate-radar-ripple"></span>
                                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-radar-blip"></span>
                                    </div>
                                    <span class="text-[8px] font-mono text-amber-300/90 font-bold bg-slate-950/90 px-1 py-0.5 rounded border border-amber-500/40 hidden sm:inline shadow-sm">ONION.01</span>
                                </div>

                                <!-- Target 2: Bottom-Left (Stealth Proxy) -->
                                <div class="absolute bottom-[24%] left-[23%] -translate-x-1/2 translate-y-1/2 flex items-center gap-1.5">
                                    <div class="relative flex items-center justify-center">
                                        <span class="absolute w-5 h-5 rounded-full bg-cyan-400/30 animate-radar-ripple" style="animation-delay: 1.1s;"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-radar-blip" style="animation-delay: 1.1s;"></span>
                                    </div>
                                    <span class="text-[8px] font-mono text-cyan-300/90 font-bold bg-slate-950/90 px-1 py-0.5 rounded border border-cyan-500/40 hidden sm:inline shadow-sm">SOCKS5:9050</span>
                                </div>

                                <!-- Target 3: Top-Left Micro Ping -->
                                <div class="absolute top-[32%] left-[26%] -translate-x-1/2 -translate-y-1/2">
                                    <span class="w-1 h-1 rounded-full bg-emerald-400 block animate-ping"></span>
                                </div>
                            </div>

                            <!-- 6. Orbiting Outer Satellite Node (Orbiting the perimeter) -->
                            <div class="absolute inset-0 pointer-events-none z-20 animate-spin-medium">
                                <div class="absolute top-2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center">
                                    <div class="relative flex items-center justify-center">
                                        <span class="absolute w-4 h-4 rounded-full bg-amber-400/40 animate-ping"></span>
                                        <span class="w-2 h-2 rounded-full bg-amber-300 shadow-[0_0_10px_#f59e0b]"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- 7. Central Glass Cyber Core (Majestic Seal Center) -->
                            <div class="relative z-30 w-36 h-36 sm:w-44 sm:h-44 lg:w-48 lg:h-48 xl:w-56 xl:h-56 rounded-full bg-gradient-to-b from-slate-900/95 via-slate-950/98 to-slate-900/95 border-2 border-amber-500/60 shadow-[inset_0_0_25px_rgba(245,158,11,0.25)] flex flex-col items-center justify-center p-3 sm:p-4 text-center group-hover:border-amber-400 group-hover:scale-105 transition-all duration-300 select-none pointer-events-none">
                                
                                <!-- Core Radial Ambient Flare -->
                                <div class="absolute inset-2 rounded-full bg-amber-500/10 blur-md pointer-events-none"></div>

                                <!-- Animated Icon Container -->
                                <div class="relative mb-1">
                                    <div class="absolute inset-0 bg-amber-400/30 blur-lg rounded-full animate-pulse pointer-events-none"></div>
                                    <Terminal class="relative z-10 w-9 h-9 sm:w-11 sm:h-11 lg:w-12 lg:h-12 text-amber-400 group-hover:text-amber-300 group-hover:scale-110 transition-transform duration-300 drop-shadow-[0_0_12px_rgba(245,158,11,0.6)]" />
                                </div>

                                <!-- Brand Text with Gold Shimmer -->
                                <span class="font-serif font-black text-xs sm:text-sm lg:text-base text-amber-300 tracking-wider uppercase drop-shadow">
                                    DARKDUMP
                                </span>

                                <!-- Sub-Badge -->
                                <span class="text-[8px] sm:text-[9px] lg:text-[10px] font-mono text-slate-400 tracking-widest uppercase">
                                    OSINT MATRIX
                                </span>

                                <!-- Live Telemetry Equalizer Audio/Data Waveform Bars -->
                                <div class="flex items-center gap-1 h-3.5 pt-1 sm:pt-1.5">
                                    <span class="w-1 bg-amber-500 rounded-full animate-eq-1"></span>
                                    <span class="w-1 bg-amber-400 rounded-full animate-eq-2"></span>
                                    <span class="w-1 bg-amber-300 rounded-full animate-eq-3"></span>
                                    <span class="w-1 bg-amber-400 rounded-full animate-eq-4"></span>
                                    <span class="w-1 bg-amber-500 rounded-full animate-eq-5"></span>
                                </div>

                                <!-- Telemetry Status Pill -->
                                <div class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-950/80 border border-amber-500/30 text-[8px] font-mono text-amber-300/80">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>CORE LIVE</span>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Badge Plate -->
                        <div class="mt-5 text-center space-y-0.5 select-none">
                            <div class="font-serif font-bold text-xs sm:text-sm lg:text-base tracking-widest text-amber-400 uppercase flex items-center justify-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                                <span>Forensic Threat Analysis</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                            </div>
                            <div class="text-[11px] sm:text-xs text-slate-400 font-mono tracking-wide">
                                Zero-Footprint Reconnaissance • Live Darknet Telemetry
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 1. METRICS & STATISTICS BAR (DIGITAL MANAGEMENT STYLE) -->
            <section class="reveal-item is-revealed reveal-delay-200">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-5 xl:gap-6">
                    <!-- 1: Searches Dispatched -->
                    <div class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 transition-all shadow-xl cartoon-card">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase">SEARCHES</span>
                            <Search class="w-4 h-4 text-amber-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-amber-400 font-mono tracking-tight tabular-nums mt-2">
                            {{ stats.total_queries }}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate">Multi-engine scans</p>
                    </div>

                    <!-- 2: Indexed Findings -->
                    <div class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 transition-all shadow-xl cartoon-card">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase">FINDINGS</span>
                            <Layers class="w-4 h-4 text-amber-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-amber-400 font-mono tracking-tight tabular-nums mt-2">
                            {{ stats.total_results }}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate">Indexed results</p>
                    </div>

                    <!-- 3: Case Dossiers -->
                    <div class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 transition-all shadow-xl cartoon-card">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase">DOSSIERS</span>
                            <FolderGit2 class="w-4 h-4 text-amber-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-amber-400 font-mono tracking-tight tabular-nums mt-2">
                            {{ stats.total_investigations }}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate">Active cases</p>
                    </div>

                    <!-- 4: Bookmarked Targets -->
                    <div class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 transition-all shadow-xl cartoon-card">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase">BOOKMARKS</span>
                            <Bookmark class="w-4 h-4 text-amber-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-amber-400 font-mono tracking-tight tabular-nums mt-2">
                            {{ stats.total_bookmarks }}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate">Flagged targets</p>
                    </div>

                    <!-- 5: Deep Scraped Sites -->
                    <Link href="/scraper" class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-cyan-500/50 transition-all shadow-xl cartoon-card group block">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase group-hover:text-cyan-300 transition">DEEP SCRAPES</span>
                            <Globe class="w-4 h-4 text-cyan-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-cyan-400 font-mono tracking-tight tabular-nums mt-2">
                            {{ stats.total_scraped || 0 }}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate group-hover:text-slate-300">Crawled targets →</p>
                    </Link>

                    <!-- 6: Active Recon Monitors -->
                    <Link href="/recon-monitors" class="p-5 xl:p-6 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-purple-500/50 transition-all shadow-xl cartoon-card group block">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 uppercase group-hover:text-purple-300 transition">MONITORS</span>
                            <Radio class="w-4 h-4 text-purple-400" />
                        </div>
                        <div class="text-2xl sm:text-3xl xl:text-4xl font-black text-purple-400 font-mono tracking-tight tabular-nums mt-2 flex items-center justify-between">
                            <span>{{ stats.total_monitors || 0 }}</span>
                            <span v-if="(stats.unread_alerts || 0) > 0" class="px-2 py-0.5 rounded-full bg-rose-950 border border-rose-500/40 text-rose-300 text-[10px] font-bold">
                                {{ stats.unread_alerts }} alert{{ stats.unread_alerts > 1 ? 's' : '' }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate group-hover:text-slate-300">Active watchdogs →</p>
                    </Link>
                </div>
            </section>

            <!-- Active Recon Threat Alerts Banner -->
            <div v-if="recentAlerts && recentAlerts.length > 0" class="p-5 rounded-3xl bg-rose-950/30 border border-rose-500/50 shadow-2xl space-y-3 cartoon-card reveal-item is-revealed">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <ShieldAlert class="w-5 h-5 text-rose-400 animate-pulse" />
                        <h3 class="text-xs sm:text-sm font-serif font-bold text-white uppercase tracking-wider">
                            Active Threat Intelligence Alerts ({{ stats.unread_alerts }} Unread)
                        </h3>
                    </div>
                    <Link href="/recon-monitors" class="text-xs font-mono text-rose-300 hover:text-rose-200 underline">
                        Open Recon Center →
                    </Link>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div 
                        v-for="alert in recentAlerts" 
                        :key="alert.id"
                        class="p-3.5 rounded-xl bg-slate-950/80 border border-rose-500/30 font-mono text-xs space-y-1.5"
                    >
                        <div class="flex items-center justify-between">
                            <span class="px-1.5 py-0.5 rounded bg-rose-950 border border-rose-500/40 text-rose-300 text-[9px] uppercase font-bold">
                                {{ alert.severity || 'CRITICAL' }}
                            </span>
                            <span class="text-[10px] text-slate-500">{{ new Date(alert.created_at).toLocaleTimeString() }}</span>
                        </div>
                        <h4 class="text-white font-bold truncate text-[11px] font-sans" :title="alert.title">{{ alert.title }}</h4>
                        <p v-if="alert.summary" class="text-slate-400 text-[10px] line-clamp-1 font-sans">{{ alert.summary }}</p>
                    </div>
                </div>
            </div>

            <!-- 2. RECENT QUERIES DOSSIER TABLE -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-3xl p-6 sm:p-8 xl:p-10 shadow-xl reveal-item is-revealed reveal-delay-300">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center space-x-2">
                        <Clock class="w-4 h-4 text-amber-400" />
                        <h2 class="font-serif text-sm font-bold tracking-wide text-white uppercase">Investigation Query Registry</h2>
                    </div>
                    <Link href="/queries" class="text-xs font-mono text-amber-400 hover:text-amber-300 hover:underline cartoon-btn select-none">View All Queries →</Link>
                </div>

                <div v-if="recentQueries && recentQueries.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="border-b border-slate-800 text-slate-400 select-none">
                            <tr>
                                <th class="pb-3 font-semibold">QUERY</th>
                                <th class="pb-3 font-semibold">ENGINE</th>
                                <th class="pb-3 font-semibold">RESULTS</th>
                                <th class="pb-3 font-semibold">STATUS</th>
                                <th class="pb-3 font-semibold">RECORDED</th>
                                <th class="pb-3 text-right font-semibold">ACTION</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr v-for="q in recentQueries" :key="q.id" class="hover:bg-slate-900/40 transition">
                                <td class="py-3 font-semibold text-white">{{ q.query }}</td>
                                <td class="py-3 text-slate-400 uppercase text-[11px]">{{ q.engine }}</td>
                                <td class="py-3 text-amber-400">{{ q.results_count }} items</td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[10px]">
                                        {{ q.status }}
                                    </span>
                                </td>
                                <td class="py-3 text-slate-400 text-[11px]">{{ new Date(q.created_at).toLocaleString() }}</td>
                                <td class="py-3 text-right">
                                    <Link :href="`/search/${q.id}`" class="text-amber-400 hover:text-amber-300 font-bold select-none">Inspect →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-10 text-xs font-mono text-slate-400">
                    No investigation queries recorded yet. Launch your first query above!
                </div>
            </div>
        </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Terminal, 
    Lock, 
    ShieldCheck, 
    Key, 
    Eye, 
    EyeOff, 
    ArrowRight, 
    Loader2, 
    AlertTriangle, 
    Check, 
    Zap,
    Activity
} from 'lucide-vue-next';

const props = defineProps({
    torActive: {
        type: Boolean,
        default: false,
    },
    demoCredentials: {
        type: Object,
        default: () => ({
            email: 'operator@darkdump.local',
            password: 'DarkDump@2026!',
        }),
    },
    status: {
        type: String,
        default: null,
    },
});

const showPassword = ref(false);
const demoLoaded = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: true,
});

const loadDemoOperator = () => {
    form.login = props.demoCredentials?.email || 'operator@darkdump.local';
    form.password = props.demoCredentials?.password || 'DarkDump@2026!';
    form.clearErrors();
    demoLoaded.value = true;
    setTimeout(() => {
        demoLoaded.value = false;
    }, 4000);
};

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Operator Authentication | Darkdump OSINT" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans selection:bg-amber-500 selection:text-slate-950">
        <!-- Ambient Background Grid & Radial Lighting -->
        <div class="fixed inset-0 chart-grid pointer-events-none z-0 opacity-40"></div>
        <div class="fixed top-0 right-1/4 w-[650px] h-[400px] bg-amber-500/10 blur-[150px] rounded-full pointer-events-none -z-10 animate-core-glow"></div>
        <div class="fixed bottom-0 left-10 w-[550px] h-[350px] bg-sky-600/10 blur-[140px] rounded-full pointer-events-none -z-10"></div>

        <!-- Top Amber Accent Boundary Line -->
        <div class="h-0.5 w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 relative z-50"></div>

        <!-- Top Header Status Bar -->
        <header class="w-full border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md px-4 sm:px-8 py-3.5 relative z-40">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <!-- Brand Lockup -->
                <div class="flex items-center space-x-3 select-none">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-slate-900 border border-amber-500/40 flex items-center justify-center shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                        <Terminal class="w-5 h-5 text-amber-400" />
                    </div>
                    <div>
                        <div class="font-serif font-bold text-sm sm:text-base tracking-wider text-amber-400 uppercase leading-snug flex items-center gap-1.5">
                            <span>Darkdump</span>
                            <span class="text-amber-400/80 font-mono text-[10px] font-semibold tracking-wider">OSINT v5</span>
                        </div>
                        <div class="text-[10px] font-mono text-slate-400 tracking-wider uppercase hidden sm:block">
                            Station Authentication Gateway
                        </div>
                    </div>
                </div>

                <!-- Right Security Telemetry Readouts -->
                <div class="flex items-center gap-2 sm:gap-3 font-mono text-xs">
                    <!-- Tor Routing Status -->
                    <div 
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[11px]"
                        :class="[
                            torActive 
                                ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' 
                                : 'bg-slate-900/90 border-slate-700/80 text-slate-400'
                        ]"
                    >
                        <span class="w-2 h-2 rounded-full" :class="torActive ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                        <span class="hidden sm:inline uppercase font-bold">{{ torActive ? 'Tor SOCKS5: ACTIVE' : 'Direct Link: STANDBY' }}</span>
                        <span class="sm:hidden">{{ torActive ? 'TOR' : 'CLEAR' }}</span>
                    </div>

                    <!-- Security Clearance Badge -->
                    <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[11px]">
                        <ShieldCheck class="w-3.5 h-3.5 text-amber-400" />
                        <span class="font-bold">CLEARANCE L5</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Authentication Stage -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 flex items-center justify-center relative z-20">
            <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 xl:gap-16 items-center">
                
                <!-- Animated Radar Display -->
                <div class="lg:col-span-6 xl:col-span-6 flex flex-col items-center justify-center relative py-4 lg:py-0 select-none order-2 lg:order-1">
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
                                <span class="text-[8px] font-mono text-cyan-300/90 font-bold bg-slate-950/90 px-1 py-0.5 rounded border border-cyan-500/40 hidden sm:inline shadow-sm">
                                    {{ torActive ? 'SOCKS5:9050' : 'SOCKS5:STANDBY' }}
                                </span>
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

                            <!-- Live Telemetry Equalizer Audio/Data Waveform Bars (Fixed container height prevents any bouncing) -->
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

                    <!-- Security Notice Advisory -->
                    <div class="mt-4 max-w-[440px] text-[11px] text-slate-400 font-mono leading-relaxed border-l-2 border-amber-500/60 pl-3 text-left">
                        <span class="text-amber-400 font-bold">SECURITY DIRECTIVE:</span> This workstation is strictly for authorized darknet reconnaissance and digital intelligence operations. All interactions are audited.
                    </div>
                </div>

                <!-- Operator Authentication Console -->
                <div class="lg:col-span-6 xl:col-span-6 w-full max-w-xl mx-auto order-1 lg:order-2">
                    <div class="relative rounded-3xl border border-slate-800/90 bg-gradient-to-b from-slate-900/95 via-slate-950/90 to-slate-900/95 p-6 sm:p-8 lg:p-10 shadow-2xl backdrop-blur-xl">
                        
                        <!-- Top Accent Glow -->
                        <div class="absolute -top-px left-8 right-8 h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent opacity-80"></div>

                        <!-- Card Header & Badge -->
                        <div class="space-y-3 mb-6 sm:mb-8">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-950/90 border border-amber-500/40 text-amber-300 text-[11px] font-mono uppercase tracking-wider font-semibold select-none shadow-inner">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>Terminal Gate Lock</span>
                            </div>

                            <h1 class="font-serif font-black text-2xl sm:text-3xl lg:text-4xl text-white uppercase tracking-tight leading-tight">
                                Operator Login
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-400 font-sans leading-relaxed">
                                Enter your assigned operator credentials or load the demonstration keypair to access the investigation workspace.
                            </p>
                        </div>

                        <!-- Quick-Fill Demo Operator Button -->
                        <div class="mb-6">
                            <button
                                type="button"
                                @click="loadDemoOperator"
                                class="w-full p-3 rounded-2xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/40 hover:border-amber-400 text-amber-300 hover:text-amber-200 transition-all flex items-center justify-between text-xs font-mono group cursor-pointer shadow-sm cartoon-btn"
                            >
                                <div class="flex items-center space-x-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-amber-500/20 border border-amber-500/40 flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <Zap class="w-3.5 h-3.5 text-amber-400" />
                                    </div>
                                    <div class="text-left">
                                        <div class="font-bold tracking-wide">Quick-Load Demo Operator</div>
                                        <div class="text-[10px] text-amber-400/80 font-mono">operator@darkdump.local</div>
                                    </div>
                                </div>
                                <span 
                                    class="text-[10px] uppercase font-bold px-2.5 py-1 rounded-lg border flex items-center gap-1 transition-all"
                                    :class="demoLoaded ? 'bg-emerald-500/20 border-emerald-500/60 text-emerald-300' : 'bg-slate-900 border-amber-500/30 text-amber-400'"
                                >
                                    <Check v-if="demoLoaded" class="w-3 h-3 text-emerald-400" />
                                    <span>{{ demoLoaded ? 'Loaded' : 'Fill Key' }}</span>
                                </span>
                            </button>
                        </div>

                        <!-- Flash Status Message if any -->
                        <div v-if="status" class="mb-5 p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-xs font-mono flex items-center gap-2">
                            <ShieldCheck class="w-4 h-4 text-emerald-400 shrink-0" />
                            <span>{{ status }}</span>
                        </div>

                        <!-- Validation Error Alert Banner -->
                        <div 
                            v-if="form.errors.login || form.errors.password" 
                            class="mb-6 p-4 rounded-2xl bg-rose-500/15 border border-rose-500/60 text-rose-200 text-xs font-mono space-y-1.5 shadow-lg shadow-rose-950/40"
                        >
                            <div class="flex items-center gap-2 font-bold text-rose-400 uppercase tracking-wider text-[11px]">
                                <AlertTriangle class="w-4 h-4 text-rose-400 shrink-0" />
                                <span>Access Denied</span>
                            </div>
                            <div v-if="form.errors.login" class="text-rose-300 leading-relaxed">
                                {{ form.errors.login }}
                            </div>
                            <div v-if="form.errors.password" class="text-rose-300 leading-relaxed">
                                {{ form.errors.password }}
                            </div>
                        </div>

                        <!-- Authentication Form -->
                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Operator Handle / Email Input -->
                            <div class="space-y-1.5">
                                <label for="login" class="block text-[11px] font-mono uppercase font-bold text-slate-300 tracking-wider">
                                    Operator Identifier
                                </label>
                                <div class="relative flex items-center">
                                    <Terminal class="w-4 h-4 absolute left-3.5 text-slate-500 pointer-events-none" />
                                    <input 
                                        id="login"
                                        v-model="form.login"
                                        type="text"
                                        required
                                        autocomplete="username"
                                        placeholder="operator@darkdump.local or Operator Prime"
                                        class="w-full bg-slate-950/90 border border-slate-700/80 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition shadow-inner"
                                        :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': form.errors.login }"
                                    />
                                </div>
                            </div>

                            <!-- Password / Security Key Input -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="password" class="block text-[11px] font-mono uppercase font-bold text-slate-300 tracking-wider">
                                        Cryptographic Key
                                    </label>
                                    <span class="text-[10px] font-mono text-slate-400">Min 8 Characters</span>
                                </div>
                                <div class="relative flex items-center">
                                    <Lock class="w-4 h-4 absolute left-3.5 text-slate-500 pointer-events-none" />
                                    <input 
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••••••"
                                        class="w-full bg-slate-950/90 border border-slate-700/80 rounded-xl pl-10 pr-11 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 font-mono transition shadow-inner"
                                        :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': form.errors.password }"
                                    />
                                    <button 
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 p-1 rounded-lg text-slate-400 hover:text-amber-400 transition cursor-pointer"
                                        title="Toggle password visibility"
                                    >
                                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                                        <Eye v-else class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Remember Session & Station Policies -->
                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center space-x-2.5 cursor-pointer select-none">
                                    <input 
                                        v-model="form.remember"
                                        type="checkbox"
                                        class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-400/40 focus:ring-offset-slate-950 focus:outline-none"
                                    />
                                    <span class="text-xs text-slate-300 font-mono">Persist Workstation Session</span>
                                </label>

                                <span class="text-[11px] font-mono text-slate-400 hidden sm:inline">TTL: 120m</span>
                            </div>

                            <!-- Submit Action Button -->
                            <div class="pt-2">
                                <button 
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold font-mono text-xs sm:text-sm uppercase tracking-wider flex items-center justify-center space-x-2 transition shadow-lg shadow-amber-500/25 disabled:opacity-50 disabled:cursor-not-allowed cartoon-btn cursor-pointer"
                                >
                                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                                    <Key v-else class="w-4 h-4" />
                                    <span>{{ form.processing ? 'Verifying Credentials...' : 'Authorize & Initialize Session' }}</span>
                                    <ArrowRight v-if="!form.processing" class="w-4 h-4" />
                                </button>
                            </div>
                        </form>

                        <!-- Hardware & Security Protocol Footer -->
                        <div class="mt-8 pt-5 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-[10px] font-mono text-slate-400 select-none">
                            <div class="flex items-center space-x-1.5">
                                <Activity class="w-3 h-3 text-amber-400" />
                                <span>Zero-Log Session Registry</span>
                            </div>
                            <div>
                                <span>SHA-256 Digest Verification</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>

        <!-- Bottom Footer -->
        <footer class="w-full border-t border-slate-900 bg-slate-950/80 py-5 text-center text-xs text-slate-400 font-mono relative z-20">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center space-x-2">
                    <span class="text-amber-500 font-bold">●</span>
                    <span class="text-slate-300 font-serif font-bold uppercase tracking-wider">Darkdump OSINT Suite</span>
                    <span class="text-slate-600">•</span>
                    <span>Deep Web Threat Reconnaissance Matrix</span>
                </div>
                <div class="text-[11px] text-slate-400">
                    Restricted Access • Authorized Security Personnel Only
                </div>
            </div>
        </footer>
    </div>
</template>

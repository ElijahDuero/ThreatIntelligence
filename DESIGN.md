# Darkdump OSINT Design Direction & Style Guide

## Identity & Aesthetics
Darkdump is a specialized dark web investigation workstation and OSINT reconnaissance suite. Its visual design is utilitarian, tactical, and crafted—deliberately avoiding generic corporate SaaS templates, hollow neon glow orbs, and generic AI blue/purple gradients.

## Color System
- **Base Background:** Deep Tactical Black (`bg-slate-950`, `#020617`)
- **Surface & Cards:** Dark Slate Panels (`bg-slate-900`, `bg-slate-900/90`, `#0f172a`)
- **Borders & Dividers:** Subtle Slate Hairlines (`border-slate-800`, `border-slate-800/80`)
- **Primary Cyber Accent:** Warm Amber (`text-amber-400`, `border-amber-500/40`, `bg-amber-500`)
- **Functional Accents:**
  - **Tor / Security / Safe:** Emerald (`emerald-400`, `emerald-500`)
  - **Live Streams / Search:** Cyan (`cyan-400`, `cyan-500`)
  - **Critical Alerts & Malicious Findings:** Rose / Red (`rose-400`, `red-500`)
  - **Cases & Dossiers:** Indigo (`indigo-400`, `indigo-500`)

## Typography & Hierarchy
- **Primary Display Headers:** Bold Serif Display (`font-serif font-black tracking-tight text-white uppercase`)
- **Technical Readouts, Tables, Meta:** Monospace (`font-mono text-xs text-slate-300`)
- **Prose & Guidance:** Clean Sans-serif (`font-sans text-xs sm:text-sm text-slate-300`)

## Component Craft & Styling
- **Buttons & Interactive Elements:** Tactile cyber controls (`cartoon-btn`) with distinct hover states, crisp borders, and subtle inset depth.
- **Surface Elevation:** Cards use `cartoon-card` with crisp outer borders rather than hazy multi-layered blurs.
- **Glassmorphism:** Strictly dosed as an accent (e.g. pinned header or modals), never plastered over all surfaces.
- **Copywriting:** Direct, factual, security-accurate. Banned words: *unlock, delve, elevate, seamless, next-level, game-changer*.

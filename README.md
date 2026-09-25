# Darkdump OSINT Suite — Rebuilt Modern Stack

A modernized deep web intelligence, multi-engine OSINT search, and target reconnaissance platform rewritten from Python/Flask into **Laravel 11, Vue 3, Inertia.js v2, Tailwind CSS v3, MySQL, and Redis**.

---

## Tech Stack Overview

- **Backend:** Laravel 11 / PHP 8.5
- **Frontend:** Vue 3 (Composition API `<script setup>`)
- **SPA Bridge:** Inertia.js v2
- **CSS / UI:** Tailwind CSS v3 (Dark Cyber / OSINT theme)
- **Database & ORM:** MySQL with Eloquent models & migrations
- **Cache & Queues:** Redis (`predis`) + Laravel Queues
- **PWA:** Workbox via `vite-plugin-pwa`
- **Build Tool:** Vite
- **Engine Logic:** Pure PHP Guzzle with SOCKS5h Tor routing (`socks5h://127.0.0.1:9050`) & Symfony DomCrawler (zero Python dependency required)

---

## Core Features

1. **Multi-Engine Dark Web Reconnaissance:**
   - **Engines:** DuckDuckGo (Clearnet), Ahmia (Tor/Clearnet filtered), TorDex (Deep Web), OnionLand (Tor + I2P).
   - **Live SSE Streaming:** Streams results and execution logs in real-time as search endpoints respond.
   - **Ahmia Blacklist Integration:** Automatically filters abusive `.onion` domains against Ahmia's public blacklist.
   - **Deduplication:** Automatic fingerprinting and deduplication of similar titles and snippets.

2. **Target Deep Scraper:**
   - Deep inspection of `.onion` and clearnet targets via Tor proxy.
   - Harvests email addresses via regex.
   - Discovers sensitive document files (`.pdf`, `.docx`, `.xlsx`, `.zip`, `.sql`, `.kdbx`, `.log`, `.csv`).
   - Maps internal and external site link topology.
   - Extracts visual image galleries.

3. **Breach & Credential Leak Intelligence:**
   - Auto-classifies target strings (`email`, `domain`, `hash`, `keyword`).
   - Synthesizes automated breach dorks across paste repositories, stealer logs, and database leak compilations.
   - Evaluates snippet severity (`CRITICAL`, `HIGH`, `MEDIUM`, `INFO`) and surfaces exposed credential patterns.

4. **Investigation Case Dossiers & Bookmarks:**
   - Organize queries, bookmarks, and findings into persistent cases.
   - Bookmark results directly from search cards.

5. **Forensic Export:**
   - Download search results and dossiers as **JSON**, **CSV**, or formatted **Markdown Forensic Reports**.

6. **PWA & Offline Capability:**
   - Progressive Web App installable on desktop and mobile with Workbox caching.

---

## Quick Start (Local Development)

### 1. Database & Environment
The `.env` file is pre-configured for your local MySQL server:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=darkdump
DB_USERNAME=root
DB_PASSWORD=
```
Migrations have already been migrated. If you ever need to re-run them:
```powershell
php artisan migrate
```

### 2. Start the Servers

**Terminal 1 — Laravel Backend:**
```powershell
php artisan serve --port=8080
```
*Application will be available at [http://127.0.0.1:8080](http://127.0.0.1:8080) (or simply double-click `run.bat`).*

**Terminal 2 — Vite Frontend (Hot Reloading):**
```powershell
npm run dev
```
*(Or compile for production with `npm run build`)*

---

## Running with Docker / Laravel Sail (Optional)

A `compose.yaml` is pre-configured with **MySQL 8.4** and **Redis (Alpine)**:

```powershell
# Start MySQL & Redis containers
docker compose up -d
```

---

## Tor Proxy Configuration (Optional for .onion Sites)

Clearnet searches (e.g. DuckDuckGo) work immediately without Tor. 

To search or deep-scrape hidden `.onion` services:
1. Ensure Tor is running with SOCKS5 on `127.0.0.1:9050` (or Tor Browser on port `9150`).
2. The UI includes a live Tor status badge at the top that automatically checks connectivity to `https://check.torproject.org/api/ip` and displays your current exit IP.

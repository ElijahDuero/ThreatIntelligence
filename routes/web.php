<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfrastructureReconController;
use App\Http\Controllers\InvestigationController;
use App\Http\Controllers\OsintController;
use App\Http\Controllers\QueryRegistryController;
use App\Http\Controllers\ReconMonitorController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SocialReconController;
use App\Http\Controllers\TorController;
use Illuminate\Support\Facades\Route;

// Authentication Gateway (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated OSINT Workstation Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Search & Live SSE Stream
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/stream', [SearchController::class, 'stream'])->name('search.stream');
    Route::get('/search/{id}', [SearchController::class, 'show'])->name('search.show');

    // Investigation Query Registry
    Route::get('/queries', [QueryRegistryController::class, 'index'])->name('queries.index');
    Route::delete('/api/queries/{id}', [QueryRegistryController::class, 'destroy'])->name('queries.destroy');
    Route::post('/api/queries/bulk-delete', [QueryRegistryController::class, 'bulkDestroy'])->name('queries.bulk_destroy');
    Route::post('/api/queries/clear', [QueryRegistryController::class, 'clear'])->name('queries.clear');
    Route::get('/queries/export', [QueryRegistryController::class, 'export'])->name('queries.export');

    // Deep Scraper
    Route::get('/scraper', [ScraperController::class, 'index'])->name('scraper.index');
    Route::post('/api/scraper/scrape', [ScraperController::class, 'scrape'])->name('scraper.scrape');
    Route::post('/api/scraper/screenshot', [ScraperController::class, 'captureScreenshot'])->name('scraper.screenshot');
    Route::get('/api/scraper/proxy-image', [ScraperController::class, 'proxyImage'])->name('scraper.proxy_image');
    Route::get('/api/scraper/discover-targets', [ScraperController::class, 'discoverTargets'])->name('scraper.discover_targets');

    // Social Media Recon & Entity Footprinting
    Route::get('/social-recon', [SocialReconController::class, 'index'])->name('social_recon.index');
    Route::post('/api/social-recon/probe-batch', [SocialReconController::class, 'probeBatch'])->name('social_recon.probe_batch');
    Route::post('/api/social-recon/persona/exact', [SocialReconController::class, 'exactPersonaSearch'])->name('social_recon.persona_exact');
    Route::post('/api/social-recon/persona/general', [SocialReconController::class, 'generalPersonaSearch'])->name('social_recon.persona_general');
    Route::post('/api/social-recon/persona/google', [SocialReconController::class, 'googleSearch'])->name('social_recon.persona_google');
    Route::post('/api/social-recon/reddit', [SocialReconController::class, 'searchReddit'])->name('social_recon.reddit');
    Route::post('/api/social-recon/telegram', [SocialReconController::class, 'scrapeTelegram'])->name('social_recon.telegram');
    Route::post('/api/social-recon/telegram/search', [SocialReconController::class, 'searchTelegramChannels'])->name('social_recon.telegram_search');
    Route::post('/api/social-recon/dorks', [SocialReconController::class, 'generateDorks'])->name('social_recon.dorks');
    Route::post('/api/social-recon/execute-dork', [SocialReconController::class, 'executeDork'])->name('social_recon.execute_dork');
    Route::post('/api/social-recon/activity', [SocialReconController::class, 'userActivity'])->name('social_recon.activity');
    Route::get('/api/social-recon/activity/stream', [SocialReconController::class, 'streamActivity'])->name('social_recon.activity_stream');

    // Domain & Network Infrastructure OSINT Recon
    Route::get('/infra-recon', [InfrastructureReconController::class, 'index'])->name('infra_recon.index');
    Route::post('/api/infra-recon/domain', [InfrastructureReconController::class, 'inspectDomain'])->name('infra_recon.domain');
    Route::post('/api/infra-recon/ip', [InfrastructureReconController::class, 'inspectIp'])->name('infra_recon.ip');
    Route::post('/api/infra-recon/ports', [InfrastructureReconController::class, 'inspectPorts'])->name('infra_recon.ports');
    Route::post('/api/infra-recon/link-graph', [InfrastructureReconController::class, 'linkToInvestigation'])->name('infra_recon.link_graph');

    // OSINT Framework Hub, Username & Email Reconnaissance
    Route::get('/osint', [OsintController::class, 'index'])->name('osint.index');
    Route::get('/osint/username', [OsintController::class, 'username'])->name('osint.username');
    Route::post('/api/osint/username/probe', [OsintController::class, 'probeUsername'])->name('osint.username.probe');
    Route::get('/api/osint/username/stream', [OsintController::class, 'streamEnumeration'])->name('osint.username.stream');
    Route::get('/osint/email', [OsintController::class, 'email'])->name('osint.email');
    Route::post('/api/osint/email/probe', [OsintController::class, 'probeEmail'])->name('osint.email.probe');
    Route::get('/api/osint/email/stream', [OsintController::class, 'streamEmail'])->name('osint.email.stream');
    Route::get('/osint/ip', [OsintController::class, 'ip'])->name('osint.ip');
    Route::post('/api/osint/ip/probe', [OsintController::class, 'probeIp'])->name('osint.ip.probe');
    Route::get('/api/osint/ip/stream', [OsintController::class, 'streamIp'])->name('osint.ip.stream');
    Route::get('/osint/domain', [OsintController::class, 'domain'])->name('osint.domain');
    Route::post('/api/osint/domain/probe', [OsintController::class, 'probeDomain'])->name('osint.domain.probe');
    Route::get('/api/osint/domain/stream', [OsintController::class, 'streamDomain'])->name('osint.domain.stream');

    // Investigations & Bookmarks
    Route::get('/investigations', [InvestigationController::class, 'index'])->name('investigations.index');
    Route::post('/investigations', [InvestigationController::class, 'store'])->name('investigations.store');
    Route::get('/investigations/{id}', [InvestigationController::class, 'show'])->name('investigations.show');
    Route::put('/api/investigations/{id}', [InvestigationController::class, 'update'])->name('investigations.update');
    Route::delete('/investigations/{id}', [InvestigationController::class, 'destroy'])->name('investigations.destroy');
    Route::put('/api/investigations/{id}/graph', [InvestigationController::class, 'updateGraph'])->name('investigations.update_graph');
    Route::get('/investigations/{id}/export-report', [InvestigationController::class, 'exportReport'])->name('investigations.export_report');
    Route::post('/api/bookmarks', [InvestigationController::class, 'bookmark'])->name('bookmarks.store');
    Route::delete('/api/bookmarks/{id}', [InvestigationController::class, 'deleteBookmark'])->name('bookmarks.destroy');

    // Automated Scheduled Recon Monitors & Alerts
    Route::get('/monitors', [ReconMonitorController::class, 'index'])->name('monitors.index');
    Route::post('/monitors', [ReconMonitorController::class, 'store'])->name('monitors.store');
    Route::put('/api/monitors/{id}', [ReconMonitorController::class, 'update'])->name('monitors.update');
    Route::delete('/monitors/{id}', [ReconMonitorController::class, 'destroy'])->name('monitors.destroy');
    Route::post('/api/monitors/{id}/poll', [ReconMonitorController::class, 'poll'])->name('monitors.poll');
    Route::post('/api/monitors/poll-all', [ReconMonitorController::class, 'pollAll'])->name('monitors.poll_all');

    // Cyber Threat Alert Center API
    Route::get('/api/alerts', [ReconMonitorController::class, 'getAlerts'])->name('alerts.index');
    Route::post('/api/alerts/{id}/read', [ReconMonitorController::class, 'markAlertRead'])->name('alerts.read');
    Route::post('/api/alerts/mark-all-read', [ReconMonitorController::class, 'markAllAlertsRead'])->name('alerts.mark_all_read');
    Route::delete('/api/alerts/{id}', [ReconMonitorController::class, 'deleteAlert'])->name('alerts.destroy');

    // Export Reports
    Route::get('/export/{searchId}', [ExportController::class, 'export'])->name('export.download');

    // Tor Proxy Control & Status
    Route::get('/api/tor-status', [TorController::class, 'status'])->name('tor.status');
    Route::post('/api/tor/start', [TorController::class, 'start'])->name('tor.start');
    Route::post('/api/tor/stop', [TorController::class, 'stop'])->name('tor.stop');
});

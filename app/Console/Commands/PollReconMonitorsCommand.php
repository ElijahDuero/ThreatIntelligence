<?php

namespace App\Console\Commands;

use App\Models\ReconMonitor;
use App\Services\Monitors\ReconMonitorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recon:poll-monitors {--force : Force scan all active monitors regardless of frequency}')]
#[Description('Poll scheduled reconnaissance monitors for new findings and dispatch cyber threat alerts.')]
class PollReconMonitorsCommand extends Command
{
    public function handle(ReconMonitorService $monitorService): int
    {
        $force = (bool) $this->option('force');
        $monitors = ReconMonitor::where('is_active', true)->get();

        if ($monitors->isEmpty()) {
            $this->info('No active reconnaissance monitors configured.');

            return self::SUCCESS;
        }

        $this->info("Scanning active recon monitors (Total: {$monitors->count()})...");
        $dueCount = 0;

        foreach ($monitors as $monitor) {
            $isDue = $force || $this->isMonitorDue($monitor);

            if (! $isDue) {
                continue;
            }

            $dueCount++;
            $this->line("-> Polling [{$monitor->target_type}] \"{$monitor->title}\" ({$monitor->target_value})...");

            $res = $monitorService->pollMonitor($monitor);

            if ($res['success']) {
                $this->info("   [OK] Scan finished. New alerts: {$res['new_alerts_count']}");
            } else {
                $this->error("   [FAIL] Scan error: {$res['error']}");
            }
        }

        $this->info("Completed recon monitor sweeps. Polled {$dueCount} due monitors.");

        return self::SUCCESS;
    }

    protected function isMonitorDue(ReconMonitor $monitor): bool
    {
        if (is_null($monitor->last_scanned_at)) {
            return true;
        }

        $now = now();
        $diffMinutes = $monitor->last_scanned_at->diffInMinutes($now);

        return match ($monitor->frequency) {
            '15m' => $diffMinutes >= 15,
            'hourly' => $diffMinutes >= 60,
            'daily' => $diffMinutes >= 1440,
            default => $diffMinutes >= 60,
        };
    }
}

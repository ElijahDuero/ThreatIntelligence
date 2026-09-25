<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recon_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('target_type'); // telegram, reddit, persona
            $table->string('target_value'); // e.g. durov, ransomware leak, octocat
            $table->string('frequency')->default('hourly'); // 15m, hourly, daily
            $table->boolean('is_active')->default(true);
            $table->string('webhook_url')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->json('last_seen_state')->nullable();
            $table->integer('findings_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('recon_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recon_monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('external_url')->nullable();
            $table->string('severity')->default('info'); // info, medium, high, critical
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recon_alerts');
        Schema::dropIfExists('recon_monitors');
    }
};

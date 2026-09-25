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
        Schema::table('search_queries', function (Blueprint $table) {
            $table->index('query');
            $table->index('created_at');
        });

        Schema::table('scraped_targets', function (Blueprint $table) {
            $table->string('url', 500)->change();
            $table->index('url');
            $table->index('created_at');
        });

        Schema::table('search_results', function (Blueprint $table) {
            $table->string('url', 500)->change();
            $table->index('url');
            $table->index(['is_onion', 'severity']);
        });

        Schema::table('recon_monitors', function (Blueprint $table) {
            $table->index(['is_active', 'frequency']);
            $table->index('target_type');
        });

        Schema::table('recon_alerts', function (Blueprint $table) {
            $table->index(['is_read', 'severity']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recon_alerts', function (Blueprint $table) {
            $table->dropIndex(['is_read', 'severity']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('recon_monitors', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'frequency']);
            $table->dropIndex(['target_type']);
        });

        Schema::table('search_results', function (Blueprint $table) {
            $table->dropIndex(['url']);
            $table->dropIndex(['is_onion', 'severity']);
            $table->text('url')->change();
        });

        Schema::table('scraped_targets', function (Blueprint $table) {
            $table->dropIndex(['url']);
            $table->dropIndex(['created_at']);
            $table->text('url')->change();
        });

        Schema::table('search_queries', function (Blueprint $table) {
            $table->dropIndex(['query']);
            $table->dropIndex(['created_at']);
        });
    }
};

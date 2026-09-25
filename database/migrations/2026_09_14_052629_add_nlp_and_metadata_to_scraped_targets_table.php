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
        Schema::table('scraped_targets', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->after('status_code');
            $table->json('metadata')->nullable()->after('headers');
            $table->json('keywords')->nullable()->after('metadata');
            $table->json('sentiment')->nullable()->after('keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraped_targets', function (Blueprint $table) {
            $table->dropColumn(['is_blacklisted', 'metadata', 'keywords', 'sentiment']);
        });
    }
};

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
            $table->json('custom_keywords')->nullable()->after('keywords');
            $table->json('keyword_intel')->nullable()->after('custom_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraped_targets', function (Blueprint $table) {
            $table->dropColumn(['custom_keywords', 'keyword_intel']);
        });
    }
};

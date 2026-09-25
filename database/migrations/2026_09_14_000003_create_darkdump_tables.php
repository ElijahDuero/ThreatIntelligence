<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, closed, archived
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query');
            $table->string('engine')->default('duckduckgo');
            $table->string('scan_type')->default('search'); // search, breach, scrape
            $table->boolean('use_tor')->default(false);
            $table->boolean('deep_scrape')->default(false);
            $table->boolean('collect_images')->default(false);
            $table->integer('results_count')->default(0);
            $table->float('execution_time_seconds')->nullable();
            $table->string('status')->default('completed'); // pending, running, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_query_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('url');
            $table->text('description')->nullable();
            $table->string('engine')->default('ahmia');
            $table->integer('position')->default(0);
            $table->boolean('is_onion')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->string('severity')->nullable(); // CRITICAL, HIGH, MEDIUM, INFO
            $table->string('category')->nullable(); // paste, forum, market, leak, generic
            $table->json('credentials_found')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('scraped_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_result_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url');
            $table->string('title')->nullable();
            $table->integer('status_code')->nullable();
            $table->float('response_time_seconds')->nullable();
            $table->string('server')->nullable();
            $table->json('headers')->nullable();
            $table->json('emails')->nullable();
            $table->json('documents')->nullable();
            $table->json('images')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('external_links')->nullable();
            $table->longText('raw_text_sample')->nullable();
            $table->timestamps();
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('search_result_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('url');
            $table->text('notes')->nullable();
            $table->string('severity')->default('INFO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('scraped_targets');
        Schema::dropIfExists('search_results');
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('investigations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 32);
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->string('external_id');
            $table->string('harness', 64)->nullable();
            $table->string('title')->nullable();
            $table->string('project', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('tokens_count')->default(0);
            $table->text('summary_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('summarized_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'content_hash']);
            $table->index('occurred_at');
        });

        Schema::create('knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('kind', 32)->default('fact');
            $table->string('status', 32)->default('approved');
            $table->string('title');
            $table->text('content');
            $table->longText('details')->nullable();
            $table->string('project', 128)->nullable();
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('importance')->default(3);
            $table->decimal('confidence', 3, 2)->default(1);
            $table->string('language', 8)->default('ru');
            $table->string('content_hash', 64)->unique();
            $table->unsignedInteger('tokens_count')->default(0);
            $table->unsignedInteger('terms_count')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind']);
            $table->index('project');
            $table->index('importance');
            $table->index('occurred_at');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('knowledge_entries')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->string('content_hash', 64);
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('terms_count')->default(0);
            $table->timestamps();

            $table->unique(['entry_id', 'position']);
            $table->index('entry_id');
        });

        Schema::create('knowledge_terms', function (Blueprint $table): void {
            $table->foreignId('chunk_id')->constrained('knowledge_chunks')->cascadeOnDelete();
            $table->string('term', 64);
            $table->unsignedSmallInteger('tf')->default(1);

            $table->primary(['chunk_id', 'term']);
            $table->index('term');
        });

        Schema::create('search_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('query');
            $table->string('normalized_query')->nullable();
            $table->string('source', 32)->default('admin');
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('top_entry_id')->nullable()->constrained('knowledge_entries')->nullOnDelete();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('source');
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command', 64);
            $table->string('status', 32)->default('running');
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('knowledge_terms');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_entries');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('knowledge_sources');
    }
};

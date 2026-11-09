<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\Message;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_jsonl_source_is_ingested_summarized_and_indexed(): void
    {
        $directory = storage_path('framework/testing/brain-jsonl');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/session.jsonl', implode("\n", [
            json_encode([
                'type' => 'user',
                'message' => ['role' => 'user', 'content' => 'Я предпочитаю Laravel 13 и PostgreSQL для нового проекта.'],
                'timestamp' => now()->subHour()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                'type' => 'assistant',
                'message' => ['role' => 'assistant', 'content' => 'Понял предпочтения по стеку.'],
                'timestamp' => now()->subHour()->addMinute()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                'type' => 'user',
                'message' => ['role' => 'user', 'content' => 'Ещё я не люблю микросервисы в маленькой команде, предпочитаю модульный монолит.'],
                'timestamp' => now()->subHour()->addMinutes(2)->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
        ])."\n");

        $source = KnowledgeSource::query()->create([
            'name' => 'Test JSONL',
            'slug' => 'test-jsonl',
            'type' => 'jsonl',
            'enabled' => true,
            'config' => [
                'path' => $directory,
                'project' => 'test-project',
                'harness' => 'test',
            ],
        ]);

        $stats = app(SyncService::class)->run('test-jsonl', force: true);

        $this->assertSame(1, $stats['summarized']);
        $this->assertGreaterThan(0, $stats['entries_created']);
        $this->assertDatabaseHas('conversations', [
            'source_id' => $source->id,
            'status' => 'summarized',
        ]);
        $this->assertGreaterThan(0, Conversation::query()->count());
        $this->assertGreaterThan(0, KnowledgeEntry::query()->count());

        $entry = KnowledgeEntry::query()->first();
        $this->assertNotNull($entry->indexed_at);
        $this->assertGreaterThan(0, $entry->chunks()->count());

        File::deleteDirectory($directory);
    }

    public function test_idle_sync_makes_no_ai_calls(): void
    {
        // A conversation that was already summarized: the cron keeps running
        // every 15 minutes, but there is nothing new to learn.
        Conversation::query()->create([
            'external_id' => 'idle-1',
            'source_id' => KnowledgeSource::query()->create([
                'name' => 'Idle',
                'slug' => 'idle',
                'type' => 'proxy',
                'enabled' => true,
            ])->id,
            'status' => 'summarized',
            'messages_count' => 0,
            'summarized_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
        ]);

        $before = \DB::table('knowledge_entries')->count();

        $stats = app(SyncService::class)->run('idle', force: false);

        // Silence means zero summarization, hence zero model calls and zero
        // new cards: the base must not grow while the user says nothing.
        $this->assertSame(0, $stats['summarized']);
        $this->assertSame($before, \DB::table('knowledge_entries')->count());
    }

    public function test_connector_does_not_duplicate_messages_on_second_run(): void
    {
        $directory = storage_path('framework/testing/brain-jsonl-repeat');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/session.jsonl', json_encode([
            'type' => 'user',
            'message' => ['role' => 'user', 'content' => 'Я предпочитаю Laravel и PostgreSQL для проектов.'],
            'timestamp' => now()->subHour()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE)."\n");

        KnowledgeSource::query()->create([
            'name' => 'Test JSONL repeat',
            'slug' => 'test-jsonl-repeat',
            'type' => 'jsonl',
            'enabled' => true,
            'config' => ['path' => $directory, 'project' => 'repeat'],
        ]);

        app(SyncService::class)->run('test-jsonl-repeat', force: true);
        $firstCount = Message::query()->count();

        app(SyncService::class)->run('test-jsonl-repeat', force: true);

        $this->assertSame($firstCount, Message::query()->count());

        File::deleteDirectory($directory);
    }
}

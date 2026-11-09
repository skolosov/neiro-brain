<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\SourceRegistry;
use App\Services\Brain\TranscriptRetention;
use App\Settings\SyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarized_transcripts_are_deleted_after_the_retention_window(): void
    {
        $settings = app(SyncSettings::class);
        $settings->transcript_retention_hours = 24;
        $settings->save();

        $source = app(SourceRegistry::class)->proxy();
        $conversation = app(ConversationIngestor::class)->ingest(
            $source,
            'retention-test',
            [
                ['role' => 'user', 'content' => 'Я предпочитаю модульный монолит.'],
                ['role' => 'assistant', 'content' => 'Понял.'],
            ],
        )['conversation'];

        $conversation->forceFill([
            'status' => ConversationStatus::Summarized->value,
            'summarized_at' => now()->subHours(30),
        ])->save();

        $this->assertSame(2, $conversation->messages()->count());

        $deleted = app(TranscriptRetention::class)->prune();

        $this->assertSame(2, $deleted);
        $this->assertSame(0, $conversation->fresh()->messages()->count());
        $this->assertNotNull($conversation->fresh()->metadata['transcript_purged_at'] ?? null);
        $this->assertTrue(app(TranscriptRetention::class)->isPurged($conversation->fresh()));
    }

    public function test_recent_transcripts_are_kept(): void
    {
        $settings = app(SyncSettings::class);
        $settings->transcript_retention_hours = 24;
        $settings->save();

        $source = app(SourceRegistry::class)->proxy();
        $conversation = app(ConversationIngestor::class)->ingest(
            $source,
            'retention-recent',
            [['role' => 'user', 'content' => 'Свежий диалог, который ещё может досказаться.']],
        )['conversation'];

        $conversation->forceFill([
            'status' => ConversationStatus::Summarized->value,
            'summarized_at' => now()->subHour(),
        ])->save();

        $this->assertSame(0, app(TranscriptRetention::class)->prune());
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_zero_retention_disables_cleanup(): void
    {
        $settings = app(SyncSettings::class);
        $settings->transcript_retention_hours = 0;
        $settings->save();

        $source = app(SourceRegistry::class)->proxy();
        $conversation = app(ConversationIngestor::class)->ingest(
            $source,
            'retention-off',
            [['role' => 'user', 'content' => 'Диалог, который надо сохранить.']],
        )['conversation'];

        $conversation->forceFill([
            'status' => ConversationStatus::Summarized->value,
            'summarized_at' => now()->subYear(),
        ])->save();

        $this->assertSame(0, app(TranscriptRetention::class)->prune());
        $this->assertSame(1, $conversation->messages()->count());
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Settings\SyncSettings;

/**
 * Raw transcripts are working material, not an archive.
 *
 * A conversation goes through: messages arrive -> cards are extracted ->
 * messages are deleted. The chat itself stays with the provider; the brain
 * keeps only what it learned, plus the card metadata (title, project, harness,
 * counts, links to the cards) so the graph and the admin can still explain
 * where a card came from.
 */
final class TranscriptRetention
{
    public function __construct(
        private readonly SyncSettings $settings,
    ) {}

    /**
     * Delete transcripts of conversations that are safely summarized and older
     * than the retention window.
     *
     * @return int number of deleted messages
     */
    public function prune(): int
    {
        $hours = $this->settings->transcript_retention_hours;

        if ($hours <= 0) {
            return 0;
        }

        $threshold = now()->subHours($hours);
        $deleted = 0;

        Conversation::query()
            ->whereIn('status', [
                ConversationStatus::Summarized->value,
                ConversationStatus::Skipped->value,
            ])
            ->whereNotNull('summarized_at')
            ->where('summarized_at', '<=', $threshold)
            ->whereHas('messages')
            ->orderBy('id')
            ->chunkById(50, function ($conversations) use (&$deleted): void {
                foreach ($conversations as $conversation) {
                    $deleted += $this->forget($conversation);
                }
            });

        return $deleted;
    }

    /**
     * Delete every message of one conversation.
     *
     * Messages are always deleted in full: keeping "the rest of the transcript"
     * would make a re-summary run on a truncated dialogue and write partial
     * knowledge for the same conversation.
     */
    public function forget(Conversation $conversation): int
    {
        $deleted = $conversation->messages()->delete();

        $conversation->forceFill([
            'tokens_count' => 0,
            'metadata' => array_merge($conversation->metadata ?? [], [
                'transcript_purged_at' => now()->toIso8601String(),
                'transcript_messages' => $conversation->messages_count,
            ]),
        ])->save();

        return $deleted;
    }

    /**
     * True when the transcript is gone and the conversation cannot produce new
     * cards anymore.
     */
    public function isPurged(Conversation $conversation): bool
    {
        return $conversation->messages()->count() === 0
            && ($conversation->metadata['transcript_purged_at'] ?? null) !== null;
    }
}

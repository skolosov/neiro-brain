<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\ExtractionPipeline;
use App\Services\Brain\KnowledgeWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SummaryService
{
    public function __construct(
        private readonly ExtractionPipeline $pipeline,
        private readonly KnowledgeWriter $writer,
    ) {}

    /**
     * @return array{status: string, created: int, updated: int, engine: string, error?: string}
     */
    public function summarize(Conversation $conversation, bool $force = false): array
    {
        if ($conversation->status === ConversationStatus::Processing && ! $force) {
            return ['status' => 'skipped', 'created' => 0, 'updated' => 0, 'engine' => 'none'];
        }

        if ($conversation->messages()->count() === 0) {
            $conversation->forceFill([
                'status' => ConversationStatus::Skipped->value,
                'summary_error' => null,
            ])->save();

            return ['status' => 'skipped', 'created' => 0, 'updated' => 0, 'engine' => 'none'];
        }

        $conversation->forceFill(['status' => ConversationStatus::Processing->value])->save();

        try {
            $extraction = $this->pipeline->extract($conversation);
            $result = $this->writer->writeMany(
                $extraction['entries'],
                $conversation,
                $conversation->source,
                ['engine' => $extraction['engine']],
            );

            $superseded = $this->applyUpdates($extraction['updates'] ?? [], $conversation, $extraction['engine']);

            $conversation->forceFill([
                'status' => ConversationStatus::Summarized->value,
                'summary_error' => null,
                'summarized_at' => now(),
                'last_activity_at' => $conversation->last_activity_at ?? now(),
            ])->save();

            return [
                'status' => 'summarized',
                'created' => $result['created'],
                'updated' => $result['updated'] + $superseded,
                'engine' => $extraction['engine'],
            ];
        } catch (Throwable $exception) {
            $conversation->forceFill([
                'status' => ConversationStatus::Failed->value,
                'summary_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            Log::error('Neiro Brain: conversation summarization failed.', [
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'created' => 0,
                'updated' => 0,
                'engine' => 'none',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Apply the cards the model asked to rewrite.
     *
     * The old row stays as history (`superseded_by`) instead of being edited in
     * place: nothing the user said is destroyed, yet only the new version is
     * handed out.
     *
     * @param  array<int, ExtractedEntry>  $updates
     */
    private function applyUpdates(array $updates, Conversation $conversation, string $engine): int
    {
        $count = 0;

        foreach ($updates as $id => $entry) {
            $existing = KnowledgeEntry::query()->find($id);

            if (! $existing instanceof KnowledgeEntry) {
                continue;
            }

            $this->writer->supersede(
                $existing,
                $entry,
                $conversation->source,
                ['engine' => $engine],
            );

            $count++;
        }

        return $count;
    }
}

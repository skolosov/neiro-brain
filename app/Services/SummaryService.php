<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
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

            $conversation->forceFill([
                'status' => ConversationStatus::Summarized->value,
                'summary_error' => null,
                'summarized_at' => now(),
                'last_activity_at' => $conversation->last_activity_at ?? now(),
            ])->save();

            return [
                'status' => 'summarized',
                'created' => $result['created'],
                'updated' => $result['updated'],
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
}

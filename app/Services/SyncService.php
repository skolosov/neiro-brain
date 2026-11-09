<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\SourceType;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\SearchLog;
use App\Models\SyncRun;
use App\Services\Brain\Indexer;
use App\Services\Brain\TranscriptRetention;
use App\Services\Connectors\ChatGptConnector;
use App\Services\Connectors\Connector;
use App\Services\Connectors\JsonlConnector;
use App\Services\Connectors\MarkdownConnector;
use App\Settings\SettingsInitializer;
use App\Settings\SyncSettings;
use Throwable;

final class SyncService
{
    public function __construct(
        private readonly SyncSettings $settings,
        private readonly SummaryService $summaries,
        private readonly Indexer $indexer,
        private readonly JsonlConnector $jsonl,
        private readonly ChatGptConnector $chatGpt,
        private readonly MarkdownConnector $markdown,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(?string $sourceSlug = null, bool $force = false): array
    {
        $run = SyncRun::query()->create([
            'command' => 'brain:sync',
            'status' => 'running',
            'started_at' => now(),
            'stats' => [],
        ]);

        $stats = [
            'sources' => [],
            'summarized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'entries_created' => 0,
            'entries_updated' => 0,
            'reindexed' => 0,
            'pruned_logs' => 0,
            'pruned_messages' => 0,
        ];

        try {
            app(SettingsInitializer::class)->ensure();

            if ($this->settings->enabled) {
                $this->syncSources($sourceSlug, $stats, $run);

                if ($this->settings->summarize_on_sync) {
                    $this->summarizeConversations($force, $stats);
                }

                $this->reindexStale($stats);
                $this->pruneLogs($stats);
                $stats['pruned_messages'] = app(TranscriptRetention::class)->prune();
            }

            $run->forceFill([
                'status' => 'completed',
                'stats' => $stats,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'stats' => $stats,
                'error' => mb_substr($exception->getMessage(), 0, 4000),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function syncSources(?string $sourceSlug, array &$stats, SyncRun $run): void
    {
        $sources = KnowledgeSource::query()
            ->enabled()
            ->when($sourceSlug !== null && $sourceSlug !== '', fn ($query) => $query->where('slug', $sourceSlug))
            ->get();

        foreach ($sources as $source) {
            if (in_array($source->type, [
                SourceType::Proxy,
                SourceType::Manual,
                SourceType::Api,
            ], true)) {
                continue;
            }

            $connector = $this->connectorFor($source);

            if ($connector === null) {
                $stats['sources'][] = [
                    'slug' => $source->slug,
                    'status' => 'skipped',
                    'reason' => 'no connector for type '.$source->type->value,
                ];

                continue;
            }

            try {
                $result = $connector->sync($source);
                $stats['sources'][] = [
                    'slug' => $source->slug,
                    'status' => 'ok',
                    ...$result,
                ];
            } catch (Throwable $exception) {
                $source->forceFill([
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();

                $stats['sources'][] = [
                    'slug' => $source->slug,
                    'status' => 'error',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $run->forceFill(['stats' => $stats])->save();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function summarizeConversations(bool $force, array &$stats): void
    {
        $query = Conversation::query()
            ->whereIn('status', [
                ConversationStatus::Pending->value,
                $force ? ConversationStatus::Failed->value : ConversationStatus::Skipped->value,
            ])
            ->orderBy('last_activity_at')
            ->limit(max(1, $this->settings->batch_size));

        if (! $force) {
            $query->where(function ($builder): void {
                $builder->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<=', now()->subMinutes($this->settings->quiet_minutes));
            });
        }

        foreach ($query->get() as $conversation) {
            $result = $this->summaries->summarize($conversation, $force);

            if ($result['status'] === 'summarized') {
                $stats['summarized']++;
            } elseif ($result['status'] === 'skipped') {
                $stats['skipped']++;
            } elseif ($result['status'] === 'failed') {
                $stats['failed']++;
            }

            $stats['entries_created'] += $result['created'];
            $stats['entries_updated'] += $result['updated'];
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function reindexStale(array &$stats): void
    {
        KnowledgeEntry::query()
            ->where(function ($query): void {
                $query->whereNull('indexed_at')
                    ->orWhereColumn('updated_at', '>', 'indexed_at');
            })
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (KnowledgeEntry $entry) use (&$stats): void {
                $this->indexer->index($entry);
                $stats['reindexed']++;
            });
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function pruneLogs(array &$stats): void
    {
        if ($this->settings->search_log_retention_days <= 0) {
            return;
        }

        $stats['pruned_logs'] = SearchLog::query()
            ->where('created_at', '<', now()->subDays($this->settings->search_log_retention_days))
            ->delete();
    }

    private function connectorFor(KnowledgeSource $source): ?Connector
    {
        foreach ([$this->jsonl, $this->chatGpt, $this->markdown] as $connector) {
            if ($connector->supports($source)) {
                return $connector;
            }
        }

        return null;
    }
}

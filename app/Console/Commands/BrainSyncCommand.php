<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class BrainSyncCommand extends Command
{
    protected $signature = 'brain:sync
        {--source= : Sync only one source slug}
        {--force : Summarize immediately, ignoring the quiet period}
        {--json : Print machine-readable stats}';

    protected $description = 'Ingest sources, summarize conversations and reindex the knowledge base';

    public function handle(SyncService $sync): int
    {
        $stats = $sync->run(
            $this->option('source') ?: null,
            (bool) $this->option('force'),
        );

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->info('Neiro Brain sync complete');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Sources', count($stats['sources'])],
                ['Summarized conversations', $stats['summarized']],
                ['Skipped', $stats['skipped']],
                ['Failed', $stats['failed']],
                ['Entries created', $stats['entries_created']],
                ['Entries updated', $stats['entries_updated']],
                ['Reindexed', $stats['reindexed']],
                ['Pruned search logs', $stats['pruned_logs']],
            ],
        );

        foreach ($stats['sources'] as $source) {
            $this->line(sprintf(
                '  %s: %s',
                $source['slug'],
                $source['status'] === 'ok'
                    ? sprintf('%d conversations, %d messages', $source['conversations'], $source['messages'])
                    : ($source['error'] ?? $source['reason'] ?? 'skipped'),
            ));
        }

        return self::SUCCESS;
    }
}

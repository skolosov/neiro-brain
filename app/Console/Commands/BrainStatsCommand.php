<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeTerm;
use App\Models\SearchLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BrainStatsCommand extends Command
{
    protected $signature = 'brain:stats';

    protected $description = 'Show knowledge base statistics';

    public function handle(): int
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Sources', KnowledgeSource::query()->count()],
                ['Conversations', Conversation::query()->count()],
                ['Knowledge entries', KnowledgeEntry::query()->count()],
                ['Approved entries', KnowledgeEntry::query()->approved()->count()],
                ['Chunks', KnowledgeChunk::query()->count()],
                ['Indexed terms (rows)', KnowledgeTerm::query()->count()],
                ['Unique terms', DB::table('knowledge_terms')->distinct()->count('term')],
                ['Searches', SearchLog::query()->count()],
                ['Searches (24h)', SearchLog::query()->where('created_at', '>=', now()->subDay())->count()],
                ['Database size', $this->databaseSize()],
            ],
        );

        return self::SUCCESS;
    }

    private function databaseSize(): string
    {
        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $size = DB::selectOne('select pg_size_pretty(pg_database_size(current_database())) as size');

                return (string) ($size->size ?? 'unknown');
            }
        } catch (\Throwable) {
            // ignore
        }

        return 'n/a';
    }
}

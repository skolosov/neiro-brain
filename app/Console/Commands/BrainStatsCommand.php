<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOwnerOption;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Support\OwnerContext;
use App\Support\OwnerResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BrainStatsCommand extends Command
{
    use ResolvesOwnerOption;

    protected $signature = 'brain:stats
        {--user= : Владелец знаний: id или email (обязательно)}';

    protected $description = 'Show knowledge base statistics for one owner';

    public function handle(OwnerContext $context, OwnerResolver $resolver): int
    {
        if (! $this->setOwnerFromOption($resolver, $context)) {
            return self::FAILURE;
        }

        // Скоупы модели уже ограничивают карточки владельцем; чанки и термины
        // считаем через них, чтобы счётчики не захватили чужой индекс.
        $entryIds = fn () => KnowledgeEntry::query()->select('id');
        $chunkIds = fn () => KnowledgeChunk::query()->whereIn('entry_id', $entryIds())->select('id');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Owner', $context->owner()->email],
                ['Knowledge entries', KnowledgeEntry::query()->count()],
                ['Approved entries', KnowledgeEntry::query()->approved()->count()],
                ['Chunks', KnowledgeChunk::query()->whereIn('entry_id', $entryIds())->count()],
                ['Indexed terms (rows)', DB::table('knowledge_terms')->whereIn('chunk_id', $chunkIds())->count()],
                ['Unique terms', DB::table('knowledge_terms')->whereIn('chunk_id', $chunkIds())->distinct()->count('term')],
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

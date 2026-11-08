<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\Text;
use Illuminate\Console\Command;

class BrainSearchCommand extends Command
{
    protected $signature = 'brain:search
        {query : Search phrase}
        {--project= : Filter by project}
        {--limit=8 : Number of results}';

    protected $description = 'Search the Neiro Brain knowledge base with BM25';

    public function handle(KnowledgeSearch $search): int
    {
        $result = $search->search(new SearchQuery(
            query: (string) $this->argument('query'),
            project: $this->option('project') ?: null,
            limit: (int) $this->option('limit'),
        ), 'cli');

        $this->line(sprintf(
            'Query: %s | terms: %s | candidates: %d | %d ms',
            $result->query,
            implode(', ', $result->terms),
            $result->candidates,
            $result->tookMs,
        ));

        if ($result->isEmpty()) {
            $this->warn('No matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Score', 'Kind', 'Project', 'Title', 'Snippet'],
            array_map(fn ($hit): array => [
                $hit->entry->id,
                number_format($hit->score, 3),
                $hit->entry->kind->label(),
                $hit->entry->project ?? '—',
                Text::limit($hit->entry->title, 50),
                Text::limit(strip_tags($hit->snippet), 80),
            ], $result->hits),
        );

        return self::SUCCESS;
    }
}

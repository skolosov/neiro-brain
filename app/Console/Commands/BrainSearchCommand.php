<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOwnerOption;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\Text;
use App\Support\OwnerContext;
use App\Support\OwnerResolver;
use Illuminate\Console\Command;

class BrainSearchCommand extends Command
{
    use ResolvesOwnerOption;

    protected $signature = 'brain:search
        {query : Search phrase}
        {--user= : Владелец знаний: id или email (обязательно)}
        {--project= : Filter by project}
        {--limit=8 : Number of results}';

    protected $description = 'Search the Neiro Brain knowledge base with BM25';

    public function handle(KnowledgeSearch $search, OwnerContext $context, OwnerResolver $resolver): int
    {
        if (! $this->setOwnerFromOption($resolver, $context)) {
            return self::FAILURE;
        }

        $result = $search->search(new SearchQuery(
            query: (string) $this->argument('query'),
            project: $this->option('project') ?: null,
            limit: (int) $this->option('limit'),
        ));

        $this->line(sprintf(
            'Owner: %s | query: %s | terms: %s | candidates: %d | %d ms',
            $context->owner()->email,
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

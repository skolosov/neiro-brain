<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\SearchHit;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\Dto\SearchResult;
use App\Settings\RetrievalSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Okapi BM25 over the knowledge_terms inverted index.
 *
 * score(D, Q) = Σ IDF(qi) · (tf · (k1 + 1)) / (tf + k1 · (1 − b + b · |D| / avgdl))
 * IDF(qi)     = ln(1 + (N − df(qi) + 0.5) / (df(qi) + 0.5))
 *
 * The corpus is small (a personal knowledge bank), so candidates are scored in
 * PHP and then boosted by importance, kind, project and recency. This keeps the
 * SQL portable between PostgreSQL and SQLite (used by the test suite).
 */
final class Bm25Search
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly RetrievalSettings $settings,
    ) {}

    public function search(SearchQuery $query): SearchResult
    {
        $startedAt = hrtime(true);
        $terms = $this->tokenizer->terms($query->query);

        if ($terms === []) {
            return new SearchResult($query->query, [], [], 0, 0);
        }

        $statuses = $query->includeDrafts || $this->settings->include_drafts
            ? ['approved', 'draft']
            : ['approved'];

        $base = fn () => DB::table('knowledge_chunks as c')
            ->join('knowledge_entries as e', 'e.id', '=', 'c.entry_id')
            ->whereIn('e.status', $statuses)
            ->when($query->project !== null && $query->project !== '', fn ($builder) => $builder->where('e.project', $query->project))
            ->when($query->kinds !== [], fn ($builder) => $builder->whereIn('e.kind', $query->kinds));

        $documents = (clone $base())->count();
        $averageLength = (float) ((clone $base())->avg('c.token_count') ?? 0);
        $averageLength = max(1.0, $averageLength);

        $documentFrequencies = (clone $base())
            ->join('knowledge_terms as t', 't.chunk_id', '=', 'c.id')
            ->whereIn('t.term', $terms)
            ->groupBy('t.term')
            ->select('t.term', DB::raw('COUNT(DISTINCT t.chunk_id) as df'))
            ->pluck('df', 'term')
            ->map(fn ($value): int => (int) $value)
            ->all();

        if ($documentFrequencies === []) {
            return new SearchResult($query->query, [], $terms, $this->elapsed($startedAt), 0);
        }

        $rows = (clone $base())
            ->join('knowledge_terms as t', 't.chunk_id', '=', 'c.id')
            ->whereIn('t.term', $terms)
            ->select([
                't.chunk_id',
                't.term',
                't.tf',
                'c.entry_id',
                'c.content',
                'c.token_count',
                'e.kind',
                'e.importance',
                'e.occurred_at',
                'e.created_at',
            ])
            ->limit($this->settings->candidate_limit)
            ->get();

        if ($rows->isEmpty()) {
            return new SearchResult($query->query, [], $terms, $this->elapsed($startedAt), 0);
        }

        $k1 = $this->settings->k1;
        $b = $this->settings->b;
        $queryTermCount = max(1, count($terms));
        $normalizedQuery = $this->tokenizer->normalize($query->query);
        $chunks = [];

        foreach ($rows as $row) {
            $term = (string) $row->term;
            $df = $documentFrequencies[$term] ?? 0;

            if ($df <= 0) {
                continue;
            }

            $idf = log(1 + (($documents - $df + 0.5) / ($df + 0.5)));
            $tf = (float) $row->tf;
            $documentLength = max(1.0, (float) $row->token_count);
            $denominator = $tf + $k1 * (1 - $b + $b * ($documentLength / $averageLength));

            if ($denominator <= 0) {
                continue;
            }

            $chunkId = (int) $row->chunk_id;

            if (! isset($chunks[$chunkId])) {
                $chunks[$chunkId] = [
                    'entry_id' => (int) $row->entry_id,
                    'content' => (string) $row->content,
                    'token_count' => $documentLength,
                    'kind' => (string) $row->kind,
                    'importance' => (int) $row->importance,
                    'occurred_at' => $row->occurred_at ? Carbon::parse($row->occurred_at) : null,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at) : null,
                    'score' => 0.0,
                    'matched' => [],
                ];
            }

            $chunks[$chunkId]['score'] += $idf * (($tf * ($k1 + 1)) / $denominator);
            $chunks[$chunkId]['matched'][$term] = true;
        }

        $bestByEntry = [];

        foreach ($chunks as $chunkId => $chunk) {
            $coverage = count($chunk['matched']) / $queryTermCount;

            if ($coverage < $this->settings->min_coverage) {
                continue;
            }

            $score = $chunk['score'];
            $score *= 0.4 + 0.6 * $coverage;

            if (mb_strlen($normalizedQuery) >= 4 && str_contains(mb_strtolower($chunk['content']), $normalizedQuery)) {
                $score *= 1.25;
            }

            $score *= 1 + $this->settings->importance_weight * ($chunk['importance'] - 3);
            $score *= $this->settings->kind_weights[$chunk['kind']] ?? 1.0;
            $score *= $this->freshnessFactor($chunk['occurred_at'] ?? $chunk['created_at']);

            $entryId = $chunk['entry_id'];

            if (! isset($bestByEntry[$entryId]) || $score > $bestByEntry[$entryId]['score']) {
                $bestByEntry[$entryId] = [
                    'chunk_id' => $chunkId,
                    'score' => $score,
                    'content' => $chunk['content'],
                    'matched' => array_keys($chunk['matched']),
                ];
            }
        }

        if ($bestByEntry === []) {
            return new SearchResult($query->query, [], $terms, $this->elapsed($startedAt), count($chunks));
        }

        $entries = KnowledgeEntry::query()
            ->whereIn('id', array_keys($bestByEntry))
            ->get()
            ->keyBy('id');

        $needles = array_values(array_unique([
            $query->query,
            ...$this->tokenizer->rawTokens($query->query),
        ]));

        $hits = [];

        foreach ($bestByEntry as $entryId => $best) {
            $entry = $entries->get($entryId);

            if (! $entry instanceof KnowledgeEntry) {
                continue;
            }

            if ($best['score'] < $this->settings->min_score) {
                continue;
            }

            $hits[] = new SearchHit(
                entry: $entry,
                score: $best['score'],
                snippet: Text::snippet($best['content'], $needles),
                matchedTerms: $best['matched'],
                chunkId: $best['chunk_id'],
            );
        }

        usort($hits, fn (SearchHit $a, SearchHit $b): int => $b->score <=> $a->score);
        $hits = array_slice($hits, 0, max(1, $query->limit));

        return new SearchResult(
            query: $query->query,
            hits: $hits,
            terms: $terms,
            tookMs: $this->elapsed($startedAt),
            candidates: count($chunks),
        );
    }

    private function freshnessFactor(?Carbon $occurredAt): float
    {
        if ($occurredAt === null || $this->settings->freshness_weight <= 0) {
            return 1.0;
        }

        $ageDays = max(0.0, $occurredAt->diffInDays(now()));
        $halfLife = max(1.0, $this->settings->freshness_half_life_days);

        return 1 + $this->settings->freshness_weight * exp(-$ageDays / $halfLife);
    }

    private function elapsed(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}

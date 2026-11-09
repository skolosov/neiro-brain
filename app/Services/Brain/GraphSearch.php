<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\GraphData;
use App\Services\Brain\Dto\GraphNode;
use App\Services\Brain\Dto\SearchHit;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\Dto\SearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Graph-aware search used by the admin graph page and the MCP tools.
 *
 * Returns the usual BM25 hits together with the nodes the result belongs to,
 * so a caller can highlight a subgraph instead of only reading a list.
 */
final class GraphSearch
{
    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly BrainGraph $graph,
    ) {}

    /**
     * @return array{result: SearchResult, graph: GraphData, focus: list<string>}
     */
    public function search(string $query, ?string $project = null, int $limit = 8, int $nodes = 400, string $source = 'admin'): array
    {
        $result = $this->search->search(
            new SearchQuery(query: $query, project: $project, limit: $limit),
            $source,
        );

        $built = $this->graph->build($nodes, 'all', $project);
        $graph = $built['graph'];
        $focus = array_map(
            fn (SearchHit $hit): string => 'e'.$hit->entry->id,
            $result->hits,
        );

        return [
            'result' => $result,
            'graph' => $this->focusGraph($graph, $focus),
            'focus' => $focus,
        ];
    }

    /**
     * Keep the focus nodes and their direct neighbours.
     *
     * @param  list<string>  $focus
     */
    public function focusGraph(GraphData $graph, array $focus): GraphData
    {
        if ($focus === []) {
            return $graph;
        }

        $keep = array_fill_keys($focus, true);

        foreach ($graph->edges as $edge) {
            if (isset($keep[$edge->source]) || isset($keep[$edge->target])) {
                $keep[$edge->source] = true;
                $keep[$edge->target] = true;
            }
        }

        $nodes = array_values(array_filter(
            $graph->nodes,
            fn (GraphNode $node): bool => isset($keep[$node->id]),
        ));

        $edges = array_values(array_filter(
            $graph->edges,
            fn ($edge): bool => isset($keep[$edge->source]) && isset($keep[$edge->target]),
        ));

        return new GraphData($nodes, $edges, $graph->projects, $graph->tags, $graph->truncated);
    }

    /**
     * Entries that are linked with the given entry (same project, shared tags
     * or shared keywords), used by the "Похожие записи" block.
     *
     * @return Collection<int, KnowledgeEntry>
     */
    public function relatedEntries(KnowledgeEntry $entry, int $limit = 6): Collection
    {
        $tags = array_values(array_filter(array_map('trim', $entry->tags ?? [])));

        $candidates = KnowledgeEntry::query()
            ->whereKeyNot($entry->getKey())
            ->searchable()
            ->when($entry->status !== null, fn ($query) => $query->where('status', $entry->status?->value))
            ->when($tags !== [], fn ($query) => $query->where(function ($query) use ($tags): void {
                foreach ($tags as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            }))
            ->orderByDesc('importance')
            ->limit(40)
            ->get();

        $vector = $this->termVector($entry);

        return $candidates
            ->map(function (KnowledgeEntry $candidate) use ($entry, $tags, $vector): array {
                $candidateTags = array_values(array_filter(array_map('trim', $candidate->tags ?? [])));
                $sharedTags = array_intersect($tags, $candidateTags);
                $sharedProject = $entry->project !== null && $entry->project === $candidate->project;
                $sharedKeywords = count(array_intersect_key($vector, $this->termVector($candidate)));

                return [
                    'entry' => $candidate,
                    'score' => count($sharedTags) * 3 + ($sharedProject ? 2 : 0) + min(5, $sharedKeywords),
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('entry');
    }

    /**
     * All index terms of an entry as a set: term => true.
     *
     * @return array<string, true>
     */
    private function termVector(KnowledgeEntry $entry): array
    {
        $terms = DB::table('knowledge_terms as t')
            ->join('knowledge_chunks as c', 'c.id', '=', 't.chunk_id')
            ->where('c.entry_id', $entry->getKey())
            ->distinct()
            ->pluck('t.term');

        $vector = [];

        foreach ($terms as $term) {
            $vector[(string) $term] = true;
        }

        return $vector;
    }
}

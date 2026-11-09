<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\GraphData;
use App\Services\Brain\Dto\GraphEdge;
use App\Services\Brain\Dto\GraphNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds the knowledge graph.
 *
 * Three layers, matching how the brain is organised:
 *   project  -> entry   (where the fact came from)
 *   topic    -> entry   (what the fact is about — tags, or the kind fallback)
 *   entry    <-> entry  (rare shared keywords, a weak "same thought" link)
 *
 * Shared by the admin graph page, the search page and the MCP graph tools.
 */
final class BrainGraph
{
    /** Entries must share at least this many keywords to be linked directly. */
    private const SHARED_KEYWORDS = 5;

    /** Keep the graph readable: each entry links to its few closest neighbours. */
    private const KEYWORD_EDGES_PER_ENTRY = 2;

    private const KEYWORD_LIMIT = 64;

    private const KEYWORD_MAX_ENTRIES = 40;

    public function __construct(
        private readonly TopicMap $topics,
    ) {}

    /**
     * @return array{graph: GraphData, hidden: int}
     */
    public function build(int $limit = 400, string $mode = 'all', ?string $project = null, ?string $topic = null): array
    {
        $limit = max(1, min(2000, $limit));

        $entries = $this->entriesQuery($mode, $project)
            ->orderByDesc('importance')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($topic !== null && $topic !== '') {
            $entries = $entries
                ->filter(fn (KnowledgeEntry $entry): bool => in_array($topic, $this->topics->of($entry), true))
                ->values();
        }

        $hidden = max(0, $this->entriesQuery($mode, $project)->count() - $entries->count());

        $nodes = [];
        $url = $this->urlBuilder();

        foreach ($entries as $entry) {
            $entryId = (int) $entry->id;
            $tags = array_values(array_filter(array_map(
                fn ($tag): string => trim((string) $tag),
                $entry->tags ?? [],
            )));

            $nodes['e'.$entryId] = new GraphNode(
                id: 'e'.$entryId,
                type: 'entry',
                refId: $entryId,
                label: (string) $entry->title,
                subtitle: $entry->kind?->label() ?? 'Запись',
                kind: $entry->kind instanceof KnowledgeKind ? $entry->kind->value : (string) $entry->kind,
                status: $entry->status instanceof EntryStatus ? $entry->status->value : (string) $entry->status,
                importance: (int) $entry->importance,
                project: trim((string) $entry->project),
                tags: $tags,
                summary: Str::limit((string) $entry->content, 220),
                chunks: (int) $entry->chunks()->count(),
                terms: (int) $entry->terms_count,
                size: max(3.0, 3.0 + ((int) $entry->terms_count) / 12 + (int) $entry->importance),
                timestamp: ($entry->occurred_at ?? $entry->updated_at)?->getTimestamp(),
                url: $url($entry),
                chunkIds: array_map('intval', $entry->chunks()->pluck('id')->all()),
                keywords: $this->topics->of($entry),
            );
        }

        $edges = [];

        // A single project would connect every card to one hub and the graph
        // collapses into a star. The project layer appears only when there is
        // something to compare, i.e. two or more projects.
        $showProjects = $entries
            ->pluck('project')
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->count() > 1;

        if ($showProjects) {
            foreach ($this->projectNodes($entries, $url) as $id => $node) {
                $nodes[$id] = $node;
            }
        }

        foreach ($this->topicNodes($entries, $url) as $id => $node) {
            $nodes[$id] = $node;
        }

        foreach ($entries as $entry) {
            $project = trim((string) $entry->project);

            if ($showProjects && $project !== '' && isset($nodes['p'.$project])) {
                $edges['p'.$project.'|e'.$entry->id] = new GraphEdge('p'.$project, 'e'.$entry->id, 1);
            }

            foreach ($this->topics->of($entry) as $topic) {
                // A topic node exists only when the theme is shared, so the edge
                // must not point at a node that was filtered out.
                if (! isset($nodes['t'.$topic])) {
                    continue;
                }

                $edges['t'.$topic.'|e'.$entry->id] = new GraphEdge('t'.$topic, 'e'.$entry->id, 1);
            }
        }

        $keywordsByEntry = $this->keywords(array_map('intval', $entries->modelKeys()));

        foreach ($this->keywordEdges($keywordsByEntry) as $edge) {
            $edges[$edge->key()] = $edge;
        }

        return [
            'graph' => new GraphData(
                nodes: array_values($nodes),
                edges: array_values($edges),
                projects: $this->projects(),
                tags: $this->topics->counts(60)->keys()->all(),
                truncated: $hidden,
            ),
            'hidden' => $hidden,
        ];
    }

    private function entriesQuery(string $mode, ?string $project): Builder
    {
        return KnowledgeEntry::query()
            ->when($mode === 'approved', fn (Builder $query): Builder => $query->approved())
            ->when($project !== null && $project !== '', fn (Builder $query): Builder => $query->where('project', $project));
    }

    /**
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return array<string, GraphNode>
     */
    private function projectNodes(Collection $entries, callable $url): array
    {
        $counts = $entries
            ->groupBy(fn (KnowledgeEntry $entry): string => trim((string) $entry->project))
            ->map(fn (Collection $items): int => $items->count())
            ->all();

        $nodes = [];

        foreach ($counts as $project => $count) {
            if ($project === '') {
                continue;
            }

            $nodes['p'.$project] = new GraphNode(
                id: 'p'.$project,
                type: 'project',
                refId: $count,
                label: $project,
                subtitle: 'Проект',
                kind: 'project',
                status: '',
                importance: 0,
                project: $project,
                tags: [],
                summary: '',
                chunks: $count,
                terms: 0,
                size: 16.0 + min(26.0, $count * 1.8),
                timestamp: null,
                url: $url(null, $project),
                chunkIds: [],
            );
        }

        return $nodes;
    }

    /**
     * Only themes shared by at least two cards become nodes: a topic used once
     * is just a tag of that card and would turn the graph into a star of
     * one-off labels.
     *
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return array<string, GraphNode>
     */
    private function topicNodes(Collection $entries, callable $url): array
    {
        $nodes = [];

        foreach ($this->topics->group($entries) as $topic => $items) {
            // A theme needs a few cards behind it: on a small base every theme is
            // worth showing, on a real one a one-card theme is just that card's tag.
            $threshold = $entries->count() < 12 ? 1 : 3;

            if ($items->count() < $threshold) {
                continue;
            }

            $nodes['t'.$topic] = new GraphNode(
                id: 't'.$topic,
                type: 'topic',
                refId: $items->count(),
                label: $topic,
                subtitle: 'Тема',
                kind: 'topic',
                status: '',
                importance: (int) $items->avg('importance'),
                project: '',
                tags: [],
                summary: '',
                chunks: $items->count(),
                terms: 0,
                size: 12.0 + min(30.0, $items->count() * 2.4),
                timestamp: null,
                url: $url(null, '', $topic),
                chunkIds: [],
                keywords: $items->take(6)->map(fn (KnowledgeEntry $entry): string => (string) $entry->title)->all(),
            );
        }

        return $nodes;
    }

    /**
     * @param  list<int>  $entryIds
     * @return array<int, list<string>>
     */
    private function keywords(array $entryIds): array
    {
        if ($entryIds === [] || ! Schema::hasTable('knowledge_terms')) {
            return [];
        }

        $terms = DB::table('knowledge_terms as t')
            ->join('knowledge_chunks as c', 'c.id', '=', 't.chunk_id')
            ->join('knowledge_entries as e', 'e.id', '=', 'c.entry_id')
            ->whereIn('c.entry_id', $entryIds)
            ->where('e.status', EntryStatus::Approved->value)
            ->groupBy('t.term')
            ->havingRaw('COUNT(DISTINCT c.entry_id) BETWEEN 2 AND '.self::KEYWORD_MAX_ENTRIES)
            ->orderByRaw('COUNT(DISTINCT c.entry_id) DESC')
            ->limit(self::KEYWORD_LIMIT)
            ->pluck('t.term')
            ->map(fn ($term): string => (string) $term)
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)
            ->values()
            ->all();

        if ($terms === []) {
            return [];
        }

        $rows = DB::table('knowledge_terms as t')
            ->join('knowledge_chunks as c', 'c.id', '=', 't.chunk_id')
            ->whereIn('c.entry_id', $entryIds)
            ->whereIn('t.term', $terms)
            ->groupBy('t.term', 'c.entry_id')
            ->select('t.term', 'c.entry_id', DB::raw('SUM(t.tf) as tf'))
            ->get();

        $byEntry = [];

        foreach ($rows as $row) {
            $byEntry[(int) $row->entry_id][(string) $row->term] = (int) $row->tf;
        }

        return $byEntry;
    }

    /**
     * Keyword links, pruned to the strongest neighbours of every entry so the
     * graph stays readable instead of turning into a hairball.
     *
     * @param  array<int, array<string, int>>  $keywordsByEntry
     * @return list<GraphEdge>
     */
    private function keywordEdges(array $keywordsByEntry): array
    {
        $pairs = [];

        foreach ($keywordsByEntry as $entryId => $terms) {
            $pairs[$entryId] = array_keys($terms);
        }

        $ids = array_keys($pairs);
        $candidates = [];

        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $shared = count(array_intersect($pairs[$ids[$i]], $pairs[$ids[$j]]));

                if ($shared < self::SHARED_KEYWORDS) {
                    continue;
                }

                $candidates[] = new GraphEdge('e'.$ids[$i], 'e'.$ids[$j], $shared);
            }
        }

        usort($candidates, fn (GraphEdge $left, GraphEdge $right): int => $right->weight <=> $left->weight);

        $degree = [];
        $edges = [];

        foreach ($candidates as $edge) {
            $left = $edge->source;
            $right = $edge->target;

            if (($degree[$left] ?? 0) >= self::KEYWORD_EDGES_PER_ENTRY || ($degree[$right] ?? 0) >= self::KEYWORD_EDGES_PER_ENTRY) {
                continue;
            }

            $degree[$left] = ($degree[$left] ?? 0) + 1;
            $degree[$right] = ($degree[$right] ?? 0) + 1;
            $edges[] = $edge;
        }

        return $edges;
    }

    public function resolve(string $nodeId): ?KnowledgeEntry
    {
        if (! str_starts_with($nodeId, 'e')) {
            return null;
        }

        $id = (int) substr($nodeId, 1);

        return $id > 0 ? KnowledgeEntry::query()->find($id) : null;
    }

    /**
     * Projects that have at least one entry, alphabetically.
     *
     * @return list<string>
     */
    public function projects(): array
    {
        return KnowledgeEntry::query()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->distinct()
            ->orderBy('project')
            ->pluck('project')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function urlBuilder(): callable
    {
        return function (?KnowledgeEntry $entry = null, string $project = '', string $topic = ''): string {
            if (! Route::has('moonshine.page')) {
                return '';
            }

            if ($entry !== null) {
                return route('moonshine.page', ['pageUri' => 'search-page', 'entry' => $entry->getKey()]);
            }

            if ($topic !== '') {
                return route('moonshine.page', ['pageUri' => 'search-page', 'topic' => $topic]);
            }

            if ($project !== '') {
                return route('moonshine.page', ['pageUri' => 'search-page', 'project' => $project]);
            }

            return route('moonshine.page', ['pageUri' => 'search-page']);
        };
    }
}

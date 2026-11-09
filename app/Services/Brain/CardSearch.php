<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\SearchHit;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\Dto\SearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Card-oriented view on top of BM25: everything the admin cards need in one
 * pass — hits with highlighted snippets, the facts each entry states and links
 * to the related cards. Also works without a query (browse mode), which keeps
 * the knowledge list useful when nothing is typed into the search box.
 */
final class CardSearch
{
    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly GraphSearch $graphSearch,
        private readonly TopicMap $topics,
    ) {}

    /**
     * Cards of one topic, ordered by importance. Topic mode needs no query:
     * it is the "разложить по темам" view of the brain.
     *
     * @return array{cards: list<array<string, mixed>>, took_ms: int, terms: list<string>, empty_reason: string|null}
     */
    public function runTopic(string $topic, int $limit = 30): array
    {
        $entries = $this->topics->entries($topic, $limit);
        $cards = [];

        foreach ($entries as $entry) {
            $cards[] = $this->cardFromEntry($entry, null);
        }

        return [
            'cards' => $cards,
            'took_ms' => 0,
            'terms' => [],
            'empty_reason' => $cards === [] ? 'В этой теме пока нет карточек.' : null,
        ];
    }

    /**
     * @param  list<string>  $kinds
     * @return array{
     *     cards: list<array<string, mixed>>,
     *     took_ms: int,
     *     terms: list<string>,
     *     empty_reason: string|null
     * }
     */
    public function run(
        string $query = '',
        ?string $project = null,
        array $kinds = [],
        int $limit = 8,
        bool $includeDrafts = false,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return $this->browse($project, $kinds, $limit, $includeDrafts);
        }

        $searchQuery = new SearchQuery(
            query: $query,
            project: $project,
            kinds: $kinds,
            limit: $limit,
            includeDrafts: $includeDrafts,
        );

        $result = $this->search->search($searchQuery, 'admin');
        $needles = [...$result->terms, $query];

        $cards = [];

        foreach ($result->hits as $hit) {
            $cards[] = $this->card($hit, $hit->snippet, $needles);
        }

        return [
            'cards' => $cards,
            'took_ms' => $result->tookMs,
            'terms' => $result->terms,
            'empty_reason' => $this->emptyReason($result),
        ];
    }

    /**
     * Recent entries shown when there is no query yet.
     *
     * @param  list<string>  $kinds
     * @return array{cards: list<array<string, mixed>>, took_ms: int, terms: list<string>, empty_reason: string|null}
     */
    private function browse(?string $project, array $kinds, int $limit, bool $includeDrafts): array
    {
        $entries = KnowledgeEntry::query()
            ->when(! $includeDrafts, fn ($query) => $query->approved())
            ->forProject($project)
            ->ofKinds($kinds)
            ->orderByDesc('importance')
            ->orderByDesc('updated_at')
            ->limit(max(1, min(50, $limit)))
            ->get();

        $cards = [];

        foreach ($entries as $entry) {
            $cards[] = $this->cardFromEntry($entry, null);
        }

        return [
            'cards' => $cards,
            'took_ms' => 0,
            'terms' => [],
            'empty_reason' => $cards === [] ? 'База пуста по этому фильтру. Запустите синхронизацию или снимите фильтры.' : null,
        ];
    }

    private function emptyReason(SearchResult $result): ?string
    {
        if (! $result->isEmpty()) {
            return null;
        }

        if ($result->terms === []) {
            return 'Запрос состоит только из стоп-слов. Попробуйте другие слова или термин из названия карточки.';
        }

        return 'Ничего не найдено. BM25 ищет по основам слов, а не по смыслу: попробуйте синоним, '
            .'название проекта или слово из карточки. Полный список — с пустым полем поиска.';
    }

    /**
     * @param  list<string>  $needles
     * @return array<string, mixed>
     */
    private function card(SearchHit $hit, string $snippet, array $needles): array
    {
        $card = $this->cardFromEntry($hit->entry, $hit->snippet);
        $card['snippet_html'] = Text::highlight($snippet, $needles);
        $card['score'] = round($hit->score, 3);
        $card['matched_terms'] = $hit->matchedTerms;

        return $card;
    }

    /**
     * Public card view for one entry (search panel, graph detail).
     *
     * @return array<string, mixed>
     */
    public function cardFor(KnowledgeEntry $entry): array
    {
        return $this->cardFromEntry($entry, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFromEntry(KnowledgeEntry $entry, ?string $snippet): array
    {
        $atoms = $this->atoms($entry);
        $tags = array_values(array_filter(array_map('trim', $entry->tags ?? [])));

        return [
            'id' => (int) $entry->id,
            'title' => (string) $entry->title,
            'kind' => $entry->kind?->value,
            'kind_label' => $entry->kind?->label(),
            'status' => $entry->status?->value,
            'status_label' => $entry->status?->label(),
            'project' => $entry->project,
            'tags' => $tags,
            'importance' => (int) $entry->importance,
            'confidence' => (float) $entry->confidence,
            'content' => (string) $entry->content,
            'snippet' => $snippet,
            'snippet_html' => e($snippet ?? (string) $entry->content),
            'atoms' => $atoms,
            'atoms_total' => $this->atomTotal($entry),
            'keywords' => $this->keywords($entry),
            'links' => $this->links($entry, $tags),
            'url' => $this->entryUrl($entry),
            'edit_url' => $this->editUrl($entry),
            'created_at' => $entry->created_at?->format('d.m.Y H:i'),
            'occurred_at_label' => ($entry->occurred_at ?? $entry->created_at)?->format('d.m.Y'),
            'updated_at' => $entry->updated_at?->format('d.m.Y H:i'),
            'last_verified_at' => $entry->last_verified_at?->format('d.m.Y'),
            'source' => $entry->source?->name,
            'conversation_url' => $this->conversationUrl($entry),
            'indexed' => $entry->indexed_at !== null,
        ];
    }

    /**
     * Facts of a card: stored details first, then sentences of the content.
     *
     * @return list<array{type: string, text: string}>
     */
    public function atoms(KnowledgeEntry $entry, int $limit = 8): array
    {
        $atoms = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $entry->details) ?: [] as $line) {
            $line = trim(preg_replace('/^[-*•\d.)]+\s*/u', '', $line) ?? $line);

            if ($line === '') {
                continue;
            }

            [$type, $text] = $this->atomType($line);
            $atoms[] = ['type' => $type, 'text' => $text];

            if (count($atoms) >= $limit) {
                return $atoms;
            }
        }

        $content = Text::normalize((string) $entry->content);
        $sentences = preg_split('/(?<=[.!?…])\s+(?=[A-ZА-ЯЁ0-9«"(])/u', $content) ?: [$content];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if (mb_strlen($sentence) < 12) {
                continue;
            }

            [$type, $text] = $this->atomType($sentence);
            $atoms[] = ['type' => $type, 'text' => $text];

            if (count($atoms) >= $limit) {
                break;
            }
        }

        return $atoms;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function atomType(string $text): array
    {
        $lower = mb_strtolower($text);

        foreach ([
            'команда' => ['git ', 'composer ', 'npm ', 'pnpm ', 'yarn ', 'php ', 'artisan', 'docker ', 'curl ', 'cd '],
            'решение' => ['решили', 'приняли решение', 'договорились', 'выбрали', 'пришли к'],
            'ошибка' => ['ошибка', 'ошибк', 'не работает', 'падает', 'сломал', 'failed', 'exception'],
            'стек' => ['laravel', 'postgres', 'php ', 'vue', 'redis', 'docker'],
            'процесс' => ['каждые', 'раз в ', 'каждый ', 'цикл', 'crud', 'процесс'],
        ] as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return [$type, $this->cleanAtom($text)];
                }
            }
        }

        return ['факт', $this->cleanAtom($text)];
    }

    private function cleanAtom(string $text): string
    {
        return Str::of($text)->replaceMatches('/\s+/u', ' ')->trim(' ', '-', '–', '—')->toString();
    }

    private function atomTotal(KnowledgeEntry $entry): int
    {
        $details = trim((string) $entry->details);

        if ($details !== '') {
            return max(1, substr_count($details, "\n") + 1);
        }

        $content = Text::normalize((string) $entry->content);

        return max(1, count(preg_split('/[.!?…]+(?=\s|$)/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: 1));
    }

    /**
     * Rare index terms of the entry — the closest thing BM25 knows to
     * "keywords" and what the MCP tools call key_terms.
     *
     * @return list<string>
     */
    public function keywords(KnowledgeEntry $entry, int $limit = 8): array
    {
        if (! Schema::hasTable('knowledge_terms')) {
            return [];
        }

        return DB::table('knowledge_terms as t')
            ->join('knowledge_chunks as c', 'c.id', '=', 't.chunk_id')
            ->where('c.entry_id', $entry->getKey())
            ->groupBy('t.term')
            ->select('t.term', DB::raw('SUM(t.tf) as tf'))
            ->orderByDesc('tf')
            ->limit($limit * 3)
            ->pluck('t.term')
            ->map(fn ($term): string => (string) $term)
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Links rendered under a card: project, related cards, graph, similar.
     *
     * @param  list<string>  $tags
     * @return list<array{type: string, label: string, url: string, icon: string}>
     */
    private function links(KnowledgeEntry $entry, array $tags): array
    {
        $links = [];

        if (($project = trim((string) $entry->project)) !== '') {
            $links[] = [
                'type' => 'project',
                'label' => $project,
                'url' => $this->graphUrl(['project' => $project]),
                'icon' => 'folder',
            ];
        }

        foreach (array_slice($tags, 0, 2) as $tag) {
            $links[] = [
                'type' => 'tag',
                'label' => $tag,
                'url' => $this->searchUrl(['q' => $tag]),
                'icon' => 'tag',
            ];
        }

        foreach ($this->graphSearch->relatedEntries($entry, 2) as $related) {
            $links[] = [
                'type' => 'related',
                'label' => (string) $related->title,
                'url' => $this->entryUrl($related),
                'icon' => 'link',
            ];
        }

        $links[] = [
            'type' => 'graph',
            'label' => 'На графе',
            'url' => $this->graphUrl(['focus' => 'e'.$entry->getKey()]),
            'icon' => 'graph',
        ];

        return $links;
    }

    public function graphUrl(array $params = []): string
    {
        return route('moonshine.page', ['pageUri' => 'graph-page', ...$params]);
    }

    public function searchUrl(array $params = []): string
    {
        return route('moonshine.page', ['pageUri' => 'search-page', ...$params]);
    }

    public function entryUrl(KnowledgeEntry $entry): string
    {
        return $this->searchUrl(['entry' => $entry->getKey()]);
    }

    public function editUrl(KnowledgeEntry $entry): string
    {
        return route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-form-page',
            'resourceItem' => $entry->getKey(),
        ]);
    }

    private function conversationUrl(KnowledgeEntry $entry): ?string
    {
        if ($entry->conversation_id === null) {
            return null;
        }

        return route('moonshine.resource.page', [
            'resourceUri' => 'conversation-resource',
            'pageUri' => 'conversation-detail-page',
            'resourceItem' => $entry->conversation_id,
        ]);
    }

    /**
     * All entries sharing a tag, used by the "по тегу" card view.
     *
     * @return list<KnowledgeEntry>
     */
    public function byTag(string $tag, int $limit = 24): array
    {
        return KnowledgeEntry::query()
            ->searchable()
            ->whereJsonContains('tags', $tag)
            ->orderByDesc('importance')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Entry ids grouped by tag for the sidebar cloud.
     *
     * @return Collection<string, int>
     */
    public function tagCloud(int $limit = 40): Collection
    {
        $rows = DB::table('knowledge_entries')
            ->whereIn('status', ['approved', 'draft'])
            ->whereNotNull('tags')
            ->pluck('tags');

        $counts = [];

        foreach ($rows as $json) {
            $tags = is_string($json) ? json_decode($json, true) : $json;

            foreach ((array) $tags as $tag) {
                $tag = trim((string) $tag);

                if ($tag !== '') {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return collect(array_slice($counts, 0, $limit, true));
    }
}

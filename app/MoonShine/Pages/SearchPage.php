<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\CardSearch;
use App\Services\Brain\GraphSearch;
use App\Services\Brain\TopicMap;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Knowledge as cards, laid out by topics.
 *
 * Three modes on one page: a BM25 query, a topic (cards of one theme), or the
 * latest entries. In every mode the right panel shows one card in full.
 */
#[Icon('magnifying-glass')]
#[Group('База знаний', 'book-open')]
#[Order(5)]
class SearchPage extends Page
{
    public function getTitle(): string
    {
        return 'Поиск';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /**
     * @return iterable<Renderable|string>
     */
    protected function components(): iterable
    {
        $request = request();

        $query = trim((string) $request->query('q', ''));
        $project = trim((string) $request->query('project', ''));
        $kind = trim((string) $request->query('kind', ''));
        $topic = trim((string) $request->query('topic', ''));
        $entryId = (int) $request->query('entry', 0);

        $cardSearch = app(CardSearch::class);
        $graphSearch = app(GraphSearch::class);
        $topics = app(TopicMap::class);

        if ($query === '' && $topic !== '') {
            $result = $cardSearch->runTopic(topic: $topic, limit: 30);
        } else {
            $result = $cardSearch->run(
                query: $query,
                project: $project !== '' ? $project : null,
                kinds: $kind !== '' && KnowledgeKind::tryFrom($kind) !== null ? [$kind] : [],
            );
        }

        $entry = $entryId > 0
            ? KnowledgeEntry::query()->find($entryId)
            : ($result['cards'] !== []
                ? KnowledgeEntry::query()->find($result['cards'][0]['id'])
                : null);

        $selected = $entry instanceof KnowledgeEntry ? $cardSearch->cardFor($entry) : null;

        $related = $entry instanceof KnowledgeEntry
            ? $graphSearch->relatedEntries($entry, 6)
                ->map(fn (KnowledgeEntry $item): array => [
                    'title' => (string) $item->title,
                    'url' => $cardSearch->entryUrl($item),
                ])
                ->all()
            : [];

        $data = [
            'result' => $result,
            'query' => $query,
            'project' => $project,
            'kind' => $kind,
            'topic' => $topic,
            'kinds' => KnowledgeKind::cases(),
            'total' => KnowledgeEntry::query()->count(),
            'topicCounts' => $topics->counts(36, min: 2),
            'selected' => $selected,
            'related' => $related,
            'activeId' => $selected['id'] ?? 0,
            'pageUrl' => $cardSearch->searchUrl(),
            'searchUrl' => $cardSearch->searchUrl(),
            'graphUrl' => $cardSearch->graphUrl(),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.search', $data)->render()),
        ];
    }
}

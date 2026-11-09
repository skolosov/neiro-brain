<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\KnowledgeEntry;
use App\Services\Brain\BrainGraph;
use App\Services\Brain\CardSearch;
use App\Services\Brain\GraphSearch;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Graph view of the whole brain: projects, entries and the links between them.
 *
 * Search on the page highlights matching entries, then a click opens the card
 * in the side panel.
 */
#[Icon('share')]
#[Group('База знаний', 'book-open')]
#[Order(15)]
class GraphPage extends Page
{
    public function getTitle(): string
    {
        return 'Граф';
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
        $focus = trim((string) $request->query('focus', ''));

        $graphBuilder = app(BrainGraph::class);
        $graphSearch = app(GraphSearch::class);
        $cardSearch = app(CardSearch::class);

        if ($query !== '') {
            $payload = $graphSearch->search(
                query: $query,
                project: $project !== '' ? $project : null,
                limit: 12,
            );

            $graph = $payload['graph'];
            $focusNodes = $payload['focus'];
            $results = $payload['result']->toArray();
        } else {
            $built = $graphBuilder->build(project: $project !== '' ? $project : null);
            $graph = $built['graph'];
            $focusNodes = $focus !== '' ? [$focus] : [];
            $results = [];

            if ($focus !== '') {
                $entry = $graphBuilder->resolve($focus);
                $focusNodes = $entry instanceof KnowledgeEntry ? ['e'.$entry->getKey()] : [];
            }
        }

        $data = [
            'graph' => $graph->toArray(),
            'focusNodes' => $focusNodes,
            'results' => $results,
            'query' => $query,
            'project' => $project,
            'truncated' => $graph->truncated,
            'stats' => [
                'nodes' => count($graph->nodes),
                'edges' => count($graph->edges),
            ],
            'listProjects' => $graphBuilder->projects(),
            'pageUrl' => $cardSearch->graphUrl(),
            'searchUrl' => $cardSearch->searchUrl(),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.graph', $data)->render()),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Brain\BrainGraph;
use App\Services\Brain\GraphSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Граф знаний текущего пользователя.
 *
 * Данные строят те же сервисы, что и в админке; меняются только адреса узлов:
 * в кабинете карточка открывается на `/app/entries/{id}`, а не в MoonShine.
 */
class GraphController extends Controller
{
    public function index(Request $request, BrainGraph $builder, GraphSearch $search): View
    {
        $query = trim((string) $request->query('q', ''));
        $project = trim((string) $request->query('project', ''));

        if ($query !== '') {
            $payload = $search->search(
                query: $query,
                project: $project !== '' ? $project : null,
                limit: 12,
            );

            $graph = $payload['graph'];
            $focusNodes = $payload['focus'];
            $results = $payload['result']->toArray();
        } else {
            $built = $builder->build(project: $project !== '' ? $project : null, mode: 'approved');

            $graph = $built['graph'];
            $focusNodes = [];
            $results = [];
        }

        $graphData = $graph->toArray();

        foreach ($graphData['nodes'] as &$node) {
            $node['url'] = match ($node['type']) {
                'entry' => route('app.entries.show', ['entry' => $node['ref_id']]),
                'project' => route('app.search', ['project' => $node['project']]),
                default => route('app.search', ['q' => $node['label']]),
            };
        }

        unset($node);

        return view('app.graph', [
            'graph' => $graphData,
            'focusNodes' => $focusNodes,
            'results' => $results,
            'query' => $query,
            'project' => $project,
            'truncated' => $graph->truncated,
            'stats' => [
                'nodes' => count($graph->nodes),
                'edges' => count($graph->edges),
            ],
            'listProjects' => $builder->projects(),
            'pageUrl' => route('app.graph'),
            'searchUrl' => route('app.search'),
        ]);
    }
}

@include('partials.brain-graph', [
    'graph' => $graph,
    'focusNodes' => $focusNodes,
    'results' => $results,
    'query' => $query,
    'project' => $project,
    'truncated' => $truncated,
    'stats' => $stats,
    'listProjects' => $listProjects,
    'pageUrl' => $pageUrl,
    'searchUrl' => $searchUrl,
    'entryUrl' => fn (int $id): string => $searchUrl.'?entry='.$id,
])

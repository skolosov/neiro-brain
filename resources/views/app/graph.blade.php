@extends('app.layout')

@section('title', 'Граф')

@section('content')
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
        'entryUrl' => fn (int $id): string => route('app.entries.show', ['entry' => $id]),
        'theme' => 'dark',
    ])
@endsection

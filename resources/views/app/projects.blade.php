@extends('app.layout')

@section('title', 'Проекты')

@section('content')
    <div class="kicker">База знаний</div>
    <h1>Проекты</h1>
    <p class="lead">Проекты, по которым у вас есть карточки. Число — сколько карточек в проекте.</p>

    @php
        $items = array_map(fn (array $project): array => [
            'name' => (string) $project['name'],
            'count' => (int) $project['cards'],
            'url' => route('app.search', ['project' => $project['name']]),
        ], $projects);
    @endphp

    @include('partials.project-list', [
        'projects' => array_values($items),
        'emptyTitle' => 'Проектов пока нет',
        'emptyText' => 'Карточки без проекта живут в общем профиле и окружении.',
    ])
@endsection

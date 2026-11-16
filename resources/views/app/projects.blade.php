@extends('app.layout')

@section('title', 'Проекты')

@section('content')
    <div class="kicker">База знаний</div>
    <h1>Проекты</h1>
    <p class="lead">Проекты, по которым у вас есть карточки. Число — сколько карточек в проекте.</p>

    @if ($projects === [])
        <section class="card">
            <h2>Проектов пока нет</h2>
            <p class="muted">Карточки без проекта живут в общем профиле и окружении.</p>
        </section>
    @else
        <ul class="entry-list">
            @foreach ($projects as $project)
                <li class="entry-item">
                    <h2>
                        <a href="{{ route('app.search', ['project' => $project['name']]) }}">{{ $project['name'] }}</a>
                    </h2>
                    <div class="meta-row">
                        <span>карточек: {{ $project['cards'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection

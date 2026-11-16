@extends('app.layout')

@section('title', 'Поиск')

@section('content')
    <div class="kicker">База знаний</div>
    <h1>Поиск</h1>
    <p class="lead">Ищем только по вашим карточкам. Пустой запрос показывает последние добавленные.</p>

    <form method="GET" action="{{ route('app.search') }}" class="card">
        <div class="field">
            <label for="q">Запрос</label>
            <input type="text" id="q" name="q" value="{{ $query }}" placeholder="Например: стек, проект, решение" autofocus>
        </div>

        @if ($projects !== [])
            <div class="field">
                <label for="project">Проект</label>
                <select id="project" name="project">
                    <option value="">Все проекты</option>
                    @foreach ($projects as $item)
                        <option value="{{ $item['name'] }}" @selected($project === $item['name'])>
                            {{ $item['name'] }} ({{ $item['cards'] }})
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="actions">
            <button type="submit" class="btn">Найти</button>
            @if ($query !== '' || $project !== '')
                <a class="btn secondary" href="{{ route('app.search') }}">Сбросить</a>
            @endif
        </div>
    </form>

    @if ($cards === [])
        <section class="card">
            @if ($searched)
                <h2>Ничего не найдено</h2>
                <p class="muted">
                    В вашей базе нет карточек по запросу «{{ $query }}».
                    Попробуйте синоним, название проекта или слово из заголовка.
                </p>
            @else
                <h2>Карточек пока нет</h2>
                <p class="muted">Как только агент запишет первые факты, они появятся здесь.</p>
            @endif
        </section>
    @else
        <p class="muted">Найдено карточек: {{ count($cards) }}.</p>

        <ul class="entry-list">
            @foreach ($cards as $card)
                <li class="entry-item">
                    <h2><a href="{{ route('app.entries.show', ['entry' => $card['id']]) }}">{{ $card['title'] }}</a></h2>
                    <div class="meta-row">
                        @if ($card['project'])
                            <span>{{ $card['project'] }}</span>
                        @endif
                        @if ($card['theme'])
                            <span>тема: {{ $card['theme'] }}</span>
                        @endif
                        @if ($card['kind'])
                            <span>{{ $card['kind'] }}</span>
                        @endif
                        <span>важность: {{ $card['importance'] }}/5</span>
                        @if ($card['updated_at'])
                            <span>{{ $card['updated_at'] }}</span>
                        @endif
                    </div>
                    <p class="entry-text">{{ $card['summary'] }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection

@extends('app.layout')

@section('title', 'Поиск')

@section('content')
    <div class="kicker">База знаний</div>
    <h1>Поиск</h1>
    <p class="lead">Ищем только по вашим карточкам. Пустой запрос показывает последние добавленные.</p>

    @include('partials.search-results', [
        'action' => route('app.search'),
        'query' => $query,
        'project' => $project,
        'projectOptions' => $projects,
        'cards' => $cards,
        'cardUrl' => fn (array $card): string => route('app.entries.show', ['entry' => $card['id']]),
        'listTitle' => $cards === [] ? null : 'Найдено карточек: '.count($cards),
        'emptyTitle' => $searched ? 'Ничего не найдено' : 'Карточек пока нет',
        'emptyText' => $searched
            ? 'В вашей базе нет карточек по запросу «'.$query.'». Попробуйте синоним, название проекта или слово из заголовка.'
            : 'Как только агент запишет первые факты, они появятся здесь.',
        'autofocus' => true,
    ])
@endsection

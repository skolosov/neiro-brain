<?php

declare(strict_types=1);

/**
 * Search form and results of the knowledge base.
 *
 * One partial for the cabinet and MoonShine: the form action, the project
 * filter, the kind filter and the topic chips are flags, so the admin page
 * keeps what only it has (highlighted snippets, kind filter, topics) without
 * a second copy of the markup.
 *
 * The list itself is a row per card, rendered by `partials.knowledge-card`;
 * the caller passes a `$cardUrl` closure so the route stays outside the view.
 *
 * @var string $action
 * @var string $query
 * @var string $project
 * @var string $kind
 * @var string $topic
 * @var array<int, array{name: string, cards: int}> $projectOptions
 * @var bool $showProjectInput
 * @var array<int, mixed> $kinds
 * @var array<string, int> $topics
 * @var callable(string): string|null $topicUrl
 * @var array<int, array<string, mixed>> $cards
 * @var callable(array<string, mixed>): string $cardUrl
 * @var bool $highlight
 * @var int $activeId
 * @var string|null $listTitle
 * @var string|null $listMeta
 * @var string|null $emptyTitle
 * @var string $emptyText
 * @var array{title: string, url: string}|null $outsideCard
 * @var string|null $queryPlaceholder
 * @var bool $autofocus
 */
$query = (string) ($query ?? '');
$project = (string) ($project ?? '');
$kind = (string) ($kind ?? '');
$topic = (string) ($topic ?? '');
$cards = (array) ($cards ?? []);
$projectOptions = (array) ($projectOptions ?? []);
$kinds = (array) ($kinds ?? []);
$topics = (array) ($topics ?? []);
$highlight = (bool) ($highlight ?? false);
$activeId = (int) ($activeId ?? 0);
$outsideCard = $outsideCard ?? null;
$emptyText = (string) ($emptyText ?? 'Ничего не найдено.');
$topicUrl = $topicUrl ?? static fn (string $name): string => $name === ''
    ? $action
    : $action.'?topic='.urlencode($name);
$cardUrl = $cardUrl ?? static fn (array $card): string => (string) ($card['url'] ?? $action.'?entry='.$card['id']);
?>
<form method="GET" action="{{ $action }}" class="nb-card nb-search-form">
    <div class="nb-search-bar">
        <div class="nb-search-input-wrap">
            <label class="nb-label" for="brain-q">Запрос</label>
            <input id="brain-q" class="nb-input" type="search" name="q" value="{{ $query }}"
                   placeholder="{{ $queryPlaceholder ?? 'Например: стек, проект, решение' }}"
                   autocomplete="off" @if (! empty($autofocus)) autofocus @endif>
        </div>

        @if ($kinds !== [])
            <div>
                <label class="nb-label" for="brain-kind">Тип</label>
                <select id="brain-kind" class="nb-select" name="kind">
                    <option value="">Любой</option>
                    @foreach ($kinds as $case)
                        <option value="{{ $case->value }}" @selected($kind === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($projectOptions !== [])
            <div>
                <label class="nb-label" for="brain-project">Проект</label>
                <select id="brain-project" class="nb-select" name="project">
                    <option value="">Все проекты</option>
                    @foreach ($projectOptions as $item)
                        <option value="{{ $item['name'] }}" @selected($project === $item['name'])>
                            {{ $item['name'] }} ({{ $item['cards'] }})
                        </option>
                    @endforeach
                </select>
            </div>
        @elseif (! empty($showProjectInput))
            <div>
                <label class="nb-label" for="brain-project">Проект</label>
                <input id="brain-project" class="nb-input" type="text" name="project" value="{{ $project }}"
                       placeholder="любой" autocomplete="off">
            </div>
        @endif

        <button type="submit" class="nb-btn nb-btn-primary">Найти</button>

        @if ($query !== '' || $project !== '' || $kind !== '' || $topic !== '')
            <a href="{{ $action }}" class="nb-btn nb-btn-secondary">Сбросить</a>
        @endif
    </div>

    @if ($topics !== [])
        <div class="nb-search-topics">
            <div class="nb-label">Темы</div>
            <div class="nb-chip-row">
                <a class="nb-chip {{ $topic === '' && $query === '' ? 'nb-chip-active' : '' }}"
                   href="{{ $topicUrl('') }}">Все</a>
                @foreach ($topics as $name => $count)
                    <a class="nb-chip {{ $topic === (string) $name ? 'nb-chip-active' : '' }}"
                       href="{{ $topicUrl((string) $name) }}">
                        {{ $name }} <span class="nb-chip-count">{{ $count }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</form>

<div class="nb-search-main">
    @if (! empty($listTitle))
        <div class="nb-results-head">
            <h2 class="nb-results-title">{{ $listTitle }}</h2>
            @if (! empty($listMeta))
                <span class="nb-results-meta">{{ $listMeta }}</span>
            @endif
        </div>
    @endif

    @if ($outsideCard !== null)
        <div class="nb-empty-hint">
            Открыта карточка «{{ $outsideCard['title'] }}» — она не входит в этот список,
            полный текст справа. <a href="{{ $outsideCard['url'] }}">Показать список</a>
        </div>
    @endif

    @if ($cards === [])
        <div class="nb-empty-hint">
            @if (! empty($emptyTitle))
                <strong>{{ $emptyTitle }}.</strong>
            @endif
            {{ $emptyText }}
        </div>
    @else
        <ul class="nb-results nb-entry-list">
            @foreach ($cards as $card)
                @include('partials.knowledge-card', [
                    'entry' => $card,
                    'url' => $cardUrl($card),
                    'active' => $activeId > 0 && $activeId === (int) ($card['id'] ?? 0),
                    'highlight' => $highlight,
                ])
            @endforeach
        </ul>
    @endif
</div>

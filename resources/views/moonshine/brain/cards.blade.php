<?php

declare(strict_types=1);

/**
 * Cards screen of the admin: the search form, the list of cards and the panel
 * of the open card.
 *
 * The markup itself is shared with the search page: `partials.search-results`
 * draws the form and the list, `partials.entry-panel` draws the open card.
 * Only the data and the URLs are the page's own — the card page lives in
 * MoonShine, so every link is passed in instead of being built here.
 *
 * @var array<string, mixed> $result
 * @var array<string, mixed>|null $selected
 * @var list<array{title: string, url: string}> $related
 * @var \Illuminate\Support\Collection<int, int> $topicCounts
 * @var string $query
 * @var string $project
 * @var string $kind
 * @var string $topic
 * @var array<int, mixed> $kinds
 * @var int $activeId
 * @var string $pageUrl
 * @var string $searchUrl
 * @var string $graphUrl
 */
$activeId = (int) $activeId;
$cards = $result['cards'];

$listTitle = match (true) {
    $query !== '' => 'Результаты: '.count($cards),
    $topic !== '' => 'Тема «'.$topic.'»: '.count($cards),
    default => 'Последние знания',
};

$listMeta = match (true) {
    $query !== '' => $result['took_ms'].' мс · сортировка по score BM25',
    $topic !== '' => 'карточки темы, по важности',
    default => 'выберите карточку или начните с запроса',
};

// The open card is shown in the right-hand panel in full, so the list marks it
// instead of repeating it.
$resultIds = array_map('intval', array_column($cards, 'id'));
$outsideId = $activeId > 0 && ! in_array($activeId, $resultIds, true);

$outsideCard = $outsideId && $selected !== null
    ? ['title' => (string) $selected['title'], 'url' => $pageUrl]
    : null;

$cardUrl = fn (array $card): string => (string) ($card['url'] ?? $pageUrl.'?entry='.$card['id']);
$topicUrl = fn (string $name): string => $name === '' ? $pageUrl : $pageUrl.'?topic='.urlencode($name);
?>
<div class="nb-search-layout">
    @include('partials.search-results', [
        'action' => $pageUrl,
        'query' => $query,
        'project' => $project,
        'kind' => $kind,
        'kinds' => $kinds,
        'topics' => $topicCounts->all(),
        'topic' => $topic,
        'topicUrl' => $topicUrl,
        'cards' => $cards,
        'cardUrl' => $cardUrl,
        'highlight' => true,
        'activeId' => $activeId,
        'listTitle' => $listTitle,
        'listMeta' => $listMeta,
        'emptyText' => (string) ($result['empty_reason'] ?? 'Ничего не найдено.'),
        'outsideCard' => $outsideCard,
        'showProjectInput' => true,
        'queryPlaceholder' => 'например: стеки, решения по Sklad, стиль кода',
    ])

    <aside class="nb-search-side">
        @if ($selected !== null)
            @include('partials.entry-panel', [
                'entry' => $selected,
                'related' => $related,
                'tagUrl' => fn (string $tag): string => $searchUrl.'?q='.urlencode($tag),
                'actions' => [
                    ['label' => 'Редактировать', 'url' => $selected['edit_url'], 'variant' => 'nb-btn-primary'],
                    ['label' => 'На графе', 'url' => $graphUrl.'?focus=e'.$selected['id']],
                ],
            ])
        @else
            <div class="nb-peek">
                <h3>Карточка знания</h3>
                <p class="nb-peek-text">
                    Нажмите на заголовок карточки — справа откроется полный текст:
                    что утверждает карточка, теги и похожие записи.
                </p>
                <div class="nb-graph-mini">
                    <a href="{{ $graphUrl }}">Открыть граф →</a>
                </div>
            </div>

            <div class="nb-peek">
                <h3>Как устроен поиск</h3>
                <p class="nb-peek-text">
                    Карточка — это тема: заголовок, краткое описание (что она утверждает)
                    и полный текст. Поиск идёт по основам слов (Snowball, ru+en) через BM25
                    и отдаёт сначала описания — полный текст читается только для нужных карточек.
                    Поэтому вся база не перечитывается целиком.
                </p>
                <div class="nb-graph-mini">
                    <a href="{{ $graphUrl }}">Открыть граф →</a>
                </div>
            </div>
        @endif
    </aside>
</div>

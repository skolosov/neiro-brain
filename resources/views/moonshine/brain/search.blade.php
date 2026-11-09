@include('moonshine.brain.styles')
@include('moonshine.brain.cards')

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Знания</h1>
            <p class="nb-subtitle">
                {{ $total }} карточек · профиль, проекты и окружение
                @if ($result['terms'] !== [])
                    · найдено по: {{ implode(', ', array_slice($result['terms'], 0, 8)) }}
                @endif
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $graphUrl }}" class="nb-btn nb-btn-primary">Граф</a>
            <form method="POST" action="{{ route('brain.admin.sync') }}">
                @csrf
                <button type="submit" class="nb-btn nb-btn-secondary">Синхронизировать</button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ $pageUrl }}" class="nb-card">
        <div class="nb-search-bar">
            <div class="nb-search-input-wrap">
                <label class="nb-label" for="nb-q">Запрос</label>
                <input id="nb-q" class="nb-input" type="search" name="q" value="{{ $query }}"
                       placeholder="например: стеки, решения по Sklad, стиль кода" autocomplete="off">
            </div>
            <div>
                <label class="nb-label" for="nb-kind">Тип</label>
                <select id="nb-kind" class="nb-select" name="kind">
                    <option value="">Любой</option>
                    @foreach ($kinds as $case)
                        <option value="{{ $case->value }}" @selected($kind === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="nb-label" for="nb-project">Проект</label>
                <input id="nb-project" class="nb-input" type="text" name="project" value="{{ $project }}" placeholder="любой" autocomplete="off">
            </div>
            <button type="submit" class="nb-btn nb-btn-primary">Найти</button>
            @if ($query !== '' || $project !== '' || $kind !== '')
                <a href="{{ $pageUrl }}" class="nb-btn nb-btn-secondary">Сбросить</a>
            @endif
        </div>

        @if ($topicCounts->isNotEmpty())
            <div style="margin-top:.9rem">
                <div class="nb-label" style="margin-bottom:.4rem">Темы</div>
                <div class="nb-chip-row">
                    <a class="nb-chip {{ $topic === '' && $query === '' ? 'nb-chip-active' : '' }}" href="{{ $pageUrl }}">Все</a>
                    @foreach ($topicCounts as $name => $count)
                        <a class="nb-chip {{ $topic === $name ? 'nb-chip-active' : '' }}"
                           href="{{ $pageUrl.'?topic='.urlencode($name) }}">
                            {{ $name }} <span class="nb-chip-count">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </form>

    <div class="nb-search-layout">
        <div class="nb-search-main">
            <div class="nb-results-head">
                <h2 class="nb-results-title">
                    @if ($query !== '')
                        Результаты: {{ count($result['cards']) }}
                    @elseif ($topic !== '')
                        Тема «{{ $topic }}»: {{ count($result['cards']) }}
                    @else
                        Последние знания
                    @endif
                </h2>
                <span class="nb-results-meta">
                    @if ($query !== '')
                        {{ $result['took_ms'] }} мс · сортировка по score BM25
                    @elseif ($topic !== '')
                        карточки темы, по важности
                    @else
                        выберите карточку или начните с запроса
                    @endif
                </span>
            </div>

            @if ($result['cards'] === [])
                <div class="nb-empty-hint">{{ $result['empty_reason'] ?? 'Ничего не найдено.' }}</div>
            @endif

            @php
                // The open card is shown in the right-hand panel in full, so the
                // list marks it instead of repeating it.
                $resultIds = array_column($result['cards'], 'id');
                $activeOutside = $activeId > 0 && ! in_array($activeId, $resultIds, true);
            @endphp

            @if ($activeOutside && $selected !== null)
                <div class="nb-empty-hint">
                    Открыта карточка «{{ $selected['title'] }}» — она не входит в этот список,
                    полный текст справа. <a href="{{ $pageUrl }}">Показать список</a>
                </div>
            @endif

            <div class="nb-results nb-entry-list">
                @foreach ($result['cards'] as $card)
                    @include('moonshine.brain.partials.knowledge-card', ['card' => $card, 'activeId' => $activeId])
                @endforeach
            </div>
        </div>

        <aside class="nb-search-side">
            @if ($selected !== null)
                @include('moonshine.brain.partials.entry-panel', ['card' => $selected])
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
            @endif

            @if ($selected === null)
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
</div>

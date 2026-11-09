@include('moonshine.brain.styles')
@include('moonshine.brain.cards')

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Граф знаний</h1>
            <p class="nb-subtitle">
                Узлы: проекты, темы и карточки. Связи: проект → карточка, тема → карточка
                и общие редкие слова. Показано {{ $stats['nodes'] }} узлов и {{ $stats['edges'] }} связей.
                @if ($truncated > 0)
                    Ещё {{ $truncated }} записей не попало — отфильтруйте по проекту.
                @endif
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $searchUrl }}" class="nb-btn nb-btn-secondary">К карточкам</a>
            <form method="POST" action="{{ route('brain.admin.sync') }}">
                @csrf
                <button type="submit" class="nb-btn nb-btn-primary">Синхронизировать</button>
            </form>
        </div>
    </div>

    <div class="nb-chip-row">
        <a class="nb-chip {{ $project === '' ? 'nb-chip-active' : '' }}" href="{{ $pageUrl }}">Все проекты</a>
        @foreach ($listProjects as $name)
            <a class="nb-chip {{ $project === $name ? 'nb-chip-active' : '' }}"
               href="{{ $pageUrl.'?project='.urlencode($name) }}">{{ $name }}</a>
        @endforeach
    </div>

    <div class="nb-graph-layout">
        <div class="nb-graph-wrap" id="nb-graph-wrap">
            <div class="nb-graph-toolbar">
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="zoom-in" title="Приблизить">+</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="zoom-out" title="Отдалить">−</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="fit" title="Вписать всё">Вписать</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="cluster" title="Раскрасить по проектам">Вид</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="fullscreen" title="На весь экран">⛶</button>
            </div>

            <input type="search" class="nb-input nb-graph-search" placeholder="Найти на графе" value="{{ $query }}"
                   data-nb-graph-search autocomplete="off">

            <canvas class="nb-graph-canvas" id="nb-graph-canvas"
                    data-nb-graph-focus="{{ implode(',', $focusNodes) }}"></canvas>

            <div class="nb-graph-legend">
                <span><i style="background:#8b5cf6"></i> проект</span>
                <span><i style="background:#0ea5e9"></i> тема</span>
                <span><i style="background:#22c55e"></i> карточка</span>
                <span><i style="background:#eab308"></i> найдено по запросу</span>
                <span>Клик — карточка, двойной клик — открыть, Esc — сброс</span>
            </div>
        </div>

        <aside class="nb-graph-side">
            <div class="nb-peek" id="nb-graph-detail">
                <h3>Карточка узла</h3>
                <p class="nb-peek-text">
                    Кликните узел на графе — здесь появится карточка знания:
                    краткий ответ, факты, теги и связи.
                </p>
                @if ($results !== [])
                    <div class="nb-card-w-links" style="margin-top:.6rem">
                        @foreach (array_slice($results, 0, 5) as $hit)
                            <a class="nb-card-w-link" href="{{ $searchUrl.'?entry='.$hit['id'] }}">
                                <span class="nb-card-w-link-dot"></span>
                                <span class="nb-card-w-link-label">{{ $hit['title'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>

<script type="application/json" id="nb-graph-data">@json($graph)</script>
<script type="module" src="{{ asset('brain/graph.js') }}?v={{ @filemtime(public_path('brain/graph.js')) ?: 1 }}"></script>

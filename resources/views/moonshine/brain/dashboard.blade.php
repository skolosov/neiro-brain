@include('moonshine.brain.styles')
@include('moonshine.brain.cards')

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Что лежит в базе</h1>
            <p class="nb-subtitle">
                Карточек: {{ $total }} · в индексе BM25: {{ $indexed }} из {{ $approved }}
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $searchUrl }}" class="nb-btn nb-btn-secondary">Карточки</a>
            <a href="{{ $graphUrl }}" class="nb-btn nb-btn-secondary">Граф</a>
        </div>
    </div>

    <div class="nb-grid-4">
        <div class="nb-card">
            <div class="nb-metric-label">Карточек</div>
            <div class="nb-metric-value">{{ $total }}</div>
            <div class="nb-metric-hint">{{ $approved }} подтверждено · {{ $drafts }} черновиков</div>
        </div>
        <div class="nb-card">
            <div class="nb-metric-label">Проектов</div>
            <div class="nb-metric-value">{{ $projects }}</div>
            <div class="nb-metric-hint">по ним раскладываются карточки</div>
        </div>
        <div class="nb-card">
            <div class="nb-metric-label">Тем</div>
            <div class="nb-metric-value">{{ $topics->count() }}</div>
            <div class="nb-metric-hint">сгруппированы по смыслу</div>
        </div>
        <div class="nb-card">
            <div class="nb-metric-label">Чанков</div>
            <div class="nb-metric-value">{{ $chunks }}</div>
            <div class="nb-metric-hint">по ним ищет BM25</div>
        </div>
    </div>

    <div class="nb-grid-2">
        <div class="nb-card">
            <div class="nb-card-head">
                <h2 class="nb-section-title">Карточки по неделям</h2>
                <span class="nb-results-meta">последние 12 недель</span>
            </div>
            <div class="nb-chart">
                @foreach ($series as $point)
                    <div class="nb-chart-bar" title="{{ $point['label'] }}: {{ $point['value'] }}">
                        <div class="nb-chart-fill" style="height: {{ max(2, (int) round($point['value'] / $seriesMax * 100)) }}%"></div>
                        <span class="nb-chart-label">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="nb-card">
            <div class="nb-card-head">
                <h2 class="nb-section-title">Темы</h2>
                <a href="{{ $searchUrl }}">Все →</a>
            </div>
            @forelse ($topics as $topic => $count)
                <a class="nb-topic-row" href="{{ $searchUrl.'?topic='.urlencode($topic) }}">
                    <span class="nb-topic-name">{{ $topic }}</span>
                    <span class="nb-topic-bar"><i style="width: {{ (int) round($count / $topicsMax * 100) }}%"></i></span>
                    <span class="nb-topic-count">{{ $count }}</span>
                </a>
            @empty
                <p class="nb-empty">Тем пока нет: добавьте карточку через MCP, API или админку.</p>
            @endforelse
        </div>
    </div>

    <div class="nb-card">
        <div class="nb-card-head">
            <h2 class="nb-section-title">Последние карточки</h2>
            <a href="{{ $searchUrl }}">Все карточки →</a>
        </div>
        @forelse ($recentCards as $card)
            <div class="nb-row">
                <div style="min-width:0">
                    <div class="nb-row-title"><a href="{{ $card['url'] }}">{{ $card['title'] }}</a></div>
                    <div class="nb-meta">
                        {{ $card['kind_label'] }}
                        @if ($card['project']) · {{ $card['project'] }} @endif
                        · {{ $card['occurred_at_label'] }}
                        @if ($card['tags'] !== []) · {{ implode(', ', array_slice($card['tags'], 0, 3)) }} @endif
                    </div>
                </div>
                @if ($card['status'] !== 'approved')
                    <span class="nb-pill nb-pill-draft">{{ $card['status_label'] }}</span>
                @endif
            </div>
        @empty
            <p class="nb-empty">Пока пусто. Добавьте карточку через MCP или API — она появится здесь.</p>
        @endforelse
    </div>
</div>

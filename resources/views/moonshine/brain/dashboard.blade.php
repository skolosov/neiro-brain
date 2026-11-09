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
                @if ($lastRun)
                    · последняя синхронизация {{ $lastRun->finished_at?->diffForHumans() ?? 'идёт' }}
                @else
                    · синхронизация ещё не запускалась
                @endif
            </p>
        </div>
        <div class="nb-actions">
            <form method="POST" action="{{ route('brain.admin.sync') }}">
                @csrf
                <button type="submit" class="nb-btn nb-btn-primary">Синхронизировать</button>
            </form>
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
            <div class="nb-metric-hint">{{ $chunks }} чанков в индексе</div>
        </div>
        <div class="nb-card">
            <div class="nb-metric-label">Очередь</div>
            <div class="nb-metric-value">{{ $pending }}</div>
            <div class="nb-metric-hint">{{ $summarized }} диалогов уже пересказано</div>
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
                <p class="nb-empty">Тем пока нет: запустите синхронизацию или добавьте карточку.</p>
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
            <p class="nb-empty">Пока пусто. Пропустите диалог через прокси — карточки появятся после синхронизации.</p>
        @endforelse
    </div>

    <div class="nb-card">
        <div class="nb-card-head">
            <h2 class="nb-section-title">Здоровье источников</h2>
            <a href="{{ route('moonshine.resource.page', ['resourceUri' => 'knowledge-source-resource', 'pageUri' => 'knowledge-source-index-page']) }}">Источники →</a>
        </div>
        <div class="nb-table">
            <div class="nb-table-row nb-table-head">
                <span>Источник</span>
                <span>Диалогов</span>
                <span>Карточек</span>
                <span>Синхронизация</span>
            </div>
            @foreach ($sources as $source)
                <div class="nb-table-row">
                    <span>
                        <span class="nb-status-dot {{ $source['error'] ? 'nb-status-error' : ($source['enabled'] ? 'nb-status-ok' : '') }}"></span>
                        {{ $source['name'] }}
                        <span class="nb-meta">{{ $source['type'] }}</span>
                    </span>
                    <span>{{ $source['conversations'] }}</span>
                    <span>{{ $source['cards'] }}</span>
                    <span class="nb-meta">
                        @if ($source['error'])
                            {{ \Illuminate\Support\Str::limit($source['error'], 60) }}
                        @elseif ($source['last_synced_at'])
                            {{ $source['last_synced_at']->diffForHumans() }}
                        @else
                            ещё не было
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="nb-card">
        <div class="nb-card-head">
            <h2 class="nb-section-title">Последние поиски</h2>
            <a href="{{ route('moonshine.resource.page', ['resourceUri' => 'search-log-resource', 'pageUri' => 'search-log-index-page']) }}">Журнал →</a>
        </div>
        @forelse ($recentSearches as $search)
            <div class="nb-row">
                <div class="nb-row-title">{{ $search->query }}</div>
                <span class="nb-meta">{{ $search->source }} · {{ $search->results_count }} · {{ $search->latency_ms }} мс</span>
            </div>
        @empty
            <p class="nb-empty">Пока никто не искал.</p>
        @endforelse
    </div>
</div>

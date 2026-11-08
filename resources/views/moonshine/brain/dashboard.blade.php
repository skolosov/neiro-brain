@include('moonshine.brain.styles')

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Neiro Brain — обзор</h1>
            <p class="nb-subtitle">Личная база знаний для LLM: BM25, MCP и прозрачный прокси.</p>
        </div>
        <div class="nb-actions">
            <form method="POST" action="{{ route('brain.admin.sync') }}">
                @csrf
                <button type="submit" class="nb-btn nb-btn-primary">Синхронизировать сейчас</button>
            </form>
            <form method="POST" action="{{ route('brain.admin.reindex') }}">
                @csrf
                <button type="submit" class="nb-btn nb-btn-secondary">Переиндексировать</button>
            </form>
            <a href="{{ route('moonshine.page', ['pageUri' => 'settings-page']) }}" class="nb-btn nb-btn-secondary">Настройки</a>
        </div>
    </div>

    <div class="nb-grid-4">
        @foreach ([
            ['Знаний', $entries, 'всего'],
            ['Подтверждено', $approved, 'доступно поиску'],
            ['Черновиков', $drafts, 'ждут проверки'],
            ['Диалогов', $conversations, $pending.' в очереди'],
            ['Чанков', $chunks, 'BM25-индекс'],
            ['Источников', $sources, 'подключено'],
            ['Поисков за 24ч', $searches24h, 'MCP + прокси'],
            ['Последний синк', $lastRun?->finished_at?->format('H:i') ?? '—', $lastRun?->status ?? 'ещё не было'],
        ] as [$label, $value, $hint])
            <div class="nb-card">
                <div class="nb-metric-label">{{ $label }}</div>
                <div class="nb-metric-value">{{ $value }}</div>
                <div class="nb-metric-hint">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="nb-grid-2">
        <div class="nb-card">
            <div class="nb-card-head">
                <h2 class="nb-section-title">Последние знания</h2>
                <a href="{{ route('moonshine.resource.page', ['resourceUri' => 'knowledge-entry-resource', 'pageUri' => 'knowledge-entry-index-page']) }}">Все знания →</a>
            </div>
            @forelse ($recent as $entry)
                <div class="nb-row">
                    <div>
                        <div class="nb-row-title">{{ $entry->title }}</div>
                        <div class="nb-meta">{{ $entry->project ?? 'без проекта' }} · {{ $entry->updated_at?->format('d.m.Y H:i') }}</div>
                    </div>
                    <span class="nb-badge">{{ $entry->kind->label() }}</span>
                </div>
            @empty
                <p class="nb-empty">Пока пусто. Запустите синхронизацию или добавьте запись.</p>
            @endforelse
        </div>

        <div class="nb-card">
            <h2 class="nb-section-title" style="margin-bottom:.75rem">Проекты</h2>
            @forelse ($projects as $project)
                <div class="nb-row">
                    <span>{{ $project->project }}</span>
                    <span class="nb-meta">{{ $project->entries }}</span>
                </div>
            @empty
                <p class="nb-empty">Проекты ещё не размечены.</p>
            @endforelse
        </div>
    </div>

    <div class="nb-card">
        <h2 class="nb-section-title" style="margin-bottom:.75rem">Ожидают суммаризации</h2>
        @forelse ($pendingConversations as $conversation)
            <div class="nb-row">
                <div>
                    <div class="nb-row-title">{{ $conversation->title ?? 'Без названия' }}</div>
                    <div class="nb-meta">
                        #{{ $conversation->id }} · {{ $conversation->harness ?? 'unknown' }} ·
                        {{ $conversation->messages_count }} сообщений · {{ $conversation->last_activity_at?->diffForHumans() }}
                    </div>
                </div>
                <form method="POST" action="{{ route('brain.admin.summarize', $conversation) }}">
                    @csrf
                    <button type="submit" class="nb-btn nb-btn-primary">Суммаризировать</button>
                </form>
            </div>
        @empty
            <p class="nb-empty">Очередь пуста.</p>
        @endforelse
    </div>

    @if ($lastRun)
        <div class="nb-card">
            <h2 class="nb-section-title" style="margin-bottom:.75rem">Последний запуск</h2>
            <div class="nb-grid-4">
                <div><span class="nb-meta">Статус</span><div class="nb-row-title">{{ $lastRun->status }}</div></div>
                <div><span class="nb-meta">Создано</span><div class="nb-row-title">{{ $lastRun->stats['entries_created'] ?? 0 }}</div></div>
                <div><span class="nb-meta">Обновлено</span><div class="nb-row-title">{{ $lastRun->stats['entries_updated'] ?? 0 }}</div></div>
                <div><span class="nb-meta">Длительность</span><div class="nb-row-title">{{ $lastRun->started_at?->diffInSeconds($lastRun->finished_at) }} c</div></div>
            </div>
        </div>
    @endif
</div>

<div class="space-y-6">
    @if (session('brain_status'))
        <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:border-green-700 dark:bg-green-900/30 dark:text-green-100">
            {{ session('brain_status') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Neiro Brain — обзор</h1>
            <p class="text-sm text-gray-500">Личная база знаний для LLM: BM25, MCP и прозрачный прокси.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('brain.admin.sync') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
                    Синхронизировать сейчас
                </button>
            </form>
            <form method="POST" action="{{ route('brain.admin.reindex') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-purple-300 px-4 py-2 text-sm font-semibold text-purple-700 hover:bg-purple-50 dark:text-purple-200">
                    Переиндексировать
                </button>
            </form>
            <a href="{{ route('moonshine.page', ['pageUri' => 'settings-page']) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-200">
                Настройки
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
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
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-3xl font-bold">{{ $value }}</div>
                <div class="text-xs text-gray-400">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Последние знания</h2>
                <a href="{{ route('moonshine.resource.page', ['resourceUri' => 'knowledge-entry-resource', 'pageUri' => 'knowledge-entry-index-page']) }}" class="text-sm text-purple-600">Все знания →</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recent as $entry)
                    <div class="py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $entry->title }}</span>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $entry->kind->label() }}</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ $entry->project ?? 'без проекта' }} · {{ $entry->updated_at?->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500">Пока пусто. Запустите синхронизацию или добавьте запись.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 text-lg font-semibold">Проекты</h2>
            @forelse ($projects as $project)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 dark:border-gray-700">
                    <span>{{ $project->project }}</span>
                    <span class="text-sm text-gray-500">{{ $project->entries }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Проекты ещё не размечены.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="mb-3 text-lg font-semibold">Ожидают суммаризации</h2>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($pendingConversations as $conversation)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                        <div class="font-medium">{{ $conversation->title ?? 'Без названия' }}</div>
                        <div class="text-xs text-gray-500">
                            #{{ $conversation->id }} · {{ $conversation->harness ?? 'unknown' }} · {{ $conversation->messages_count }} сообщений · {{ $conversation->last_activity_at?->diffForHumans() }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('brain.admin.summarize', $conversation) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-700">
                            Суммаризировать
                        </button>
                    </form>
                </div>
            @empty
                <p class="py-2 text-sm text-gray-500">Очередь пуста.</p>
            @endforelse
        </div>
    </div>

    @if ($lastRun)
        <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-2 text-lg font-semibold">Последний запуск</h2>
            <div class="grid gap-2 md:grid-cols-4">
                <div>Статус: <b>{{ $lastRun->status }}</b></div>
                <div>Создано: <b>{{ $lastRun->stats['entries_created'] ?? 0 }}</b></div>
                <div>Обновлено: <b>{{ $lastRun->stats['entries_updated'] ?? 0 }}</b></div>
                <div>Длительность: <b>{{ $lastRun->started_at?->diffInSeconds($lastRun->finished_at) }} c</b></div>
            </div>
        </div>
    @endif
</div>

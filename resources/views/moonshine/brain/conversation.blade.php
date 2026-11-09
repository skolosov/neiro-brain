@include('moonshine.brain.styles')
@include('moonshine.brain.cards')

@php
    $conversation = $item;
    $transcript = $conversation->transcript_text;
    $purgedAt = $conversation->metadata['transcript_purged_at'] ?? null;
@endphp

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">{{ $conversation->title ?? 'Диалог #'.$conversation->id }}</h1>
            <p class="nb-subtitle">
                <span class="nb-pill">{{ $conversation->harness ?? 'харнесс неизвестен' }}</span>
                @if ($conversation->project)
                    <span class="nb-pill">{{ $conversation->project }}</span>
                @endif
                <span class="nb-pill">{{ $conversation->status?->label() }}</span>
                {{ $conversation->messages_count }} сообщений ·
                начало {{ $conversation->started_at?->format('d.m.Y H:i') ?? '—' }}
            </p>
        </div>
        <div class="nb-actions">
            @if ($conversation->messages_count > 0)
                <form method="POST" action="{{ route('brain.admin.summarize', $conversation) }}">
                    @csrf
                    <button type="submit" class="nb-btn nb-btn-primary">Суммаризировать</button>
                </form>
            @endif
            <a href="{{ $backUrl }}" class="nb-btn nb-btn-secondary">К диалогам</a>
        </div>
    </div>

    @if ($purgedAt)
        <div class="nb-empty-hint">
            Переписка удалена {{ \Illuminate\Support\Carbon::parse($purgedAt)->format('d.m.Y H:i') }}
            после суммаризации. Это нормальный режим работы: карточки знаний уже созданы,
            а сам диалог остался у провайдера.
        </div>
    @endif

    <div class="nb-search-layout">
        <div class="nb-search-main">
            <div class="nb-card">
                <div class="nb-card-head">
                    <h2 class="nb-section-title">Знания из этого диалога</h2>
                    <span class="nb-results-meta">{{ count($cards) }}</span>
                </div>

                @if ($cards === [])
                    <p class="nb-empty">
                        Пока ничего. Запустите суммаризацию — каждый факт станет отдельной карточкой,
                        а переписка после этого станет не нужна.
                    </p>
                @else
                    <div class="nb-results">
                        @foreach ($cards as $card)
                            @include('moonshine.brain.partials.knowledge-card', ['card' => $card, 'mode' => $activeId === $card['id'] ? 'row' : 'grid'])
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($transcript !== '')
                <details class="nb-card">
                    <summary class="nb-section-title" style="cursor:pointer">Переписка (пока не удалена)</summary>
                    <pre style="margin-top:.8rem;white-space:pre-wrap;font-size:.8rem;line-height:1.5;max-height:32rem;overflow:auto">{{ $transcript }}</pre>
                </details>
            @endif
        </div>

        <aside class="nb-search-side">
            @if ($selected)
                @include('moonshine.brain.partials.entry-panel', ['card' => $selected])
            @else
                <div class="nb-peek">
                    <h3>Карточка знания</h3>
                    <p class="nb-peek-text">
                        Выберите карточку — справа появится её полное содержимое, факты и связи.
                    </p>
                </div>
            @endif

            <div class="nb-peek">
                <h3>Зачем хранить переписку</h3>
                <p class="nb-peek-text">
                    Только чтобы однажды извлечь из неё знания. После суммаризации сообщения
                    удаляются по настройке «Хранить переписку, часов»; здесь остаются
                    заголовок, проект, харнесс и счётчики.
                </p>
            </div>
        </aside>
    </div>
</div>

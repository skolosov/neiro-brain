<?php

declare(strict_types=1);

/**
 * Right-hand panel of the search page: one entry as a full card.
 *
 * @var array<string, mixed> $card
 * @var list<array<string, mixed>> $related
 */
?>
<article class="nb-peek nb-peek-card">
    <div class="nb-card-w-meta" style="margin-bottom:.4rem">
        @if (! empty($card['kind_label']))
            <span class="nb-pill nb-k-{{ $card['kind'] }}-pill">{{ $card['kind_label'] }}</span>
        @endif
        @if ($card['project'])
            <span class="nb-card-w-project">{{ $card['project'] }}</span>
        @endif
        <span class="nb-card-w-date">{{ $card['occurred_at_label'] ?? $card['updated_at'] }}</span>
    </div>

    <h3><a href="{{ $card['url'] }}">{{ $card['title'] }}</a></h3>
    <div class="nb-peek-meta">
        @if ($card['status'] !== 'approved')
            <span class="nb-pill nb-pill-draft">{{ $card['status_label'] }}</span> ·
        @endif
        важность {{ $card['importance'] }}/5 · уверенность {{ number_format($card['confidence'], 2, '.', '') }}
        @if (! $card['indexed'])
            · <span class="nb-pill nb-pill-draft">не в индексе</span>
        @endif
    </div>

    <p class="nb-peek-text">{!! $card['snippet_html'] !!}</p>

    @if ($card['atoms'] !== [])
        <div class="nb-atoms">
            @foreach ($card['atoms'] as $atom)
                <div class="nb-atom">
                    <span class="nb-atom-type">{{ $atom['type'] }}</span>
                    <span class="nb-atom-text">{{ $atom['text'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($card['tags'] !== [])
        <div class="nb-chip-row" style="margin:.7rem 0">
            @foreach ($card['tags'] as $tag)
                <a class="nb-chip" href="{{ $searchUrl.'?q='.urlencode($tag) }}">#{{ $tag }}</a>
            @endforeach
        </div>
    @endif

    @php
        $keywords = array_values(array_diff($card['keywords'] ?? [], $card['tags'] ?? []));
    @endphp
    @if ($keywords !== [])
        <div class="nb-label" style="margin-top:.8rem">Ключевые слова</div>
        <div class="nb-chip-row">
            @foreach ($keywords as $keyword)
                <a class="nb-chip" href="{{ $searchUrl.'?q='.urlencode($keyword) }}">{{ $keyword }}</a>
            @endforeach
        </div>
    @endif

    <div class="nb-peek-grid">
        <div><b>Обновлено</b>{{ $card['updated_at'] }}</div>
        <div><b>Проверено</b>{{ $card['last_verified_at'] ?? '—' }}</div>
        <div><b>Источник</b>{{ $card['source'] ?? '—' }}</div>
        <div><b>Фактов</b>{{ $card['atoms_total'] }}</div>
    </div>

    <div class="nb-actions" style="margin-top:.4rem">
        <a href="{{ $card['edit_url'] }}" class="nb-btn nb-btn-primary">Редактировать</a>
        <a href="{{ $graphUrl.'?focus=e'.$card['id'] }}" class="nb-btn nb-btn-secondary">На графе</a>
        @if ($card['conversation_url'])
            <a href="{{ $card['conversation_url'] }}" class="nb-btn nb-btn-secondary">Диалог</a>
        @endif
    </div>

    @if ($related !== [])
        <footer class="nb-card-w-links" style="margin-top:.9rem">
            <div style="width:100%;font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--nb-muted)">
                Похожие записи
            </div>
            @foreach (array_slice($related, 0, 6) as $item)
                <a class="nb-card-w-link" href="{{ $item['url'] }}">
                    <span class="nb-card-w-link-dot" aria-hidden="true"></span>
                    <span class="nb-card-w-link-label">{{ $item['title'] }}</span>
                </a>
            @endforeach
        </footer>
    @endif
</article>

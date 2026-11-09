<?php

declare(strict_types=1);

/**
 * Right-hand panel of the search page: one entry read as a document.
 *
 * Same content as the list card, just complete — no duplicated metadata blocks
 * and no per-sentence labels.
 *
 * @var array<string, mixed> $card
 * @var list<array<string, mixed>> $related
 */
?>
<article class="nb-peek nb-peek-card">
    <div class="nb-card-w-meta" style="margin-bottom:.4rem">
        @if ($card['theme'])
            <span class="nb-pill nb-k-{{ $card['kind'] }}-pill">{{ $card['theme'] }}</span>
        @elseif (! empty($card['kind_label']))
            <span class="nb-pill nb-k-{{ $card['kind'] }}-pill">{{ $card['kind_label'] }}</span>
        @endif
        @if ($card['project'])
            <span class="nb-card-w-project">{{ $card['project'] }}</span>
        @elseif (! empty($card['scope_label']) && ($card['scope'] ?? null) !== 'project')
            <span class="nb-card-w-project">{{ $card['scope_label'] }}</span>
        @endif
        <span class="nb-card-w-date">{{ $card['occurred_at_label'] ?? $card['updated_at'] }}</span>
    </div>

    <h3>{{ $card['title'] }}</h3>

    @if (! empty($card['summary']))
        <p class="nb-peek-lead">{{ $card['summary'] }}</p>
    @endif

    <div class="nb-peek-body">{!! nl2br(e($card['content'])) !!}</div>

    @if ($card['tags'] !== [])
        <div class="nb-chip-row" style="margin:.7rem 0">
            @foreach ($card['tags'] as $tag)
                <a class="nb-chip" href="{{ $searchUrl.'?q='.urlencode($tag) }}">#{{ $tag }}</a>
            @endforeach
        </div>
    @endif

    <div class="nb-peek-grid">
        <div><b>Тип</b>{{ $card['kind_label'] }}</div>
        <div><b>Обновлено</b>{{ $card['updated_at'] }}</div>
        <div><b>Источник</b>{{ $card['source'] ?? '—' }}</div>
        <div><b>Важность</b>{{ $card['importance'] }}/5</div>
    </div>

    <div class="nb-actions" style="margin-top:.4rem">
        <a href="{{ $card['edit_url'] }}" class="nb-btn nb-btn-primary">Редактировать</a>
        <a href="{{ $graphUrl.'?focus=e'.$card['id'] }}" class="nb-btn nb-btn-secondary">На графе</a>
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

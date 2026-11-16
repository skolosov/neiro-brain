<?php

declare(strict_types=1);

/**
 * One entry read as a document: the panel of the search page and the whole
 * cabinet card page.
 *
 * Same content as the list row, just complete — no duplicated metadata blocks
 * and no per-sentence labels. Everything the panel links to is a parameter:
 * actions, tags and neighbouring cards carry their own URLs, so the partial
 * knows nothing about cabinet or MoonShine routes.
 *
 * @var array<string, mixed> $entry
 * @var list<array{label: string, url: string}> $related
 * @var list<array{label: string, url: string, variant?: string}> $actions
 * @var callable(string): string|null $tagUrl
 * @var string $titleTag
 */
$related = $related ?? [];
$actions = $actions ?? [];
$tagUrl = $tagUrl ?? null;
$titleTag = in_array($titleTag ?? 'h3', ['h1', 'h2', 'h3', 'h4'], true) ? ($titleTag ?? 'h3') : 'h3';

// The caller may pass either {label, url} or {title, url} for neighbouring cards.
$relatedLinks = array_map(
    static fn (array $item): array => [
        'label' => (string) ($item['label'] ?? $item['title'] ?? ''),
        'url' => (string) $item['url'],
    ],
    array_values($related),
);

$kind = preg_replace('/[^a-z_]/', '', strtolower((string) ($entry['kind'] ?? ''))) ?: 'other';
$theme = trim((string) ($entry['theme'] ?? ''));
$label = trim((string) ($entry['kind_label'] ?? ''));
$project = trim((string) ($entry['project'] ?? ''));
$scopeLabel = trim((string) ($entry['scope_label'] ?? ''));
$tags = array_values(array_filter(array_map('strval', $entry['tags'] ?? [])));
$date = (string) ($entry['occurred_at_label'] ?? $entry['updated_at'] ?? '');
?>
<article class="nb-peek nb-peek-card">
    <div class="nb-card-w-meta" style="margin-bottom:.4rem">
        @if ($theme !== '')
            <span class="nb-pill nb-k-{{ $kind }}-pill">{{ $theme }}</span>
        @elseif ($label !== '')
            <span class="nb-pill nb-k-{{ $kind }}-pill">{{ $label }}</span>
        @endif
        @if ($project !== '')
            <span class="nb-card-w-project">{{ $project }}</span>
        @elseif ($scopeLabel !== '' && ($entry['scope'] ?? null) !== 'project')
            <span class="nb-card-w-project">{{ $scopeLabel }}</span>
        @endif
        @if ($date !== '')
            <span class="nb-card-w-date">{{ $date }}</span>
        @endif
    </div>

    <{{ $titleTag }}>{{ $entry['title'] }}</{{ $titleTag }}>

    @if (! empty($entry['summary']))
        <p class="nb-peek-lead">{{ $entry['summary'] }}</p>
    @endif

    <div class="nb-peek-body">{!! nl2br(e($entry['content'] ?? '')) !!}</div>

    @if (! empty($entry['details']))
        <div class="nb-peek-body">{!! nl2br(e($entry['details'])) !!}</div>
    @endif

    @if ($tags !== [])
        <div class="nb-chip-row" style="margin:.7rem 0">
            @foreach ($tags as $tag)
                @if ($tagUrl !== null)
                    <a class="nb-chip" href="{{ $tagUrl($tag) }}">#{{ $tag }}</a>
                @else
                    <span class="nb-chip">#{{ $tag }}</span>
                @endif
            @endforeach
        </div>
    @endif

    <div class="nb-peek-grid">
        <div><b>Тип</b>{{ $label !== '' ? $label : '—' }}</div>
        <div><b>Проект</b>{{ ($entry['project'] ?? '') !== '' ? $entry['project'] : '—' }}</div>
        <div><b>Обновлено</b>{{ $entry['updated_at'] ?? '—' }}</div>
        <div><b>Важность</b>{{ $entry['importance'] ?? 0 }}/5</div>
    </div>

    @if ($actions !== [])
        <div class="nb-actions" style="margin-top:.4rem">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" class="nb-btn {{ $action['variant'] ?? 'nb-btn-secondary' }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    @endif

    @include('partials.entry-links', ['links' => $relatedLinks, 'title' => 'Похожие записи'])
</article>

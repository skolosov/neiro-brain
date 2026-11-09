<?php

declare(strict_types=1);

/**
 * One knowledge card: title, one line of metadata, the summary and the full
 * text. No "facts" breakdown — the card is written as prose and read as prose.
 *
 * @var array<string, mixed> $card
 * @var string $mode  'row' (compact list) or 'grid' (dashboard)
 */
$mode = $mode ?? 'row';
$kind = $card['kind'];
$kindClass = 'nb-k-'.preg_replace('/[^a-z]/', '', is_string($kind) ? strtolower($kind) : 'other');
$pill = $kindClass.'-pill';

// In the list a card is a line in the table of contents: title, one paragraph,
// a few links. The full text lives in the right-hand panel, so showing it twice
// would only make the page longer.
$text = $card['summary'] ?? '';
$isActive = ($activeId ?? 0) === $card['id'];
?>
<article class="nb-card-w nb-knowledge-card {{ $kindClass }}" id="entry-{{ $card['id'] }}">
    <header class="nb-card-w-head">
        <h3 class="nb-card-w-title">
            <a href="{{ $card['url'] }}">{{ $card['title'] }}</a>
            @if (($card['status'] ?? 'approved') !== 'approved')
                <span class="nb-pill nb-pill-draft">{{ $card['status_label'] }}</span>
            @endif
        </h3>
        <div class="nb-card-w-meta">
            @if ($card['theme'])
                <span class="nb-pill {{ $pill }}">{{ $card['theme'] }}</span>
            @else
                <span class="nb-pill {{ $pill }}">{{ $card['kind_label'] }}</span>
            @endif
            @if ($card['project'])
                <span class="nb-card-w-project">{{ $card['project'] }}</span>
            @elseif (! empty($card['scope_label']) && ($card['scope'] ?? null) !== 'project')
                <span class="nb-card-w-project">{{ $card['scope_label'] }}</span>
            @endif
            <span class="nb-card-w-date">{{ $card['occurred_at_label'] ?? $card['updated_at'] }}</span>
        </div>
    </header>

    <div class="nb-card-w-summary">{!! nl2br(e($text)) !!}</div>

    @include('moonshine.brain.partials.entry-links', ['card' => $card])
</article>

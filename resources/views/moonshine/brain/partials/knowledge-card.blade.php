<?php

declare(strict_types=1);

/**
 * One knowledge card: title, source metadata, the facts it states and the
 * links back to projects, related cards and the graph.
 *
 * @var array<string, mixed> $card
 * @var string $mode  'row' (compact list) or 'grid' (dashboard)
 */
$mode = $mode ?? 'row';
$kind = $card['kind'];
$kindClass = 'nb-k-'.preg_replace('/[^a-z]/', '', is_string($kind) ? strtolower($kind) : 'other');
$atoms = array_slice($card['atoms'] ?? [], 0, $mode === 'grid' ? 4 : 8);
$pill = $kindClass.'-pill';
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
            <span class="nb-pill {{ $pill }}">{{ $card['kind_label'] }}</span>
            @if ($card['project'])
                <span class="nb-card-w-project">{{ $card['project'] }}</span>
            @endif
            <span class="nb-card-w-date">{{ $card['occurred_at_label'] ?? $card['updated_at'] }}</span>
        </div>
    </header>

    <div class="nb-card-w-summary">{!! $card['snippet_html'] !!}</div>

    @if ($atoms !== [])
        <div class="nb-atoms">
            @foreach ($atoms as $atom)
                <div class="nb-atom">
                    <span class="nb-atom-type">{{ $atom['type'] }}</span>
                    <span class="nb-atom-text">{{ $atom['text'] }}</span>
                </div>
            @endforeach
        </div>
        @if (($card['atoms_total'] ?? 0) > count($atoms))
            <div class="nb-atoms-more">
                <a href="{{ $card['url'] }}">Все факты: {{ $card['atoms_total'] }}</a>
            </div>
        @endif
    @endif

    @include('moonshine.brain.partials.entry-links', ['card' => $card])
</article>

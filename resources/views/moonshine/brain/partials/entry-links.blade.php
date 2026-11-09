<?php

declare(strict_types=1);

/**
 * "Похожие записи" block: each link is a card reference with the project on the
 * right, so a card always leads somewhere else in the brain.
 *
 * @var array<string, mixed> $card
 */
$links = array_filter(
    $card['links'] ?? [],
    fn (array $link): bool => $link['type'] !== 'project',
);
?>
<footer class="nb-card-w-links">
    @foreach (array_slice($links, 0, 4) as $link)
        <a href="{{ $link['url'] }}" class="nb-card-w-link {{ $link['type'] === 'graph' ? 'nb-card-w-link-graph' : '' }}">
            <span class="nb-card-w-link-dot" aria-hidden="true"></span>
            <span class="nb-card-w-link-label">{{ $link['label'] }}</span>
            <span class="nb-card-w-link-meta">{{ $link['type'] === 'graph' ? 'граф' : ($link['type'] === 'tag' ? 'тег' : 'запись') }}</span>
        </a>
    @endforeach

    @if ($card['project'])
        <a href="{{ $card['links'][0]['url'] ?? '#' }}" class="nb-card-w-link nb-card-w-link-project">
            <span class="nb-card-w-link-label">{{ $card['project'] }}</span>
        </a>
    @endif
</footer>

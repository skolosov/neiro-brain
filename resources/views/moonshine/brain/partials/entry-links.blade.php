<?php

declare(strict_types=1);

/**
 * The quiet footer of a card: only what a reader might click. No type labels —
 * "ТЕГ" or "ГРАФ" next to a word says less than the word itself.
 *
 * @var array<string, mixed> $card
 */
$links = $card['links'] ?? [];
?>

@if ($links !== [])
    <footer class="nb-card-w-links">
        @foreach ($links as $link)
            <a class="nb-card-w-link" href="{{ $link['url'] }}">
                <span class="nb-card-w-link-dot" aria-hidden="true"></span>
                <span class="nb-card-w-link-label">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </footer>
@endif

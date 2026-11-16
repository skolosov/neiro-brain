<?php

declare(strict_types=1);

/**
 * The quiet footer of a card: only what a reader might click. No type labels —
 * "ТЕГ" or "ГРАФ" next to a word says less than the word itself.
 *
 * The caller passes its own links, so the partial never builds a route: the
 * panel supplies MoonShine page addresses.
 *
 * @var list<array{label: string, url: string}> $links
 * @var string|null $title
 */
$links = $links ?? [];
$title = $title ?? null;
?>

@if ($links !== [])
    <footer class="nb-card-w-links">
        @if (! empty($title))
            <div class="nb-card-w-links-title">{{ $title }}</div>
        @endif
        @foreach (array_slice($links, 0, 6) as $link)
            <a class="nb-card-w-link" href="{{ $link['url'] }}">
                <span class="nb-card-w-link-dot" aria-hidden="true"></span>
                <span class="nb-card-w-link-label">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </footer>
@endif

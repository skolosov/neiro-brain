<?php

declare(strict_types=1);

/**
 * The quiet footer of a card: only what a reader might click. No type labels —
 * "ТЕГ" or "ГРАФ" next to a word says less than the word itself.
 *
 * Both screens pass their own links, so the partial never builds a route:
 * in the cabinet they point to `/app/entries/{id}`, in MoonShine to its pages.
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

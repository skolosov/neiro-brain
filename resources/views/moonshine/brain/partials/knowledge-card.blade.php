<?php

declare(strict_types=1);

/**
 * One knowledge card as a line in the table of contents.
 *
 * The list exists to find a card; the card itself is read in the panel. So a
 * row carries only what helps recognition — theme, title, the summary cut to a
 * single line — and nothing that repeats what the eye already sees.
 *
 * @var array<string, mixed> $card
 * @var int $activeId
 */
$kind = $card['kind'];
$kindClass = 'nb-k-'.preg_replace('/[^a-z]/', '', is_string($kind) ? strtolower($kind) : 'other');
$isActive = ($activeId ?? 0) === $card['id'];
?>
<li class="nb-entry-row {{ $isActive ? 'is-active' : '' }}">
    <a class="nb-entry-row-link" href="{{ $card['url'] }}">
        <span class="nb-entry-row-theme">{{ $card['theme'] ?? $card['kind_label'] }}</span>
        <span class="nb-entry-row-body">
            <span class="nb-entry-row-title">{{ $card['title'] }}</span>
            <span class="nb-entry-row-summary">{{ $card['summary'] }}</span>
        </span>
        @if ($card['project'])
            <span class="nb-entry-row-project">{{ $card['project'] }}</span>
        @endif
    </a>
</li>

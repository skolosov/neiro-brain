<?php

declare(strict_types=1);

/**
 * One knowledge card as a line in the table of contents.
 *
 * The list exists to find a card; the card itself is read in the panel. So a
 * row carries only what helps recognition — theme, title, the summary cut to a
 * single line — and nothing that repeats what the eye already sees.
 *
 * The row is rendered by the panel's search page and its card lists, so the link
 * is a parameter: the caller decides where it opens.
 *
 * @var array<string, mixed> $entry
 * @var string $url
 * @var bool $active
 * @var bool $highlight
 */
$theme = trim((string) ($entry['theme'] ?? ''));

if ($theme === '') {
    $theme = trim((string) ($entry['kind_label'] ?? ''));
}

$summary = (string) ($entry['summary'] ?? '');
$summaryHtml = e($summary);

// The admin list highlights what BM25 matched; the text stays the same summary.
if (($highlight ?? false) && ! empty($entry['matched_terms'])) {
    $summaryHtml = \App\Services\Brain\Text::highlight($summary, array_map('strval', (array) $entry['matched_terms']));
}

$active = $active ?? false;
$project = trim((string) ($entry['project'] ?? ''));
?>
<li class="nb-entry-row {{ $active ? 'is-active' : '' }}" data-brain-card>
    <a class="nb-entry-row-link" href="{{ $url }}">
        <span class="nb-entry-row-theme">{{ $theme }}</span>
        <span class="nb-entry-row-body">
            <span class="nb-entry-row-title">{{ $entry['title'] }}</span>
            <span class="nb-entry-row-summary">{!! $summaryHtml !!}</span>
        </span>
        @if ($project !== '')
            <span class="nb-entry-row-project">{{ $project }}</span>
        @endif
    </a>
</li>

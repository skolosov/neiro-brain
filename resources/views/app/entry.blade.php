@extends('app.layout')

@section('title', $entry->title)

@section('content')
    <div class="kicker">Карточка знаний</div>

    @php
        $panelEntry = [
            'id' => (int) $entry->getKey(),
            'title' => (string) $entry->title,
            'summary' => $summary,
            'content' => (string) $entry->content,
            'details' => $entry->details,
            'theme' => trim((string) $entry->theme) !== '' ? (string) $entry->theme : null,
            'kind' => $entry->kind?->value,
            'kind_label' => $entry->kind?->label(),
            'project' => trim((string) $entry->project) !== '' ? (string) $entry->project : null,
            'scope' => $entry->scope?->value,
            'scope_label' => $entry->scope?->label(),
            'occurred_at_label' => ($entry->occurred_at ?? $entry->created_at)?->format('d.m.Y'),
            'updated_at' => $entry->updated_at?->format('d.m.Y H:i'),
            'source' => $entry->source?->name,
            'tags' => $tags,
            'importance' => (int) $entry->importance,
        ];

        // Neighbouring cards come from the same graph search the admin panel
        // uses; only the URL differs, and the cabinet opens `/app/entries/{id}`.
        $related = app(\App\Services\Brain\GraphSearch::class)
            ->relatedEntries($entry, 6)
            ->map(fn (\App\Models\KnowledgeEntry $item): array => [
                'title' => (string) $item->title,
                'url' => route('app.entries.show', ['entry' => $item->getKey()]),
            ])
            ->values()
            ->all();
    @endphp

    @include('partials.entry-panel', [
        'entry' => $panelEntry,
        'related' => $related,
        'tagUrl' => fn (string $tag): string => route('app.search', ['q' => $tag]),
        'actions' => [
            ['label' => 'На графе', 'url' => route('app.graph')],
        ],
        'titleTag' => 'h1',
    ])

    <section class="card">
        <h2>История версий</h2>
        <p class="muted">Старые версии остаются здесь: видно, что карточка утверждала раньше.</p>

        @foreach ($history as $version)
            <div class="version {{ $version['is_current'] ? 'current' : 'outdated' }}">
                <h3>{{ $version['title'] }}</h3>
                <div class="meta-row">
                    @if ($version['is_current'])
                        <span class="badge">текущая версия</span>
                    @else
                        <span class="badge">устаревшая версия</span>
                    @endif
                    @if ($version['updated_at'])
                        <span>{{ $version['updated_at'] }}</span>
                    @endif
                </div>
                <p class="entry-text">{{ $version['summary'] }}</p>
                @unless ($version['is_current'])
                    <div class="prose">{!! nl2br(e($version['content'])) !!}</div>
                @endunless
            </div>
        @endforeach
    </section>

    <div class="actions">
        <a class="btn secondary" href="{{ route('app.search') }}">К поиску</a>
    </div>
@endsection

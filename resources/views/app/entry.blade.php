@extends('app.layout')

@section('title', $entry->title)

@section('content')
    <div class="kicker">Карточка знаний</div>
    <h1>{{ $entry->title }}</h1>

    <div class="meta-row">
        @if ($entry->kind)
            <span>{{ $entry->kind->label() }}</span>
        @endif
        @if ($entry->scope)
            <span>{{ $entry->scope->label() }}</span>
        @endif
        @if (trim((string) $entry->project) !== '')
            <span>проект: {{ $entry->project }}</span>
        @endif
        @if (trim((string) $entry->theme) !== '')
            <span>тема: {{ $entry->theme }}</span>
        @endif
        <span>важность: {{ $entry->importance }}/5</span>
        <span>
            {{ ($entry->occurred_at ?? $entry->created_at)?->format('d.m.Y') }}
        </span>
    </div>

    <section class="card">
        <h2>Кратко</h2>
        <p class="entry-text">{{ $summary }}</p>

        <h2>Текст</h2>
        <div class="prose">{!! nl2br(e($entry->content)) !!}</div>

        @if (trim((string) $entry->details) !== '')
            <h2>Детали</h2>
            <div class="prose">{!! nl2br(e($entry->details)) !!}</div>
        @endif

        @if ($tags !== [])
            <h2>Теги</h2>
            <div class="meta-row">
                @foreach ($tags as $tag)
                    <span class="badge">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
    </section>

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

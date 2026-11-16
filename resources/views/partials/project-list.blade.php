<?php

declare(strict_types=1);

/**
 * Projects of the knowledge base: one card per project, shared by the cabinet
 * and MoonShine.
 *
 * The caller normalizes its own data: `name`, `count`, `url` are required;
 * `topics`, `topicUrl`, `importance`, `last` and `graphUrl` are optional, so
 * the admin keeps its topic chips and graph link while the cabinet shows the
 * same card without them.
 *
 * @var list<array{name: string, count: int, url: string, topics?: array<string, int>, topicUrl?: callable(string): string, importance?: int|null, last?: string|null, graphUrl?: string|null}> $projects
 * @var string|null $emptyTitle
 * @var string $emptyText
 * @var array{label: string, url: string}|null $emptyLink
 */
$projects = $projects ?? [];
$emptyText = (string) ($emptyText ?? 'Проектов пока нет.');
$emptyLink = $emptyLink ?? null;
?>
@if ($projects === [])
    <div class="nb-empty-hint">
        @if (! empty($emptyTitle))
            <strong>{{ $emptyTitle }}.</strong>
        @endif
        {{ $emptyText }}
        @if ($emptyLink !== null)
            <a href="{{ $emptyLink['url'] }}">{{ $emptyLink['label'] }}</a>
        @endif
    </div>
@else
    <div class="nb-projects-grid">
        @foreach ($projects as $project)
            <article class="nb-card nb-project-card">
                <header class="nb-project-head">
                    <h2><a href="{{ $project['url'] }}">{{ $project['name'] }}</a></h2>
                    <span class="nb-pill">{{ $project['count'] }} карточек</span>
                </header>

                @if (! empty($project['importance']) || ! empty($project['last']))
                    <div class="nb-project-meta">
                        @if (! empty($project['importance']))
                            <span>важность {{ $project['importance'] }}/5</span>
                        @endif
                        @if (! empty($project['last']))
                            <span>последняя {{ $project['last'] }}</span>
                        @endif
                    </div>
                @endif

                @if (! empty($project['topics']))
                    <div class="nb-chip-row">
                        @foreach ($project['topics'] as $topic => $count)
                            <a class="nb-chip" href="{{ ($project['topicUrl'] ?? fn (string $name): string => $project['url'])($topic) }}">
                                {{ $topic }} <span class="nb-chip-count">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if (! empty($project['graphUrl']))
                    <footer class="nb-card-w-links">
                        <a class="nb-card-w-link" href="{{ $project['url'] }}">
                            <span class="nb-card-w-link-dot" aria-hidden="true"></span>
                            <span class="nb-card-w-link-label">Все карточки проекта</span>
                        </a>
                        <a class="nb-card-w-link nb-card-w-link-project" href="{{ $project['graphUrl'] }}">На графе</a>
                    </footer>
                @endif
            </article>
        @endforeach
    </div>
@endif

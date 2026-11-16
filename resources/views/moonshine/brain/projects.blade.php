@include('partials.brain-styles')

<div class="brain-ui nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Проекты</h1>
            <p class="nb-subtitle">
                Каждый проект — это набор карточек с общими темами.
                Проектов в базе: {{ $projects->count() }}.
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $searchUrl }}" class="nb-btn nb-btn-secondary">Карточки</a>
            <a href="{{ $graphUrl }}" class="nb-btn nb-btn-secondary">Граф</a>
        </div>
    </div>

    @php
        $items = $projects
            ->map(fn (array $project): array => [
                'name' => (string) $project['name'],
                'count' => (int) $project['count'],
                'url' => (string) $project['url'],
                'topics' => (array) $project['topics'],
                'topicUrl' => fn (string $topic): string => route('moonshine.page', [
                    'pageUri' => 'search-page',
                    'topic' => $topic,
                    'project' => $project['name'],
                ]),
                'importance' => (int) $project['importance'],
                'last' => $project['last']?->diffForHumans(),
                'graphUrl' => $graphUrl.'?project='.urlencode($project['name']),
            ])
            ->values()
            ->all();
    @endphp

    @include('partials.project-list', [
        'projects' => $items,
        'emptyTitle' => 'Проектов пока нет',
        'emptyText' => 'В карточках не заполнено поле «Проект». Это нормально, если знания общие — тогда смотрите',
        'emptyLink' => ['label' => 'карточки по темам', 'url' => $searchUrl],
    ])
</div>

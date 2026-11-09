@include('moonshine.brain.styles')
@include('moonshine.brain.cards')

<div class="nb-page">
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

    @if ($projects->isEmpty())
        <div class="nb-empty-hint">
            Проектов пока нет: в карточках не заполнено поле «Проект».
            Это нормально, если знания общие — тогда смотрите
            <a href="{{ $searchUrl }}">карточки по темам</a>.
        </div>
    @else
        <div class="nb-projects-grid">
            @foreach ($projects as $project)
                <article class="nb-card nb-project-card">
                    <header class="nb-project-head">
                        <h2><a href="{{ $project['url'] }}">{{ $project['name'] }}</a></h2>
                        <span class="nb-pill">{{ $project['count'] }} карточек</span>
                    </header>

                    <div class="nb-project-meta">
                        <span>важность {{ $project['importance'] }}/5</span>
                        @if ($project['last'])
                            <span>последняя {{ $project['last']->diffForHumans() }}</span>
                        @endif
                    </div>

                    <div class="nb-chip-row">
                        @foreach ($project['topics'] as $topic => $count)
                            <a class="nb-chip" href="{{ $searchUrl.'?topic='.urlencode($topic) }}&project={{ urlencode($project['name']) }}">
                                {{ $topic }} <span class="nb-chip-count">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>

                    <footer class="nb-card-w-links">
                        <a class="nb-card-w-link" href="{{ $project['url'] }}">
                            <span class="nb-card-w-link-dot"></span>
                            <span class="nb-card-w-link-label">Все карточки проекта</span>
                        </a>
                        <a class="nb-card-w-link nb-card-w-link-project"
                           href="{{ $graphUrl.'?project='.urlencode($project['name']) }}">На графе</a>
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
</div>

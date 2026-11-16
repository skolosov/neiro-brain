@php
    $theme = $theme ?? '';
    $entryUrl = $entryUrl ?? static fn (int $id): string => '#';
    $stats = $stats ?? ['nodes' => 0, 'edges' => 0];
    $focusNodes = $focusNodes ?? [];
    $results = $results ?? [];
    $listProjects = $listProjects ?? [];
@endphp

<style>
    /* Graph page: self-contained so the cabinet and MoonShine share one component. */
    .brain-graph-page {
        --nb-text: #111827;
        --nb-muted: #6b7280;
        --nb-border: #e5e7eb;
        --nb-card: #ffffff;
        --nb-input: #ffffff;
        --nb-accent: #7c3aed;
        --nb-accent-soft: #f5f3ff;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        color: var(--nb-text);
    }
    .brain-graph-page.dark,
    .dark .brain-graph-page {
        --nb-text: #f5f2ff;
        --nb-muted: #a79ec4;
        --nb-border: #332b52;
        --nb-card: #17122b;
        --nb-input: #120e22;
        --nb-accent: #c4b5fd;
        --nb-accent-soft: #241c40;
    }
    .brain-graph-page a { color: var(--nb-accent); text-decoration: none; }
    .brain-graph-page h1, .brain-graph-page h2, .brain-graph-page h3 { color: var(--nb-text); }
    .brain-graph-page p { color: var(--nb-muted); }
    .brain-graph-page .nb-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .brain-graph-page .nb-title { margin: 0; font-size: 1.5rem; line-height: 1.25; font-weight: 700; }
    .brain-graph-page .nb-subtitle { margin: .3rem 0 0; font-size: .9rem; color: var(--nb-muted); }
    .brain-graph-page .nb-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .brain-graph-page .nb-btn { display: inline-block; border: 1px solid transparent; border-radius: .5rem; padding: .55rem .9rem; font-size: .85rem; font-weight: 600; line-height: 1.2; cursor: pointer; text-decoration: none; }
    .brain-graph-page .nb-btn-primary { background: #7c3aed; color: #fff !important; }
    .brain-graph-page .nb-btn-primary:hover { background: #6d28d9; }
    .brain-graph-page .nb-btn-secondary { background: var(--nb-card); border-color: var(--nb-border); color: var(--nb-text) !important; }
    .brain-graph-page .nb-btn-secondary:hover { background: var(--nb-accent-soft); }
    .brain-graph-page .nb-chip-row { display: flex; flex-wrap: wrap; gap: .4rem; }
    .brain-graph-page .nb-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border: 1px solid var(--nb-border);
        border-radius: 999px;
        padding: .3rem .7rem;
        font-size: .78rem;
        color: var(--nb-text);
        text-decoration: none;
        background: var(--nb-card);
    }
    .brain-graph-page .nb-chip:hover, .brain-graph-page .nb-chip-active { border-color: var(--nb-accent); color: var(--nb-accent); }
    .brain-graph-page .nb-flash { border: 1px solid #86efac; background: #f0fdf4; color: #14532d; padding: .8rem 1rem; border-radius: .5rem; font-size: .9rem; }
    .brain-graph-page.dark .nb-flash, .dark .brain-graph-page .nb-flash { border-color: #166534; background: #052e16; color: #bbf7d0; }

    .brain-graph-page .nb-graph-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 1200px) { .brain-graph-page .nb-graph-layout { grid-template-columns: minmax(0, 1fr) 22rem; } }
    .brain-graph-page .nb-graph-wrap { position: relative; border: 1px solid var(--nb-border); border-radius: .75rem; background: var(--nb-card); overflow: hidden; }
    .brain-graph-page .nb-graph-canvas { display: block; width: 100%; height: 34rem; touch-action: none; cursor: grab; }
    .brain-graph-page .nb-graph-canvas:active { cursor: grabbing; }
    .brain-graph-page .nb-graph-toolbar {
        position: absolute;
        top: .6rem;
        left: .6rem;
        display: flex;
        gap: .3rem;
        z-index: 2;
    }
    .brain-graph-page .nb-graph-toolbar .nb-btn { padding: .35rem .6rem; font-size: .8rem; background: var(--nb-card); }
    .brain-graph-page .nb-graph-legend {
        position: absolute;
        bottom: .6rem;
        left: .6rem;
        display: flex;
        flex-wrap: wrap;
        gap: .9rem;
        font-size: .72rem;
        color: var(--nb-muted);
        z-index: 2;
        background: color-mix(in srgb, var(--nb-card) 82%, transparent);
        padding: .3rem .5rem;
        border-radius: .4rem;
    }
    .brain-graph-page .nb-graph-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .brain-graph-page .nb-graph-legend i { width: .5rem; height: .5rem; border-radius: 999px; display: inline-block; }
    .brain-graph-page .nb-graph-search {
        position: absolute;
        top: .6rem;
        right: .6rem;
        width: min(20rem, 60%);
        z-index: 2;
        box-sizing: border-box;
        border: 1px solid var(--nb-border);
        border-radius: .5rem;
        padding: .55rem .65rem;
        background: var(--nb-input);
        color: var(--nb-text);
        font-size: .875rem;
    }
    .brain-graph-page .nb-graph-search:focus { outline: 2px solid color-mix(in srgb, #7c3aed 45%, transparent); outline-offset: 1px; }
    .brain-graph-page .nb-graph-side { display: flex; flex-direction: column; gap: .75rem; }
    .brain-graph-page .nb-peek { border: 1px solid var(--nb-border); border-radius: .75rem; padding: .9rem 1rem; background: var(--nb-card); }
    .brain-graph-page .nb-peek h3 { margin: 0 0 .25rem; font-size: 1rem; line-height: 1.3; }
    .brain-graph-page .nb-peek-meta { font-size: .72rem; color: var(--nb-muted); margin-bottom: .5rem; }
    .brain-graph-page .nb-peek-text { font-size: .82rem; line-height: 1.45; }
    .brain-graph-page .nb-peek-body { font-size: .84rem; line-height: 1.55; color: var(--nb-text); }
    .brain-graph-page .nb-peek-body a { color: var(--nb-accent); }
    .brain-graph-page .nb-graph-mini { display: flex; flex-wrap: wrap; gap: .4rem; }
    .brain-graph-page .nb-graph-mini a { font-size: .8rem; }

    .brain-graph-page .nb-card-w-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .brain-graph-page .nb-card-w-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; font-size: .72rem; color: var(--nb-muted); }
    .brain-graph-page .nb-card-w-project { color: var(--nb-accent); font-weight: 600; }
    .brain-graph-page .nb-card-w-date { color: var(--nb-muted); }
    .brain-graph-page .nb-card-w-links { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .9rem; border-top: 1px solid var(--nb-border); padding-top: .55rem; }
    .brain-graph-page .nb-card-w-link { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--nb-muted); max-width: 100%; }
    .brain-graph-page .nb-card-w-link:hover { color: var(--nb-accent); }
    .brain-graph-page .nb-card-w-link-dot { width: .4rem; height: .4rem; border-radius: 999px; background: #22c55e; flex: none; }
    .brain-graph-page .nb-card-w-link-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 16rem; }
    .brain-graph-page .nb-card-w-link-meta { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; opacity: .75; }
    .brain-graph-page .nb-pill { display: inline-block; white-space: nowrap; border-radius: 999px; padding: .1rem .5rem; font-size: .68rem; font-weight: 600; letter-spacing: .02em; background: var(--nb-accent-soft); color: var(--nb-accent); }
    .brain-graph-page .nb-k-profile-pill { background: #dbeafe; color: #1e40af; }
    .brain-graph-page .nb-k-preference-pill { background: #ede9fe; color: #5b21b6; }
    .brain-graph-page .nb-k-project-pill { background: #dcfce7; color: #166534; }
    .brain-graph-page .nb-k-decision-pill { background: #ffedd5; color: #9a3412; }
    .brain-graph-page .nb-k-person-pill { background: #fce7f3; color: #9d174d; }
    .brain-graph-page .nb-k-company-pill { background: #cffafe; color: #155e75; }
    .brain-graph-page .nb-k-stack-pill { background: #fef9c3; color: #854d0e; }
    .brain-graph-page .nb-k-style-pill { background: #e0e7ff; color: #3730a3; }
    .brain-graph-page .nb-k-process-pill { background: #ccfbf1; color: #115e59; }
    .brain-graph-page .nb-k-fact-pill, .brain-graph-page .nb-k-other-pill { background: #f3f4f6; color: #374151; }
    .brain-graph-page.dark .nb-k-profile-pill, .dark .brain-graph-page .nb-k-profile-pill { background: #1e3a8a; color: #bfdbfe; }
    .brain-graph-page.dark .nb-k-preference-pill, .dark .brain-graph-page .nb-k-preference-pill { background: #4c1d95; color: #ddd6fe; }
    .brain-graph-page.dark .nb-k-project-pill, .dark .brain-graph-page .nb-k-project-pill { background: #14532d; color: #bbf7d0; }
    .brain-graph-page.dark .nb-k-decision-pill, .dark .brain-graph-page .nb-k-decision-pill { background: #7c2d12; color: #fed7aa; }
    .brain-graph-page.dark .nb-k-person-pill, .dark .brain-graph-page .nb-k-person-pill { background: #831843; color: #fbcfe8; }
    .brain-graph-page.dark .nb-k-company-pill, .dark .brain-graph-page .nb-k-company-pill { background: #164e63; color: #a5f3fc; }
    .brain-graph-page.dark .nb-k-stack-pill, .dark .brain-graph-page .nb-k-stack-pill { background: #713f12; color: #fef08a; }
    .brain-graph-page.dark .nb-k-style-pill, .dark .brain-graph-page .nb-k-style-pill { background: #312e81; color: #c7d2fe; }
    .brain-graph-page.dark .nb-k-process-pill, .dark .brain-graph-page .nb-k-process-pill { background: #134e4a; color: #99f6e4; }
    .brain-graph-page.dark .nb-k-fact-pill, .brain-graph-page.dark .nb-k-other-pill,
    .dark .brain-graph-page .nb-k-fact-pill, .dark .brain-graph-page .nb-k-other-pill { background: #374151; color: #d1d5db; }
    .brain-graph-page .nb-atoms { display: flex; flex-direction: column; gap: .35rem; border-top: 1px dashed var(--nb-border); padding-top: .6rem; }
    .brain-graph-page .nb-atom { display: grid; grid-template-columns: 5.5rem minmax(0, 1fr); gap: .6rem; align-items: start; }
    .brain-graph-page .nb-atom-type { font-size: .64rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--nb-muted); padding-top: .15rem; white-space: nowrap; }
    .brain-graph-page .nb-atom-text { font-size: .82rem; line-height: 1.45; color: var(--nb-text); }
</style>

<div class="brain-graph-page nb-page{{ $theme === 'dark' ? ' dark' : '' }}">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Граф знаний</h1>
            <p class="nb-subtitle">
                Узлы: проекты, темы и карточки. Связи: проект → карточка, тема → карточка
                и общие редкие слова. Показано {{ $stats['nodes'] }} узлов и {{ $stats['edges'] }} связей.
                @if ($truncated > 0)
                    Ещё {{ $truncated }} записей не попало — отфильтруйте по проекту.
                @endif
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $searchUrl }}" class="nb-btn nb-btn-secondary">К карточкам</a>
        </div>
    </div>

    <div class="nb-chip-row">
        <a class="nb-chip {{ $project === '' ? 'nb-chip-active' : '' }}" href="{{ $pageUrl }}">Все проекты</a>
        @foreach ($listProjects as $name)
            <a class="nb-chip {{ $project === $name ? 'nb-chip-active' : '' }}"
               href="{{ $pageUrl.'?project='.urlencode($name) }}">{{ $name }}</a>
        @endforeach
    </div>

    <div class="nb-graph-layout">
        <div class="nb-graph-wrap" id="nb-graph-wrap">
            <div class="nb-graph-toolbar">
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="zoom-in" title="Приблизить">+</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="zoom-out" title="Отдалить">−</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="fit" title="Вписать всё">Вписать</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="cluster" title="Раскрасить по проектам">Вид</button>
                <button type="button" class="nb-btn nb-btn-secondary" data-nb-graph="fullscreen" title="На весь экран">⛶</button>
            </div>

            <input type="search" class="nb-input nb-graph-search" placeholder="Найти на графе" value="{{ $query }}"
                   data-nb-graph-search autocomplete="off">

            <canvas class="nb-graph-canvas" id="nb-graph-canvas"
                    data-nb-graph-focus="{{ implode(',', $focusNodes) }}"></canvas>

            <div class="nb-graph-legend">
                <span><i style="background:#8b5cf6"></i> проект</span>
                <span><i style="background:#0ea5e9"></i> тема</span>
                <span><i style="background:#22c55e"></i> карточка</span>
                <span><i style="background:#eab308"></i> найдено по запросу</span>
                <span>Клик — карточка, двойной клик — открыть, Esc — сброс</span>
            </div>
        </div>

        <aside class="nb-graph-side">
            <div class="nb-peek" id="nb-graph-detail">
                <h3>Карточка узла</h3>
                <p class="nb-peek-text">
                    Кликните узел на графе — здесь появится карточка знания:
                    краткий ответ, факты, теги и связи.
                </p>
                @if ($results !== [])
                    <div class="nb-card-w-links" style="margin-top:.6rem">
                        @foreach (array_slice($results, 0, 5) as $hit)
                            <a class="nb-card-w-link" href="{{ $entryUrl((int) $hit['id']) }}">
                                <span class="nb-card-w-link-dot"></span>
                                <span class="nb-card-w-link-label">{{ $hit['title'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>

<script type="application/json" id="nb-graph-data">@json($graph, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
<script type="module" src="{{ asset('brain/graph.js') }}?v={{ @filemtime(public_path('brain/graph.js')) ?: 1 }}"></script>

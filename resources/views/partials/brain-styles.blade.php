{{--
    Единый файл стилей базы знаний.

    Всё живёт под обёрткой `.brain-ui`: это контент страниц панели MoonShine.
    Поэтому правила не протекают в оболочку MoonShine и обратно. Палитра
    включается по контексту: `.dark` — тёмная тема MoonShine. Набор переменных
    один, чтобы карточка, панель и поиск выглядели одинаково.
--}}
<style>
    .brain-ui {
        --nb-text: #111827;
        --nb-muted: #6b7280;
        --nb-border: #e5e7eb;
        --nb-card: #ffffff;
        --nb-input: #ffffff;
        --nb-accent: #7c3aed;
        --nb-accent-soft: #f5f3ff;
        color: var(--nb-text);
    }
    .dark .brain-ui {
        --nb-text: #f9fafb;
        --nb-muted: #9ca3af;
        --nb-border: #374151;
        --nb-card: #1f2937;
        --nb-input: #111827;
        --nb-accent: #c4b5fd;
        --nb-accent-soft: #2e1065;
    }
    .brain-ui.brain-ui-dark {
        --nb-text: #f5f2ff;
        --nb-muted: #a79ec4;
        --nb-border: #332b52;
        --nb-card: #17122b;
        --nb-input: #120e22;
        --nb-accent: #c4b5fd;
        --nb-accent-soft: #241c40;
    }

    /* Page skeleton */
    .brain-ui.nb-page { display: flex; flex-direction: column; gap: 1.5rem; }
    .brain-ui.nb-page a { color: var(--nb-accent); text-decoration: none; }
    .brain-ui .nb-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .brain-ui .nb-title { margin: 0; font-size: 1.5rem; line-height: 1.25; font-weight: 700; }
    .brain-ui .nb-subtitle { margin: .3rem 0 0; font-size: .9rem; color: var(--nb-muted); }
    .brain-ui .nb-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .brain-ui .nb-grid-4 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .brain-ui .nb-grid-2 { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
    @media (min-width: 1024px) {
        .brain-ui .nb-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .brain-ui .nb-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Buttons */
    .brain-ui .nb-btn { display: inline-block; border: 1px solid transparent; border-radius: .5rem; padding: .55rem .9rem; font-size: .85rem; font-weight: 600; line-height: 1.2; cursor: pointer; text-decoration: none; }
    .brain-ui .nb-btn-primary { background: #7c3aed; color: #fff !important; }
    .brain-ui .nb-btn-primary:hover { background: #6d28d9; }
    .brain-ui .nb-btn-secondary { background: transparent; border-color: var(--nb-border); color: var(--nb-text) !important; }
    .brain-ui .nb-btn-secondary:hover { background: var(--nb-accent-soft); }

    /* Cards and metrics */
    .brain-ui .nb-card {
        background: var(--nb-card);
        border: 1px solid var(--nb-border);
        border-radius: .75rem;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / .06);
    }
    .brain-ui .nb-metric-label { font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--nb-muted); }
    .brain-ui .nb-metric-value { margin: .2rem 0 0; font-size: 1.8rem; line-height: 1.15; font-weight: 700; }
    .brain-ui .nb-metric-hint { font-size: .75rem; color: var(--nb-muted); }
    .brain-ui .nb-card-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
    .brain-ui .nb-section-title { margin: 0; font-size: 1.05rem; font-weight: 650; }
    .brain-ui .nb-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--nb-border); }
    .brain-ui .nb-row:last-child { border-bottom: 0; }
    .brain-ui .nb-row-title { font-weight: 550; font-size: .9rem; }
    .brain-ui .nb-meta { margin-top: .15rem; font-size: .75rem; color: var(--nb-muted); }
    .brain-ui .nb-badge { display: inline-block; white-space: nowrap; border-radius: 999px; padding: .12rem .55rem; font-size: .7rem; background: var(--nb-accent-soft); color: var(--nb-accent); }
    .brain-ui .nb-empty { margin: .4rem 0; color: var(--nb-muted); font-size: .875rem; }

    /* Flash messages */
    .brain-ui .nb-flash { border: 1px solid #86efac; background: #f0fdf4; color: #14532d; padding: .8rem 1rem; border-radius: .5rem; font-size: .9rem; }
    .dark .brain-ui .nb-flash, .brain-ui.brain-ui-dark .nb-flash { border-color: #166534; background: #052e16; color: #bbf7d0; }
    .brain-ui .nb-flash-error { border-color: #fca5a5; background: #fef2f2; color: #7f1d1d; }
    .dark .brain-ui .nb-flash-error, .brain-ui.brain-ui-dark .nb-flash-error { border-color: #991b1b; background: #450a0a; color: #fecaca; }

    /* Forms */
    .brain-ui .nb-form { display: flex; flex-direction: column; gap: 1.25rem; }
    .brain-ui .nb-section { background: var(--nb-card); border: 1px solid var(--nb-border); border-radius: .75rem; padding: 1.1rem 1.2rem; }
    .brain-ui .nb-section > h2 { margin: 0 0 .9rem; font-size: 1.05rem; font-weight: 650; }
    .brain-ui .nb-form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 768px) { .brain-ui .nb-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .brain-ui .nb-form-grid.nb-form-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .brain-ui .nb-label { display: block; margin-bottom: .3rem; font-size: .8rem; font-weight: 600; }
    .brain-ui .nb-input, .brain-ui .nb-select {
        box-sizing: border-box;
        width: 100%;
        border: 1px solid var(--nb-border);
        border-radius: .5rem;
        padding: .55rem .65rem;
        background: var(--nb-input);
        color: var(--nb-text);
        font-size: .875rem;
    }
    .brain-ui .nb-input:focus, .brain-ui .nb-select:focus { outline: 2px solid color-mix(in srgb, #7c3aed 45%, transparent); outline-offset: 1px; border-color: #7c3aed; }
    .brain-ui .nb-checks { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: .9rem; }
    .brain-ui .nb-check { display: flex; align-items: center; gap: .45rem; font-size: .85rem; }
    .brain-ui .nb-help { margin: .45rem 0 0; font-size: .75rem; color: var(--nb-muted); }
    .brain-ui .nb-actions-bottom { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
    .brain-ui .nb-note { font-size: .8rem; color: var(--nb-muted); }

    /* Pills and knowledge kinds */
    .brain-ui .nb-pill {
        display: inline-block;
        white-space: nowrap;
        border-radius: 999px;
        padding: .1rem .5rem;
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: .02em;
        background: var(--nb-accent-soft);
        color: var(--nb-accent);
    }
    .brain-ui .nb-pill-draft { background: #fef3c7; color: #92400e; }
    .dark .brain-ui .nb-pill-draft, .brain-ui.brain-ui-dark .nb-pill-draft { background: #451a03; color: #fcd34d; }
    .brain-ui .nb-k-profile-pill { background: #dbeafe; color: #1e40af; }
    .brain-ui .nb-k-preference-pill { background: #ede9fe; color: #5b21b6; }
    .brain-ui .nb-k-project-pill { background: #dcfce7; color: #166534; }
    .brain-ui .nb-k-decision-pill { background: #ffedd5; color: #9a3412; }
    .brain-ui .nb-k-person-pill { background: #fce7f3; color: #9d174d; }
    .brain-ui .nb-k-company-pill { background: #cffafe; color: #155e75; }
    .brain-ui .nb-k-stack-pill { background: #fef9c3; color: #854d0e; }
    .brain-ui .nb-k-style-pill { background: #e0e7ff; color: #3730a3; }
    .brain-ui .nb-k-process-pill { background: #ccfbf1; color: #115e59; }
    .brain-ui .nb-k-fact-pill, .brain-ui .nb-k-other-pill { background: #f3f4f6; color: #374151; }
    .dark .brain-ui .nb-k-profile-pill, .brain-ui.brain-ui-dark .nb-k-profile-pill { background: #1e3a8a; color: #bfdbfe; }
    .dark .brain-ui .nb-k-preference-pill, .brain-ui.brain-ui-dark .nb-k-preference-pill { background: #4c1d95; color: #ddd6fe; }
    .dark .brain-ui .nb-k-project-pill, .brain-ui.brain-ui-dark .nb-k-project-pill { background: #14532d; color: #bbf7d0; }
    .dark .brain-ui .nb-k-decision-pill, .brain-ui.brain-ui-dark .nb-k-decision-pill { background: #7c2d12; color: #fed7aa; }
    .dark .brain-ui .nb-k-person-pill, .brain-ui.brain-ui-dark .nb-k-person-pill { background: #831843; color: #fbcfe8; }
    .dark .brain-ui .nb-k-company-pill, .brain-ui.brain-ui-dark .nb-k-company-pill { background: #164e63; color: #a5f3fc; }
    .dark .brain-ui .nb-k-stack-pill, .brain-ui.brain-ui-dark .nb-k-stack-pill { background: #713f12; color: #fef08a; }
    .dark .brain-ui .nb-k-style-pill, .brain-ui.brain-ui-dark .nb-k-style-pill { background: #312e81; color: #c7d2fe; }
    .dark .brain-ui .nb-k-process-pill, .brain-ui.brain-ui-dark .nb-k-process-pill { background: #134e4a; color: #99f6e4; }
    .dark .brain-ui .nb-k-fact-pill, .dark .brain-ui .nb-k-other-pill,
    .brain-ui.brain-ui-dark .nb-k-fact-pill, .brain-ui.brain-ui-dark .nb-k-other-pill { background: #374151; color: #d1d5db; }

    .brain-ui .nb-k-profile { border-left: 3px solid #3b82f6; }
    .brain-ui .nb-k-preference { border-left: 3px solid #8b5cf6; }
    .brain-ui .nb-k-project { border-left: 3px solid #22c55e; }
    .brain-ui .nb-k-decision { border-left: 3px solid #f97316; }
    .brain-ui .nb-k-person { border-left: 3px solid #ec4899; }
    .brain-ui .nb-k-company { border-left: 3px solid #06b6d4; }
    .brain-ui .nb-k-stack { border-left: 3px solid #eab308; }
    .brain-ui .nb-k-style { border-left: 3px solid #6366f1; }
    .brain-ui .nb-k-process { border-left: 3px solid #14b8a6; }
    .brain-ui .nb-k-fact, .brain-ui .nb-k-other { border-left: 3px solid #94a3b8; }

    /* Search form and results */
    .brain-ui .nb-search-form { margin: 0 0 1.25rem; }
    .brain-ui .nb-search-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: flex-end; }
    .brain-ui .nb-search-input-wrap { flex: 1 1 22rem; }
    .brain-ui .nb-search-topics { margin-top: .9rem; }
    .brain-ui .nb-search-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .brain-ui .nb-results { display: flex; flex-direction: column; gap: 1rem; }
    .brain-ui .nb-results.nb-entry-list { gap: .1rem; }
    .brain-ui .nb-results-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; }
    .brain-ui .nb-results-title { margin: 0; font-size: 1rem; font-weight: 650; }
    .brain-ui .nb-results-meta { font-size: .75rem; color: var(--nb-muted); }
    .brain-ui .nb-chip-row { display: flex; flex-wrap: wrap; gap: .4rem; }
    .brain-ui .nb-chip {
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
    .brain-ui .nb-chip:hover, .brain-ui .nb-chip-active { border-color: var(--nb-accent); color: var(--nb-accent); }
    .brain-ui .nb-chip-count { font-size: .7rem; color: var(--nb-muted); }
    .brain-ui .nb-empty-hint { border: 1px dashed var(--nb-border); border-radius: .6rem; padding: 1rem; font-size: .85rem; color: var(--nb-muted); }
    .brain-ui .nb-empty-hint a { color: var(--nb-accent); }

    /* Two-column "list + detail" search page */
    .brain-ui .nb-search-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    /* Reading the open card is the main job here, so the panel gets the room
       and the list beside it stays a compact table of contents. */
    @media (min-width: 1280px) { .brain-ui .nb-search-layout { grid-template-columns: minmax(0, 1fr) 34rem; align-items: start; } }
    @media (min-width: 1680px) { .brain-ui .nb-search-layout { grid-template-columns: minmax(0, 1fr) 46rem; } }
    .brain-ui .nb-search-layout > .nb-search-form { grid-column: 1 / -1; margin-bottom: 0; }
    .brain-ui .nb-search-side { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 1rem; }
    .brain-ui .nb-search-side .nb-peek-card { padding: 1.2rem 1.5rem; }
    .brain-ui .nb-search-side .nb-peek-body { font-size: .95rem; line-height: 1.7; max-width: 70ch; color: var(--nb-text); }
    .brain-ui .nb-search-side .nb-peek-lead { font-size: 1rem; line-height: 1.6; max-width: 70ch; }
    .brain-ui .nb-search-side .nb-peek h3 { font-size: 1.25rem; line-height: 1.3; }

    /* The list: one line per card (the shared knowledge-card partial) */
    .brain-ui .nb-entry-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .1rem; }
    .brain-ui .nb-entry-row { margin: 0; padding: 0; }
    .brain-ui .nb-entry-row-link {
        display: grid;
        grid-template-columns: 7rem minmax(0, 1fr) auto;
        gap: .9rem;
        align-items: baseline;
        padding: .55rem .7rem;
        border-radius: .35rem;
        text-decoration: none;
        border-left: 2px solid transparent;
    }
    .brain-ui .nb-entry-row-link:hover { background: rgba(127, 127, 127, .09); }
    .brain-ui .nb-entry-row.is-active .nb-entry-row-link {
        background: var(--nb-accent-soft);
        border-left-color: var(--nb-accent);
    }
    .brain-ui .nb-entry-row-theme {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--nb-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .brain-ui .nb-entry-row.is-active .nb-entry-row-theme { color: var(--nb-accent); }
    .brain-ui .nb-entry-row-body { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
    .brain-ui .nb-entry-row-title { font-size: .92rem; font-weight: 600; color: var(--nb-text); line-height: 1.35; }
    .brain-ui .nb-entry-row-summary {
        font-size: .8rem;
        line-height: 1.4;
        color: var(--nb-muted);
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .brain-ui .nb-entry-row-project { font-size: .72rem; color: var(--nb-muted); white-space: nowrap; }
    /* The admin list highlights what the query matched. */
    .brain-ui .nb-entry-row-summary mark { padding: 0 .12rem; border-radius: .2rem; background: #fde68a; color: #4b3b02; }
    .dark .brain-ui .nb-entry-row-summary mark, .brain-ui.brain-ui-dark .nb-entry-row-summary mark { background: #78350f; color: #fef3c7; }
    @media (max-width: 720px) {
        .brain-ui .nb-entry-row-link { grid-template-columns: minmax(0, 1fr); gap: .2rem; }
        .brain-ui .nb-entry-row-project { display: none; }
    }

    /* The card as a document: lead paragraph, then the body */
    .brain-ui .nb-peek { border: 1px solid var(--nb-border); border-radius: .75rem; padding: .9rem 1rem; background: var(--nb-card); }
    .brain-ui .nb-peek h3 { margin: 0 0 .25rem; font-size: 1rem; line-height: 1.3; }
    .brain-ui .nb-peek-meta { font-size: .72rem; color: var(--nb-muted); margin-bottom: .5rem; }
    .brain-ui .nb-peek-text { font-size: .82rem; line-height: 1.45; }
    .brain-ui .nb-peek-lead {
        font-size: .88rem;
        line-height: 1.5;
        margin: 0 0 .55rem;
        padding-left: .65rem;
        border-left: 2px solid var(--nb-accent);
        color: var(--nb-text);
    }
    .brain-ui .nb-peek-body { font-size: .84rem; line-height: 1.55; color: var(--nb-text); }
    .brain-ui .nb-peek-body a { color: var(--nb-accent); }
    .brain-ui .nb-peek-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; margin: .6rem 0; }
    .brain-ui .nb-peek-grid div { font-size: .78rem; }
    .brain-ui .nb-peek-grid b { display: block; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--nb-muted); font-weight: 600; }
    .brain-ui .nb-graph-mini { display: flex; flex-wrap: wrap; gap: .4rem; }
    .brain-ui .nb-graph-mini a { font-size: .8rem; }

    /* Quiet footer of a card: links to neighbouring cards */
    .brain-ui .nb-card-w-links { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .9rem; border-top: 1px solid var(--nb-border); padding-top: .55rem; margin-top: .9rem; }
    .brain-ui .nb-card-w-links-title { width: 100%; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--nb-muted); }
    .brain-ui .nb-card-w-link { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--nb-muted); max-width: 100%; }
    .brain-ui .nb-card-w-link:hover { color: var(--nb-accent); }
    .brain-ui .nb-card-w-link-dot { width: .4rem; height: .4rem; border-radius: 999px; background: #22c55e; flex: none; }
    .brain-ui .nb-card-w-link-graph .nb-card-w-link-dot { background: var(--nb-accent); }
    .brain-ui .nb-card-w-link-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 16rem; }
    .brain-ui .nb-card-w-link-meta { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; opacity: .75; }
    .brain-ui .nb-card-w-link-project { margin-left: auto; font-weight: 600; color: var(--nb-accent); }
    .brain-ui .nb-card-w-link-mark { background: none; border: 0; padding: 0; cursor: pointer; font: inherit; }
    .brain-ui .nb-card-w-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; font-size: .72rem; color: var(--nb-muted); }
    .brain-ui .nb-card-w-project { color: var(--nb-accent); font-weight: 600; }
    .brain-ui .nb-card-w-date { color: var(--nb-muted); }

    /* Projects */
    .brain-ui .nb-projects-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 900px) { .brain-ui .nb-projects-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1400px) { .brain-ui .nb-projects-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .brain-ui .nb-project-card { display: flex; flex-direction: column; gap: .6rem; }
    .brain-ui .nb-project-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
    .brain-ui .nb-project-head h2 { margin: 0; font-size: 1rem; line-height: 1.3; }
    .brain-ui .nb-project-head h2 a { color: var(--nb-text); }
    .brain-ui .nb-project-head h2 a:hover { color: var(--nb-accent); }
    .brain-ui .nb-project-meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .72rem; color: var(--nb-muted); }

    /* Overview: weekly bars, topic rows, source table */
    .brain-ui .nb-chart { display: flex; align-items: flex-end; gap: .3rem; height: 10rem; padding-top: .4rem; }
    .brain-ui .nb-chart-bar { flex: 1 1 0; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; gap: .25rem; }
    .brain-ui .nb-chart-fill { width: 100%; border-radius: .25rem .25rem 0 0; background: linear-gradient(180deg, #8b5cf6, #6d28d9); min-height: 2px; }
    .brain-ui .nb-chart-label { font-size: .6rem; color: var(--nb-muted); white-space: nowrap; }
    .brain-ui .nb-topic-row { display: grid; grid-template-columns: minmax(0, 1fr) 6rem 2rem; align-items: center; gap: .6rem; padding: .3rem 0; color: var(--nb-text); text-decoration: none; border-bottom: 1px solid var(--nb-border); }
    .brain-ui .nb-topic-row:last-child { border-bottom: 0; }
    .brain-ui .nb-topic-row:hover .nb-topic-name { color: var(--nb-accent); }
    .brain-ui .nb-topic-name { font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .brain-ui .nb-topic-bar { height: .4rem; border-radius: 999px; background: var(--nb-accent-soft); overflow: hidden; }
    .brain-ui .nb-topic-bar i { display: block; height: 100%; background: #8b5cf6; border-radius: 999px; }
    .brain-ui .nb-topic-count { font-size: .75rem; color: var(--nb-muted); text-align: right; }
    .brain-ui .nb-table { display: flex; flex-direction: column; }
    .brain-ui .nb-table-row { display: grid; grid-template-columns: minmax(0, 2fr) 6rem 6rem minmax(0, 1.5fr); gap: .75rem; align-items: center; padding: .5rem 0; border-bottom: 1px solid var(--nb-border); font-size: .85rem; }
    .brain-ui .nb-table-row:last-child { border-bottom: 0; }
    .brain-ui .nb-table-head { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--nb-muted); }
    .brain-ui .nb-status-dot { display: inline-block; width: .45rem; height: .45rem; border-radius: 999px; background: #cbd5e1; margin-right: .35rem; }
    .brain-ui .nb-status-ok { background: #22c55e; }
    .brain-ui .nb-status-error { background: #ef4444; }

    /* Atoms: the facts a card states */
    .brain-ui .nb-atoms { display: flex; flex-direction: column; gap: .35rem; border-top: 1px dashed var(--nb-border); padding-top: .6rem; }
    .brain-ui .nb-atom { display: grid; grid-template-columns: 5.5rem minmax(0, 1fr); gap: .6rem; align-items: start; }
    .brain-ui .nb-atom-type {
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--nb-muted);
        padding-top: .15rem;
        white-space: nowrap;
    }
    .brain-ui .nb-atom-text { font-size: .82rem; line-height: 1.45; color: var(--nb-text); }
    .brain-ui .nb-atoms-more { font-size: .75rem; }
    .brain-ui .nb-atoms-more a { color: var(--nb-accent); }
</style>

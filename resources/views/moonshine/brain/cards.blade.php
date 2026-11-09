<style>
    /* Knowledge cards, graph page and shared "second brain" primitives. */
    .nb-pill {
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
    .nb-pill-draft { background: #fef3c7; color: #92400e; }
    .dark .nb-pill-draft { background: #451a03; color: #fcd34d; }
    .nb-k-profile-pill { background: #dbeafe; color: #1e40af; }
    .nb-k-preference-pill { background: #ede9fe; color: #5b21b6; }
    .nb-k-project-pill { background: #dcfce7; color: #166534; }
    .nb-k-decision-pill { background: #ffedd5; color: #9a3412; }
    .nb-k-person-pill { background: #fce7f3; color: #9d174d; }
    .nb-k-company-pill { background: #cffafe; color: #155e75; }
    .nb-k-stack-pill { background: #fef9c3; color: #854d0e; }
    .nb-k-style-pill { background: #e0e7ff; color: #3730a3; }
    .nb-k-process-pill { background: #ccfbf1; color: #115e59; }
    .nb-k-fact-pill { background: #f3f4f6; color: #374151; }
    .nb-k-other-pill { background: #f3f4f6; color: #374151; }
    .dark .nb-k-profile-pill { background: #1e3a8a; color: #bfdbfe; }
    .dark .nb-k-preference-pill { background: #4c1d95; color: #ddd6fe; }
    .dark .nb-k-project-pill { background: #14532d; color: #bbf7d0; }
    .dark .nb-k-decision-pill { background: #7c2d12; color: #fed7aa; }
    .dark .nb-k-person-pill { background: #831843; color: #fbcfe8; }
    .dark .nb-k-company-pill { background: #164e63; color: #a5f3fc; }
    .dark .nb-k-stack-pill { background: #713f12; color: #fef08a; }
    .dark .nb-k-style-pill { background: #312e81; color: #c7d2fe; }
    .dark .nb-k-process-pill { background: #134e4a; color: #99f6e4; }
    .dark .nb-k-fact-pill { background: #374151; color: #d1d5db; }
    .dark .nb-k-other-pill { background: #374151; color: #d1d5db; }

    .nb-k-profile { border-left: 3px solid #3b82f6; }
    .nb-k-preference { border-left: 3px solid #8b5cf6; }
    .nb-k-project { border-left: 3px solid #22c55e; }
    .nb-k-decision { border-left: 3px solid #f97316; }
    .nb-k-person { border-left: 3px solid #ec4899; }
    .nb-k-company { border-left: 3px solid #06b6d4; }
    .nb-k-stack { border-left: 3px solid #eab308; }
    .nb-k-style { border-left: 3px solid #6366f1; }
    .nb-k-process { border-left: 3px solid #14b8a6; }
    .nb-k-fact { border-left: 3px solid #94a3b8; }
    .nb-k-other { border-left: 3px solid #94a3b8; }

    .nb-knowledge-card {
        display: flex;
        flex-direction: column;
        gap: .7rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .nb-knowledge-card:hover { border-color: var(--nb-accent); box-shadow: 0 4px 18px rgb(0 0 0 / .08); }
    .nb-card-w-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .nb-card-w-title { margin: 0; font-size: 1.02rem; line-height: 1.35; font-weight: 650; }
    .nb-card-w-title a { color: var(--nb-text); }
    .nb-card-w-title a:hover { color: var(--nb-accent); }
    .nb-card-w-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; font-size: .72rem; color: var(--nb-muted); }
    .nb-card-w-meta .nb-pill { padding: .1rem .5rem; font-size: .68rem; }
    .nb-card-w-project { color: var(--nb-accent); font-weight: 600; }
    .nb-card-w-date { color: var(--nb-muted); }
    .nb-card-w-summary { font-size: .875rem; line-height: 1.5; color: var(--nb-text); }
    .nb-card-w-summary mark { padding: 0 .12rem; border-radius: .2rem; background: #fde68a; color: #4b3b02; }
    .dark .nb-card-w-summary mark { background: #78350f; color: #fef3c7; }

    .nb-atoms { display: flex; flex-direction: column; gap: .35rem; border-top: 1px dashed var(--nb-border); padding-top: .6rem; }
    .nb-atom { display: grid; grid-template-columns: 5.5rem minmax(0, 1fr); gap: .6rem; align-items: start; }
    .nb-atom-type {
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--nb-muted);
        padding-top: .15rem;
        white-space: nowrap;
    }
    .nb-atom-text { font-size: .82rem; line-height: 1.45; color: var(--nb-text); }
    .nb-atoms-more { font-size: .75rem; }
    .nb-atoms-more a { color: var(--nb-accent); }

    .nb-card-w-links { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .9rem; border-top: 1px solid var(--nb-border); padding-top: .55rem; }
    .nb-card-w-link { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--nb-muted); max-width: 100%; }
    .nb-card-w-link:hover { color: var(--nb-accent); }
    .nb-card-w-link-dot { width: .4rem; height: .4rem; border-radius: 999px; background: #22c55e; flex: none; }
    .nb-card-w-link-graph .nb-card-w-link-dot { background: var(--nb-accent); }
    .nb-card-w-link-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 16rem; }
    .nb-card-w-link-meta { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; opacity: .75; }
    .nb-card-w-link-project { margin-left: auto; font-weight: 600; color: var(--nb-accent); }

    .nb-results { display: flex; flex-direction: column; gap: 1rem; }
    .nb-results-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; }
    .nb-results-title { margin: 0; font-size: 1rem; font-weight: 650; }
    .nb-results-meta { font-size: .75rem; color: var(--nb-muted); }
    .nb-search-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: flex-end; }
    .nb-search-input-wrap { flex: 1 1 22rem; }
    .nb-chip-row { display: flex; flex-wrap: wrap; gap: .4rem; }
    .nb-chip {
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
    .nb-chip:hover, .nb-chip-active { border-color: var(--nb-accent); color: var(--nb-accent); }
    .nb-chip-count { font-size: .7rem; color: var(--nb-muted); }
    .nb-empty-hint { border: 1px dashed var(--nb-border); border-radius: .6rem; padding: 1rem; font-size: .85rem; color: var(--nb-muted); }

    /* Projects page */
    .nb-projects-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 900px) { .nb-projects-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1400px) { .nb-projects-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .nb-project-card { display: flex; flex-direction: column; gap: .6rem; }
    .nb-project-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
    .nb-project-head h2 { margin: 0; font-size: 1rem; line-height: 1.3; }
    .nb-project-head h2 a { color: var(--nb-text); }
    .nb-project-head h2 a:hover { color: var(--nb-accent); }
    .nb-project-meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .72rem; color: var(--nb-muted); }

    /* Overview: weekly bars, topic rows, source table */
    .nb-chart { display: flex; align-items: flex-end; gap: .3rem; height: 10rem; padding-top: .4rem; }
    .nb-chart-bar { flex: 1 1 0; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; gap: .25rem; }
    .nb-chart-fill { width: 100%; border-radius: .25rem .25rem 0 0; background: linear-gradient(180deg, #8b5cf6, #6d28d9); min-height: 2px; }
    .nb-chart-label { font-size: .6rem; color: var(--nb-muted); white-space: nowrap; }
    .nb-topic-row { display: grid; grid-template-columns: minmax(0, 1fr) 6rem 2rem; align-items: center; gap: .6rem; padding: .3rem 0; color: var(--nb-text); text-decoration: none; border-bottom: 1px solid var(--nb-border); }
    .nb-topic-row:last-child { border-bottom: 0; }
    .nb-topic-row:hover .nb-topic-name { color: var(--nb-accent); }
    .nb-topic-name { font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nb-topic-bar { height: .4rem; border-radius: 999px; background: var(--nb-accent-soft); overflow: hidden; }
    .nb-topic-bar i { display: block; height: 100%; background: #8b5cf6; border-radius: 999px; }
    .nb-topic-count { font-size: .75rem; color: var(--nb-muted); text-align: right; }
    .nb-table { display: flex; flex-direction: column; }
    .nb-table-row { display: grid; grid-template-columns: minmax(0, 2fr) 6rem 6rem minmax(0, 1.5fr); gap: .75rem; align-items: center; padding: .5rem 0; border-bottom: 1px solid var(--nb-border); font-size: .85rem; }
    .nb-table-row:last-child { border-bottom: 0; }
    .nb-table-head { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--nb-muted); }
    .nb-status-dot { display: inline-block; width: .45rem; height: .45rem; border-radius: 999px; background: #cbd5e1; margin-right: .35rem; }
    .nb-status-ok { background: #22c55e; }
    .nb-status-error { background: #ef4444; }

    /* Graph page */
    .nb-graph-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 1200px) { .nb-graph-layout { grid-template-columns: minmax(0, 1fr) 22rem; } }
    .nb-graph-wrap { position: relative; border: 1px solid var(--nb-border); border-radius: .75rem; background: var(--nb-card); overflow: hidden; }
    .nb-graph-canvas { display: block; width: 100%; height: 34rem; touch-action: none; cursor: grab; }
    .nb-graph-canvas:active { cursor: grabbing; }
    .nb-graph-toolbar {
        position: absolute;
        top: .6rem;
        left: .6rem;
        display: flex;
        gap: .3rem;
        z-index: 2;
    }
    .nb-graph-toolbar .nb-btn { padding: .35rem .6rem; font-size: .8rem; background: var(--nb-card); }
    .nb-graph-legend {
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
    .nb-graph-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .nb-graph-legend i { width: .5rem; height: .5rem; border-radius: 999px; display: inline-block; }
    .nb-graph-search {
        position: absolute;
        top: .6rem;
        right: .6rem;
        width: min(20rem, 60%);
        z-index: 2;
    }
    .nb-graph-side { display: flex; flex-direction: column; gap: .75rem; }
    .nb-peek { border: 1px solid var(--nb-border); border-radius: .75rem; padding: .9rem 1rem; background: var(--nb-card); }
    .nb-peek h3 { margin: 0 0 .25rem; font-size: 1rem; line-height: 1.3; }
    .nb-peek-meta { font-size: .72rem; color: var(--nb-muted); margin-bottom: .5rem; }
    .nb-peek-text { font-size: .82rem; line-height: 1.45; }
    /* The card as a document: lead paragraph, then the body. */
    .nb-peek-lead {
        font-size: .88rem;
        line-height: 1.5;
        margin: 0 0 .55rem;
        padding-left: .65rem;
        border-left: 2px solid var(--nb-accent);
        color: var(--nb-text);
    }
    .nb-peek-body { font-size: .84rem; line-height: 1.55; color: var(--nb-text); }
    .nb-peek-body a { color: var(--nb-accent); }
    .nb-peek-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; margin: .6rem 0; }
    .nb-peek-grid div { font-size: .78rem; }
    .nb-peek-grid b { display: block; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--nb-muted); font-weight: 600; }
    .nb-graph-mini { display: flex; flex-wrap: wrap; gap: .4rem; }
    .nb-graph-mini a { font-size: .8rem; }
    .nb-card-w-link-mark { background: none; border: 0; padding: 0; cursor: pointer; font: inherit; }

    /* Two-column "list + detail" search page */
    .nb-search-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 1280px) { .nb-search-layout { grid-template-columns: minmax(0, 1fr) 24rem; align-items: start; } }
    .nb-search-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .nb-search-side { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 1rem; }
</style>

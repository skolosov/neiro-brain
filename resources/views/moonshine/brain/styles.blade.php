<style>
    .nb-page {
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
    .dark .nb-page {
        --nb-text: #f9fafb;
        --nb-muted: #9ca3af;
        --nb-border: #374151;
        --nb-card: #1f2937;
        --nb-input: #111827;
        --nb-accent: #c4b5fd;
        --nb-accent-soft: #2e1065;
    }
    .nb-page a { color: var(--nb-accent); text-decoration: none; }
    .nb-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .nb-title { margin: 0; font-size: 1.5rem; line-height: 1.25; font-weight: 700; }
    .nb-subtitle { margin: .3rem 0 0; font-size: .9rem; color: var(--nb-muted); }
    .nb-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .nb-btn { display: inline-block; border: 1px solid transparent; border-radius: .5rem; padding: .55rem .9rem; font-size: .85rem; font-weight: 600; line-height: 1.2; cursor: pointer; text-decoration: none; }
    .nb-btn-primary { background: #7c3aed; color: #fff !important; }
    .nb-btn-primary:hover { background: #6d28d9; }
    .nb-btn-secondary { background: transparent; border-color: var(--nb-border); color: var(--nb-text) !important; }
    .nb-btn-secondary:hover { background: var(--nb-accent-soft); }
    .nb-grid-4 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .nb-grid-2 { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
    @media (min-width: 1024px) {
        .nb-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .nb-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .nb-card {
        background: var(--nb-card);
        border: 1px solid var(--nb-border);
        border-radius: .75rem;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / .06);
    }
    .nb-metric-label { font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--nb-muted); }
    .nb-metric-value { margin: .2rem 0 0; font-size: 1.8rem; line-height: 1.15; font-weight: 700; }
    .nb-metric-hint { font-size: .75rem; color: var(--nb-muted); }
    .nb-card-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
    .nb-section-title { margin: 0; font-size: 1.05rem; font-weight: 650; }
    .nb-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--nb-border); }
    .nb-row:last-child { border-bottom: 0; }
    .nb-row-title { font-weight: 550; font-size: .9rem; }
    .nb-meta { margin-top: .15rem; font-size: .75rem; color: var(--nb-muted); }
    .nb-badge { display: inline-block; white-space: nowrap; border-radius: 999px; padding: .12rem .55rem; font-size: .7rem; background: var(--nb-accent-soft); color: var(--nb-accent); }
    .nb-empty { margin: .4rem 0; color: var(--nb-muted); font-size: .875rem; }
    .nb-flash { border: 1px solid #86efac; background: #f0fdf4; color: #14532d; padding: .8rem 1rem; border-radius: .5rem; font-size: .9rem; }
    .dark .nb-flash { border-color: #166534; background: #052e16; color: #bbf7d0; }
    .nb-flash-error { border-color: #fca5a5; background: #fef2f2; color: #7f1d1d; }
    .dark .nb-flash-error { border-color: #991b1b; background: #450a0a; color: #fecaca; }
    .nb-form { display: flex; flex-direction: column; gap: 1.25rem; }
    .nb-section { background: var(--nb-card); border: 1px solid var(--nb-border); border-radius: .75rem; padding: 1.1rem 1.2rem; }
    .nb-section > h2 { margin: 0 0 .9rem; font-size: 1.05rem; font-weight: 650; }
    .nb-form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    @media (min-width: 768px) { .nb-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .nb-form-grid.nb-form-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .nb-label { display: block; margin-bottom: .3rem; font-size: .8rem; font-weight: 600; }
    .nb-input, .nb-select {
        box-sizing: border-box;
        width: 100%;
        border: 1px solid var(--nb-border);
        border-radius: .5rem;
        padding: .55rem .65rem;
        background: var(--nb-input);
        color: var(--nb-text);
        font-size: .875rem;
    }
    .nb-input:focus, .nb-select:focus { outline: 2px solid color-mix(in srgb, #7c3aed 45%, transparent); outline-offset: 1px; border-color: #7c3aed; }
    .nb-checks { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: .9rem; }
    .nb-check { display: flex; align-items: center; gap: .45rem; font-size: .85rem; }
    .nb-help { margin: .45rem 0 0; font-size: .75rem; color: var(--nb-muted); }
    .nb-actions-bottom { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
    .nb-note { font-size: .8rem; color: var(--nb-muted); }
    .nb-token {
        border: 1px solid var(--nb-border);
        border-radius: .6rem;
        padding: .8rem .9rem;
        margin-bottom: .75rem;
        background: var(--nb-accent-soft);
    }
    .nb-token-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .nb-token-value code {
        display: inline-block;
        max-width: 100%;
        padding: .25rem .55rem;
        border-radius: .4rem;
        background: var(--nb-card);
        border: 1px solid var(--nb-border);
        color: var(--nb-text);
        font-size: .82rem;
        word-break: break-all;
    }
    .nb-token-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .7rem; }
    .nb-token-actions .nb-btn { padding: .4rem .8rem; font-size: .78rem; }
</style>

<style>
    .mel-results {
        --mr-primary: #075c7a;
        --mr-primary-dark: #05465d;
        --mr-primary-soft: #eaf4f7;
        --mr-ink: #17343e;
        --mr-muted: #657980;
        --mr-line: #dce7ea;
        --mr-canvas: #f5f8f9;
        --mr-success: #187459;
        --mr-warning: #a56a17;
        --mr-danger: #ae3f3d;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--mr-ink);
        font-size: .875rem;
    }
    .mel-results *, .mel-results *::before, .mel-results *::after { box-sizing: border-box; }
    .mel-results .mr-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.45rem 1.5rem;
        border: 1px solid rgba(7,92,122,.18);
        border-radius: 18px;
        background: linear-gradient(125deg,var(--mr-primary-dark),var(--mr-primary));
        color: #fff;
        box-shadow: 0 15px 36px rgba(6,73,96,.14);
    }
    .mel-results .mr-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .67rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-results .mr-header h1 { margin: 0; color: #fff; font-size: clamp(1.35rem,2vw,1.78rem); font-weight: 800; line-height: 1.2; }
    .mel-results .mr-header p { max-width: 820px; margin: .48rem 0 0; color: rgba(255,255,255,.82); font-size: .79rem; line-height: 1.55; }
    .mel-results .mr-header-side { display: flex; flex: 0 0 auto; align-items: flex-end; flex-direction: column; gap: .65rem; }
    .mel-results .mr-scope { padding: .58rem .75rem; border: 1px solid rgba(255,255,255,.25); border-radius: 11px; background: rgba(255,255,255,.1); text-align: right; }
    .mel-results .mr-scope span, .mel-results .mr-scope strong { display: block; }
    .mel-results .mr-scope span { color: rgba(255,255,255,.7); font-size: .59rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .mel-results .mr-scope strong { margin-top: .12rem; color: #fff; font-size: .74rem; }
    .mel-results .mr-header-actions, .mel-results .mr-actions { display: flex; flex-wrap: wrap; gap: .45rem; }
    .mel-results .mr-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .38rem; padding: .5rem .76rem; border: 1px solid transparent; border-radius: 9px; background: #fff; color: #435d66; font-size: .68rem; font-weight: 780; line-height: 1.2; text-decoration: none; cursor: pointer; }
    .mel-results .mr-btn:hover { color: var(--mr-primary); }
    .mel-results .mr-btn-primary { border-color: var(--mr-primary); background: var(--mr-primary); color: #fff; }
    .mel-results .mr-btn-primary:hover { background: var(--mr-primary-dark); color: #fff; }
    .mel-results .mr-btn-secondary { border-color: #ccdade; background: #fff; color: #425b64; }
    .mel-results .mr-btn-secondary:hover { border-color: var(--mr-primary); color: var(--mr-primary); }
    .mel-results .mr-btn-header { border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; }
    .mel-results .mr-btn-header:hover { background: #fff; color: var(--mr-primary); }
    .mel-results .mr-btn-small { min-height: 32px; padding: .38rem .58rem; font-size: .64rem; }
    .mel-results .mr-guardrail { display: flex; align-items: flex-start; gap: .7rem; margin-top: .9rem; padding: .75rem .9rem; border: 1px solid #bfe0d1; border-radius: 12px; background: #eff8f4; color: #245f4a; }
    .mel-results .mr-guardrail-mark { display: grid; width: 30px; height: 30px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #dcefe6; color: var(--mr-success); font-size: .64rem; font-weight: 900; }
    .mel-results .mr-guardrail strong { display: block; font-size: .71rem; }
    .mel-results .mr-guardrail p { margin: .16rem 0 0; color: #477161; font-size: .65rem; line-height: 1.45; }
    .mel-results .mr-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }
    .mel-results .mr-metric { position: relative; min-height: 105px; overflow: hidden; padding: .95rem 1rem; border: 1px solid var(--mr-line); border-radius: 14px; background: #fff; box-shadow: 0 5px 18px rgba(24,55,65,.045); }
    .mel-results .mr-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric,var(--mr-primary)); content: ""; }
    .mel-results .mr-metric-label { display: block; color: var(--mr-muted); font-size: .61rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
    .mel-results .mr-metric strong { display: block; margin-top: .38rem; color: var(--mr-ink); font-size: 1.48rem; font-weight: 830; line-height: 1; }
    .mel-results .mr-metric small { display: block; margin-top: .43rem; color: var(--mr-muted); font-size: .63rem; line-height: 1.35; }
    .mel-results .mr-panel { overflow: hidden; border: 1px solid var(--mr-line); border-radius: 16px; background: #fff; box-shadow: 0 7px 22px rgba(25,57,67,.045); }
    .mel-results .mr-panel + .mr-panel { margin-top: 1rem; }
    .mel-results .mr-panel-head { display: flex; min-height: 60px; align-items: center; justify-content: space-between; gap: 1rem; padding: .86rem 1rem; border-bottom: 1px solid var(--mr-line); background: #fff; }
    .mel-results .mr-panel-head h2 { margin: 0; color: var(--mr-ink); font-size: .9rem; font-weight: 790; }
    .mel-results .mr-panel-head p { margin: .2rem 0 0; color: var(--mr-muted); font-size: .65rem; line-height: 1.4; }
    .mel-results .mr-panel-body { padding: 1rem; }
    .mel-results details.mr-panel > summary { cursor: pointer; list-style: none; }
    .mel-results details.mr-panel > summary::-webkit-details-marker { display: none; }
    .mel-results .mr-summary-right { display: flex; align-items: center; gap: .45rem; }
    .mel-results .mr-chevron { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--mr-line); border-radius: 8px; color: var(--mr-muted); font-size: .72rem; transition: transform .2s ease; }
    .mel-results details[open] .mr-chevron { transform: rotate(180deg); }
    .mel-results .mr-badge { display: inline-flex; width: fit-content; align-items: center; gap: .28rem; padding: .27rem .5rem; border-radius: 999px; background: var(--mr-primary-soft); color: var(--mr-primary); font-size: .61rem; font-weight: 800; line-height: 1.15; }
    .mel-results .mr-filter-grid { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: .78rem; }
    .mel-results .mr-field-wide { grid-column: span 2; }
    .mel-results .mr-field label { display: block; margin-bottom: .31rem; color: #48616a; font-size: .63rem; font-weight: 760; }
    .mel-results .mr-field small { display: block; margin-top: .27rem; color: var(--mr-muted); font-size: .59rem; line-height: 1.35; }
    .mel-results .form-control, .mel-results .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--mr-ink); font-size: .72rem; box-shadow: none; }
    .mel-results .form-control:focus, .mel-results .form-select:focus { border-color: var(--mr-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-results .mr-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: .75rem; padding-top: .9rem; border-top: 1px solid var(--mr-line); }
    .mel-results .mr-filter-tip { max-width: 670px; margin: 0; color: var(--mr-muted); font-size: .62rem; line-height: 1.45; }
    .mel-results .mr-grid { display: grid; grid-template-columns: minmax(0,1.28fr) minmax(360px,.72fr); gap: 1rem; margin-top: 1rem; }
    .mel-results .mr-chart { min-height: 300px; padding: .45rem .75rem .75rem; }
    .mel-results .mr-chart-tall { min-height: 370px; }
    .mel-results .mr-legend { display: flex; flex-wrap: wrap; gap: .45rem .8rem; padding: 0 1rem 1rem; }
    .mel-results .mr-legend-item { display: inline-flex; align-items: center; gap: .38rem; color: #516970; font-size: .62rem; }
    .mel-results .mr-legend-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--legend); }
    .mel-results .mr-mini-charts { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .6rem; padding: .7rem; }
    .mel-results .mr-mini-chart { min-width: 0; border: 1px solid #e7eef0; border-radius: 12px; background: #fbfcfc; }
    .mel-results .mr-mini-chart h3 { margin: .75rem .8rem 0; color: var(--mr-ink); font-size: .7rem; font-weight: 780; }
    .mel-results .mr-mini-chart p { margin: .16rem .8rem 0; color: var(--mr-muted); font-size: .58rem; }
    .mel-results .mr-mini-chart-canvas { min-height: 235px; }
    .mel-results .mr-insight-grid { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(330px,.8fr); gap: 1rem; margin-top: 1rem; }
    .mel-results .mr-attention-list { display: grid; gap: .55rem; }
    .mel-results .mr-attention-item { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .8rem; align-items: center; padding: .72rem .8rem; border: 1px solid #e5edef; border-radius: 11px; }
    .mel-results .mr-attention-code { display: inline-flex; margin-right: .38rem; padding: .2rem .4rem; border-radius: 6px; background: var(--mr-primary-soft); color: var(--mr-primary); font-size: .59rem; font-weight: 850; }
    .mel-results .mr-attention-item strong { color: var(--mr-ink); font-size: .69rem; }
    .mel-results .mr-attention-item p { margin: .25rem 0 0; color: var(--mr-muted); font-size: .61rem; line-height: 1.4; }
    .mel-results .mr-attention-value { text-align: right; }
    .mel-results .mr-attention-value strong { display: block; font-size: .82rem; }
    .mel-results .mr-signal-list { display: grid; gap: .65rem; }
    .mel-results .mr-signal { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding-bottom: .62rem; border-bottom: 1px solid #ebf0f2; }
    .mel-results .mr-signal:last-child { padding-bottom: 0; border-bottom: 0; }
    .mel-results .mr-signal span { color: var(--mr-muted); font-size: .64rem; }
    .mel-results .mr-signal strong { color: var(--mr-ink); font-size: .72rem; text-align: right; }
    .mel-results .mr-empty { padding: 3rem 1rem; text-align: center; }
    .mel-results .mr-empty-mark { display: grid; width: 50px; height: 50px; margin: 0 auto .75rem; place-items: center; border-radius: 14px; background: var(--mr-primary-soft); color: var(--mr-primary); font-size: .68rem; font-weight: 900; }
    .mel-results .mr-empty strong { display: block; font-size: .78rem; }
    .mel-results .mr-empty p { max-width: 500px; margin: .3rem auto 0; color: var(--mr-muted); font-size: .65rem; line-height: 1.5; }
    .mel-results .mr-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .75rem 1rem; border-bottom: 1px solid var(--mr-line); background: #fafcfc; }
    .mel-results .mr-table-controls { display: flex; min-width: 0; flex: 1; gap: .5rem; }
    .mel-results .mr-search { position: relative; width: min(380px,100%); }
    .mel-results .mr-search input { width: 100%; padding-left: 2rem; }
    .mel-results .mr-search-mark { position: absolute; top: 50%; left: .72rem; color: #81939a; font-size: .7rem; transform: translateY(-50%); pointer-events: none; }
    .mel-results .mr-status-filter { width: 190px; }
    .mel-results .mr-row-count { flex: 0 0 auto; color: var(--mr-muted); font-size: .63rem; font-weight: 730; }
    .mel-results .mr-table-wrap { max-height: 690px; overflow: auto; scrollbar-color: #b9cbd0 #edf2f3; scrollbar-width: thin; }
    .mel-results .mr-table { width: 100%; min-width: 1450px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .mel-results .mr-table th { position: sticky; z-index: 2; top: 0; padding: .68rem .72rem; border-bottom: 1px solid var(--mr-line); background: #f3f7f8; color: #596e75; font-size: .59rem; font-weight: 820; letter-spacing: .045em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-results .mr-table td { padding: .75rem .72rem; border-bottom: 1px solid #e8eff1; background: #fff; color: #344f58; font-size: .66rem; line-height: 1.45; vertical-align: top; }
    .mel-results .mr-table tbody tr:hover td { background: #fafcfc; }
    .mel-results .mr-table th:first-child, .mel-results .mr-table td:first-child { position: sticky; z-index: 1; left: 0; min-width: 280px; box-shadow: 7px 0 12px -13px #17343e; }
    .mel-results .mr-table th:first-child { z-index: 3; background: #f3f7f8; }
    .mel-results .mr-indicator { display: flex; align-items: flex-start; gap: .55rem; }
    .mel-results .mr-code { display: inline-flex; flex: 0 0 auto; padding: .22rem .42rem; border-radius: 6px; background: var(--mr-primary-soft); color: var(--mr-primary); font-size: .59rem; font-weight: 850; }
    .mel-results .mr-indicator strong { display: block; max-width: 380px; color: var(--mr-ink); font-size: .68rem; line-height: 1.4; }
    .mel-results .mr-indicator small, .mel-results .mr-cell-note { display: block; margin-top: .22rem; color: var(--mr-muted); font-size: .58rem; }
    .mel-results .mr-status { display: inline-flex; padding: .27rem .5rem; border-radius: 999px; color: #fff; font-size: .59rem; font-weight: 820; white-space: nowrap; }
    .mel-results .mr-trend { font-weight: 790; white-space: nowrap; }
    .mel-results .mr-trend.up { color: var(--mr-success); }
    .mel-results .mr-trend.down { color: var(--mr-danger); }
    .mel-results .mr-trend.flat, .mel-results .mr-trend.changed, .mel-results .mr-trend.none { color: var(--mr-muted); }
    .mel-results .mr-progress { width: 105px; height: 6px; margin-top: .35rem; overflow: hidden; border-radius: 999px; background: #e8eff1; }
    .mel-results .mr-progress span { display: block; height: 100%; border-radius: inherit; background: var(--progress,var(--mr-primary)); }
    .mel-results .mr-table-filter-empty { display: none; }
    .mel-results .mr-scroll-tip { display: flex; justify-content: space-between; gap: .5rem; padding: .6rem 1rem; border-top: 1px solid var(--mr-line); background: #fafcfc; color: var(--mr-muted); font-size: .59rem; }
    .mel-results .mr-setup { margin-top: 1rem; }
    @media(max-width:1250px) {
        .mel-results .mr-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-results .mr-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-results .mr-grid, .mel-results .mr-insight-grid { grid-template-columns: 1fr; }
    }
    @media(max-width:800px) {
        .mel-results .mr-header { flex-direction: column; }
        .mel-results .mr-header-side { width: 100%; align-items: stretch; }
        .mel-results .mr-scope { text-align: left; }
        .mel-results .mr-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-results .mr-filter-actions, .mel-results .mr-table-toolbar { align-items: stretch; flex-direction: column; }
        .mel-results .mr-table-controls { width: 100%; }
        .mel-results .mr-row-count { align-self: flex-start; }
    }
    @media(max-width:560px) {
        .mel-results .mr-header { padding: 1.1rem; border-radius: 14px; }
        .mel-results .mr-metrics, .mel-results .mr-filter-grid, .mel-results .mr-mini-charts { grid-template-columns: 1fr; }
        .mel-results .mr-field-wide { grid-column: span 1; }
        .mel-results .mr-filter-actions .mr-actions, .mel-results .mr-header-actions { display: grid; width: 100%; grid-template-columns: 1fr; }
        .mel-results .mr-filter-tip { order: 2; }
        .mel-results .mr-table-controls { flex-direction: column; }
        .mel-results .mr-search, .mel-results .mr-status-filter { width: 100%; }
        .mel-results .mr-panel-head { align-items: flex-start; }
        .mel-results .mr-summary-right .mr-badge { display: none; }
    }
    @media(prefers-reduced-motion:reduce) {
        .mel-results *, .mel-results *::before, .mel-results *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>

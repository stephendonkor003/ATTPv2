<style>
    .mel-review-shell {
        --mel-primary: #075c7a;
        --mel-primary-dark: #05465d;
        --mel-primary-soft: #eaf4f7;
        --mel-ink: #18333d;
        --mel-muted: #64777e;
        --mel-line: #dce7ea;
        --mel-surface: #ffffff;
        --mel-canvas: #f5f8f9;
        --mel-success: #18765a;
        --mel-warning: #a76512;
        --mel-danger: #b33b39;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--mel-ink);
        font-size: .875rem;
    }
    .mel-review-shell *, .mel-review-shell *::before, .mel-review-shell *::after { box-sizing: border-box; }
    .mel-review-shell a { color: var(--mel-primary); }
    .mel-review-shell .mel-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.35rem 1.45rem;
        border: 1px solid rgba(7, 92, 122, .18);
        border-radius: 18px;
        background: linear-gradient(125deg, var(--mel-primary-dark), var(--mel-primary));
        box-shadow: 0 14px 34px rgba(7, 70, 93, .13);
        color: #fff;
    }
    .mel-review-shell .mel-eyebrow {
        display: block;
        margin-bottom: .35rem;
        color: rgba(255,255,255,.72);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
    }
    .mel-review-shell .mel-page-header h1 { margin: 0; color: #fff; font-size: clamp(1.3rem, 2vw, 1.75rem); font-weight: 750; line-height: 1.2; }
    .mel-review-shell .mel-page-header p { max-width: 850px; margin: .5rem 0 0; color: rgba(255,255,255,.79); font-size: .82rem; line-height: 1.55; }
    .mel-review-shell .mel-header-actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; gap: .55rem; }
    .mel-review-shell .mel-header-button {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        padding: .55rem .8rem;
        border: 1px solid rgba(255,255,255,.3);
        border-radius: 9px;
        background: rgba(255,255,255,.1);
        color: #fff;
        font-size: .73rem;
        font-weight: 700;
        text-decoration: none;
    }
    .mel-review-shell .mel-header-button:hover { background: #fff; color: var(--mel-primary-dark); }
    .mel-review-shell .mel-alert { margin-top: 1rem; border-radius: 12px; }
    .mel-review-shell .mel-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; margin: 1rem 0; }
    .mel-review-shell .mel-metric {
        position: relative;
        min-height: 104px;
        padding: 1rem 1.05rem;
        overflow: hidden;
        border: 1px solid var(--mel-line);
        border-radius: 14px;
        background: var(--mel-surface);
        box-shadow: 0 5px 18px rgba(27, 59, 68, .045);
    }
    .mel-review-shell .mel-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric-color, var(--mel-primary)); content: ""; }
    .mel-review-shell .mel-metric-label { display: block; color: var(--mel-muted); font-size: .67rem; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; }
    .mel-review-shell .mel-metric-value { display: block; margin-top: .35rem; color: var(--mel-ink); font-size: 1.55rem; font-weight: 800; line-height: 1; }
    .mel-review-shell .mel-metric-help { display: block; margin-top: .42rem; color: var(--mel-muted); font-size: .68rem; }
    .mel-review-shell .mel-panel { border: 1px solid var(--mel-line); border-radius: 16px; background: var(--mel-surface); box-shadow: 0 7px 22px rgba(25, 57, 67, .045); }
    .mel-review-shell .mel-panel + .mel-panel { margin-top: 1rem; }
    .mel-review-shell .mel-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 58px; padding: .85rem 1.05rem; border-bottom: 1px solid var(--mel-line); }
    .mel-review-shell .mel-panel-header h2, .mel-review-shell .mel-panel-header h3 { margin: 0; color: var(--mel-ink); font-size: .9rem; font-weight: 760; }
    .mel-review-shell .mel-panel-header p { margin: .2rem 0 0; color: var(--mel-muted); font-size: .69rem; }
    .mel-review-shell .mel-panel-body { padding: 1.05rem; }
    .mel-review-shell .mel-stage-scroller { margin-bottom: 1rem; overflow-x: auto; scrollbar-color: #9abac5 transparent; scrollbar-width: thin; }
    .mel-review-shell .mel-stages { display: flex; gap: .5rem; min-width: max-content; padding: .15rem .05rem .45rem; }
    .mel-review-shell .mel-stage {
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: center;
        gap: .55rem;
        min-width: 132px;
        padding: .67rem .75rem;
        border: 1px solid var(--mel-line);
        border-radius: 11px;
        background: #fff;
        color: var(--mel-muted);
        text-decoration: none;
    }
    .mel-review-shell .mel-stage:hover, .mel-review-shell .mel-stage.active { border-color: var(--mel-primary); background: var(--mel-primary-soft); color: var(--mel-primary-dark); }
    .mel-review-shell .mel-stage-count { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 8px; background: var(--mel-canvas); color: var(--mel-ink); font-size: .78rem; font-weight: 800; }
    .mel-review-shell .mel-stage.active .mel-stage-count { background: var(--mel-primary); color: #fff; }
    .mel-review-shell .mel-stage-name { font-size: .69rem; font-weight: 750; line-height: 1.2; }
    .mel-review-shell .mel-filter-summary { cursor: pointer; list-style: none; }
    .mel-review-shell .mel-filter-summary::-webkit-details-marker { display: none; }
    .mel-review-shell .mel-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
    .mel-review-shell .mel-filter-wide { grid-column: span 2; }
    .mel-review-shell .mel-field label { display: block; margin: 0 0 .32rem; color: #465e67; font-size: .66rem; font-weight: 750; }
    .mel-review-shell .form-control, .mel-review-shell .form-select {
        min-height: 40px;
        border-color: #ccdce1;
        border-radius: 9px;
        color: var(--mel-ink);
        font-size: .76rem;
        box-shadow: none;
    }
    .mel-review-shell .form-control:focus, .mel-review-shell .form-select:focus { border-color: var(--mel-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-review-shell .mel-filter-actions { display: flex; justify-content: flex-end; gap: .55rem; padding-top: .95rem; border-top: 1px solid var(--mel-line); grid-column: 1 / -1; }
    .mel-review-shell .mel-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 39px; padding: .55rem .85rem; border: 1px solid transparent; border-radius: 9px; font-size: .72rem; font-weight: 750; text-decoration: none; }
    .mel-review-shell .mel-btn-primary { border-color: var(--mel-primary); background: var(--mel-primary); color: #fff; }
    .mel-review-shell .mel-btn-primary:hover { background: var(--mel-primary-dark); color: #fff; }
    .mel-review-shell .mel-btn-secondary { border-color: #cddce1; background: #fff; color: #405860; }
    .mel-review-shell .mel-btn-secondary:hover { border-color: var(--mel-primary); color: var(--mel-primary); }
    .mel-review-shell .mel-btn-danger { border-color: #dfb5b4; background: #fff; color: var(--mel-danger); }
    .mel-review-shell .mel-btn-danger:hover { background: #fff2f1; color: #8e2e2c; }
    .mel-review-shell .mel-badge { display: inline-flex; align-items: center; gap: .28rem; width: fit-content; padding: .27rem .48rem; border-radius: 999px; background: var(--mel-primary-soft); color: var(--mel-primary); font-size: .63rem; font-weight: 800; line-height: 1.2; }
    .mel-review-shell .mel-badge.success, .mel-review-shell .mel-status-approved { background: #e9f6f1; color: var(--mel-success); }
    .mel-review-shell .mel-badge.warning, .mel-review-shell .mel-status-returned { background: #fff4df; color: var(--mel-warning); }
    .mel-review-shell .mel-badge.danger, .mel-review-shell .mel-status-rejected { background: #fff0ef; color: var(--mel-danger); }
    .mel-review-shell .mel-status-draft { background: #edf1f2; color: #566a72; }
    .mel-review-shell .mel-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .82rem 1rem; border-bottom: 1px solid var(--mel-line); color: var(--mel-muted); font-size: .69rem; }
    .mel-review-shell .mel-table-toolbar strong { color: var(--mel-ink); }
    .mel-review-shell .mel-table-scroll { max-height: 66vh; overflow: auto; scrollbar-color: #91b2bd #eef3f4; scrollbar-width: thin; }
    .mel-review-shell .mel-table-scroll:focus { outline: 3px solid rgba(7,92,122,.12); outline-offset: -3px; }
    .mel-review-shell .mel-data-table { width: 100%; min-width: 1460px; margin: 0; border-collapse: separate; border-spacing: 0; font-size: .72rem; }
    .mel-review-shell .mel-data-table th { position: sticky; top: 0; z-index: 3; padding: .72rem .75rem; border-bottom: 1px solid var(--mel-line); background: #f3f7f8; color: #51676f; font-size: .62rem; font-weight: 800; letter-spacing: .045em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-review-shell .mel-data-table td { padding: .8rem .75rem; border-bottom: 1px solid #e8eff1; color: #334c55; vertical-align: middle; }
    .mel-review-shell .mel-data-table tbody tr:hover td { background: #f8fbfc; }
    .mel-review-shell .mel-data-table th:last-child, .mel-review-shell .mel-data-table td:last-child { position: sticky; right: 0; z-index: 2; border-left: 1px solid var(--mel-line); background: #fff; box-shadow: -8px 0 13px rgba(23,49,58,.035); }
    .mel-review-shell .mel-data-table th:last-child { z-index: 4; background: #f3f7f8; }
    .mel-review-shell .mel-data-table tbody tr:hover td:last-child { background: #f8fbfc; }
    .mel-review-shell .mel-cell-title { display: block; max-width: 290px; overflow: hidden; color: var(--mel-ink); font-size: .75rem; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
    .mel-review-shell .mel-cell-meta { display: block; margin-top: .22rem; color: var(--mel-muted); font-size: .64rem; line-height: 1.35; }
    .mel-review-shell .mel-counts { display: flex; flex-wrap: wrap; gap: .3rem; }
    .mel-review-shell .mel-count { padding: .24rem .38rem; border: 1px solid var(--mel-line); border-radius: 6px; background: #fafcfc; color: #526a72; font-size: .61rem; white-space: nowrap; }
    .mel-review-shell .mel-empty { padding: 3.4rem 1rem !important; text-align: center; }
    .mel-review-shell .mel-empty strong { display: block; color: var(--mel-ink); font-size: .88rem; }
    .mel-review-shell .mel-empty span { display: block; margin: .35rem auto 0; max-width: 500px; color: var(--mel-muted); font-size: .72rem; }
    .mel-review-shell .mel-pagination { padding: .85rem 1rem; border-top: 1px solid var(--mel-line); }
    .mel-review-shell .mel-mobile-list { display: none; padding: .75rem; }
    .mel-review-shell .mel-mobile-card { padding: .9rem; border: 1px solid var(--mel-line); border-radius: 12px; }
    .mel-review-shell .mel-mobile-card + .mel-mobile-card { margin-top: .7rem; }
    .mel-review-shell .mel-mobile-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .mel-review-shell .mel-mobile-facts { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem; margin: .8rem 0; }
    .mel-review-shell .mel-mobile-facts small, .mel-review-shell .mel-fact small { display: block; color: var(--mel-muted); font-size: .6rem; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
    .mel-review-shell .mel-mobile-facts strong { display: block; margin-top: .15rem; color: var(--mel-ink); font-size: .7rem; }
    .mel-review-shell .mel-review-grid { display: grid; grid-template-columns: minmax(0, 2.05fr) minmax(310px, .95fr); gap: 1rem; margin-top: 1rem; }
    .mel-review-shell .mel-sticky-column { align-self: start; position: sticky; top: 1rem; }
    .mel-review-shell .mel-fact-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .7rem; }
    .mel-review-shell .mel-fact { min-height: 78px; padding: .75rem; border: 1px solid var(--mel-line); border-radius: 10px; background: #fbfcfc; }
    .mel-review-shell .mel-fact strong { display: block; margin-top: .3rem; color: var(--mel-ink); font-size: .76rem; line-height: 1.35; }
    .mel-review-shell .mel-lifecycle { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin-top: 1rem; padding: .9rem 1rem; border: 1px solid var(--mel-line); border-radius: 14px; background: #fff; }
    .mel-review-shell .mel-life-step { position: relative; padding: 0 .55rem 0 2rem; color: var(--mel-muted); font-size: .66rem; font-weight: 700; }
    .mel-review-shell .mel-life-step::before { position: absolute; left: .25rem; top: -.1rem; display: grid; width: 22px; height: 22px; place-items: center; border: 2px solid #c8d8dc; border-radius: 50%; background: #fff; content: ""; z-index: 2; }
    .mel-review-shell .mel-life-step:not(:last-child)::after { position: absolute; left: 22px; right: -4px; top: 10px; height: 2px; background: #dbe6e9; content: ""; z-index: 1; }
    .mel-review-shell .mel-life-step.done::before, .mel-review-shell .mel-life-step.current::before { border-color: var(--mel-primary); background: var(--mel-primary); box-shadow: inset 0 0 0 5px #fff; }
    .mel-review-shell .mel-life-step.current { color: var(--mel-primary-dark); }
    .mel-review-shell .mel-life-step.done:not(:last-child)::after { background: var(--mel-primary); }
    .mel-review-shell .mel-result-card + .mel-result-card { margin-top: 1rem; }
    .mel-review-shell .mel-irs { margin-top: .8rem; border: 1px solid #d8e8ec; border-radius: 10px; background: #f5fafb; }
    .mel-review-shell .mel-irs summary { padding: .72rem .8rem; color: var(--mel-primary-dark); cursor: pointer; font-size: .7rem; font-weight: 750; }
    .mel-review-shell .mel-irs-body { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; padding: 0 .8rem .8rem; }
    .mel-review-shell .mel-irs-item { color: #405a63; font-size: .7rem; line-height: 1.5; }
    .mel-review-shell .mel-irs-item strong { display: block; color: var(--mel-ink); font-size: .64rem; text-transform: uppercase; }
    .mel-review-shell .mel-answer { padding: .78rem 0; border-bottom: 1px solid #e8eff1; }
    .mel-review-shell .mel-answer:last-child { border-bottom: 0; }
    .mel-review-shell .mel-answer-label { display: block; color: #50666e; font-size: .65rem; font-weight: 750; }
    .mel-review-shell .mel-answer-value { margin-top: .3rem; color: var(--mel-ink); font-size: .74rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
    .mel-review-shell .mel-evidence { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .7rem 0; border-bottom: 1px solid #e8eff1; }
    .mel-review-shell .mel-evidence:last-child { border-bottom: 0; }
    .mel-review-shell .mel-finding { padding: .72rem; border: 1px solid #eed9ab; border-left: 4px solid #d9951a; border-radius: 9px; background: #fffaf0; }
    .mel-review-shell .mel-finding + .mel-finding { margin-top: .65rem; }
    .mel-review-shell .mel-finding.error { border-color: #edc4c2; border-left-color: var(--mel-danger); background: #fff5f4; }
    .mel-review-shell .mel-finding-title { color: var(--mel-ink); font-size: .67rem; font-weight: 800; }
    .mel-review-shell .mel-finding-message { margin: .28rem 0 .55rem; color: #536a72; font-size: .68rem; line-height: 1.45; }
    .mel-review-shell .mel-decision-copy { margin: 0 0 .8rem; color: var(--mel-muted); font-size: .69rem; line-height: 1.5; }
    .mel-review-shell .mel-action-grid { display: grid; gap: .5rem; margin-top: .65rem; }
    .mel-review-shell .mel-action-grid button { width: 100%; }
    .mel-review-shell .mel-timeline { position: relative; padding-left: 1rem; border-left: 2px solid #d8e5e8; }
    .mel-review-shell .mel-event { position: relative; padding: 0 0 1rem .45rem; }
    .mel-review-shell .mel-event:last-child { padding-bottom: 0; }
    .mel-review-shell .mel-event::before { position: absolute; left: -1.39rem; top: .15rem; width: 10px; height: 10px; border: 2px solid #fff; border-radius: 50%; background: var(--mel-primary); box-shadow: 0 0 0 1px var(--mel-primary); content: ""; }
    .mel-review-shell .mel-event-title { color: var(--mel-ink); font-size: .68rem; font-weight: 800; }
    .mel-review-shell .mel-event-meta { margin-top: .12rem; color: var(--mel-muted); font-size: .61rem; }
    .mel-review-shell .mel-event-comment { margin: .3rem 0 0; color: #435c65; font-size: .68rem; line-height: 1.45; }
    @media (max-width: 1120px) {
        .mel-review-shell .mel-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .mel-review-shell .mel-review-grid { grid-template-columns: minmax(0, 1fr); }
        .mel-review-shell .mel-sticky-column { position: static; }
    }
    @media (max-width: 820px) {
        .mel-review-shell .mel-page-header { flex-direction: column; }
        .mel-review-shell .mel-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-review-shell .mel-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-review-shell .mel-filter-wide { grid-column: span 2; }
        .mel-review-shell .mel-fact-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-review-shell .mel-desktop-table { display: none; }
        .mel-review-shell .mel-mobile-list { display: block; }
        .mel-review-shell .mel-irs-body { grid-template-columns: 1fr; }
    }
    @media (max-width: 520px) {
        .mel-review-shell .mel-page-header { padding: 1.1rem; border-radius: 14px; }
        .mel-review-shell .mel-header-actions, .mel-review-shell .mel-header-button { width: 100%; }
        .mel-review-shell .mel-metrics, .mel-review-shell .mel-filter-grid, .mel-review-shell .mel-fact-grid { grid-template-columns: 1fr; }
        .mel-review-shell .mel-filter-wide { grid-column: span 1; }
        .mel-review-shell .mel-filter-actions { flex-direction: column-reverse; }
        .mel-review-shell .mel-filter-actions .mel-btn { width: 100%; }
        .mel-review-shell .mel-lifecycle { grid-template-columns: 1fr; gap: .7rem; }
        .mel-review-shell .mel-life-step:not(:last-child)::after { display: none; }
    }
</style>

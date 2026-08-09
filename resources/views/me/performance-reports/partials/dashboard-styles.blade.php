<style>
    .mel-reporting {
        --rp-primary: #075c7a;
        --rp-primary-dark: #05465d;
        --rp-primary-soft: #eaf4f7;
        --rp-ink: #17343e;
        --rp-muted: #657980;
        --rp-line: #dce7ea;
        --rp-canvas: #f5f8f9;
        --rp-success: #187459;
        --rp-warning: #a56a17;
        --rp-danger: #ae3f3d;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--rp-ink);
        font-size: .875rem;
    }
    .mel-reporting *, .mel-reporting *::before, .mel-reporting *::after { box-sizing: border-box; }
    .mel-reporting .rp-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.25rem; padding: 1.45rem 1.5rem; border: 1px solid rgba(7,92,122,.18); border-radius: 18px; background: linear-gradient(125deg,var(--rp-primary-dark),var(--rp-primary)); color: #fff; box-shadow: 0 15px 36px rgba(6,73,96,.14); }
    .mel-reporting .rp-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-reporting .rp-header h1 { margin: 0; color: #fff; font-size: clamp(1.35rem,2vw,1.78rem); font-weight: 800; line-height: 1.2; }
    .mel-reporting .rp-header p { max-width: 820px; margin: .48rem 0 0; color: rgba(255,255,255,.84); font-size: .84rem; line-height: 1.55; }
    .mel-reporting .rp-header-side { display: flex; flex: 0 0 auto; align-items: flex-end; flex-direction: column; gap: .65rem; }
    .mel-reporting .rp-generated { padding: .55rem .72rem; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; background: rgba(255,255,255,.1); color: rgba(255,255,255,.86); font-size: .7rem; font-weight: 730; }
    .mel-reporting .rp-actions { display: flex; flex-wrap: wrap; gap: .45rem; }
    .mel-reporting .rp-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .38rem; padding: .5rem .76rem; border: 1px solid transparent; border-radius: 9px; background: #fff; color: #435d66; font-size: .74rem; font-weight: 780; line-height: 1.2; text-decoration: none; cursor: pointer; }
    .mel-reporting .rp-btn:hover { color: var(--rp-primary); }
    .mel-reporting .rp-btn-primary { border-color: var(--rp-primary); background: var(--rp-primary); color: #fff; }
    .mel-reporting .rp-btn-primary:hover { background: var(--rp-primary-dark); color: #fff; }
    .mel-reporting .rp-btn-secondary { border-color: #ccdade; color: #425b64; }
    .mel-reporting .rp-btn-secondary:hover { border-color: var(--rp-primary); color: var(--rp-primary); }
    .mel-reporting .rp-btn-header { border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; }
    .mel-reporting .rp-btn-header:hover { background: #fff; color: var(--rp-primary); }
    .mel-reporting .rp-btn-small { min-height: 34px; padding: .4rem .62rem; font-size: .7rem; }
    .mel-reporting .rp-scope-note { display: flex; align-items: flex-start; gap: .7rem; margin-top: .9rem; padding: .73rem .9rem; border: 1px solid #c7dde3; border-radius: 12px; background: #f0f6f8; color: #3c6470; }
    .mel-reporting .rp-scope-mark { display: grid; width: 30px; height: 30px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #dcecf0; color: var(--rp-primary); font-size: .61rem; font-weight: 900; }
    .mel-reporting .rp-scope-note strong { display: block; color: #214954; font-size: .7rem; }
    .mel-reporting .rp-scope-note p { margin: .14rem 0 0; color: #577781; font-size: .7rem; line-height: 1.45; }
    .mel-reporting .rp-panel, .mel-reporting .rp-metric { border: 1px solid var(--rp-line); border-radius: 15px; background: #fff; box-shadow: 0 6px 20px rgba(24,55,65,.045); }
    .mel-reporting .rp-filter { margin-top: 1rem; overflow: hidden; }
    .mel-reporting .rp-panel-head { display: flex; min-height: 60px; align-items: center; justify-content: space-between; gap: 1rem; padding: .86rem 1rem; border-bottom: 1px solid var(--rp-line); }
    .mel-reporting .rp-panel-head h2 { margin: 0; color: var(--rp-ink); font-size: .9rem; font-weight: 790; }
    .mel-reporting .rp-panel-head p { margin: .2rem 0 0; color: var(--rp-muted); font-size: .72rem; line-height: 1.4; }
    .mel-reporting .rp-panel-body { padding: 1rem; }
    .mel-reporting details.rp-panel > summary { cursor: pointer; list-style: none; }
    .mel-reporting details.rp-panel > summary::-webkit-details-marker { display: none; }
    .mel-reporting .rp-summary-right { display: flex; align-items: center; gap: .45rem; }
    .mel-reporting .rp-chevron { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--rp-line); border-radius: 8px; color: var(--rp-muted); transition: transform .2s ease; }
    .mel-reporting details[open] .rp-chevron { transform: rotate(180deg); }
    .mel-reporting .rp-badge { display: inline-flex; width: fit-content; align-items: center; gap: .28rem; padding: .27rem .5rem; border-radius: 999px; background: var(--rp-primary-soft); color: var(--rp-primary); font-size: .61rem; font-weight: 800; line-height: 1.15; }
    .mel-reporting .rp-badge.warning { background: #fff4df; color: var(--rp-warning); }
    .mel-reporting .rp-badge.danger { background: #fff0ef; color: var(--rp-danger); }
    .mel-reporting .rp-filter-grid { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: .78rem; }
    .mel-reporting .rp-field-wide { grid-column: span 2; }
    .mel-reporting .rp-field label { display: block; margin-bottom: .31rem; color: #48616a; font-size: .7rem; font-weight: 760; }
    .mel-reporting .rp-field small { display: block; margin-top: .26rem; color: var(--rp-muted); font-size: .66rem; line-height: 1.35; }
    .mel-reporting .form-control, .mel-reporting .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--rp-ink); font-size: .78rem; box-shadow: none; }
    .mel-reporting .form-control:focus, .mel-reporting .form-select:focus { border-color: var(--rp-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-reporting .rp-advanced { grid-column: 1/-1; border: 1px solid #e1eaed; border-radius: 11px; background: #fafcfc; }
    .mel-reporting .rp-advanced summary { padding: .7rem .8rem; color: #3e5d66; font-size: .66rem; font-weight: 780; cursor: pointer; }
    .mel-reporting .rp-advanced .rp-filter-grid { padding: 0 .8rem .8rem; }
    .mel-reporting .rp-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: .8rem; padding-top: .85rem; border-top: 1px solid var(--rp-line); }
    .mel-reporting .rp-filter-tip { max-width: 700px; margin: 0; color: var(--rp-muted); font-size: .61rem; line-height: 1.45; }
    .mel-reporting .rp-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }
    .mel-reporting .rp-metric { position: relative; min-height: 106px; overflow: hidden; padding: .92rem 1rem; color: inherit; text-decoration: none; }
    .mel-reporting .rp-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric,var(--rp-primary)); content: ""; }
    .mel-reporting a.rp-metric:hover { border-color: #b8d2d9; color: inherit; transform: translateY(-1px); }
    .mel-reporting .rp-metric-label { display: block; color: var(--rp-muted); font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .mel-reporting .rp-metric strong { display: block; margin-top: .38rem; color: var(--rp-ink); font-size: 1.45rem; font-weight: 830; line-height: 1; }
    .mel-reporting .rp-metric small { display: block; margin-top: .43rem; color: var(--rp-muted); font-size: .7rem; line-height: 1.35; }
    .mel-reporting .rp-grid { display: grid; grid-template-columns: minmax(0,1.25fr) minmax(360px,.75fr); gap: 1rem; margin-top: 1rem; }
    .mel-reporting .rp-chart { min-height: 305px; padding: .4rem .75rem .75rem; }
    .mel-reporting .rp-chart-tall { min-height: 350px; }
    .mel-reporting .rp-mini-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .65rem; padding: .75rem; }
    .mel-reporting .rp-mini-card { min-width: 0; border: 1px solid #e5edef; border-radius: 12px; background: #fbfcfc; }
    .mel-reporting .rp-mini-card h3 { margin: .75rem .8rem 0; color: var(--rp-ink); font-size: .7rem; font-weight: 780; }
    .mel-reporting .rp-mini-card p { margin: .15rem .8rem 0; color: var(--rp-muted); font-size: .58rem; }
    .mel-reporting .rp-mini-chart { min-height: 245px; }
    .mel-reporting .rp-insight-grid { display: grid; grid-template-columns: minmax(0,1.25fr) minmax(340px,.75fr); gap: 1rem; margin-top: 1rem; }
    .mel-reporting .rp-attention-list { display: grid; gap: .55rem; }
    .mel-reporting .rp-attention { display: grid; grid-template-columns: 32px minmax(0,1fr) auto; gap: .7rem; align-items: start; padding: .72rem .8rem; border: 1px solid #e5edef; border-radius: 11px; }
    .mel-reporting .rp-attention-mark { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 9px; background: var(--mark-soft,#eef4f6); color: var(--mark,#64748b); font-size: .59rem; font-weight: 900; }
    .mel-reporting .rp-attention strong { display: block; color: var(--rp-ink); font-size: .69rem; }
    .mel-reporting .rp-attention p { margin: .22rem 0 0; color: var(--rp-muted); font-size: .61rem; line-height: 1.4; }
    .mel-reporting .rp-attention-meta { margin-top: .3rem; color: #70838a; font-size: .57rem; }
    .mel-reporting .rp-signal-list { display: grid; gap: .65rem; }
    .mel-reporting .rp-signal { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding-bottom: .62rem; border-bottom: 1px solid #ebf0f2; }
    .mel-reporting .rp-signal:last-child { padding-bottom: 0; border-bottom: 0; }
    .mel-reporting .rp-signal span { color: var(--rp-muted); font-size: .64rem; }
    .mel-reporting .rp-signal strong { color: var(--rp-ink); font-size: .71rem; text-align: right; }
    .mel-reporting .rp-empty { padding: 2.8rem 1rem; text-align: center; }
    .mel-reporting .rp-empty-mark { display: grid; width: 50px; height: 50px; margin: 0 auto .75rem; place-items: center; border-radius: 14px; background: var(--rp-primary-soft); color: var(--rp-primary); font-size: .66rem; font-weight: 900; }
    .mel-reporting .rp-empty strong { display: block; font-size: .77rem; }
    .mel-reporting .rp-empty p { max-width: 500px; margin: .3rem auto 0; color: var(--rp-muted); font-size: .64rem; line-height: 1.5; }
    .mel-reporting .rp-records { margin-top: 1rem; overflow: hidden; }
    .mel-reporting .rp-record-summary { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .7rem 1rem; border-bottom: 1px solid var(--rp-line); background: #fafcfc; color: var(--rp-muted); font-size: .63rem; }
    .mel-reporting .rp-record-summary strong { color: var(--rp-ink); }
    .mel-reporting .rp-table-wrap { max-height: 720px; overflow: auto; scrollbar-color: #b9cbd0 #edf2f3; scrollbar-width: thin; }
    .mel-reporting .rp-table { width: 100%; min-width: 1380px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .mel-reporting .rp-table th { position: sticky; z-index: 2; top: 0; padding: .68rem .72rem; border-bottom: 1px solid var(--rp-line); background: #f3f7f8; color: #596e75; font-size: .66rem; font-weight: 820; letter-spacing: .045em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-reporting .rp-table td { padding: .74rem .72rem; border-bottom: 1px solid #e8eff1; background: #fff; color: #344f58; font-size: .72rem; line-height: 1.45; vertical-align: top; }
    .mel-reporting .rp-table tbody tr:hover td { background: #fafcfc; }
    .mel-reporting .rp-table th:first-child, .mel-reporting .rp-table td:first-child { position: sticky; z-index: 1; left: 0; min-width: 280px; box-shadow: 7px 0 12px -13px #17343e; }
    .mel-reporting .rp-table th:first-child { z-index: 3; background: #f3f7f8; }
    .mel-reporting .rp-record-title { display: block; max-width: 350px; color: var(--rp-ink); font-size: .75rem; font-weight: 790; line-height: 1.4; }
    .mel-reporting .rp-record-meta { display: block; margin-top: .2rem; color: var(--rp-muted); font-size: .66rem; }
    .mel-reporting .rp-status { display: inline-flex; align-items: center; padding: .27rem .5rem; border: 1px solid var(--pill); border-radius: 999px; background: var(--soft); color: var(--pill); font-size: .67rem; font-weight: 820; white-space: nowrap; }
    .mel-reporting .apexcharts-series path, .mel-reporting .apexcharts-bar-area { cursor: pointer; }
    .mel-reporting .rp-progress { width: 110px; height: 6px; margin-top: .32rem; overflow: hidden; border-radius: 999px; background: #e8eff1; }
    .mel-reporting .rp-progress span { display: block; height: 100%; border-radius: inherit; background: var(--bar,var(--rp-primary)); }
    .mel-reporting .rp-pagination { padding: .8rem 1rem; border-top: 1px solid var(--rp-line); }
    .mel-reporting .rp-scroll-tip { display: flex; justify-content: space-between; gap: .6rem; padding: .58rem 1rem; border-top: 1px solid var(--rp-line); background: #fafcfc; color: var(--rp-muted); font-size: .58rem; }
    @media(max-width:1250px) {
        .mel-reporting .rp-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-reporting .rp-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-reporting .rp-grid, .mel-reporting .rp-insight-grid { grid-template-columns: 1fr; }
    }
    @media(max-width:800px) {
        .mel-reporting .rp-header { flex-direction: column; }
        .mel-reporting .rp-header-side { width: 100%; align-items: stretch; }
        .mel-reporting .rp-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-reporting .rp-filter-actions { align-items: stretch; flex-direction: column; }
        .mel-reporting .rp-panel-head { align-items: flex-start; }
    }
    @media(max-width:560px) {
        .mel-reporting .rp-header { padding: 1.1rem; border-radius: 14px; }
        .mel-reporting .rp-metrics, .mel-reporting .rp-filter-grid, .mel-reporting .rp-mini-grid { grid-template-columns: 1fr; }
        .mel-reporting .rp-field-wide { grid-column: span 1; }
        .mel-reporting .rp-actions { display: grid; width: 100%; grid-template-columns: 1fr; }
        .mel-reporting .rp-attention { grid-template-columns: 31px minmax(0,1fr); }
        .mel-reporting .rp-attention .rp-btn { grid-column: 2; }
        .mel-reporting .rp-summary-right .rp-badge { display: none; }
    }
    @media print {
        .nxl-navigation, .nxl-header, .rp-header-side, .rp-filter, .rp-records .rp-actions, .rp-pagination { display: none !important; }
        .content { padding: 0 !important; }
        .mel-reporting { max-width: none; font-size: 10px; }
        .mel-reporting .rp-header { color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
        .mel-reporting .rp-panel, .mel-reporting .rp-metric { box-shadow: none; break-inside: avoid; }
        .mel-reporting .rp-table-wrap { max-height: none; overflow: visible; }
        .mel-reporting .rp-table { min-width: 100%; }
    }
</style>

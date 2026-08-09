<style>
    .mel-consolidated {
        --cr-primary: #075c7a;
        --cr-primary-dark: #05465d;
        --cr-primary-soft: #eaf4f7;
        --cr-ink: #17343e;
        --cr-muted: #657980;
        --cr-line: #dce7ea;
        --cr-success: #187459;
        --cr-warning: #a56a17;
        --cr-danger: #ae3f3d;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--cr-ink);
        font-size: .875rem;
    }
    .mel-consolidated *, .mel-consolidated *::before, .mel-consolidated *::after { box-sizing: border-box; }
    .mel-consolidated .cr-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.25rem; padding: 1.45rem 1.5rem; border: 1px solid rgba(7,92,122,.18); border-radius: 18px; background: linear-gradient(125deg,var(--cr-primary-dark),var(--cr-primary)); color: #fff; box-shadow: 0 15px 36px rgba(6,73,96,.14); }
    .mel-consolidated .cr-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.74); font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-consolidated .cr-header h1 { margin: 0; color: #fff; font-size: clamp(1.35rem,2vw,1.78rem); font-weight: 800; line-height: 1.2; }
    .mel-consolidated .cr-header p { max-width: 820px; margin: .48rem 0 0; color: rgba(255,255,255,.84); font-size: .84rem; line-height: 1.55; }
    .mel-consolidated .cr-header-side { display: flex; flex: 0 0 auto; align-items: flex-end; flex-direction: column; gap: .65rem; }
    .mel-consolidated .cr-generated { padding: .55rem .72rem; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; background: rgba(255,255,255,.1); color: rgba(255,255,255,.86); font-size: .7rem; font-weight: 730; }
    .mel-consolidated .cr-actions { display: flex; flex-wrap: wrap; gap: .45rem; }
    .mel-consolidated .cr-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .38rem; padding: .5rem .76rem; border: 1px solid transparent; border-radius: 9px; background: #fff; color: #435d66; font-size: .74rem; font-weight: 780; line-height: 1.2; text-decoration: none; cursor: pointer; }
    .mel-consolidated .cr-btn:hover { color: var(--cr-primary); }
    .mel-consolidated .cr-btn-primary { border-color: var(--cr-primary); background: var(--cr-primary); color: #fff; }
    .mel-consolidated .cr-btn-primary:hover { background: var(--cr-primary-dark); color: #fff; }
    .mel-consolidated .cr-btn-secondary { border-color: #ccdade; color: #425b64; }
    .mel-consolidated .cr-btn-secondary:hover { border-color: var(--cr-primary); color: var(--cr-primary); }
    .mel-consolidated .cr-btn-header { border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; }
    .mel-consolidated .cr-btn-header:hover { background: #fff; color: var(--cr-primary); }
    .mel-consolidated .cr-btn-small { min-height: 34px; padding: .4rem .62rem; font-size: .7rem; }
    .mel-consolidated .cr-note { display: flex; align-items: flex-start; gap: .7rem; margin-top: .9rem; padding: .78rem .9rem; border: 1px solid #c7dde3; border-radius: 12px; background: #f0f6f8; color: #3c6470; }
    .mel-consolidated .cr-note-mark { display: grid; width: 32px; height: 32px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #dcecf0; color: var(--cr-primary); font-size: .65rem; font-weight: 900; }
    .mel-consolidated .cr-note strong { display: block; color: #214954; font-size: .75rem; }
    .mel-consolidated .cr-note p { margin: .15rem 0 0; color: #577781; font-size: .7rem; line-height: 1.5; }
    .mel-consolidated .cr-alert { margin-top: .9rem; padding: .75rem .9rem; border: 1px solid #efc9c6; border-radius: 11px; background: #fff5f4; color: #8d3532; font-size: .75rem; }
    .mel-consolidated .cr-panel, .mel-consolidated .cr-metric { border: 1px solid var(--cr-line); border-radius: 15px; background: #fff; box-shadow: 0 6px 20px rgba(24,55,65,.045); }
    .mel-consolidated .cr-filter { margin-top: 1rem; overflow: hidden; }
    .mel-consolidated .cr-panel-head { display: flex; min-height: 60px; align-items: center; justify-content: space-between; gap: 1rem; padding: .88rem 1rem; border-bottom: 1px solid var(--cr-line); }
    .mel-consolidated .cr-panel-head h2 { margin: 0; color: var(--cr-ink); font-size: .94rem; font-weight: 790; }
    .mel-consolidated .cr-panel-head p { margin: .2rem 0 0; color: var(--cr-muted); font-size: .72rem; line-height: 1.4; }
    .mel-consolidated .cr-panel-body { padding: 1rem; }
    .mel-consolidated details.cr-panel > summary { cursor: pointer; list-style: none; }
    .mel-consolidated details.cr-panel > summary::-webkit-details-marker { display: none; }
    .mel-consolidated .cr-summary-right { display: flex; align-items: center; gap: .45rem; }
    .mel-consolidated .cr-chevron { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--cr-line); border-radius: 8px; color: var(--cr-muted); transition: transform .2s ease; }
    .mel-consolidated details[open] .cr-chevron { transform: rotate(180deg); }
    .mel-consolidated .cr-badge { display: inline-flex; width: fit-content; align-items: center; padding: .3rem .52rem; border-radius: 999px; background: var(--cr-primary-soft); color: var(--cr-primary); font-size: .67rem; font-weight: 800; line-height: 1.15; }
    .mel-consolidated .cr-badge.warning { background: #fff4df; color: var(--cr-warning); }
    .mel-consolidated .cr-badge.danger { background: #fff0ef; color: var(--cr-danger); }
    .mel-consolidated .cr-filter-grid { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: .78rem; }
    .mel-consolidated .cr-field label { display: block; margin-bottom: .31rem; color: #48616a; font-size: .7rem; font-weight: 760; }
    .mel-consolidated .form-control, .mel-consolidated .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--cr-ink); font-size: .78rem; box-shadow: none; }
    .mel-consolidated .form-control:focus, .mel-consolidated .form-select:focus { border-color: var(--cr-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-consolidated .cr-advanced { grid-column: 1/-1; border: 1px solid #e1eaed; border-radius: 11px; background: #fafcfc; }
    .mel-consolidated .cr-advanced summary { padding: .72rem .82rem; color: #3e5d66; font-size: .72rem; font-weight: 780; cursor: pointer; }
    .mel-consolidated .cr-advanced .cr-filter-grid { padding: 0 .82rem .82rem; }
    .mel-consolidated .cr-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: .8rem; padding-top: .85rem; border-top: 1px solid var(--cr-line); }
    .mel-consolidated .cr-filter-tip { max-width: 720px; margin: 0; color: var(--cr-muted); font-size: .68rem; line-height: 1.45; }
    .mel-consolidated .cr-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }
    .mel-consolidated .cr-metric { position: relative; min-height: 112px; overflow: hidden; padding: .95rem 1rem; }
    .mel-consolidated .cr-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric,var(--cr-primary)); content: ""; }
    .mel-consolidated .cr-metric-label { display: block; color: var(--cr-muted); font-size: .68rem; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
    .mel-consolidated .cr-metric strong { display: block; margin-top: .4rem; color: var(--cr-ink); font-size: 1.45rem; font-weight: 830; line-height: 1; }
    .mel-consolidated .cr-metric small { display: block; margin-top: .45rem; color: var(--cr-muted); font-size: .7rem; line-height: 1.35; }
    .mel-consolidated .cr-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; margin-top: 1rem; }
    .mel-consolidated .cr-chart { min-height: 310px; padding: .35rem .72rem .72rem; }
    .mel-consolidated .cr-mini-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .65rem; padding: .75rem; }
    .mel-consolidated .cr-mini-card { min-width: 0; border: 1px solid #e5edef; border-radius: 12px; background: #fbfcfc; }
    .mel-consolidated .cr-mini-card h3 { margin: .78rem .82rem 0; color: var(--cr-ink); font-size: .76rem; font-weight: 780; }
    .mel-consolidated .cr-mini-card p { margin: .17rem .82rem 0; color: var(--cr-muted); font-size: .67rem; }
    .mel-consolidated .cr-mini-chart { min-height: 245px; }
    .mel-consolidated .cr-quality-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .65rem; }
    .mel-consolidated .cr-quality { padding: .78rem; border: 1px solid #e5edef; border-radius: 11px; background: #fbfcfc; }
    .mel-consolidated .cr-quality span { display: block; color: var(--cr-muted); font-size: .68rem; }
    .mel-consolidated .cr-quality strong { display: block; margin-top: .3rem; color: var(--cr-ink); font-size: 1rem; }
    .mel-consolidated .cr-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .65rem; padding: .72rem 1rem; border-bottom: 1px solid var(--cr-line); background: #fafcfc; }
    .mel-consolidated .cr-toolbar-fields { display: flex; flex-wrap: wrap; gap: .5rem; }
    .mel-consolidated .cr-toolbar .form-control, .mel-consolidated .cr-toolbar .form-select { min-width: 210px; min-height: 36px; }
    .mel-consolidated .cr-toolbar-count { color: var(--cr-muted); font-size: .7rem; font-weight: 720; }
    .mel-consolidated .cr-table-wrap { max-height: 720px; overflow: auto; scrollbar-color: #b9cbd0 #edf2f3; scrollbar-width: thin; }
    .mel-consolidated .cr-table { width: 100%; min-width: 1280px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .mel-consolidated .cr-table.wide { min-width: 1680px; }
    .mel-consolidated .cr-table th { position: sticky; z-index: 2; top: 0; padding: .7rem .72rem; border-bottom: 1px solid var(--cr-line); background: #f3f7f8; color: #596e75; font-size: .66rem; font-weight: 820; letter-spacing: .04em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-consolidated .cr-table td { padding: .76rem .72rem; border-bottom: 1px solid #e8eff1; background: #fff; color: #344f58; font-size: .72rem; line-height: 1.48; vertical-align: top; }
    .mel-consolidated .cr-table tbody tr:hover td { background: #fafcfc; }
    .mel-consolidated .cr-table th:first-child, .mel-consolidated .cr-table td:first-child { position: sticky; z-index: 1; left: 0; min-width: 265px; box-shadow: 7px 0 12px -13px #17343e; }
    .mel-consolidated .cr-table th:first-child { z-index: 3; background: #f3f7f8; }
    .mel-consolidated .cr-title { display: block; max-width: 380px; color: var(--cr-ink); font-size: .76rem; font-weight: 790; line-height: 1.4; }
    .mel-consolidated .cr-meta { display: block; margin-top: .2rem; color: var(--cr-muted); font-size: .66rem; }
    .mel-consolidated .cr-status { display: inline-flex; align-items: center; margin: 0 .25rem .25rem 0; padding: .28rem .52rem; border: 1px solid var(--pill); border-radius: 999px; background: var(--soft); color: var(--pill); font-size: .67rem; font-weight: 820; white-space: nowrap; }
    .mel-consolidated .cr-result { color: var(--cr-primary); font-size: .88rem; font-weight: 830; }
    .mel-consolidated .cr-qualitative { display: grid; min-width: 280px; gap: .3rem; }
    .mel-consolidated .cr-qualitative div { padding: .38rem .45rem; border-left: 3px solid #6b63a8; border-radius: 0 7px 7px 0; background: #f7f5fb; }
    .mel-consolidated .cr-qualitative strong { display: block; color: #514b7f; font-size: .66rem; }
    .mel-consolidated .cr-chip { display: inline-flex; margin: .1rem .15rem .1rem 0; padding: .22rem .4rem; border-radius: 999px; background: #eef4f6; color: #46616a; font-size: .64rem; font-weight: 700; }
    .mel-consolidated .cr-warning { margin-top: .3rem; color: var(--cr-warning); font-size: .67rem; font-weight: 760; }
    .mel-consolidated .cr-empty { padding: 2.8rem 1rem; text-align: center; }
    .mel-consolidated .cr-empty-mark { display: grid; width: 50px; height: 50px; margin: 0 auto .75rem; place-items: center; border-radius: 14px; background: var(--cr-primary-soft); color: var(--cr-primary); font-size: .7rem; font-weight: 900; }
    .mel-consolidated .cr-empty strong { display: block; font-size: .82rem; }
    .mel-consolidated .cr-empty p { max-width: 520px; margin: .3rem auto 0; color: var(--cr-muted); font-size: .7rem; line-height: 1.5; }
    .mel-consolidated .cr-scroll-tip { display: flex; justify-content: space-between; gap: .6rem; padding: .62rem 1rem; border-top: 1px solid var(--cr-line); background: #fafcfc; color: var(--cr-muted); font-size: .66rem; }
    .mel-consolidated .apexcharts-series path, .mel-consolidated .apexcharts-bar-area { cursor: pointer; }
    @media(max-width:1250px) {
        .mel-consolidated .cr-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-consolidated .cr-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-consolidated .cr-quality-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:820px) {
        .mel-consolidated .cr-header { flex-direction: column; }
        .mel-consolidated .cr-header-side { width: 100%; align-items: stretch; }
        .mel-consolidated .cr-filter-grid, .mel-consolidated .cr-grid { grid-template-columns: 1fr; }
        .mel-consolidated .cr-filter-actions { align-items: stretch; flex-direction: column; }
        .mel-consolidated .cr-panel-head { align-items: flex-start; }
    }
    @media(max-width:560px) {
        .mel-consolidated .cr-header { padding: 1.1rem; border-radius: 14px; }
        .mel-consolidated .cr-metrics, .mel-consolidated .cr-mini-grid, .mel-consolidated .cr-quality-grid { grid-template-columns: 1fr; }
        .mel-consolidated .cr-actions { display: grid; width: 100%; grid-template-columns: 1fr; }
        .mel-consolidated .cr-toolbar-fields { width: 100%; }
        .mel-consolidated .cr-toolbar .form-control, .mel-consolidated .cr-toolbar .form-select { width: 100%; min-width: 0; }
        .mel-consolidated .cr-summary-right .cr-badge { display: none; }
    }
    @media print {
        .nxl-navigation, .nxl-header, .cr-header-side, .cr-filter, .cr-toolbar, .cr-scroll-tip { display: none !important; }
        .content { padding: 0 !important; }
        .mel-consolidated { max-width: none; font-size: 10px; }
        .mel-consolidated .cr-header { color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
        .mel-consolidated .cr-panel, .mel-consolidated .cr-metric { box-shadow: none; break-inside: avoid; }
        .mel-consolidated .cr-table-wrap { max-height: none; overflow: visible; }
        .mel-consolidated .cr-table { min-width: 100%; }
    }
</style>

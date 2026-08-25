<style>
    .mel-consolidation-engine {
        --ce-primary: #075c7a;
        --ce-primary-dark: #05465d;
        --ce-primary-soft: #eaf4f7;
        --ce-ink: #17343e;
        --ce-muted: #657980;
        --ce-line: #dce7ea;
        --ce-success: #187459;
        --ce-warning: #a56a17;
        --ce-danger: #ae3f3d;
        max-width: 1580px;
        margin: 0 auto;
        color: var(--ce-ink);
        font-size: .875rem;
    }
    .mel-consolidation-engine *, .mel-consolidation-engine *::before, .mel-consolidation-engine *::after { box-sizing: border-box; }
    .mel-consolidation-engine a:focus-visible,
    .mel-consolidation-engine button:focus-visible,
    .mel-consolidation-engine input:focus-visible,
    .mel-consolidation-engine select:focus-visible,
    .mel-consolidation-engine summary:focus-visible { outline: 3px solid rgba(22,118,184,.24); outline-offset: 2px; }

    .mel-consolidation-engine .ce-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.25rem; padding: 1.45rem 1.5rem; border: 1px solid rgba(7,92,122,.18); border-radius: 18px; background: linear-gradient(125deg,var(--ce-primary-dark),var(--ce-primary)); color: #fff; box-shadow: 0 15px 36px rgba(6,73,96,.14); }
    .mel-consolidation-engine .ce-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.74); font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-consolidation-engine .ce-header h1 { margin: 0; color: #fff; font-size: clamp(1.38rem,2vw,1.82rem); font-weight: 820; line-height: 1.2; }
    .mel-consolidation-engine .ce-header p { max-width: 850px; margin: .48rem 0 0; color: rgba(255,255,255,.84); font-size: .82rem; line-height: 1.55; }
    .mel-consolidation-engine .ce-header-side { display: flex; flex: 0 0 auto; align-items: flex-end; flex-direction: column; gap: .65rem; }
    .mel-consolidation-engine .ce-scope { max-width: 440px; padding: .56rem .72rem; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; background: rgba(255,255,255,.1); color: rgba(255,255,255,.88); text-align: right; }
    .mel-consolidation-engine .ce-scope span { display: block; color: rgba(255,255,255,.68); font-size: .62rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .mel-consolidation-engine .ce-scope strong { display: block; margin-top: .15rem; font-size: .7rem; line-height: 1.4; }
    .mel-consolidation-engine .ce-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .42rem; }
    .mel-consolidation-engine .ce-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .38rem; padding: .5rem .76rem; border: 1px solid transparent; border-radius: 9px; background: #fff; color: #435d66; font-size: .72rem; font-weight: 790; line-height: 1.2; text-decoration: none; cursor: pointer; transition: border-color .18s ease, background .18s ease, color .18s ease, transform .18s ease; }
    .mel-consolidation-engine .ce-btn:hover { color: var(--ce-primary); transform: translateY(-1px); }
    .mel-consolidation-engine .ce-btn-primary { border-color: var(--ce-primary); background: var(--ce-primary); color: #fff; }
    .mel-consolidation-engine .ce-btn-primary:hover { background: var(--ce-primary-dark); color: #fff; }
    .mel-consolidation-engine .ce-btn-secondary { border-color: #ccdade; background: #fff; color: #425b64; }
    .mel-consolidation-engine .ce-btn-secondary:hover { border-color: var(--ce-primary); color: var(--ce-primary); }
    .mel-consolidation-engine .ce-btn-header { border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; }
    .mel-consolidation-engine .ce-btn-header:hover { background: #fff; color: var(--ce-primary); }
    .mel-consolidation-engine .ce-btn-small { min-height: 33px; padding: .38rem .58rem; font-size: .67rem; }

    .mel-consolidation-engine .ce-guardrail { display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: .72rem; margin-top: .9rem; padding: .78rem .9rem; border: 1px solid #c7dde3; border-radius: 12px; background: #f0f6f8; color: #3c6470; }
    .mel-consolidation-engine .ce-guardrail-mark { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; background: #dcecf0; color: var(--ce-primary); font-size: .63rem; font-weight: 900; }
    .mel-consolidation-engine .ce-guardrail strong { display: block; color: #214954; font-size: .75rem; }
    .mel-consolidation-engine .ce-guardrail p { margin: .15rem 0 0; color: #577781; font-size: .68rem; line-height: 1.5; }
    .mel-consolidation-engine .ce-approved-pill { display: inline-flex; align-items: center; gap: .32rem; padding: .35rem .58rem; border-radius: 999px; background: #e6f5ed; color: var(--ce-success); font-size: .65rem; font-weight: 820; white-space: nowrap; }
    .mel-consolidation-engine .ce-approved-pill::before { width: 7px; height: 7px; border-radius: 50%; background: var(--ce-success); content: ""; }
    .mel-consolidation-engine .ce-alert { margin-top: .9rem; padding: .76rem .9rem; border: 1px solid #efc9c6; border-radius: 11px; background: #fff5f4; color: #8d3532; font-size: .73rem; }

    .mel-consolidation-engine .ce-level-tabs { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .7rem; margin: 1rem 0; padding: .42rem; border: 1px solid var(--ce-line); border-radius: 14px; background: #fff; box-shadow: 0 6px 20px rgba(24,55,65,.045); }
    .mel-consolidation-engine .ce-level-tab { display: flex; min-width: 0; align-items: center; gap: .7rem; padding: .76rem .82rem; border: 1px solid transparent; border-radius: 11px; color: #526b74; text-decoration: none; }
    .mel-consolidation-engine .ce-level-tab:hover { border-color: #cbdce0; background: #f7fafb; color: var(--ce-primary); }
    .mel-consolidation-engine .ce-level-tab.active { border-color: rgba(7,92,122,.34); background: linear-gradient(135deg,#edf7f8,#e7f2f5); color: var(--ce-primary); box-shadow: inset 0 0 0 1px rgba(255,255,255,.7); }
    .mel-consolidation-engine .ce-level-icon { display: grid; width: 38px; height: 38px; flex: 0 0 auto; place-items: center; border-radius: 10px; background: #edf3f5; color: #4b6871; font-size: 1rem; }
    .mel-consolidation-engine .ce-level-tab.active .ce-level-icon { background: var(--ce-primary); color: #fff; }
    .mel-consolidation-engine .ce-level-copy strong { display: block; font-size: .78rem; }
    .mel-consolidation-engine .ce-level-copy small { display: block; margin-top: .14rem; color: var(--ce-muted); font-size: .64rem; line-height: 1.35; }

    .mel-consolidation-engine .ce-panel, .mel-consolidation-engine .ce-metric { border: 1px solid var(--ce-line); border-radius: 15px; background: #fff; box-shadow: 0 6px 20px rgba(24,55,65,.045); }
    .mel-consolidation-engine .ce-filter { overflow: hidden; }
    .mel-consolidation-engine .ce-panel-head { display: flex; min-height: 60px; align-items: center; justify-content: space-between; gap: 1rem; padding: .88rem 1rem; border-bottom: 1px solid var(--ce-line); }
    .mel-consolidation-engine .ce-panel-head h2 { margin: 0; color: var(--ce-ink); font-size: .93rem; font-weight: 800; }
    .mel-consolidation-engine .ce-panel-head p { margin: .2rem 0 0; color: var(--ce-muted); font-size: .69rem; line-height: 1.42; }
    .mel-consolidation-engine .ce-panel-body { padding: 1rem; }
    .mel-consolidation-engine details.ce-panel > summary { cursor: pointer; list-style: none; }
    .mel-consolidation-engine details.ce-panel > summary::-webkit-details-marker { display: none; }
    .mel-consolidation-engine .ce-summary-right { display: flex; align-items: center; gap: .45rem; }
    .mel-consolidation-engine .ce-chevron { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--ce-line); border-radius: 8px; color: var(--ce-muted); transition: transform .2s ease; }
    .mel-consolidation-engine details[open] > summary .ce-chevron { transform: rotate(180deg); }
    .mel-consolidation-engine .ce-badge { display: inline-flex; width: fit-content; align-items: center; padding: .3rem .52rem; border-radius: 999px; background: var(--ce-primary-soft); color: var(--ce-primary); font-size: .65rem; font-weight: 810; line-height: 1.15; }
    .mel-consolidation-engine .ce-badge.warning { background: #fff4df; color: var(--ce-warning); }
    .mel-consolidation-engine .ce-badge.danger { background: #fff0ef; color: var(--ce-danger); }
    .mel-consolidation-engine .ce-filter-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .78rem; }
    .mel-consolidation-engine .ce-field-wide { grid-column: span 2; }
    .mel-consolidation-engine .ce-field label { display: block; margin-bottom: .31rem; color: #48616a; font-size: .68rem; font-weight: 770; }
    .mel-consolidation-engine .ce-field small { display: block; margin-top: .28rem; color: var(--ce-muted); font-size: .61rem; line-height: 1.35; }
    .mel-consolidation-engine .form-control, .mel-consolidation-engine .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--ce-ink); font-size: .75rem; box-shadow: none; }
    .mel-consolidation-engine .form-control:focus, .mel-consolidation-engine .form-select:focus { border-color: var(--ce-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-consolidation-engine .ce-advanced { grid-column: 1/-1; border: 1px solid #e1eaed; border-radius: 11px; background: #fafcfc; }
    .mel-consolidation-engine .ce-advanced summary { padding: .72rem .82rem; color: #3e5d66; font-size: .7rem; font-weight: 790; cursor: pointer; }
    .mel-consolidation-engine .ce-advanced .ce-filter-grid { padding: 0 .82rem .82rem; }
    .mel-consolidation-engine .ce-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: .8rem; padding-top: .86rem; border-top: 1px solid var(--ce-line); }
    .mel-consolidation-engine .ce-filter-tip { max-width: 780px; margin: 0; color: var(--ce-muted); font-size: .66rem; line-height: 1.47; }

    .mel-consolidation-engine .ce-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }
    .mel-consolidation-engine .ce-metric { position: relative; min-height: 116px; overflow: hidden; padding: .95rem 1rem; }
    .mel-consolidation-engine .ce-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric,var(--ce-primary)); content: ""; }
    .mel-consolidation-engine .ce-metric-label { display: block; color: var(--ce-muted); font-size: .65rem; font-weight: 810; letter-spacing: .045em; text-transform: uppercase; }
    .mel-consolidation-engine .ce-metric strong { display: block; margin-top: .4rem; color: var(--ce-ink); font-size: 1.42rem; font-weight: 840; line-height: 1; }
    .mel-consolidation-engine .ce-metric small { display: block; margin-top: .45rem; color: var(--ce-muted); font-size: .66rem; line-height: 1.36; }
    .mel-consolidation-engine .ce-grid { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(380px,.8fr); gap: 1rem; margin-top: 1rem; }
    .mel-consolidation-engine .ce-chart { min-height: 315px; padding: .35rem .72rem .72rem; }

    .mel-consolidation-engine .ce-method-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .72rem; }
    .mel-consolidation-engine .ce-method { display: grid; grid-template-columns: auto 1fr; gap: .65rem; padding: .78rem; border: 1px solid #e4edef; border-radius: 11px; background: #fbfcfc; }
    .mel-consolidation-engine .ce-method-mark { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; background: var(--ce-primary-soft); color: var(--ce-primary); font-size: .66rem; font-weight: 900; }
    .mel-consolidation-engine .ce-method strong { display: block; font-size: .72rem; }
    .mel-consolidation-engine .ce-method p { margin: .18rem 0 0; color: var(--ce-muted); font-size: .64rem; line-height: 1.45; }
    .mel-consolidation-engine .ce-quality-grid { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .62rem; }
    .mel-consolidation-engine .ce-quality { position: relative; min-height: 91px; padding: .72rem; border: 1px solid #e5edef; border-radius: 11px; background: #fbfcfc; }
    .mel-consolidation-engine .ce-quality.has-issue { border-color: #ead5b2; background: #fffbf3; }
    .mel-consolidation-engine .ce-quality.is-clear { border-color: #cae5d8; background: #f5fbf8; }
    .mel-consolidation-engine .ce-quality span { display: block; color: var(--ce-muted); font-size: .62rem; line-height: 1.35; }
    .mel-consolidation-engine .ce-quality strong { display: block; margin-top: .32rem; color: var(--ce-ink); font-size: 1rem; }
    .mel-consolidation-engine .ce-quality.has-issue strong { color: var(--ce-warning); }
    .mel-consolidation-engine .ce-quality.is-clear strong { color: var(--ce-success); }

    .mel-consolidation-engine .ce-register { margin-top: 1rem; overflow: hidden; }
    .mel-consolidation-engine .ce-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .65rem; padding: .72rem 1rem; border-bottom: 1px solid var(--ce-line); background: #fafcfc; }
    .mel-consolidation-engine .ce-toolbar-fields { display: flex; min-width: 0; flex-wrap: wrap; gap: .5rem; }
    .mel-consolidation-engine .ce-search { position: relative; min-width: min(340px,100%); }
    .mel-consolidation-engine .ce-search i { position: absolute; top: 50%; left: .72rem; color: #81939a; transform: translateY(-50%); pointer-events: none; }
    .mel-consolidation-engine .ce-search input { width: 100%; padding-left: 2rem; }
    .mel-consolidation-engine .ce-toolbar .form-control, .mel-consolidation-engine .ce-toolbar .form-select { min-width: 210px; min-height: 36px; }
    .mel-consolidation-engine .ce-toolbar-count { color: var(--ce-muted); font-size: .68rem; font-weight: 730; }
    .mel-consolidation-engine .ce-table-wrap { max-height: 760px; overflow: auto; scrollbar-color: #b9cbd0 #edf2f3; scrollbar-width: thin; }
    .mel-consolidation-engine .ce-table { width: 100%; min-width: 1560px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .mel-consolidation-engine .ce-table.project { min-width: 1420px; }
    .mel-consolidation-engine .ce-table th { position: sticky; z-index: 2; top: 0; padding: .68rem .7rem; border-bottom: 1px solid var(--ce-line); background: #f3f7f8; color: #596e75; font-size: .61rem; font-weight: 830; letter-spacing: .04em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-consolidation-engine .ce-table td { padding: .73rem .7rem; border-bottom: 1px solid #e8eff1; background: #fff; color: #344f58; font-size: .69rem; line-height: 1.46; vertical-align: top; }
    .mel-consolidation-engine .ce-table tbody tr.ce-main-row:hover > td { background: #fafcfc; }
    .mel-consolidation-engine .ce-table th:first-child, .mel-consolidation-engine .ce-table .ce-main-row > td:first-child { position: sticky; z-index: 1; left: 0; min-width: 280px; box-shadow: 7px 0 12px -13px #17343e; }
    .mel-consolidation-engine .ce-table th:first-child { z-index: 3; background: #f3f7f8; }
    .mel-consolidation-engine .ce-title { display: block; max-width: 390px; color: var(--ce-ink); font-size: .73rem; font-weight: 800; line-height: 1.4; }
    .mel-consolidation-engine .ce-code { display: inline-flex; margin-bottom: .25rem; padding: .22rem .4rem; border-radius: 6px; background: var(--ce-primary-soft); color: var(--ce-primary); font-size: .61rem; font-weight: 840; letter-spacing: .03em; }
    .mel-consolidation-engine .ce-meta { display: block; margin-top: .2rem; color: var(--ce-muted); font-size: .62rem; line-height: 1.4; }
    .mel-consolidation-engine .ce-value { color: var(--ce-primary); font-size: .79rem; font-weight: 840; }
    .mel-consolidation-engine .ce-status { display: inline-flex; align-items: center; padding: .28rem .5rem; border-radius: 999px; background: var(--status,#64748b); color: #fff; font-size: .62rem; font-weight: 820; white-space: nowrap; }
    .mel-consolidation-engine .ce-chip { display: inline-flex; margin: .12rem .14rem .08rem 0; padding: .21rem .38rem; border-radius: 999px; background: #eef4f6; color: #46616a; font-size: .59rem; font-weight: 720; }
    .mel-consolidation-engine .ce-progress { width: 118px; height: 6px; overflow: hidden; margin-top: .34rem; border-radius: 999px; background: #e7eef0; }
    .mel-consolidation-engine .ce-progress span { display: block; height: 100%; border-radius: inherit; background: var(--progress,var(--ce-primary)); }
    .mel-consolidation-engine .ce-detail-row > td { padding: 0 !important; background: #f8fbfb !important; }
    .mel-consolidation-engine .ce-detail-row[hidden] { display: none; }
    .mel-consolidation-engine .ce-detail-shell { padding: .85rem 1rem 1rem; border-bottom: 1px solid #d8e5e8; box-shadow: inset 0 8px 13px -15px #17343e; }
    .mel-consolidation-engine .ce-detail-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; margin-bottom: .68rem; }
    .mel-consolidation-engine .ce-detail-head strong { color: var(--ce-ink); font-size: .72rem; }
    .mel-consolidation-engine .ce-detail-head p { max-width: 900px; margin: .14rem 0 0; color: var(--ce-muted); font-size: .62rem; line-height: 1.45; }
    .mel-consolidation-engine .ce-detail-facts { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .55rem; margin-bottom: .65rem; }
    .mel-consolidation-engine .ce-detail-fact { padding: .58rem .64rem; border: 1px solid #e2ebed; border-radius: 9px; background: #fff; }
    .mel-consolidation-engine .ce-detail-fact small { display: block; color: var(--ce-muted); font-size: .58rem; font-weight: 760; text-transform: uppercase; }
    .mel-consolidation-engine .ce-detail-fact strong { display: block; margin-top: .2rem; color: var(--ce-ink); font-size: .67rem; }
    .mel-consolidation-engine .ce-source-wrap { overflow-x: auto; border: 1px solid #dfe9eb; border-radius: 10px; background: #fff; }
    .mel-consolidation-engine .ce-source-table { width: 100%; min-width: 980px; border-collapse: collapse; }
    .mel-consolidation-engine .ce-source-table th, .mel-consolidation-engine .ce-source-table td { position: static !important; min-width: 0 !important; padding: .54rem .6rem; border-bottom: 1px solid #e8eff1; box-shadow: none !important; background: #fff; font-size: .61rem; }
    .mel-consolidation-engine .ce-source-table th { background: #f3f7f8; color: #596e75; font-size: .56rem; text-transform: uppercase; }
    .mel-consolidation-engine .ce-source-empty { padding: .8rem; color: var(--ce-muted); font-size: .64rem; text-align: center; }
    .mel-consolidation-engine .ce-method-warning { margin-bottom: .66rem; padding: .62rem .7rem; border: 1px solid #ead5b2; border-radius: 9px; background: #fffbf3; color: #73531f; font-size: .63rem; line-height: 1.45; }
    .mel-consolidation-engine .ce-scroll-tip { display: flex; justify-content: space-between; gap: .6rem; padding: .62rem 1rem; border-top: 1px solid var(--ce-line); background: #fafcfc; color: var(--ce-muted); font-size: .62rem; }
    .mel-consolidation-engine .ce-empty { padding: 3rem 1rem; text-align: center; }
    .mel-consolidation-engine .ce-empty-mark { display: grid; width: 52px; height: 52px; margin: 0 auto .75rem; place-items: center; border-radius: 14px; background: var(--ce-primary-soft); color: var(--ce-primary); font-size: .67rem; font-weight: 900; }
    .mel-consolidation-engine .ce-empty strong { display: block; font-size: .79rem; }
    .mel-consolidation-engine .ce-empty p { max-width: 540px; margin: .3rem auto 0; color: var(--ce-muted); font-size: .66rem; line-height: 1.5; }
    .mel-consolidation-engine .ce-empty .ce-btn { margin-top: .75rem; }
    .mel-consolidation-engine .ce-table-filter-empty { display: none; }
    .mel-consolidation-engine .ce-table-filter-empty.is-visible { display: table-row; }
    .mel-consolidation-engine .apexcharts-series path, .mel-consolidation-engine .apexcharts-bar-area { cursor: pointer; }

    @media(max-width:1280px) {
        .mel-consolidation-engine .ce-metrics, .mel-consolidation-engine .ce-quality-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-consolidation-engine .ce-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
    }
    @media(max-width:980px) {
        .mel-consolidation-engine .ce-grid { grid-template-columns: 1fr; }
        .mel-consolidation-engine .ce-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-consolidation-engine .ce-detail-facts { grid-template-columns: repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:760px) {
        .mel-consolidation-engine .ce-header { flex-direction: column; }
        .mel-consolidation-engine .ce-header-side { width: 100%; align-items: stretch; }
        .mel-consolidation-engine .ce-scope { max-width: none; text-align: left; }
        .mel-consolidation-engine .ce-level-tabs { display: flex; overflow-x: auto; scroll-snap-type: x proximity; }
        .mel-consolidation-engine .ce-level-tab { min-width: min(360px,88vw); scroll-snap-align: start; }
        .mel-consolidation-engine .ce-filter-actions, .mel-consolidation-engine .ce-toolbar, .mel-consolidation-engine .ce-detail-head { align-items: stretch; flex-direction: column; }
        .mel-consolidation-engine .ce-field-wide { grid-column: auto; }
        .mel-consolidation-engine .ce-guardrail { grid-template-columns: auto minmax(0,1fr); }
        .mel-consolidation-engine .ce-approved-pill { grid-column: 1/-1; justify-self: start; }
        .mel-consolidation-engine .ce-method-grid { grid-template-columns: 1fr; }
    }
    @media(max-width:560px) {
        .mel-consolidation-engine .ce-header { padding: 1.1rem; border-radius: 14px; }
        .mel-consolidation-engine .ce-actions { display: grid; width: 100%; grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-consolidation-engine .ce-filter-grid, .mel-consolidation-engine .ce-metrics, .mel-consolidation-engine .ce-quality-grid, .mel-consolidation-engine .ce-detail-facts { grid-template-columns: 1fr; }
        .mel-consolidation-engine .ce-summary-right .ce-badge { display: none; }
        .mel-consolidation-engine .ce-toolbar-fields, .mel-consolidation-engine .ce-search { width: 100%; min-width: 0; }
        .mel-consolidation-engine .ce-toolbar .form-control, .mel-consolidation-engine .ce-toolbar .form-select { width: 100%; min-width: 0; }
        .mel-consolidation-engine .ce-scroll-tip { flex-direction: column; }
    }
    @media print {
        .nxl-navigation, .nxl-header, .ce-no-print, .mel-consolidation-engine .ce-header-side, .mel-consolidation-engine .ce-level-tabs, .mel-consolidation-engine .ce-filter, .mel-consolidation-engine .ce-toolbar, .mel-consolidation-engine .ce-scroll-tip, .mel-consolidation-engine .ce-btn, .mel-consolidation-engine .ce-detail-row[hidden] { display: none !important; }
        .content { padding: 0 !important; }
        .mel-consolidation-engine { max-width: none; font-size: 10px; }
        .mel-consolidation-engine .ce-header { color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
        .mel-consolidation-engine .ce-panel, .mel-consolidation-engine .ce-metric { box-shadow: none; break-inside: avoid; }
        .mel-consolidation-engine .ce-grid { grid-template-columns: 1fr 1fr; }
        .mel-consolidation-engine .ce-table-wrap { max-height: none; overflow: visible; }
        .mel-consolidation-engine .ce-table { min-width: 100%; }
        .mel-consolidation-engine .ce-table th:first-child, .mel-consolidation-engine .ce-table .ce-main-row > td:first-child { position: static; box-shadow: none; }
        .mel-consolidation-engine .ce-detail-row:not([hidden]) { display: table-row !important; }
    }
    @media(prefers-reduced-motion:reduce) {
        .mel-consolidation-engine *, .mel-consolidation-engine *::before, .mel-consolidation-engine *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
    }
</style>

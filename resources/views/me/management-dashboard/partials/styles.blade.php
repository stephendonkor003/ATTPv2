<style>
    .mel-management {
        --md-primary: #075c7a;
        --md-primary-dark: #05465d;
        --md-primary-soft: #eaf4f7;
        --md-ink: #17343e;
        --md-muted: #657980;
        --md-line: #dce7ea;
        --md-success: #187459;
        --md-warning: #a56a17;
        --md-danger: #ae3f3d;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--md-ink);
        font-size: .875rem;
    }
    .mel-management *, .mel-management *::before, .mel-management *::after { box-sizing: border-box; }
    .mel-management .md-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.25rem; padding: 1.45rem 1.5rem; border: 1px solid rgba(7,92,122,.18); border-radius: 18px; background: linear-gradient(125deg,var(--md-primary-dark),var(--md-primary)); color: #fff; box-shadow: 0 15px 36px rgba(6,73,96,.14); }
    .mel-management .md-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-management .md-header h1 { margin: 0; color: #fff; font-size: clamp(1.35rem,2vw,1.78rem); font-weight: 800; line-height: 1.2; }
    .mel-management .md-header p { max-width: 800px; margin: .48rem 0 0; color: rgba(255,255,255,.84); font-size: .84rem; line-height: 1.55; }
    .mel-management .md-header-side { display: flex; flex: 0 0 auto; align-items: flex-end; flex-direction: column; gap: .65rem; }
    .mel-management .md-generated { padding: .55rem .72rem; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; background: rgba(255,255,255,.1); color: rgba(255,255,255,.86); font-size: .7rem; font-weight: 730; }
    .mel-management .md-actions { display: flex; flex-wrap: wrap; gap: .45rem; }
    .mel-management .md-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .35rem; padding: .5rem .76rem; border: 1px solid transparent; border-radius: 9px; background: #fff; color: #435d66; font-size: .74rem; font-weight: 780; line-height: 1.2; text-decoration: none; cursor: pointer; }
    .mel-management .md-btn:hover { color: var(--md-primary); }
    .mel-management .md-btn-primary { border-color: var(--md-primary); background: var(--md-primary); color: #fff; }
    .mel-management .md-btn-primary:hover { background: var(--md-primary-dark); color: #fff; }
    .mel-management .md-btn-secondary { border-color: #ccdade; color: #425b64; }
    .mel-management .md-btn-secondary:hover { border-color: var(--md-primary); color: var(--md-primary); }
    .mel-management .md-btn-header { border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; }
    .mel-management .md-btn-header:hover { background: #fff; color: var(--md-primary); }
    .mel-management .md-btn-small { min-height: 32px; padding: .38rem .58rem; font-size: .68rem; white-space: nowrap; }
    .mel-management .md-guardrail { display: flex; align-items: flex-start; gap: .7rem; margin-top: .9rem; padding: .75rem .9rem; border: 1px solid #bfe0d1; border-radius: 12px; background: #eff8f4; color: #245f4a; }
    .mel-management .md-guardrail-mark { display: grid; width: 32px; height: 32px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #dcefe6; color: var(--md-success); font-size: .6rem; font-weight: 900; }
    .mel-management .md-guardrail strong { display: block; font-size: .72rem; }
    .mel-management .md-guardrail p { margin: .16rem 0 0; color: #477161; font-size: .69rem; line-height: 1.45; }
    .mel-management .md-panel, .mel-management .md-metric { border: 1px solid var(--md-line); border-radius: 15px; background: #fff; box-shadow: 0 6px 20px rgba(24,55,65,.045); }
    .mel-management .md-filter { margin-top: 1rem; overflow: hidden; }
    .mel-management .md-panel-head { display: flex; min-height: 60px; align-items: center; justify-content: space-between; gap: 1rem; padding: .86rem 1rem; border-bottom: 1px solid var(--md-line); }
    .mel-management .md-panel-head h2 { margin: 0; color: var(--md-ink); font-size: .9rem; font-weight: 790; }
    .mel-management .md-panel-head p { margin: .2rem 0 0; color: var(--md-muted); font-size: .72rem; line-height: 1.4; }
    .mel-management .md-panel-body { padding: 1rem; }
    .mel-management details.md-panel > summary { cursor: pointer; list-style: none; }
    .mel-management details.md-panel > summary::-webkit-details-marker { display: none; }
    .mel-management .md-summary-right { display: flex; align-items: center; gap: .45rem; }
    .mel-management .md-chevron { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--md-line); border-radius: 8px; color: var(--md-muted); transition: transform .2s ease; }
    .mel-management details[open] .md-chevron { transform: rotate(180deg); }
    .mel-management .md-badge, .mel-management .md-status { display: inline-flex; width: fit-content; align-items: center; padding: .27rem .5rem; border-radius: 999px; background: var(--md-primary-soft); color: var(--md-primary); font-size: .65rem; font-weight: 800; line-height: 1.15; white-space: nowrap; }
    .mel-management .md-badge.success, .mel-management .md-status.success { background: #eaf8f0; color: var(--md-success); }
    .mel-management .md-badge.danger, .mel-management .md-status.danger { background: #fff0ef; color: var(--md-danger); }
    .mel-management .md-status.info { background: #eaf5fc; color: #1676b8; }
    .mel-management .md-status.neutral { background: #f1f5f9; color: #64748b; }
    .mel-management .md-filter-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .8rem; }
    .mel-management .md-field label { display: block; margin-bottom: .31rem; color: #48616a; font-size: .7rem; font-weight: 760; }
    .mel-management .md-field small { display: block; margin-top: .27rem; color: var(--md-muted); font-size: .65rem; line-height: 1.35; }
    .mel-management .form-control, .mel-management .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--md-ink); font-size: .76rem; box-shadow: none; }
    .mel-management .form-control:focus, .mel-management .form-select:focus { border-color: var(--md-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-management .md-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: .8rem; padding-top: .85rem; border-top: 1px solid var(--md-line); }
    .mel-management .md-filter-tip { max-width: 760px; margin: 0; color: var(--md-muted); font-size: .65rem; line-height: 1.45; }
    .mel-management .md-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }
    .mel-management .md-metric { position: relative; min-height: 112px; overflow: hidden; padding: .94rem 1rem; color: inherit; text-decoration: none; }
    .mel-management .md-metric::after { position: absolute; right: 0; bottom: 0; width: 54px; height: 4px; border-radius: 4px 0 0; background: var(--metric,var(--md-primary)); content: ""; }
    .mel-management a.md-metric:hover { border-color: #b8d2d9; color: inherit; transform: translateY(-1px); }
    .mel-management .md-metric-label { display: block; color: var(--md-muted); font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .mel-management .md-metric strong { display: block; margin-top: .38rem; color: var(--md-ink); font-size: 1.45rem; font-weight: 830; line-height: 1; }
    .mel-management .md-metric small { display: block; margin-top: .43rem; color: var(--md-muted); font-size: .68rem; line-height: 1.35; }
    .mel-management .md-grid { display: grid; grid-template-columns: minmax(0,1.45fr) minmax(370px,.55fr); gap: 1rem; margin-top: 1rem; }
    .mel-management .md-grid-balanced { grid-template-columns: repeat(3,minmax(0,1fr)); }
    .mel-management .md-chart { min-height: 295px; padding: .35rem .7rem .65rem; }
    .mel-management .md-chart-tall { min-height: 340px; }
    .mel-management .apexcharts-series path, .mel-management .apexcharts-bar-area { cursor: pointer; }
    .mel-management .md-action-list { display: grid; gap: .55rem; padding: .8rem; }
    .mel-management .md-action-item { display: grid; grid-template-columns: 34px minmax(0,1fr) auto; gap: .68rem; align-items: start; padding: .7rem; border: 1px solid #e5edef; border-radius: 11px; }
    .mel-management .md-action-mark { display: grid; width: 33px; height: 33px; place-items: center; border-radius: 9px; background: var(--md-primary-soft); color: var(--md-primary); font-size: .53rem; font-weight: 900; }
    .mel-management .md-action-mark.warning { background: #fff4df; color: var(--md-warning); }
    .mel-management .md-action-mark.danger { background: #fff0ef; color: var(--md-danger); }
    .mel-management .md-action-item > div > strong { display: block; color: var(--md-ink); font-size: .7rem; }
    .mel-management .md-action-item p { margin: .2rem 0 0; color: var(--md-muted); font-size: .62rem; line-height: 1.4; }
    .mel-management .md-action-end { text-align: right; }
    .mel-management .md-action-end strong { font-size: .95rem !important; }
    .mel-management .md-action-end a { display: block; margin-top: .28rem; color: var(--md-primary); font-size: .59rem; font-weight: 780; text-decoration: none; white-space: nowrap; }
    .mel-management .md-aging { display: grid; grid-template-columns: repeat(3,1fr); gap: .45rem; padding: 0 .8rem .8rem; }
    .mel-management .md-aging span { padding: .55rem; border: 1px solid #e5edef; border-radius: 9px; color: var(--md-muted); font-size: .58rem; text-align: center; }
    .mel-management .md-aging strong { display: block; margin-bottom: .15rem; color: var(--md-ink); font-size: .82rem; }
    .mel-management .md-aging .danger { border-color: #f1cbc8; background: #fff7f6; }
    .mel-management .md-aging .danger strong { color: var(--md-danger); }
    .mel-management .md-table-panel { margin-top: 1rem; overflow: hidden; }
    .mel-management .md-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .7rem 1rem; border-bottom: 1px solid var(--md-line); background: #fafcfc; color: var(--md-muted); font-size: .65rem; }
    .mel-management .md-search { position: relative; width: min(390px,100%); }
    .mel-management .md-search span { position: absolute; top: 50%; left: .72rem; color: #81939a; transform: translateY(-50%); pointer-events: none; }
    .mel-management .md-search input { width: 100%; padding-left: 2rem; }
    .mel-management .md-table-wrap { max-height: 640px; overflow: auto; scrollbar-color: #b9cbd0 #edf2f3; scrollbar-width: thin; }
    .mel-management .md-table { width: 100%; min-width: 1280px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .mel-management .md-table th { position: sticky; z-index: 2; top: 0; padding: .68rem .72rem; border-bottom: 1px solid var(--md-line); background: #f3f7f8; color: #596e75; font-size: .65rem; font-weight: 820; letter-spacing: .04em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-management .md-table td { padding: .74rem .72rem; border-bottom: 1px solid #e8eff1; background: #fff; color: #344f58; font-size: .71rem; line-height: 1.45; vertical-align: middle; }
    .mel-management .md-table tbody tr:hover td { background: #fafcfc; }
    .mel-management .md-table th:first-child, .mel-management .md-table td:first-child { position: sticky; z-index: 1; left: 0; min-width: 255px; box-shadow: 7px 0 12px -13px #17343e; }
    .mel-management .md-table th:first-child { z-index: 3; background: #f3f7f8; }
    .mel-management .md-table-title { display: block; max-width: 330px; color: var(--md-ink); font-size: .74rem; }
    .mel-management .md-table td small { display: block; margin-top: .18rem; color: var(--md-muted); font-size: .64rem; }
    .mel-management .md-progress { display: block; width: 95px; height: 6px; margin-top: .32rem; overflow: hidden; border-radius: 999px; background: #e8eff1; }
    .mel-management .md-progress span { display: block; height: 100%; border-radius: inherit; background: var(--bar,var(--md-primary)); }
    .mel-management .md-progress-wide { width: 100%; }
    .mel-management .md-scroll-tip { display: flex; justify-content: space-between; gap: .5rem; padding: .58rem 1rem; border-top: 1px solid var(--md-line); background: #fafcfc; color: var(--md-muted); font-size: .6rem; }
    .mel-management .md-bottom-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 1rem; margin-top: 1rem; }
    .mel-management .md-period-list, .mel-management .md-decision-list { display: grid; gap: .55rem; padding: .8rem; }
    .mel-management .md-period { padding: .7rem .75rem; border: 1px solid #e5edef; border-radius: 11px; }
    .mel-management .md-period-top, .mel-management .md-period-meta { display: flex; align-items: flex-start; justify-content: space-between; gap: .7rem; }
    .mel-management .md-period-top strong { display: block; color: var(--md-ink); font-size: .7rem; }
    .mel-management .md-period-top small { display: block; margin-top: .18rem; color: var(--md-muted); font-size: .61rem; }
    .mel-management .md-period-meta { margin: .55rem 0; color: var(--md-muted); font-size: .59rem; }
    .mel-management .md-period-meta strong { color: #405b64; }
    .mel-management .md-decision { display: grid; grid-template-columns: 34px minmax(0,1fr) auto; gap: .68rem; align-items: start; padding: .7rem; border: 1px solid #e5edef; border-radius: 11px; }
    .mel-management .md-decision-mark { display: grid; width: 33px; height: 33px; place-items: center; border-radius: 9px; background: #eaf8f0; color: var(--md-success); font-size: .52rem; font-weight: 900; }
    .mel-management .md-decision strong { display: block; color: var(--md-ink); font-size: .69rem; line-height: 1.4; }
    .mel-management .md-decision p { margin: .2rem 0 0; color: var(--md-muted); font-size: .61rem; line-height: 1.4; }
    .mel-management .md-decision small { display: block; margin-top: .22rem; color: #74868c; font-size: .57rem; }
    .mel-management .md-workspaces { display: grid; gap: .5rem; padding: .8rem; }
    .mel-management .md-workspaces a { position: relative; display: block; padding: .68rem .72rem .68rem 3.35rem; border: 1px solid #e5edef; border-radius: 10px; color: inherit; text-decoration: none; }
    .mel-management .md-workspaces a:hover { border-color: #b8d2d9; background: #fafcfc; }
    .mel-management .md-workspaces span { position: absolute; top: .68rem; left: .7rem; display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; background: var(--md-primary-soft); color: var(--md-primary); font-size: .53rem; font-weight: 900; }
    .mel-management .md-workspaces strong { display: block; color: var(--md-ink); font-size: .69rem; }
    .mel-management .md-workspaces small { display: block; margin-top: .18rem; color: var(--md-muted); font-size: .6rem; line-height: 1.35; }
    .mel-management .md-empty { padding: 2.8rem 1rem; text-align: center; }
    .mel-management .md-empty-mark { display: grid; width: 50px; height: 50px; margin: 0 auto .75rem; place-items: center; border-radius: 14px; background: var(--md-primary-soft); color: var(--md-primary); font-size: .64rem; font-weight: 900; }
    .mel-management .md-empty strong { display: block; font-size: .77rem; }
    .mel-management .md-empty p { max-width: 480px; margin: .3rem auto 0; color: var(--md-muted); font-size: .65rem; line-height: 1.5; }
    .mel-management .md-muted { color: var(--md-muted); font-size: .64rem; }
    @media(max-width:1280px) {
        .mel-management .md-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-management .md-grid-balanced, .mel-management .md-bottom-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-management .md-bottom-grid > :last-child { grid-column: 1/-1; }
    }
    @media(max-width:980px) {
        .mel-management .md-grid, .mel-management .md-grid-balanced, .mel-management .md-bottom-grid { grid-template-columns: 1fr; }
        .mel-management .md-bottom-grid > :last-child { grid-column: auto; }
        .mel-management .md-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:760px) {
        .mel-management .md-header { flex-direction: column; }
        .mel-management .md-header-side { width: 100%; align-items: stretch; }
        .mel-management .md-filter-actions, .mel-management .md-table-toolbar { align-items: stretch; flex-direction: column; }
        .mel-management .md-search { width: 100%; }
    }
    @media(max-width:560px) {
        .mel-management .md-header { padding: 1.1rem; border-radius: 14px; }
        .mel-management .md-metrics, .mel-management .md-filter-grid { grid-template-columns: 1fr; }
        .mel-management .md-actions { display: grid; width: 100%; grid-template-columns: 1fr; }
        .mel-management .md-action-item, .mel-management .md-decision { grid-template-columns: 33px minmax(0,1fr); }
        .mel-management .md-action-end, .mel-management .md-decision .md-btn { grid-column: 2; text-align: left; }
        .mel-management .md-action-end a { display: inline; margin-left: .4rem; }
        .mel-management .md-period-top, .mel-management .md-period-meta { flex-direction: column; }
        .mel-management .md-summary-right .md-badge { display: none; }
    }
    @media print {
        .nxl-navigation, .nxl-header, .md-header-side, .md-filter, .md-table-toolbar, .md-scroll-tip, .md-workspaces { display: none !important; }
        .content { padding: 0 !important; }
        .mel-management { max-width: none; font-size: 10px; }
        .mel-management .md-header { color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
        .mel-management .md-panel, .mel-management .md-metric { box-shadow: none; break-inside: avoid; }
        .mel-management .md-table-wrap { max-height: none; overflow: visible; }
        .mel-management .md-table { min-width: 100%; }
    }
    @media(prefers-reduced-motion:reduce) {
        .mel-management *, .mel-management *::before, .mel-management *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>

<style>
    .mel-notify {
        --mn-primary: #075c7a;
        --mn-primary-dark: #05465d;
        --mn-primary-soft: #eaf4f7;
        --mn-ink: #17343e;
        --mn-muted: #657980;
        --mn-line: #dce7ea;
        --mn-canvas: #f5f8f9;
        --mn-danger: #ae3f3d;
        --mn-warning: #9e6416;
        --mn-success: #187459;
        max-width: 1420px;
        margin: 0 auto;
        color: var(--mn-ink);
        font-size: .875rem;
    }
    .mel-notify *, .mel-notify *::before, .mel-notify *::after { box-sizing: border-box; }
    .mel-notify .mn-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.4rem 1.5rem;
        border: 1px solid rgba(7,92,122,.18);
        border-radius: 18px;
        background: linear-gradient(125deg, var(--mn-primary-dark), var(--mn-primary));
        color: #fff;
        box-shadow: 0 15px 36px rgba(6,73,96,.14);
    }
    .mel-notify .mn-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .67rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .mel-notify .mn-header h1 { margin: 0; color: #fff; font-size: clamp(1.35rem,2vw,1.75rem); font-weight: 780; line-height: 1.2; }
    .mel-notify .mn-header p { max-width: 780px; margin: .5rem 0 0; color: rgba(255,255,255,.8); font-size: .8rem; line-height: 1.55; }
    .mel-notify .mn-header-count { display: flex; flex: 0 0 auto; align-items: center; gap: .55rem; min-height: 44px; padding: .55rem .8rem; border: 1px solid rgba(255,255,255,.28); border-radius: 11px; background: rgba(255,255,255,.1); }
    .mel-notify .mn-header-count strong { font-size: 1.1rem; }
    .mel-notify .mn-header-count span { font-size: .69rem; font-weight: 700; }
    .mel-notify .mn-alert { margin-top: 1rem; border-radius: 12px; }
    .mel-notify .mn-metrics { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .8rem; margin: 1rem 0; }
    .mel-notify .mn-metric { position: relative; min-height: 100px; padding: .95rem 1rem; overflow: hidden; border: 1px solid var(--mn-line); border-radius: 14px; background: #fff; box-shadow: 0 5px 18px rgba(24,55,65,.045); }
    .mel-notify .mn-metric::after { position: absolute; right: 0; bottom: 0; width: 55px; height: 4px; border-radius: 4px 0 0; background: var(--metric, var(--mn-primary)); content: ""; }
    .mel-notify .mn-metric-label { display: block; color: var(--mn-muted); font-size: .64rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
    .mel-notify .mn-metric strong { display: block; margin-top: .36rem; color: var(--mn-ink); font-size: 1.5rem; font-weight: 820; line-height: 1; }
    .mel-notify .mn-metric small { display: block; margin-top: .42rem; color: var(--mn-muted); font-size: .65rem; }
    .mel-notify .mn-panel { border: 1px solid var(--mn-line); border-radius: 16px; background: #fff; box-shadow: 0 7px 22px rgba(25,57,67,.045); }
    .mel-notify .mn-panel + .mn-panel { margin-top: 1rem; }
    .mel-notify .mn-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 59px; padding: .85rem 1rem; border-bottom: 1px solid var(--mn-line); }
    .mel-notify .mn-panel-head h2 { margin: 0; color: var(--mn-ink); font-size: .9rem; font-weight: 780; }
    .mel-notify .mn-panel-head p { margin: .2rem 0 0; color: var(--mn-muted); font-size: .67rem; }
    .mel-notify .mn-panel-body { padding: 1rem; }
    .mel-notify .mn-tabs { display: flex; gap: .35rem; padding: .3rem; border: 1px solid var(--mn-line); border-radius: 11px; background: var(--mn-canvas); }
    .mel-notify .mn-tab { display: inline-flex; align-items: center; gap: .4rem; min-height: 34px; padding: .42rem .65rem; border-radius: 8px; color: #526870; font-size: .68rem; font-weight: 760; text-decoration: none; }
    .mel-notify .mn-tab span { display: inline-grid; min-width: 22px; height: 22px; padding: 0 .3rem; place-items: center; border-radius: 7px; background: #fff; font-size: .61rem; }
    .mel-notify .mn-tab.active, .mel-notify .mn-tab:hover { background: var(--mn-primary); color: #fff; }
    .mel-notify .mn-tab.active span, .mel-notify .mn-tab:hover span { color: var(--mn-primary); }
    .mel-notify .mn-filter-summary { cursor: pointer; list-style: none; }
    .mel-notify .mn-filter-summary::-webkit-details-marker { display: none; }
    .mel-notify .mn-filter-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .8rem; }
    .mel-notify .mn-filter-wide { grid-column: span 2; }
    .mel-notify .mn-field label { display: block; margin-bottom: .32rem; color: #48616a; font-size: .64rem; font-weight: 760; }
    .mel-notify .form-control, .mel-notify .form-select { min-height: 40px; border-color: #ccdce1; border-radius: 9px; color: var(--mn-ink); font-size: .74rem; box-shadow: none; }
    .mel-notify .form-control:focus, .mel-notify .form-select:focus { border-color: var(--mn-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-notify .mn-filter-actions { display: flex; justify-content: flex-end; gap: .5rem; padding-top: .9rem; border-top: 1px solid var(--mn-line); grid-column: 1/-1; }
    .mel-notify .mn-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: .52rem .78rem; border: 1px solid transparent; border-radius: 9px; background: #fff; font-size: .69rem; font-weight: 760; line-height: 1.2; text-decoration: none; }
    .mel-notify .mn-btn-primary { border-color: var(--mn-primary); background: var(--mn-primary); color: #fff; }
    .mel-notify .mn-btn-primary:hover { background: var(--mn-primary-dark); color: #fff; }
    .mel-notify .mn-btn-secondary { border-color: #ccdade; color: #425b64; }
    .mel-notify .mn-btn-secondary:hover { border-color: var(--mn-primary); color: var(--mn-primary); }
    .mel-notify .mn-badge { display: inline-flex; align-items: center; gap: .3rem; width: fit-content; padding: .26rem .48rem; border-radius: 999px; background: var(--mn-primary-soft); color: var(--mn-primary); font-size: .61rem; font-weight: 800; line-height: 1.2; }
    .mel-notify .mn-badge.danger { background: #fff0ef; color: var(--mn-danger); }
    .mel-notify .mn-badge.warning { background: #fff4df; color: var(--mn-warning); }
    .mel-notify .mn-badge.success { background: #e9f6f1; color: var(--mn-success); }
    .mel-notify .mn-badge.secondary { background: #edf1f2; color: #586b72; }
    .mel-notify .mn-bulk { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 57px; padding: .7rem 1rem; border-bottom: 1px solid var(--mn-line); background: #fbfcfc; }
    .mel-notify .mn-bulk-left, .mel-notify .mn-bulk-actions { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .mel-notify .mn-select-label { display: inline-flex; align-items: center; gap: .45rem; color: #536a72; font-size: .66rem; font-weight: 750; }
    .mel-notify .mn-checkbox { width: 16px; height: 16px; border: 1px solid #a9bec5; border-radius: 4px; accent-color: var(--mn-primary); }
    .mel-notify .mn-showing { color: var(--mn-muted); font-size: .65rem; }
    .mel-notify .mn-list { overflow: hidden; }
    .mel-notify .mn-item { display: grid; grid-template-columns: 26px 42px minmax(0,1fr) auto; gap: .75rem; align-items: start; padding: .9rem 1rem; border-bottom: 1px solid #e8eff1; background: #fff; }
    .mel-notify .mn-item:last-child { border-bottom: 0; }
    .mel-notify .mn-item.unread { background: linear-gradient(90deg,rgba(7,92,122,.055),#fff 42%); }
    .mel-notify .mn-item:hover { background-color: #f9fbfc; }
    .mel-notify .mn-item-icon { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 11px; background: var(--mn-primary-soft); color: var(--mn-primary); font-size: .72rem; font-weight: 850; }
    .mel-notify .mn-item-icon.danger { background: #fff0ef; color: var(--mn-danger); }
    .mel-notify .mn-item-icon.warning { background: #fff4df; color: var(--mn-warning); }
    .mel-notify .mn-item-icon.success { background: #e9f6f1; color: var(--mn-success); }
    .mel-notify .mn-item-content { min-width: 0; }
    .mel-notify .mn-item-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: .42rem; }
    .mel-notify .mn-item-title { margin: 0; overflow: hidden; color: var(--mn-ink); font-size: .77rem; font-weight: 790; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
    .mel-notify .mn-new-dot { width: 7px; height: 7px; flex: 0 0 auto; border-radius: 50%; background: var(--mn-primary); box-shadow: 0 0 0 3px rgba(7,92,122,.12); }
    .mel-notify .mn-message { max-width: 900px; margin: .28rem 0 0; color: #536a72; font-size: .7rem; line-height: 1.5; }
    .mel-notify .mn-meta { display: flex; flex-wrap: wrap; gap: .35rem .75rem; margin-top: .52rem; color: var(--mn-muted); font-size: .61rem; }
    .mel-notify .mn-item-actions { display: flex; align-items: center; gap: .4rem; padding-top: .05rem; }
    .mel-notify .mn-empty { padding: 3.8rem 1rem; text-align: center; }
    .mel-notify .mn-empty-mark { display: grid; width: 56px; height: 56px; margin: 0 auto .8rem; place-items: center; border-radius: 16px; background: var(--mn-primary-soft); color: var(--mn-primary); font-size: 1.2rem; font-weight: 850; }
    .mel-notify .mn-empty strong { display: block; font-size: .87rem; }
    .mel-notify .mn-empty span { display: block; max-width: 500px; margin: .35rem auto 0; color: var(--mn-muted); font-size: .7rem; }
    .mel-notify .mn-pagination { padding: .8rem 1rem; border-top: 1px solid var(--mn-line); }
    @media(max-width:1000px) {
        .mel-notify .mn-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-notify .mn-item { grid-template-columns: 25px 42px minmax(0,1fr); }
        .mel-notify .mn-item-actions { grid-column: 3; justify-content: flex-start; }
    }
    @media(max-width:760px) {
        .mel-notify .mn-header { flex-direction: column; }
        .mel-notify .mn-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-notify .mn-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-notify .mn-filter-wide { grid-column: span 2; }
        .mel-notify .mn-panel-head { align-items: flex-start; flex-direction: column; }
        .mel-notify .mn-tabs { width: 100%; overflow-x: auto; }
        .mel-notify .mn-bulk { align-items: flex-start; flex-direction: column; }
    }
    @media(max-width:520px) {
        .mel-notify .mn-header { padding: 1.1rem; border-radius: 14px; }
        .mel-notify .mn-header-count { width: 100%; }
        .mel-notify .mn-metrics, .mel-notify .mn-filter-grid { grid-template-columns: 1fr; }
        .mel-notify .mn-filter-wide { grid-column: span 1; }
        .mel-notify .mn-filter-actions { flex-direction: column-reverse; }
        .mel-notify .mn-filter-actions .mn-btn { width: 100%; }
        .mel-notify .mn-item { grid-template-columns: 22px minmax(0,1fr); padding: .85rem .75rem; }
        .mel-notify .mn-item-icon { display: none; }
        .mel-notify .mn-item-actions { grid-column: 2; flex-wrap: wrap; }
        .mel-notify .mn-item-title { white-space: normal; }
        .mel-notify .mn-bulk-actions .mn-btn { flex: 1; }
    }
</style>

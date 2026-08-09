<style>
    .dq-workspace {
        --dq-primary:#075c7a;
        --dq-primary-dark:#05465d;
        --dq-primary-soft:#eaf4f7;
        --dq-ink:#17343e;
        --dq-muted:#64777f;
        --dq-line:#dbe7ea;
        --dq-canvas:#f5f8f9;
        --dq-danger:#ad3e3b;
        --dq-warning:#a26816;
        --dq-success:#177258;
        max-width:1540px;
        margin:0 auto;
        color:var(--dq-ink);
        font-size:.875rem;
    }
    .dq-workspace *, .dq-workspace *::before, .dq-workspace *::after { box-sizing:border-box; }
    .dq-workspace .dq-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1.25rem; padding:1.4rem 1.5rem; border:1px solid rgba(7,92,122,.18); border-radius:18px; background:linear-gradient(125deg,var(--dq-primary-dark),var(--dq-primary)); color:#fff; box-shadow:0 15px 36px rgba(6,73,96,.14); }
    .dq-workspace .dq-eyebrow { display:block; margin-bottom:.35rem; color:rgba(255,255,255,.72); font-size:.67rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .dq-workspace .dq-header h1 { margin:0; color:#fff; font-size:clamp(1.35rem,2vw,1.75rem); font-weight:780; line-height:1.2; }
    .dq-workspace .dq-header p { max-width:850px; margin:.5rem 0 0; color:rgba(255,255,255,.8); font-size:.79rem; line-height:1.55; }
    .dq-workspace .dq-header-actions { display:flex; flex:0 0 auto; flex-wrap:wrap; gap:.5rem; }
    .dq-workspace .dq-header-link { display:inline-flex; align-items:center; min-height:39px; padding:.52rem .75rem; border:1px solid rgba(255,255,255,.28); border-radius:9px; background:rgba(255,255,255,.1); color:#fff; font-size:.68rem; font-weight:760; text-decoration:none; }
    .dq-workspace .dq-header-link:hover { background:#fff; color:var(--dq-primary-dark); }
    .dq-workspace .dq-alert { margin-top:1rem; border-radius:12px; }
    .dq-workspace .dq-metrics { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:.75rem; margin:1rem 0; }
    .dq-workspace .dq-metric { position:relative; min-height:104px; padding:.92rem .95rem; overflow:hidden; border:1px solid var(--dq-line); border-radius:14px; background:#fff; box-shadow:0 5px 18px rgba(24,55,65,.045); }
    .dq-workspace .dq-metric::after { position:absolute; right:0; bottom:0; width:52px; height:4px; border-radius:4px 0 0; background:var(--metric,var(--dq-primary)); content:""; }
    .dq-workspace .dq-metric-label { display:block; color:var(--dq-muted); font-size:.61rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    .dq-workspace .dq-metric strong { display:block; margin-top:.38rem; color:var(--dq-ink); font-size:1.43rem; font-weight:820; line-height:1; }
    .dq-workspace .dq-metric small { display:block; margin-top:.42rem; color:var(--dq-muted); font-size:.61rem; line-height:1.35; }
    .dq-workspace .dq-flow { display:grid; grid-template-columns:repeat(4,1fr); margin-bottom:1rem; padding:.85rem 1rem; border:1px solid var(--dq-line); border-radius:14px; background:#fff; }
    .dq-workspace .dq-flow-step { position:relative; min-height:38px; padding:.05rem .7rem 0 2.15rem; color:var(--dq-muted); font-size:.64rem; line-height:1.35; }
    .dq-workspace .dq-flow-step strong { display:block; color:var(--dq-ink); font-size:.68rem; }
    .dq-workspace .dq-flow-step::before { position:absolute; left:.2rem; top:.05rem; display:grid; width:25px; height:25px; place-items:center; border:2px solid var(--dq-primary); border-radius:50%; background:var(--dq-primary-soft); color:var(--dq-primary); content:attr(data-step); font-size:.62rem; font-weight:850; z-index:2; }
    .dq-workspace .dq-flow-step:not(:last-child)::after { position:absolute; left:25px; right:-5px; top:12px; height:2px; background:#d5e3e7; content:""; z-index:1; }
    .dq-workspace .dq-panel { border:1px solid var(--dq-line); border-radius:16px; background:#fff; box-shadow:0 7px 22px rgba(25,57,67,.045); }
    .dq-workspace .dq-panel + .dq-panel { margin-top:1rem; }
    .dq-workspace .dq-panel-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:58px; padding:.82rem 1rem; border-bottom:1px solid var(--dq-line); }
    .dq-workspace .dq-panel-head h2, .dq-workspace .dq-panel-head h3 { margin:0; color:var(--dq-ink); font-size:.88rem; font-weight:780; }
    .dq-workspace .dq-panel-head p { margin:.2rem 0 0; color:var(--dq-muted); font-size:.65rem; }
    .dq-workspace .dq-panel-body { padding:1rem; }
    .dq-workspace .dq-filter-summary { cursor:pointer; list-style:none; }
    .dq-workspace .dq-filter-summary::-webkit-details-marker { display:none; }
    .dq-workspace .dq-filter-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.78rem; }
    .dq-workspace .dq-filter-wide { grid-column:span 2; }
    .dq-workspace .dq-field label { display:block; margin-bottom:.3rem; color:#48616a; font-size:.62rem; font-weight:760; }
    .dq-workspace .form-control, .dq-workspace .form-select { min-height:40px; border-color:#cadbe0; border-radius:9px; color:var(--dq-ink); font-size:.72rem; box-shadow:none; }
    .dq-workspace .form-control:focus, .dq-workspace .form-select:focus { border-color:var(--dq-primary); box-shadow:0 0 0 3px rgba(7,92,122,.1); }
    .dq-workspace .dq-filter-actions { display:flex; justify-content:flex-end; gap:.5rem; padding-top:.9rem; border-top:1px solid var(--dq-line); grid-column:1/-1; }
    .dq-workspace .dq-btn { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:.51rem .76rem; border:1px solid transparent; border-radius:9px; background:#fff; font-size:.68rem; font-weight:760; line-height:1.2; text-decoration:none; }
    .dq-workspace .dq-btn-primary { border-color:var(--dq-primary); background:var(--dq-primary); color:#fff; }
    .dq-workspace .dq-btn-primary:hover { background:var(--dq-primary-dark); color:#fff; }
    .dq-workspace .dq-btn-secondary { border-color:#cad9de; color:#405a63; }
    .dq-workspace .dq-btn-secondary:hover { border-color:var(--dq-primary); color:var(--dq-primary); }
    .dq-workspace .dq-btn-danger { border-color:#dfb6b4; color:var(--dq-danger); }
    .dq-workspace .dq-btn-danger:hover { background:#fff2f1; color:#8c302e; }
    .dq-workspace .dq-badge { display:inline-flex; align-items:center; gap:.25rem; width:fit-content; padding:.26rem .47rem; border-radius:999px; background:var(--dq-primary-soft); color:var(--dq-primary); font-size:.6rem; font-weight:820; line-height:1.2; }
    .dq-workspace .dq-badge.error, .dq-workspace .dq-badge.rejected { background:#fff0ef; color:var(--dq-danger); }
    .dq-workspace .dq-badge.warning, .dq-workspace .dq-badge.returned { background:#fff4df; color:var(--dq-warning); }
    .dq-workspace .dq-badge.resolved, .dq-workspace .dq-badge.approved { background:#e9f6f1; color:var(--dq-success); }
    .dq-workspace .dq-badge.superseded, .dq-workspace .dq-badge.draft { background:#edf1f2; color:#566b72; }
    .dq-workspace .dq-tabs { display:flex; gap:.4rem; margin:1rem 0; overflow-x:auto; scrollbar-width:thin; }
    .dq-workspace .dq-tab { display:flex; align-items:center; gap:.55rem; min-width:180px; padding:.72rem .8rem; border:1px solid var(--dq-line); border-radius:11px; background:#fff; color:#50666e; text-decoration:none; }
    .dq-workspace .dq-tab strong, .dq-workspace .dq-tab small { display:block; }
    .dq-workspace .dq-tab strong { color:inherit; font-size:.72rem; }
    .dq-workspace .dq-tab small { margin-top:.1rem; color:var(--dq-muted); font-size:.58rem; }
    .dq-workspace .dq-tab-count { display:grid; min-width:31px; height:31px; padding:0 .35rem; place-items:center; border-radius:8px; background:var(--dq-canvas); color:var(--dq-ink); font-size:.66rem; font-weight:820; }
    .dq-workspace .dq-tab.active, .dq-workspace .dq-tab:hover { border-color:var(--dq-primary); background:var(--dq-primary-soft); color:var(--dq-primary-dark); }
    .dq-workspace .dq-tab.active .dq-tab-count { background:var(--dq-primary); color:#fff; }
    .dq-workspace .dq-insights { display:grid; grid-template-columns:1fr 1.5fr; gap:.8rem; margin-bottom:1rem; }
    .dq-workspace .dq-aging { display:grid; grid-template-columns:repeat(3,1fr); gap:.55rem; }
    .dq-workspace .dq-age { padding:.68rem; border:1px solid var(--dq-line); border-radius:10px; background:#fbfcfc; }
    .dq-workspace .dq-age small, .dq-workspace .dq-age strong { display:block; }
    .dq-workspace .dq-age small { color:var(--dq-muted); font-size:.58rem; font-weight:760; text-transform:uppercase; }
    .dq-workspace .dq-age strong { margin-top:.22rem; font-size:1rem; }
    .dq-workspace .dq-rule-bars { display:grid; gap:.48rem; }
    .dq-workspace .dq-rule-bar-row { display:grid; grid-template-columns:minmax(130px,1fr) 2fr 34px; gap:.55rem; align-items:center; font-size:.61rem; }
    .dq-workspace .dq-bar { height:7px; overflow:hidden; border-radius:999px; background:#edf2f3; }
    .dq-workspace .dq-bar span { display:block; height:100%; border-radius:inherit; background:var(--dq-primary); }
    .dq-workspace .dq-bar span.error { background:var(--dq-danger); }
    .dq-workspace .dq-table-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:56px; padding:.7rem 1rem; border-bottom:1px solid var(--dq-line); background:#fbfcfc; color:var(--dq-muted); font-size:.64rem; }
    .dq-workspace .dq-table-toolbar strong { color:var(--dq-ink); }
    .dq-workspace .dq-toolbar-actions { display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; }
    .dq-workspace .dq-table-scroll { max-height:68vh; overflow:auto; scrollbar-color:#91b2bd #eef3f4; scrollbar-width:thin; }
    .dq-workspace .dq-table-scroll:focus { outline:3px solid rgba(7,92,122,.12); outline-offset:-3px; }
    .dq-workspace .dq-table { width:100%; min-width:1460px; margin:0; border-collapse:separate; border-spacing:0; font-size:.69rem; }
    .dq-workspace .dq-table th { position:sticky; top:0; z-index:3; padding:.7rem .72rem; border-bottom:1px solid var(--dq-line); background:#f2f7f8; color:#51676f; font-size:.59rem; font-weight:820; letter-spacing:.045em; text-align:left; text-transform:uppercase; white-space:nowrap; }
    .dq-workspace .dq-table td { padding:.78rem .72rem; border-bottom:1px solid #e8eff1; color:#334d56; vertical-align:top; }
    .dq-workspace .dq-table tbody tr:hover td { background:#f9fbfc; }
    .dq-workspace .dq-table th:last-child, .dq-workspace .dq-table td:last-child { position:sticky; right:0; z-index:2; border-left:1px solid var(--dq-line); background:#fff; box-shadow:-8px 0 13px rgba(23,49,58,.035); }
    .dq-workspace .dq-table th:last-child { z-index:4; background:#f2f7f8; }
    .dq-workspace .dq-table tbody tr:hover td:last-child { background:#f9fbfc; }
    .dq-workspace .dq-cell-title { display:block; max-width:300px; overflow:hidden; color:var(--dq-ink); font-size:.72rem; font-weight:780; line-height:1.35; text-overflow:ellipsis; white-space:nowrap; }
    .dq-workspace .dq-cell-meta { display:block; margin-top:.22rem; color:var(--dq-muted); font-size:.6rem; line-height:1.4; }
    .dq-workspace .dq-message { max-width:370px; margin-top:.35rem; color:#425c65; font-size:.66rem; line-height:1.45; }
    .dq-workspace .dq-counts { display:flex; flex-wrap:wrap; gap:.3rem; }
    .dq-workspace .dq-count { padding:.23rem .37rem; border:1px solid var(--dq-line); border-radius:6px; background:#fafcfc; color:#536a72; font-size:.59rem; white-space:nowrap; }
    .dq-workspace .dq-resolution { min-width:260px; }
    .dq-workspace .dq-resolution summary { color:var(--dq-primary); cursor:pointer; font-size:.63rem; font-weight:780; }
    .dq-workspace .dq-resolution form { margin-top:.5rem; }
    .dq-workspace .dq-resolution textarea { min-height:62px; }
    .dq-workspace .dq-empty { padding:3.5rem 1rem!important; text-align:center; }
    .dq-workspace .dq-empty strong { display:block; font-size:.86rem; }
    .dq-workspace .dq-empty span { display:block; max-width:530px; margin:.35rem auto 0; color:var(--dq-muted); font-size:.69rem; }
    .dq-workspace .dq-pagination { padding:.8rem 1rem; border-top:1px solid var(--dq-line); }
    .dq-workspace .dq-rule-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
    .dq-workspace .dq-rule-card { padding:.9rem; border:1px solid var(--dq-line); border-radius:12px; background:#fff; }
    .dq-workspace .dq-rule-card-head { display:flex; justify-content:space-between; gap:.7rem; }
    .dq-workspace .dq-rule-card h3 { margin:0; color:var(--dq-ink); font-size:.74rem; }
    .dq-workspace .dq-rule-code { display:block; margin-top:.18rem; color:var(--dq-muted); font-family:monospace; font-size:.57rem; }
    .dq-workspace .dq-rule-card p { margin:.65rem 0 0; color:#4c646d; font-size:.65rem; line-height:1.5; }
    .dq-workspace .dq-rule-card footer { margin-top:.7rem; padding-top:.55rem; border-top:1px solid var(--dq-line); color:var(--dq-muted); font-size:.59rem; }
    .dq-workspace .dq-note { padding:.85rem 1rem; border:1px solid #d7e7eb; border-left:4px solid var(--dq-primary); border-radius:10px; background:#f4fafb; color:#405a63; font-size:.68rem; line-height:1.5; }
    @media(max-width:1250px) { .dq-workspace .dq-metrics { grid-template-columns:repeat(3,minmax(0,1fr)); } .dq-workspace .dq-filter-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    @media(max-width:980px) { .dq-workspace .dq-filter-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } .dq-workspace .dq-insights { grid-template-columns:1fr; } .dq-workspace .dq-rule-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:760px) { .dq-workspace .dq-header { flex-direction:column; } .dq-workspace .dq-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .dq-workspace .dq-filter-wide { grid-column:span 2; } .dq-workspace .dq-flow { grid-template-columns:1fr 1fr; gap:.8rem; } .dq-workspace .dq-flow-step:not(:last-child)::after { display:none; } .dq-workspace .dq-panel-head, .dq-workspace .dq-table-toolbar { align-items:flex-start; flex-direction:column; } }
    @media(max-width:520px) { .dq-workspace .dq-header { padding:1.1rem; border-radius:14px; } .dq-workspace .dq-header-actions, .dq-workspace .dq-header-link { width:100%; } .dq-workspace .dq-metrics, .dq-workspace .dq-filter-grid, .dq-workspace .dq-rule-grid, .dq-workspace .dq-aging { grid-template-columns:1fr; } .dq-workspace .dq-filter-wide { grid-column:span 1; } .dq-workspace .dq-filter-actions { flex-direction:column-reverse; } .dq-workspace .dq-filter-actions .dq-btn { width:100%; } .dq-workspace .dq-flow { grid-template-columns:1fr; } }
</style>

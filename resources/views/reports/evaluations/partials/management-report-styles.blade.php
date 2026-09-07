<style>
    .eval-management { --eval-ink: #153047; --eval-muted: #63788b; --eval-border: #dce6ec; --eval-soft: #f5f8fb; color: var(--eval-ink); min-width: 0; }
    .eval-management *, .eval-management *::before, .eval-management *::after { box-sizing: border-box; }
    .eval-management :focus-visible { outline: 3px solid #128c80; outline-offset: 3px; }
    .eval-management h2, .eval-management h3, .eval-management h4 { color: var(--eval-ink); font-weight: 800; }
    .eval-nav { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; padding: 12px; margin: 22px 0; border: 1px solid var(--eval-border); background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(21,48,71,.04); }
    .eval-nav a { display: flex; align-items: center; gap: 9px; padding: 10px; color: #34546b; font-size: .76rem; line-height: 1.4; font-weight: 700; border-radius: 10px; text-decoration: none; }
    .eval-nav a:hover { background: #eaf7f3; color: #08695f; }
    .eval-nav-number { display: grid; place-items: center; flex: 0 0 26px; height: 26px; border-radius: 8px; background: #eaf7f3; color: #08756a; font-size: .75rem; font-weight: 800; }
    .eval-report-section { background: #fff; border: 1px solid var(--eval-border); border-radius: 18px; overflow: hidden; margin-bottom: 26px; box-shadow: 0 10px 26px rgba(21,48,71,.035); scroll-margin-top: 100px; }
    .eval-section-head { display: flex; gap: 15px; align-items: flex-start; padding: 26px 28px; border-bottom: 1px solid var(--eval-border); background: linear-gradient(120deg, #f5faf9, #f7f9fc); }
    .eval-section-icon { display: grid; place-items: center; flex: 0 0 42px; height: 42px; border-radius: 12px; background: #e0f3ed; color: #08796d; font-size: 1.15rem; }
    .eval-section-head h2 { font-size: clamp(1.12rem, 2.2vw, 1.42rem); margin: 0 0 7px; line-height: 1.35; letter-spacing: -.015em; }
    .eval-section-head p { font-size: .8rem; line-height: 1.6; color: var(--eval-muted); margin: 0; max-width: 900px; }
    .eval-section-body { padding: 26px 28px; min-width: 0; }
    .eval-stat-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
    .eval-stat { padding: 19px; border: 1px solid var(--eval-border); border-radius: 13px; background: #fff; min-width: 0; }
    .eval-stat strong { display: block; font-size: 1.95rem; font-weight: 850; line-height: 1.2; margin-bottom: 7px; letter-spacing: -.035em; color: var(--eval-ink); overflow-wrap: anywhere; }
    .eval-stat span { font-size: .76rem; line-height: 1.45; color: var(--eval-muted); }
    .eval-time-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; padding: 22px; background: var(--eval-soft); border: 1px solid #e6edf3; border-radius: 13px; margin: 20px 0 12px; }
    .eval-label { display: block; color: var(--eval-muted); font-size: .7rem; line-height: 1.45; margin-bottom: 5px; font-weight: 500; }
    .eval-value { display: block; color: var(--eval-ink); font-size: .82rem; font-weight: 750; line-height: 1.5; overflow-wrap: anywhere; }
    .eval-note { color: var(--eval-muted); font-size: .75rem; line-height: 1.6; margin: 12px 0 0; }
    .eval-subtitle { font-size: 1rem; margin: 26px 0 14px; }
    .eval-table-scroll { position: relative; overflow-x: auto; border: 1px solid var(--eval-border); border-radius: 12px; max-width: 100%; }
    .eval-table { width: 100%; border-collapse: collapse; font-size: .76rem; margin: 0; }
    .eval-table th { background: var(--eval-soft); font-size: .67rem; letter-spacing: .035em; text-transform: uppercase; font-weight: 800; color: #536e82; padding: 13px 15px; text-align: left; border-bottom: 1px solid var(--eval-border); white-space: nowrap; }
    .eval-table td { padding: 15px; border-bottom: 1px solid #e9eef3; color: #29465c; vertical-align: top; line-height: 1.55; min-width: 85px; }
    .eval-table tbody tr:last-child td { border-bottom: 0; }
    .eval-table tbody tr:hover td { background: #fcfefd; }
    .eval-table .eval-person { min-width: 180px; max-width: 340px; }
    .eval-person strong { display: block; color: var(--eval-ink); font-weight: 750; }
    .eval-person small { display: block; font-size: .69rem; color: var(--eval-muted); margin-top: 4px; overflow-wrap: anywhere; }
    .eval-nowrap { white-space: nowrap; }
    .eval-table .eval-number { text-align: right; font-variant-numeric: tabular-nums; }
    .eval-evaluator-name { display: flex; gap: 8px; align-items: center; }
    .eval-color-dot { width: 10px; height: 10px; display: inline-block; flex: 0 0 10px; border-radius: 3px; background: #0f766e; }
    .eval-rank { display: inline-grid; place-items: center; width: 29px; height: 29px; background: #e8f5f0; color: #087160; border: 1px solid #cde8dd; border-radius: 8px; font-weight: 850; }
    .eval-status { display: inline-block; font-size: .69rem; line-height: 1.45; padding: 5px 9px; border-radius: 7px; background: #eef3f8; color: #426078; font-weight: 750; white-space: normal; min-width: 85px; }
    .eval-group { margin-top: 23px; padding-top: 23px; border-top: 1px solid var(--eval-border); }
    .eval-group:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
    .eval-group-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; margin-bottom: 17px; }
    .eval-group-head h3 { font-size: 1.05rem; line-height: 1.4; margin: 3px 0 0; }
    .eval-kicker { color: #08776b; font-size: .65rem; font-weight: 850; text-transform: uppercase; letter-spacing: .07em; }
    .eval-tag { display: inline-block; border: 1px solid var(--eval-border); background: #f8fafc; padding: 6px 9px; border-radius: 8px; font-size: .68rem; color: #567084; white-space: nowrap; }
    .eval-chart-card { border: 1px solid var(--eval-border); border-radius: 13px; padding: 19px; margin: 20px 0 0; background: #fff; min-width: 0; break-inside: avoid; }
    .eval-chart-card h4 { font-size: .9rem; margin: 0 0 13px; line-height: 1.45; }
    .eval-chart-scroll { overflow-x: auto; max-width: 100%; }
    .eval-chart { display: block; width: 100%; min-width: 650px; height: auto; background: #fff; }
    .eval-chart-card figcaption { font-size: .73rem; color: var(--eval-muted); line-height: 1.6; margin-top: 12px; }
    .eval-record { border: 1px solid var(--eval-border); border-radius: 13px; overflow: hidden; margin-top: 19px; }
    .eval-record-head { display: grid; grid-template-columns: minmax(200px, 1.4fr) repeat(2, minmax(140px, 1fr)); gap: 20px; padding: 22px; background: #f6f9fc; border-bottom: 1px solid var(--eval-border); }
    .eval-record-head h4 { font-size: .96rem; margin: 4px 0; line-height: 1.5; }
    .eval-record .eval-table-scroll { border: 0; border-radius: 0; }
    .eval-record-comments { border-top: 1px solid var(--eval-border); padding: 18px 22px; }
    .eval-comment { white-space: pre-line; overflow-wrap: anywhere; font-size: .76rem; color: #3d566b; line-height: 1.6; margin: 0; }
    .eval-criteria-table td:nth-child(1) { min-width: 150px; }
    .eval-criteria-table td:nth-child(2), .eval-criteria-table td:last-child { min-width: 200px; }
    .eval-insight-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
    .eval-insight { border: 1px solid var(--eval-border); border-left: 4px solid #287eaf; border-radius: 12px; padding: 20px; background: #f8fbfd; }
    .eval-insight h3 { font-size: .88rem; margin: 0 0 8px; }
    .eval-insight p { margin: 0; color: #526c7e; font-size: .77rem; line-height: 1.65; }
    .eval-insight--success { background: #f2faf6; border-left-color: #16835c; }
    .eval-insight--warning { background: #fffaf0; border-left-color: #c18a1a; }
    .eval-insight--danger { background: #fff6f5; border-left-color: #bc4c46; }
    .eval-governance-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; }
    .eval-governance-card { border: 1px solid var(--eval-border); border-radius: 12px; padding: 23px; }
    .eval-governance-card h3 { font-size: .94rem; margin: 0 0 14px; }
    .eval-governance-card ul, .eval-governance-card ol { margin: 0; padding-left: 20px; color: #476278; font-size: .77rem; line-height: 1.7; }
    .eval-governance-card li + li { margin-top: 10px; }
    .eval-empty { padding: 25px; border: 1px dashed #cfdce5; border-radius: 12px; background: #f9fbfd; color: var(--eval-muted); font-size: .8rem; line-height: 1.7; }
    .eval-empty strong { display: block; color: var(--eval-ink); margin-bottom: 5px; }
    .eval-report-footer { font-size: .73rem; color: var(--eval-muted); text-align: right; margin: 17px 0 0; }
    @media (max-width: 1199px) { .eval-nav { grid-template-columns: repeat(3, minmax(0, 1fr)); } .eval-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .eval-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); } .eval-section-head, .eval-section-body { padding: 21px 18px; } .eval-stat-grid, .eval-time-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .eval-insight-grid, .eval-governance-grid { grid-template-columns: 1fr; } .eval-record-head { grid-template-columns: 1fr; gap: 13px; } .eval-group-head { flex-direction: column; gap: 8px; } .eval-chart-card { padding: 14px; } .eval-section-icon { display: none; } .eval-stat { padding: 15px; } .eval-stat strong { font-size: 1.7rem; } }
    @media (max-width: 420px) { .eval-nav { gap: 3px; padding: 7px; } .eval-nav a { padding: 8px 5px; font-size: .7rem; } .eval-time-grid { grid-template-columns: 1fr; } .eval-stat-grid { gap: 8px; } }
    @media print { .eval-nav { display: none !important; } .eval-report-section { border: 0; border-radius: 0; box-shadow: none; overflow: visible; margin-bottom: 25px; break-inside: auto; } .eval-section-head { background: #f1f5f7 !important; break-after: avoid; padding: 14px 0; } .eval-section-body { padding: 16px 0; } .eval-stat-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } .eval-stat { padding: 10px; } .eval-stat strong { font-size: 19pt; } .eval-chart-scroll, .eval-table-scroll { overflow: visible; max-width: none; } .eval-chart { min-width: 0; width: 100%; } .eval-chart-card { break-inside: avoid; } .eval-table { font-size: 8pt; table-layout: auto; } .eval-table th { white-space: normal; font-size: 7pt; } .eval-table td, .eval-table th { min-width: 0 !important; padding: 7px 6px; overflow-wrap: anywhere; } .eval-table thead { display: table-header-group; } .eval-table tr { break-inside: avoid; } .eval-nowrap { white-space: normal; } .eval-record { overflow: visible; break-inside: auto; } .eval-record-head { break-after: avoid; } .eval-insight, .eval-governance-card { break-inside: avoid; } .eval-time-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } .eval-section-icon { display: none; } }
</style>

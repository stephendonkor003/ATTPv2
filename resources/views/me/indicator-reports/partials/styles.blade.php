<style>
    .mel-indicator-reports {
        --ir-navy: #153b59;
        --ir-blue: #176b87;
        --ir-cyan: #2d8ea3;
        --ir-ink: #18313f;
        --ir-muted: #607582;
        --ir-line: #dce7eb;
        --ir-soft: #f4f8fa;
        --ir-white: #ffffff;
        --ir-green: #16815f;
        --ir-amber: #b26b14;
        --ir-red: #b83d3a;
        --ir-purple: #6b5ca5;
        color: var(--ir-ink);
        font-family: Inter, Arial, sans-serif;
        font-size: .875rem;
    }

    .mel-indicator-reports *,
    .mel-indicator-reports *::before,
    .mel-indicator-reports *::after { box-sizing: border-box; }

    .mel-indicator-reports .ir-header {
        position: relative;
        display: flex;
        justify-content: space-between;
        gap: 2rem;
        overflow: hidden;
        margin-bottom: 1rem;
        padding: 1.65rem 1.75rem;
        border-radius: 1.1rem;
        color: #fff;
        background:
            radial-gradient(circle at 92% 4%, rgba(255,255,255,.18), transparent 27%),
            linear-gradient(128deg, #123752 0%, #17677d 62%, #228ca0 100%);
        box-shadow: 0 14px 34px rgba(20, 57, 81, .18);
    }

    .mel-indicator-reports .ir-header::after {
        content: "";
        position: absolute;
        right: -58px;
        bottom: -94px;
        width: 220px;
        height: 220px;
        border: 1px solid rgba(255,255,255,.17);
        border-radius: 50%;
    }

    .mel-indicator-reports .ir-header > * { position: relative; z-index: 1; }
    .mel-indicator-reports .ir-eyebrow {
        display: block;
        margin-bottom: .45rem;
        color: #c9edf2;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .13em;
        text-transform: uppercase;
    }
    .mel-indicator-reports .ir-header h1 {
        margin: 0;
        color: #fff;
        font-size: clamp(1.5rem, 2.5vw, 2.2rem);
        font-weight: 850;
        letter-spacing: -.035em;
    }
    .mel-indicator-reports .ir-header p {
        max-width: 760px;
        margin: .55rem 0 0;
        color: #e2f2f5;
        line-height: 1.62;
    }
    .mel-indicator-reports .ir-header-side {
        display: flex;
        min-width: 320px;
        flex-direction: column;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
    }
    .mel-indicator-reports .ir-scope {
        max-width: 470px;
        padding: .65rem .8rem;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: .75rem;
        text-align: right;
        background: rgba(6, 36, 53, .22);
    }
    .mel-indicator-reports .ir-scope span,
    .mel-indicator-reports .ir-scope strong { display: block; }
    .mel-indicator-reports .ir-scope span {
        margin-bottom: .2rem;
        color: #bde3e9;
        font-size: .61rem;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
    }
    .mel-indicator-reports .ir-scope strong { color: #fff; font-size: .72rem; line-height: 1.45; }

    .mel-indicator-reports .ir-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }
    .mel-indicator-reports .ir-btn {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .58rem .82rem;
        border: 1px solid transparent;
        border-radius: .62rem;
        font: inherit;
        font-size: .7rem;
        font-weight: 800;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .mel-indicator-reports .ir-btn:hover { transform: translateY(-1px); text-decoration: none; }
    .mel-indicator-reports .ir-btn-header { border-color: rgba(255,255,255,.35); color: #fff; background: rgba(255,255,255,.12); }
    .mel-indicator-reports .ir-btn-header:hover { color: #fff; background: rgba(255,255,255,.2); }
    .mel-indicator-reports .ir-btn-primary { color: #fff; background: var(--ir-blue); box-shadow: 0 7px 16px rgba(23,107,135,.17); }
    .mel-indicator-reports .ir-btn-primary:hover { color: #fff; background: #125d77; }
    .mel-indicator-reports .ir-btn-secondary { border-color: #cadde3; color: #315565; background: #fff; }
    .mel-indicator-reports .ir-btn-secondary:hover { color: #153b59; background: #f3f8fa; }
    .mel-indicator-reports .ir-btn-small { min-height: 32px; padding: .43rem .65rem; font-size: .65rem; }
    .mel-indicator-reports .ir-btn[aria-disabled="true"] { opacity: .52; pointer-events: none; box-shadow: none; }

    .mel-indicator-reports .ir-guardrail {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .85rem;
        align-items: center;
        margin-bottom: 1rem;
        padding: .9rem 1rem;
        border: 1px solid #cde5dc;
        border-left: 4px solid var(--ir-green);
        border-radius: .85rem;
        background: linear-gradient(90deg, #f0faf6, #fbfdfc);
    }
    .mel-indicator-reports .ir-guardrail-mark {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: var(--ir-green);
        font-size: .62rem;
        font-weight: 900;
        letter-spacing: .06em;
    }
    .mel-indicator-reports .ir-guardrail strong { display: block; color: #164d3c; font-size: .82rem; }
    .mel-indicator-reports .ir-guardrail p { margin: .18rem 0 0; color: #4f7065; font-size: .7rem; line-height: 1.5; }
    .mel-indicator-reports .ir-approved-pill,
    .mel-indicator-reports .ir-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .3rem .56rem;
        border-radius: 999px;
        color: #126246;
        background: #ddf2e9;
        font-size: .61rem;
        font-weight: 850;
        white-space: nowrap;
    }
    .mel-indicator-reports .ir-badge.warning { color: #8a520e; background: #fff0d7; }
    .mel-indicator-reports .ir-badge.neutral { color: #536875; background: #edf2f4; }
    .mel-indicator-reports .ir-badge.danger { color: #91312e; background: #fde8e7; }
    .mel-indicator-reports .ir-alert {
        margin-bottom: 1rem;
        padding: .82rem 1rem;
        border: 1px solid #f0cbc8;
        border-radius: .75rem;
        color: #7f302e;
        background: #fff4f3;
    }

    .mel-indicator-reports .ir-mode-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .mel-indicator-reports .ir-mode-tab {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        padding: .9rem 1rem;
        border: 1px solid var(--ir-line);
        border-radius: .9rem;
        color: var(--ir-ink);
        background: #fff;
        box-shadow: 0 5px 16px rgba(28,61,79,.055);
        text-decoration: none;
    }
    .mel-indicator-reports .ir-mode-tab:hover { color: var(--ir-ink); border-color: #a9cbd5; text-decoration: none; }
    .mel-indicator-reports .ir-mode-tab.active { border-color: #6baabd; box-shadow: inset 0 0 0 1px #6baabd, 0 7px 20px rgba(23,107,135,.11); background: linear-gradient(135deg, #f4fbfc, #fff); }
    .mel-indicator-reports .ir-mode-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: .72rem;
        color: var(--ir-blue);
        background: #e6f3f6;
        font-size: 1rem;
    }
    .mel-indicator-reports .ir-mode-copy strong,
    .mel-indicator-reports .ir-mode-copy small { display: block; }
    .mel-indicator-reports .ir-mode-copy strong { color: var(--ir-navy); font-size: .82rem; }
    .mel-indicator-reports .ir-mode-copy small { margin-top: .18rem; color: var(--ir-muted); font-size: .66rem; line-height: 1.4; }
    .mel-indicator-reports .ir-mode-check { color: var(--ir-green); opacity: 0; }
    .mel-indicator-reports .ir-mode-tab.active .ir-mode-check { opacity: 1; }

    .mel-indicator-reports .ir-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ir-line);
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 7px 22px rgba(27,61,79,.055);
    }
    .mel-indicator-reports .ir-panel + .ir-panel { margin-top: 1rem; }
    .mel-indicator-reports .ir-panel-head {
        display: flex;
        min-height: 66px;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .92rem 1rem;
        border-bottom: 1px solid var(--ir-line);
        background: linear-gradient(90deg, #f7fafb, #fff);
    }
    .mel-indicator-reports summary.ir-panel-head { cursor: pointer; list-style: none; }
    .mel-indicator-reports summary.ir-panel-head::-webkit-details-marker { display: none; }
    .mel-indicator-reports .ir-panel-head h2,
    .mel-indicator-reports .ir-panel-head h3 { margin: 0; color: var(--ir-navy); font-size: .92rem; font-weight: 850; }
    .mel-indicator-reports .ir-panel-head p { margin: .2rem 0 0; color: var(--ir-muted); font-size: .68rem; line-height: 1.45; }
    .mel-indicator-reports .ir-panel-body { padding: 1rem; }
    .mel-indicator-reports .ir-summary-right { display: flex; align-items: center; gap: .55rem; }
    .mel-indicator-reports .ir-chevron { color: #64808c; transition: transform .15s ease; }
    .mel-indicator-reports details[open] > summary .ir-chevron { transform: rotate(180deg); }

    .mel-indicator-reports .ir-filter { margin-bottom: 1rem; }
    .mel-indicator-reports .ir-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .85rem;
    }
    .mel-indicator-reports .ir-field { min-width: 0; }
    .mel-indicator-reports .ir-field-wide { grid-column: span 2; }
    .mel-indicator-reports .ir-field label { display: block; margin-bottom: .3rem; color: #375462; font-size: .65rem; font-weight: 800; }
    .mel-indicator-reports .ir-field label .required { color: var(--ir-red); }
    .mel-indicator-reports .ir-field .form-select,
    .mel-indicator-reports .ir-field .form-control { min-height: 39px; border-color: #cbdce2; border-radius: .58rem; color: #243f4c; font-size: .72rem; }
    .mel-indicator-reports .ir-field small { display: block; margin-top: .25rem; color: #73858e; font-size: .6rem; line-height: 1.35; }
    .mel-indicator-reports .ir-filter-actions {
        display: flex;
        grid-column: 1 / -1;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-top: .15rem;
        padding-top: .85rem;
        border-top: 1px dashed #dbe7ea;
    }
    .mel-indicator-reports .ir-filter-tip { max-width: 760px; margin: 0; color: var(--ir-muted); font-size: .65rem; line-height: 1.45; }

    .mel-indicator-reports .ir-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: .72rem;
        margin: 1rem 0;
    }
    .mel-indicator-reports .ir-metric {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .88rem .85rem;
        border: 1px solid var(--ir-line);
        border-radius: .82rem;
        background: #fff;
        box-shadow: 0 5px 16px rgba(27,61,79,.05);
    }
    .mel-indicator-reports .ir-metric::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--metric, var(--ir-blue)); }
    .mel-indicator-reports .ir-metric-label { display: block; color: var(--ir-muted); font-size: .57rem; font-weight: 850; letter-spacing: .045em; text-transform: uppercase; }
    .mel-indicator-reports .ir-metric strong { display: block; margin-top: .32rem; color: var(--ir-navy); font-size: 1.32rem; font-weight: 870; letter-spacing: -.03em; }
    .mel-indicator-reports .ir-metric small { display: block; margin-top: .22rem; color: #71838c; font-size: .58rem; line-height: 1.35; }

    .mel-indicator-reports .ir-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
    .mel-indicator-reports .ir-grid > .ir-panel + .ir-panel { margin-top: 0; }
    .mel-indicator-reports .ir-chart { min-height: 300px; padding: .4rem .75rem .8rem; }
    .mel-indicator-reports .ir-chart-tall { min-height: 350px; }

    .mel-indicator-reports .ir-dossier {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(300px, .65fr);
        gap: 1rem;
        align-items: stretch;
    }
    .mel-indicator-reports .ir-profile-main { padding: 1.05rem; }
    .mel-indicator-reports .ir-profile-heading { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
    .mel-indicator-reports .ir-code { display: inline-flex; margin-bottom: .42rem; padding: .26rem .48rem; border-radius: .4rem; color: #0e6680; background: #e9f5f7; font-size: .64rem; font-weight: 900; letter-spacing: .035em; }
    .mel-indicator-reports .ir-profile-heading h2 { margin: 0; color: var(--ir-navy); font-size: 1.16rem; font-weight: 860; line-height: 1.35; }
    .mel-indicator-reports .ir-profile-heading p { margin: .32rem 0 0; color: var(--ir-muted); font-size: .69rem; }
    .mel-indicator-reports .ir-status-panel { display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 260px; padding: 1rem; text-align: center; background: linear-gradient(160deg, #f6fafb, #fff); }
    .mel-indicator-reports .ir-status-ring {
        display: grid;
        width: 132px;
        height: 132px;
        place-items: center;
        border: 13px solid #e7eef1;
        border-color: var(--status, var(--ir-blue));
        border-right-color: #e7eef1;
        border-radius: 50%;
        box-shadow: inset 0 0 0 6px #fff;
    }
    .mel-indicator-reports .ir-status-ring strong { color: var(--ir-navy); font-size: 1.34rem; font-weight: 900; }
    .mel-indicator-reports .ir-status-panel h3 { margin: .7rem 0 0; color: var(--ir-navy); font-size: .9rem; }
    .mel-indicator-reports .ir-status-panel p { margin: .22rem 0 0; color: var(--ir-muted); font-size: .65rem; }
    .mel-indicator-reports .ir-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin-top: 1rem; }
    .mel-indicator-reports .ir-fact { min-width: 0; padding: .7rem; border: 1px solid #e0eaed; border-radius: .68rem; background: #fafcfd; }
    .mel-indicator-reports .ir-fact small,
    .mel-indicator-reports .ir-fact strong { display: block; }
    .mel-indicator-reports .ir-fact small { color: var(--ir-muted); font-size: .56rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
    .mel-indicator-reports .ir-fact strong { margin-top: .24rem; color: #284654; font-size: .7rem; line-height: 1.42; overflow-wrap: anywhere; }
    .mel-indicator-reports .ir-definition-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; margin-top: .85rem; }
    .mel-indicator-reports .ir-definition { padding: .78rem; border-left: 3px solid #78b5c2; border-radius: .25rem .65rem .65rem .25rem; background: #f5fafb; }
    .mel-indicator-reports .ir-definition h3 { margin: 0 0 .3rem; color: var(--ir-navy); font-size: .69rem; font-weight: 850; }
    .mel-indicator-reports .ir-definition p { margin: 0; color: #506a76; font-size: .65rem; line-height: 1.55; white-space: pre-line; }

    .mel-indicator-reports .ir-project-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; padding: 1rem; }
    .mel-indicator-reports .ir-project-card { min-width: 0; padding: .8rem; border: 1px solid #dce8eb; border-radius: .75rem; background: #fbfdfd; }
    .mel-indicator-reports .ir-project-card-head { display: flex; justify-content: space-between; gap: .6rem; align-items: flex-start; }
    .mel-indicator-reports .ir-project-card h3 { margin: .15rem 0 0; color: var(--ir-navy); font-size: .75rem; font-weight: 850; line-height: 1.35; }
    .mel-indicator-reports .ir-project-card p { margin: .25rem 0 0; color: var(--ir-muted); font-size: .61rem; }
    .mel-indicator-reports .ir-project-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .42rem; margin-top: .7rem; }
    .mel-indicator-reports .ir-project-stat { padding: .48rem; border-radius: .5rem; background: #eef5f7; }
    .mel-indicator-reports .ir-project-stat span,
    .mel-indicator-reports .ir-project-stat strong { display: block; }
    .mel-indicator-reports .ir-project-stat span { color: #6a7e88; font-size: .52rem; }
    .mel-indicator-reports .ir-project-stat strong { margin-top: .12rem; color: #274a5a; font-size: .72rem; }

    .mel-indicator-reports .ir-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; border-bottom: 1px solid var(--ir-line); background: #fbfcfd; }
    .mel-indicator-reports .ir-toolbar-fields { display: flex; flex: 1; flex-wrap: wrap; gap: .55rem; }
    .mel-indicator-reports .ir-toolbar .form-control { width: min(100%, 360px); min-height: 37px; border-color: #cadce2; border-radius: .56rem; font-size: .7rem; }
    .mel-indicator-reports .ir-toolbar .form-select { width: 210px; min-height: 37px; border-color: #cadce2; border-radius: .56rem; font-size: .7rem; }
    .mel-indicator-reports .ir-row-count { color: var(--ir-muted); font-size: .63rem; font-weight: 750; white-space: nowrap; }

    .mel-indicator-reports .ir-table-wrap { width: 100%; overflow: auto; }
    .mel-indicator-reports .ir-table { width: 100%; min-width: 1240px; border-collapse: separate; border-spacing: 0; font-size: .66rem; }
    .mel-indicator-reports .ir-table th { position: sticky; top: 0; z-index: 1; padding: .65rem .62rem; border-bottom: 1px solid #d4e1e5; color: #506773; background: #f2f6f8; font-size: .55rem; font-weight: 880; letter-spacing: .045em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-indicator-reports .ir-table td { padding: .72rem .62rem; border-bottom: 1px solid #e5edef; color: #344f5c; vertical-align: top; line-height: 1.45; }
    .mel-indicator-reports .ir-table tbody tr:hover > td { background: #fbfdfd; }
    .mel-indicator-reports .ir-table .ir-title { display: block; max-width: 290px; color: #203f4e; font-weight: 830; line-height: 1.4; }
    .mel-indicator-reports .ir-meta { display: block; margin-top: .18rem; color: #73858e; font-size: .58rem; line-height: 1.42; }
    .mel-indicator-reports .ir-value { display: block; color: var(--ir-navy); font-size: .76rem; font-weight: 850; }
    .mel-indicator-reports .ir-progress { width: 100%; min-width: 86px; height: 5px; overflow: hidden; margin-top: .35rem; border-radius: 999px; background: #e8eef1; }
    .mel-indicator-reports .ir-progress > span { display: block; height: 100%; border-radius: inherit; background: var(--progress, var(--ir-blue)); }
    .mel-indicator-reports .ir-detail-row > td { padding: 0; background: #f8fbfc; }
    .mel-indicator-reports .ir-row-detail { border: 0; }
    .mel-indicator-reports .ir-row-detail > summary { display: inline-flex; align-items: center; gap: .3rem; color: var(--ir-blue); font-size: .61rem; font-weight: 850; cursor: pointer; list-style: none; }
    .mel-indicator-reports .ir-row-detail > summary::-webkit-details-marker { display: none; }
    .mel-indicator-reports .ir-detail-body { padding: .9rem 1rem 1rem; border-top: 1px dashed #dbe7ea; }
    .mel-indicator-reports .ir-detail-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .55rem; margin-bottom: .8rem; }
    .mel-indicator-reports .ir-detail-fact { padding: .6rem; border: 1px solid #dfe9ec; border-radius: .58rem; background: #fff; }
    .mel-indicator-reports .ir-detail-fact small,
    .mel-indicator-reports .ir-detail-fact strong { display: block; }
    .mel-indicator-reports .ir-detail-fact small { color: var(--ir-muted); font-size: .53rem; text-transform: uppercase; }
    .mel-indicator-reports .ir-detail-fact strong { margin-top: .16rem; color: #294a59; font-size: .63rem; overflow-wrap: anywhere; }
    .mel-indicator-reports .ir-source-table { width: 100%; min-width: 960px; border-collapse: collapse; font-size: .61rem; }
    .mel-indicator-reports .ir-source-table th { padding: .55rem; border: 1px solid #dce7ea; color: #526a75; background: #edf4f6; font-size: .52rem; text-transform: uppercase; }
    .mel-indicator-reports .ir-source-table td { padding: .58rem; border: 1px solid #e0e9ec; vertical-align: top; background: #fff; }
    .mel-indicator-reports .ir-link-list { display: grid; gap: .35rem; }
    .mel-indicator-reports .ir-evidence-item { display: flex; justify-content: space-between; gap: .65rem; padding: .48rem .55rem; border: 1px solid #e0e9ec; border-radius: .5rem; background: #fff; }
    .mel-indicator-reports .ir-evidence-item strong { display: block; color: #2b4b5a; font-size: .62rem; }
    .mel-indicator-reports .ir-evidence-item small { display: block; margin-top: .12rem; color: var(--ir-muted); font-size: .55rem; }
    .mel-indicator-reports .ir-scroll-tip { display: flex; justify-content: space-between; gap: 1rem; padding: .58rem 1rem; border-top: 1px solid var(--ir-line); color: #71858f; background: #fbfcfd; font-size: .58rem; }
    .mel-indicator-reports .ir-table-filter-empty { display: none; }

    .mel-indicator-reports .ir-quality-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .65rem; }
    .mel-indicator-reports .ir-quality { padding: .72rem; border: 1px solid #dfe9ec; border-radius: .65rem; background: #fbfdfd; }
    .mel-indicator-reports .ir-quality span { display: block; min-height: 2.5em; color: var(--ir-muted); font-size: .57rem; line-height: 1.3; }
    .mel-indicator-reports .ir-quality strong { display: block; margin-top: .28rem; color: var(--ir-navy); font-size: 1.05rem; }
    .mel-indicator-reports .ir-method-list { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; }
    .mel-indicator-reports .ir-method { padding: .75rem; border-left: 3px solid #72aebb; border-radius: .3rem .65rem .65rem .3rem; background: #f5f9fa; }
    .mel-indicator-reports .ir-method strong { display: block; color: var(--ir-navy); font-size: .66rem; }
    .mel-indicator-reports .ir-method p { margin: .25rem 0 0; color: var(--ir-muted); font-size: .6rem; line-height: 1.5; }

    .mel-indicator-reports .ir-empty { display: flex; min-height: 220px; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; text-align: center; }
    .mel-indicator-reports .ir-empty-mark { display: grid; width: 54px; height: 54px; place-items: center; margin-bottom: .65rem; border-radius: 50%; color: var(--ir-blue); background: #e7f3f6; font-size: .72rem; font-weight: 900; }
    .mel-indicator-reports .ir-empty strong { color: var(--ir-navy); font-size: .9rem; }
    .mel-indicator-reports .ir-empty p { max-width: 610px; margin: .35rem auto .75rem; color: var(--ir-muted); font-size: .68rem; line-height: 1.55; }

    @media (max-width: 1400px) {
        .mel-indicator-reports .ir-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-quality-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-project-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 1100px) {
        .mel-indicator-reports .ir-header { flex-direction: column; }
        .mel-indicator-reports .ir-header-side { min-width: 0; align-items: flex-start; }
        .mel-indicator-reports .ir-scope { text-align: left; }
        .mel-indicator-reports .ir-actions { justify-content: flex-start; }
        .mel-indicator-reports .ir-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-dossier { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-method-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 820px) {
        .mel-indicator-reports .ir-mode-tabs,
        .mel-indicator-reports .ir-grid { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-grid > .ir-panel + .ir-panel { margin-top: 0; }
        .mel-indicator-reports .ir-project-grid { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-definition-grid { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-detail-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-toolbar { align-items: flex-start; flex-direction: column; }
        .mel-indicator-reports .ir-row-count { white-space: normal; }
    }
    @media (max-width: 600px) {
        .mel-indicator-reports .ir-header { padding: 1.25rem 1rem; border-radius: .85rem; }
        .mel-indicator-reports .ir-header-side { width: 100%; }
        .mel-indicator-reports .ir-scope { width: 100%; }
        .mel-indicator-reports .ir-actions { width: 100%; }
        .mel-indicator-reports .ir-actions .ir-btn { flex: 1 1 calc(50% - .3rem); }
        .mel-indicator-reports .ir-guardrail { grid-template-columns: auto minmax(0, 1fr); }
        .mel-indicator-reports .ir-approved-pill { display: none; }
        .mel-indicator-reports .ir-filter-grid { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-field-wide { grid-column: span 1; }
        .mel-indicator-reports .ir-filter-actions { align-items: stretch; flex-direction: column; }
        .mel-indicator-reports .ir-filter-actions .ir-actions { justify-content: stretch; }
        .mel-indicator-reports .ir-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mel-indicator-reports .ir-quality-grid,
        .mel-indicator-reports .ir-method-list,
        .mel-indicator-reports .ir-facts,
        .mel-indicator-reports .ir-detail-facts { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-panel-head { align-items: flex-start; flex-direction: column; }
        .mel-indicator-reports .ir-project-stats { grid-template-columns: 1fr; }
        .mel-indicator-reports .ir-scroll-tip { flex-direction: column; }
    }

    @media print {
        body { background: #fff !important; }
        .mel-indicator-reports { color: #111; font-size: 9pt; }
        .mel-indicator-reports .ir-no-print,
        .mel-indicator-reports .ir-mode-tabs,
        .mel-indicator-reports .ir-filter,
        .mel-indicator-reports .ir-toolbar,
        .mel-indicator-reports .ir-scroll-tip { display: none !important; }
        .mel-indicator-reports .ir-header { padding: 14px 16px; border: 1px solid #315b6b; border-radius: 0; color: #153b59; background: #eef5f7 !important; box-shadow: none; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .mel-indicator-reports .ir-header h1,
        .mel-indicator-reports .ir-header p,
        .mel-indicator-reports .ir-scope strong { color: #153b59; }
        .mel-indicator-reports .ir-eyebrow,
        .mel-indicator-reports .ir-scope span { color: #486b79; }
        .mel-indicator-reports .ir-header-side { min-width: 280px; }
        .mel-indicator-reports .ir-scope { border-color: #abc3cc; background: transparent; }
        .mel-indicator-reports .ir-panel,
        .mel-indicator-reports .ir-metric { break-inside: avoid; box-shadow: none; }
        .mel-indicator-reports .ir-panel { overflow: visible; }
        .mel-indicator-reports .ir-table-wrap { overflow: visible; }
        .mel-indicator-reports .ir-table { min-width: 0; font-size: 6.5pt; }
        .mel-indicator-reports .ir-table th { position: static; font-size: 5.8pt; }
        .mel-indicator-reports .ir-table th,
        .mel-indicator-reports .ir-table td { padding: 4px; }
        .mel-indicator-reports .ir-detail-row { display: table-row !important; }
        .mel-indicator-reports .ir-row-detail > summary { display: none; }
        .mel-indicator-reports .ir-row-detail > .ir-detail-body { display: block !important; }
        .mel-indicator-reports .ir-chart { min-height: 220px; }
        .mel-indicator-reports .ir-grid,
        .mel-indicator-reports .ir-dossier { break-inside: avoid; }
        .mel-indicator-reports a { color: inherit; text-decoration: none; }
    }
</style>

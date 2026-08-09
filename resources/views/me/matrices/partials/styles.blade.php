<style>
    .mel-matrices {
        --mx-primary: #075c7a;
        --mx-primary-dark: #05465d;
        --mx-primary-soft: #eaf3f6;
        --mx-ink: #17343e;
        --mx-muted: #667b83;
        --mx-line: #dbe5e8;
        --mx-canvas: #f4f7f8;
        --mx-white: #fff;
        --mx-success: #187459;
        --mx-warning: #9a6518;
        --mx-danger: #a83e3d;
        color: var(--mx-ink);
        font-family: Inter, "Segoe UI", Arial, sans-serif;
        font-size: 13px;
        line-height: 1.5;
        padding: 0 0 2rem;
    }
    .mel-matrices *, .mel-matrices *::before, .mel-matrices *::after { box-sizing: border-box; }
    .mel-matrices h1, .mel-matrices h2, .mel-matrices h3, .mel-matrices p { margin-top: 0; }
    .mel-matrices a { color: inherit; text-decoration: none; }
    .mel-matrices .mx-header {
        display: flex; justify-content: space-between; gap: 2rem; padding: 1.55rem 1.7rem;
        border-radius: 17px; background: linear-gradient(125deg, #05465d 0%, #075c7a 60%, #176f88 100%);
        box-shadow: 0 14px 30px rgba(5,70,93,.16); color: #fff;
    }
    .mel-matrices .mx-header-copy { max-width: 760px; }
    .mel-matrices .mx-eyebrow, .mel-matrices .mx-eyebrow-dark {
        display: block; margin-bottom: .38rem; font-size: .66rem; font-weight: 800; letter-spacing: .11em; text-transform: uppercase;
    }
    .mel-matrices .mx-eyebrow { color: #bfe3eb; }
    .mel-matrices .mx-eyebrow-dark { color: var(--mx-primary); }
    .mel-matrices .mx-header h1 { margin-bottom: .45rem; color: #fff; font-size: clamp(1.5rem, 2.5vw, 2.15rem); font-weight: 780; letter-spacing: -.025em; }
    .mel-matrices .mx-header p { max-width: 720px; margin-bottom: 0; color: #d9edf2; font-size: .87rem; }
    .mel-matrices .mx-header-side { display: flex; min-width: 330px; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 1.3rem; }
    .mel-matrices .mx-generated { color: #c7e1e7; font-size: .68rem; font-weight: 650; }
    .mel-matrices .mx-actions { display: flex; align-items: center; flex-wrap: wrap; gap: .55rem; }
    .mel-matrices .mx-btn {
        display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: .35rem; padding: .55rem .82rem;
        border: 1px solid transparent; border-radius: 9px; background: transparent; font: inherit; font-size: .72rem; font-weight: 760;
        line-height: 1.1; cursor: pointer; transition: border-color .16s ease, background .16s ease, color .16s ease, transform .16s ease;
    }
    .mel-matrices .mx-btn:hover { transform: translateY(-1px); }
    .mel-matrices .mx-btn-header { border-color: rgba(255,255,255,.28); color: #fff; }
    .mel-matrices .mx-btn-header:hover { border-color: rgba(255,255,255,.55); background: rgba(255,255,255,.1); }
    .mel-matrices .mx-btn-solid { border-color: #fff; background: #fff; color: var(--mx-primary-dark); }
    .mel-matrices .mx-btn-primary { background: var(--mx-primary); color: #fff; }
    .mel-matrices .mx-btn-primary:hover { background: var(--mx-primary-dark); color: #fff; }
    .mel-matrices .mx-btn-secondary { border-color: var(--mx-line); background: #fff; color: #33515a; }
    .mel-matrices .mx-btn-secondary:hover { border-color: #a9c0c7; background: #f7fafb; }
    .mel-matrices .mx-btn-success { background: var(--mx-success); color: #fff; }
    .mel-matrices .mx-btn-danger { border-color: #e6b8b7; background: #fff4f3; color: var(--mx-danger); }
    .mel-matrices .mx-btn-small { min-height: 33px; padding: .43rem .66rem; font-size: .68rem; }
    .mel-matrices .mx-alert { display: flex; gap: .8rem; margin-top: 1rem; padding: .9rem 1rem; border: 1px solid; border-radius: 11px; }
    .mel-matrices .mx-alert > span { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 50%; font-size: .62rem; font-weight: 850; }
    .mel-matrices .mx-alert strong { display: block; margin-bottom: .12rem; font-size: .76rem; }
    .mel-matrices .mx-alert p, .mel-matrices .mx-alert ul { margin-bottom: 0; font-size: .72rem; }
    .mel-matrices .mx-alert.success { border-color: #b9dece; background: #edf8f3; color: #176348; }
    .mel-matrices .mx-alert.success > span { background: #d4eee3; }
    .mel-matrices .mx-alert.danger { border-color: #ecc2c0; background: #fff4f3; color: #8f3736; }
    .mel-matrices .mx-alert.danger > span { background: #f4d9d7; }
    .mel-matrices .mx-governance { display: flex; align-items: flex-start; gap: .9rem; margin-top: 1rem; padding: .85rem 1rem; border: 1px solid #c8dde3; border-left: 4px solid var(--mx-primary); border-radius: 11px; background: #eef5f7; }
    .mel-matrices .mx-governance-mark { display: grid; width: 43px; height: 43px; flex: 0 0 43px; place-items: center; border-radius: 10px; background: var(--mx-primary); color: #fff; font-size: .55rem; font-weight: 850; letter-spacing: .05em; }
    .mel-matrices .mx-governance strong { display: block; margin-bottom: .15rem; font-size: .77rem; }
    .mel-matrices .mx-governance p { margin-bottom: 0; color: #56717a; font-size: .7rem; }
    .mel-matrices .mx-metrics { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: .8rem; margin-top: 1rem; }
    .mel-matrices .mx-metric { position: relative; min-height: 115px; overflow: hidden; padding: .9rem .95rem; border: 1px solid var(--mx-line); border-radius: 12px; background: #fff; box-shadow: 0 4px 14px rgba(27,61,72,.045); }
    .mel-matrices .mx-metric::before { position: absolute; top: 0; right: 0; left: 0; height: 3px; background: var(--metric); content: ""; }
    .mel-matrices .mx-metric:hover { border-color: color-mix(in srgb,var(--metric) 45%,#fff); }
    .mel-matrices .mx-metric span { display: block; color: #71848a; font-size: .61rem; font-weight: 790; letter-spacing: .05em; text-transform: uppercase; }
    .mel-matrices .mx-metric strong { display: block; margin: .26rem 0 .18rem; color: var(--metric); font-size: 1.45rem; font-weight: 820; letter-spacing: -.03em; }
    .mel-matrices .mx-metric small { display: block; color: var(--mx-muted); font-size: .62rem; line-height: 1.35; }
    .mel-matrices .mx-panel { margin-top: 1rem; overflow: hidden; border: 1px solid var(--mx-line); border-radius: 13px; background: #fff; box-shadow: 0 5px 18px rgba(27,61,72,.04); }
    .mel-matrices .mx-panel-head { display: flex; min-height: 66px; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1rem; border-bottom: 1px solid var(--mx-line); background: #fff; }
    .mel-matrices summary.mx-panel-head { cursor: pointer; list-style: none; }
    .mel-matrices summary.mx-panel-head::-webkit-details-marker { display: none; }
    .mel-matrices .mx-panel-head h2 { margin-bottom: .14rem; color: var(--mx-ink); font-size: .88rem; font-weight: 780; }
    .mel-matrices .mx-panel-head p { margin-bottom: 0; color: var(--mx-muted); font-size: .65rem; }
    .mel-matrices .mx-summary-right { display: flex; align-items: center; gap: .6rem; }
    .mel-matrices .mx-chevron { color: var(--mx-primary); font-size: 1.1rem; transition: transform .18s ease; }
    .mel-matrices details[open] > summary .mx-chevron { transform: rotate(180deg); }
    .mel-matrices .mx-badge { display: inline-flex; min-height: 26px; align-items: center; padding: .25rem .48rem; border: 1px solid #d6e4e8; border-radius: 999px; background: #f5f9fa; color: #537079; font-size: .58rem; font-weight: 780; white-space: nowrap; }
    .mel-matrices .mx-panel-body { padding: 1rem; background: #fbfcfc; }
    .mel-matrices .mx-filter-grid, .mel-matrices .mx-form-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .8rem; }
    .mel-matrices .mx-field { min-width: 0; }
    .mel-matrices .mx-field-wide { grid-column: span 2; }
    .mel-matrices .mx-field-full { grid-column: 1/-1; }
    .mel-matrices .mx-field label { display: block; margin-bottom: .3rem; color: #334f58; font-size: .64rem; font-weight: 760; }
    .mel-matrices .mx-field small { display: block; margin-top: .28rem; color: #788a90; font-size: .58rem; line-height: 1.35; }
    .mel-matrices .form-control, .mel-matrices .form-select { min-height: 39px; border-color: #cfdbdf; border-radius: 8px; color: #294850; font-size: .71rem; }
    .mel-matrices textarea.form-control { min-height: 82px; resize: vertical; }
    .mel-matrices .form-control:focus, .mel-matrices .form-select:focus { border-color: #5d9aac; box-shadow: 0 0 0 3px rgba(7,92,122,.1); }
    .mel-matrices .mx-filter-actions { display: flex; grid-column: 1/-1; align-items: center; justify-content: space-between; gap: 1rem; padding-top: .75rem; border-top: 1px solid var(--mx-line); }
    .mel-matrices .mx-filter-actions p { margin-bottom: 0; color: var(--mx-muted); font-size: .64rem; }
    .mel-matrices .mx-analytics { display: grid; grid-template-columns: minmax(0,.85fr) minmax(0,1.35fr) minmax(0,1fr); gap: 1rem; }
    .mel-matrices .mx-chart-panel { min-width: 0; }
    .mel-matrices .mx-chart { min-height: 285px; padding: .35rem .5rem .1rem; }
    .mel-matrices .mx-chart-tall { overflow-y: auto; }
    .mel-matrices .mx-chart-unavailable { display: flex; min-height: 270px; align-items: center; justify-content: center; flex-direction: column; color: var(--mx-muted); text-align: center; }
    .mel-matrices .mx-chart-unavailable strong { color: var(--mx-ink); }
    .mel-matrices .mx-chart-unavailable span { font-size: .63rem; }
    .mel-matrices .mx-legend { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .4rem; padding: 0 .9rem .9rem; }
    .mel-matrices .mx-legend a { display: grid; grid-template-columns: 8px 1fr auto; align-items: center; gap: .42rem; padding: .4rem .5rem; border-radius: 7px; background: #f6f9fa; color: #557079; font-size: .61rem; }
    .mel-matrices .mx-legend a:hover { background: #edf4f6; }
    .mel-matrices .mx-legend i { width: 7px; height: 7px; border-radius: 50%; background: var(--legend); }
    .mel-matrices .mx-legend strong { color: var(--mx-ink); }
    .mel-matrices .mx-table-wrap { max-width: 100%; overflow: auto; scrollbar-color: #9bb9c2 #edf3f5; }
    .mel-matrices .mx-table { width: 100%; min-width: 1320px; border-collapse: separate; border-spacing: 0; }
    .mel-matrices .mx-table th { position: sticky; top: 0; z-index: 2; padding: .68rem .72rem; border-bottom: 1px solid #ccdadd; background: #edf4f6; color: #49656e; font-size: .57rem; font-weight: 820; letter-spacing: .035em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .mel-matrices .mx-table td { padding: .72rem; border-bottom: 1px solid #e7edef; color: #405c65; font-size: .65rem; vertical-align: top; }
    .mel-matrices .mx-table tbody tr { cursor: pointer; transition: background .14s ease; }
    .mel-matrices .mx-table tbody tr:hover { background: #f7fafb; }
    .mel-matrices .mx-table tbody tr.selected { background: #eef6f8; box-shadow: inset 3px 0 var(--mx-primary); }
    .mel-matrices .mx-document-cell { display: flex; min-width: 310px; gap: .65rem; }
    .mel-matrices .mx-document-cell > span { display: grid; width: 39px; height: 39px; flex: 0 0 39px; place-items: center; border-radius: 9px; background: var(--mx-primary-soft); color: var(--mx-primary); font-size: .54rem; font-weight: 840; }
    .mel-matrices .mx-document-cell a { display: block; color: #173f4c; font-size: .72rem; font-weight: 780; }
    .mel-matrices .mx-document-cell a:hover { color: var(--mx-primary); }
    .mel-matrices .mx-document-cell small, .mel-matrices .mx-cell-note { display: block; margin-top: .12rem; color: #7a8c92; font-size: .57rem; }
    .mel-matrices .mx-document-cell p { max-width: 430px; margin: .28rem 0 0; color: #62777e; font-size: .59rem; line-height: 1.35; }
    .mel-matrices .mx-cell-strong { display: block; min-width: 125px; color: #294850; font-size: .65rem; }
    .mel-matrices .mx-status { display: inline-flex; width: max-content; min-height: 24px; align-items: center; margin-top: .28rem; padding: .2rem .46rem; border: 1px solid; border-radius: 999px; font-size: .55rem; font-weight: 800; white-space: nowrap; }
    .mel-matrices .mx-status.success { border-color: #b8decf; background: #edf8f3; color: #176348; }
    .mel-matrices .mx-status.warning { border-color: #ead4a8; background: #fff8e8; color: #815613; }
    .mel-matrices .mx-status.neutral { border-color: #d5dfe2; background: #f3f6f7; color: #61757c; }
    .mel-matrices .mx-inspection-cell { display: grid; min-width: 185px; grid-template-columns: repeat(3,1fr); gap: .35rem; }
    .mel-matrices .mx-inspection-cell span { padding: .35rem; border-radius: 6px; background: #f5f8f9; color: #71848a; font-size: .53rem; text-align: center; }
    .mel-matrices .mx-inspection-cell strong { display: block; color: #294850; font-size: .66rem; }
    .mel-matrices .mx-source { display: inline-flex; padding: .24rem .42rem; border-radius: 6px; background: #edf8f3; color: #176348; font-size: .56rem; font-weight: 770; white-space: nowrap; }
    .mel-matrices .mx-source.danger { background: #fff1f0; color: var(--mx-danger); }
    .mel-matrices .mx-row-actions { display: flex; min-width: 145px; flex-wrap: wrap; gap: .3rem; }
    .mel-matrices .mx-row-actions a { padding: .3rem .42rem; border: 1px solid var(--mx-line); border-radius: 6px; background: #fff; color: var(--mx-primary); font-size: .56rem; font-weight: 760; }
    .mel-matrices .mx-row-actions a:hover { border-color: #9ebbc4; background: var(--mx-primary-soft); }
    .mel-matrices .mx-register-footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .66rem .9rem; border-top: 1px solid var(--mx-line); background: #fafcfc; color: #72858b; font-size: .59rem; }
    .mel-matrices .mx-pagination { padding: .75rem .9rem; border-top: 1px solid var(--mx-line); }
    .mel-matrices .mx-detail { padding-bottom: 1rem; }
    .mel-matrices .mx-detail-head { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .8rem; padding: 1rem; border-bottom: 1px solid var(--mx-line); }
    .mel-matrices .mx-detail-mark { display: grid; width: 51px; height: 51px; place-items: center; border-radius: 12px; background: var(--mx-primary); color: #fff; font-size: .61rem; font-weight: 850; }
    .mel-matrices .mx-detail-head h2 { margin-bottom: .15rem; color: var(--mx-ink); font-size: 1rem; font-weight: 790; }
    .mel-matrices .mx-detail-head p { margin-bottom: 0; color: var(--mx-muted); font-size: .64rem; }
    .mel-matrices .mx-detail-actions { display: flex; flex-wrap: wrap; gap: .5rem; padding: .75rem 1rem; border-bottom: 1px solid var(--mx-line); background: #fafcfc; }
    .mel-matrices .mx-inline-alert { margin: .9rem 1rem 0; padding: .75rem .85rem; border: 1px solid; border-radius: 9px; }
    .mel-matrices .mx-inline-alert strong { display: block; font-size: .7rem; }
    .mel-matrices .mx-inline-alert p { margin: .12rem 0 0; font-size: .62rem; }
    .mel-matrices .mx-inline-alert.danger { border-color: #e9c1bf; background: #fff4f3; color: #8e3937; }
    .mel-matrices .mx-detail-grid { display: grid; grid-template-columns: minmax(0,.85fr) minmax(0,1.15fr); gap: 1rem; padding: 1rem; }
    .mel-matrices .mx-detail-grid > section { min-width: 0; padding: .9rem; border: 1px solid var(--mx-line); border-radius: 10px; background: #fcfdfd; }
    .mel-matrices .mx-detail-grid h3, .mel-matrices .mx-section-head h3 { margin-bottom: .5rem; color: #294850; font-size: .75rem; font-weight: 790; }
    .mel-matrices .mx-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
    .mel-matrices .mx-section-head h3 { margin-bottom: .1rem; }
    .mel-matrices .mx-section-head p { margin-bottom: 0; color: var(--mx-muted); font-size: .59rem; }
    .mel-matrices .mx-metadata { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); margin: 0; border: 1px solid var(--mx-line); border-radius: 8px; overflow: hidden; }
    .mel-matrices .mx-metadata div { min-width: 0; padding: .55rem .6rem; border-right: 1px solid var(--mx-line); border-bottom: 1px solid var(--mx-line); }
    .mel-matrices .mx-metadata div:nth-child(2n) { border-right: 0; }
    .mel-matrices .mx-metadata dt { color: #788a90; font-size: .52rem; font-weight: 790; letter-spacing: .035em; text-transform: uppercase; }
    .mel-matrices .mx-metadata dd { margin: .12rem 0 0; color: #294850; font-size: .63rem; font-weight: 680; overflow-wrap: anywhere; }
    .mel-matrices .mx-change-summary { margin-top: .7rem; padding: .7rem; border-left: 3px solid var(--mx-primary); border-radius: 0 7px 7px 0; background: #eef5f7; }
    .mel-matrices .mx-change-summary strong { display: block; font-size: .65rem; }
    .mel-matrices .mx-change-summary p { margin: .18rem 0 0; color: #5d747c; font-size: .61rem; white-space: pre-line; }
    .mel-matrices .mx-inspection-metrics { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .45rem; margin-top: .65rem; }
    .mel-matrices .mx-inspection-metrics span { padding: .6rem .4rem; border: 1px solid var(--mx-line); border-radius: 7px; background: #fff; color: #70838a; font-size: .53rem; text-align: center; }
    .mel-matrices .mx-inspection-metrics strong { display: block; margin-bottom: .1rem; color: var(--mx-primary); font-size: .82rem; }
    .mel-matrices .mx-sheet-list { display: grid; gap: .45rem; max-height: 350px; margin-top: .65rem; overflow: auto; }
    .mel-matrices .mx-sheet-list article { position: relative; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .55rem; padding: .55rem .6rem; border: 1px solid var(--mx-line); border-radius: 8px; background: #fff; }
    .mel-matrices .mx-sheet-list article > span { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 7px; background: var(--mx-primary-soft); color: var(--mx-primary); font-size: .55rem; font-weight: 820; }
    .mel-matrices .mx-sheet-list article strong { display: block; color: #294850; font-size: .64rem; }
    .mel-matrices .mx-sheet-list article p, .mel-matrices .mx-sheet-list article small { margin: .08rem 0 0; color: #73868c; font-size: .54rem; }
    .mel-matrices .mx-sheet-list article small { text-align: right; }
    .mel-matrices .mx-sheet-list article em { grid-column: 2/-1; color: var(--mx-warning); font-size: .52rem; font-style: normal; }
    .mel-matrices .mx-lifecycle-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 0 1rem; padding: .85rem; border: 1px solid #c9dce2; border-radius: 10px; background: #eef5f7; }
    .mel-matrices .mx-lifecycle-actions strong { display: block; font-size: .7rem; }
    .mel-matrices .mx-lifecycle-actions p { margin: .12rem 0 0; color: #60767e; font-size: .59rem; }
    .mel-matrices .mx-audit-lock { color: #61767d; font-size: .6rem; font-weight: 720; }
    .mel-matrices .mx-empty { display: flex; min-height: 240px; align-items: center; justify-content: center; flex-direction: column; padding: 1.5rem; color: var(--mx-muted); text-align: center; }
    .mel-matrices .mx-empty > span { display: grid; width: 47px; height: 47px; margin-bottom: .7rem; place-items: center; border-radius: 12px; background: var(--mx-primary-soft); color: var(--mx-primary); font-size: .6rem; font-weight: 850; }
    .mel-matrices .mx-empty strong { color: var(--mx-ink); font-size: .75rem; }
    .mel-matrices .mx-empty p { max-width: 430px; margin: .2rem 0 .7rem; font-size: .62rem; }
    .mel-matrices .mx-empty-compact { min-height: 130px; padding: .8rem; }
    .mel-matrices .mx-modal { position: fixed; inset: 0; z-index: 1080; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .mel-matrices .mx-modal:target, .mel-matrices .mx-modal.is-open { display: flex; }
    .mel-matrices .mx-modal-backdrop { position: absolute; inset: 0; background: rgba(14,36,44,.64); backdrop-filter: blur(2px); }
    .mel-matrices .mx-modal-card { position: relative; z-index: 1; width: min(920px,100%); max-height: calc(100vh - 2rem); overflow: auto; border-radius: 14px; background: #fff; box-shadow: 0 24px 70px rgba(3,35,47,.28); }
    .mel-matrices .mx-modal-card > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; border-bottom: 1px solid var(--mx-line); background: #f7fafb; }
    .mel-matrices .mx-modal-card > header span { display: block; margin-bottom: .2rem; color: var(--mx-primary); font-size: .57rem; font-weight: 820; letter-spacing: .07em; text-transform: uppercase; }
    .mel-matrices .mx-modal-card > header h2 { margin-bottom: .18rem; color: var(--mx-ink); font-size: 1rem; font-weight: 790; }
    .mel-matrices .mx-modal-card > header p { max-width: 680px; margin-bottom: 0; color: var(--mx-muted); font-size: .62rem; }
    .mel-matrices .mx-modal-close { display: grid; width: 31px; height: 31px; flex: 0 0 31px; place-items: center; border: 1px solid var(--mx-line); border-radius: 8px; background: #fff; color: #536d76; font-size: 1rem; }
    .mel-matrices .mx-modal-body { padding: 1rem 1.1rem; }
    .mel-matrices .mx-modal-card footer { display: flex; align-items: center; justify-content: flex-end; gap: .55rem; padding: .8rem 1.1rem; border-top: 1px solid var(--mx-line); background: #fafcfc; }
    .mel-matrices .mx-file-drop { display: flex !important; min-height: 94px; align-items: center; justify-content: center; flex-direction: column; padding: .75rem; border: 1px dashed #94b6c0; border-radius: 9px; background: #f4f9fa; color: #4d6d76 !important; cursor: pointer; text-align: center; }
    .mel-matrices .mx-file-drop:hover { border-color: var(--mx-primary); background: #edf6f8; }
    .mel-matrices .mx-file-drop > span { color: var(--mx-primary); font-size: 1.2rem; }
    .mel-matrices .mx-file-drop strong { max-width: 100%; color: #294850; font-size: .68rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mel-matrices .mx-file-feedback { min-height: 1rem; margin: .25rem 0 0; font-size: .58rem; }
    .mel-matrices .mx-file-feedback.success { color: var(--mx-success); }
    .mel-matrices .mx-file-feedback.danger { color: var(--mx-danger); }
    .mel-matrices .mx-visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; }
    body.mx-modal-open { overflow: hidden; }
    @media (max-width: 1399px) {
        .mel-matrices .mx-metrics { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .mel-matrices .mx-analytics { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-matrices .mx-chart-panel-wide { grid-column: span 1; }
        .mel-matrices .mx-analytics .mx-chart-panel:last-child { grid-column: 1/-1; }
    }
    @media (max-width: 991px) {
        .mel-matrices .mx-header { flex-direction: column; }
        .mel-matrices .mx-header-side { min-width: 0; align-items: flex-start; }
        .mel-matrices .mx-filter-grid, .mel-matrices .mx-form-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .mel-matrices .mx-detail-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
        .mel-matrices .mx-metrics, .mel-matrices .mx-analytics { grid-template-columns: 1fr; }
        .mel-matrices .mx-analytics .mx-chart-panel:last-child { grid-column: auto; }
        .mel-matrices .mx-metric { min-height: 96px; }
        .mel-matrices .mx-panel-head, .mel-matrices .mx-filter-actions, .mel-matrices .mx-register-footer, .mel-matrices .mx-lifecycle-actions { align-items: flex-start; flex-direction: column; }
        .mel-matrices .mx-filter-grid, .mel-matrices .mx-form-grid { grid-template-columns: 1fr; }
        .mel-matrices .mx-field-wide, .mel-matrices .mx-field-full { grid-column: auto; }
        .mel-matrices .mx-detail-head { grid-template-columns: auto 1fr; }
        .mel-matrices .mx-detail-head > .mx-status { grid-column: 2; }
        .mel-matrices .mx-inspection-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }
    }
    @media (max-width: 520px) {
        .mel-matrices .mx-header { padding: 1.1rem; border-radius: 13px; }
        .mel-matrices .mx-header-side, .mel-matrices .mx-actions, .mel-matrices .mx-btn { width: 100%; }
        .mel-matrices .mx-metrics { grid-template-columns: 1fr; }
        .mel-matrices .mx-metadata { grid-template-columns: 1fr; }
        .mel-matrices .mx-metadata div { border-right: 0; }
        .mel-matrices .mx-summary-right .mx-badge { display: none; }
        .mel-matrices .mx-sheet-list article { grid-template-columns: auto 1fr; }
        .mel-matrices .mx-sheet-list article small { grid-column: 2; text-align: left; }
    }
    @media (prefers-reduced-motion: reduce) {
        .mel-matrices *, .mel-matrices *::before, .mel-matrices *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>

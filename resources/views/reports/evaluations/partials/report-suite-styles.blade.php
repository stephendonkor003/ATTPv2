<style>
    .evr-shell {
        --evr-ink: #142333;
        --evr-muted: #697a8c;
        --evr-line: #dfe7ee;
        --evr-soft: #f5f8fa;
        --evr-teal: #0f766e;
        --evr-blue: #1769aa;
        --evr-amber: #b66809;
        --evr-violet: #6941c6;
        color: var(--evr-ink);
        margin: 0 auto;
        max-width: 1540px;
        padding-bottom: 34px;
    }

    .evr-shell *, .evr-shell *::before, .evr-shell *::after { box-sizing: border-box; }
    .evr-shell a { text-decoration: none; }
    .evr-shell button, .evr-shell input, .evr-shell select { font: inherit; }

    .evr-hero {
        align-items: flex-end;
        background:
            radial-gradient(circle at 90% 15%, rgba(51, 211, 183, .22), transparent 30%),
            linear-gradient(128deg, #102a3b 0%, #124c58 52%, #0f766e 100%);
        border-radius: 20px;
        box-shadow: 0 18px 40px rgba(16, 42, 59, .18);
        color: #fff;
        display: flex;
        gap: 26px;
        justify-content: space-between;
        overflow: hidden;
        padding: 30px 32px;
        position: relative;
    }

    .evr-hero::after {
        border: 1px solid rgba(255, 255, 255, .11);
        border-radius: 50%;
        content: "";
        height: 220px;
        position: absolute;
        right: -65px;
        top: -105px;
        width: 220px;
    }

    .evr-hero__copy, .evr-hero__actions { position: relative; z-index: 1; }
    .evr-hero__copy { max-width: 790px; }
    .evr-eyebrow {
        color: #0f766e;
        display: block;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .evr-hero .evr-eyebrow { color: #8ff0dc; }
    .evr-hero h1 { color: #fff; font-size: clamp(25px, 3vw, 38px); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; margin: 8px 0; }
    .evr-hero p { color: rgba(255,255,255,.78); font-size: 13px; line-height: 1.6; margin: 0; max-width: 690px; }
    .evr-hero__meta { align-items: center; display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 15px; }
    .evr-hero__meta span { align-items: center; color: rgba(255,255,255,.88); display: inline-flex; font-size: 11px; gap: 6px; }
    .evr-hero__actions, .evr-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; }

    .evr-btn {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 9px;
        cursor: pointer;
        display: inline-flex;
        font-size: 11px;
        font-weight: 800;
        gap: 7px;
        justify-content: center;
        min-height: 38px;
        padding: 9px 13px;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        white-space: nowrap;
    }
    .evr-btn:hover { transform: translateY(-1px); }
    .evr-btn--light { background: #fff; color: #164255; }
    .evr-btn--ghost { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.3); color: #fff; }
    .evr-btn--primary { background: #0f766e; color: #fff; }
    .evr-btn--outline { background: #fff; border-color: #cdd9e3; color: #30485b; }
    .evr-btn--soft { background: #eaf6f3; border-color: #cde9e2; color: #0f6c64; }
    .evr-btn--danger-soft { background: #fff1f0; border-color: #ffd2ce; color: #b42318; }

    .evr-section { margin-top: 22px; }
    .evr-alert { align-items: flex-start; border: 1px solid; border-radius: 11px; display: flex; gap: 10px; margin-top: 16px; padding: 12px 14px; }
    .evr-alert > i { flex: 0 0 auto; font-size: 16px; margin-top: 1px; }
    .evr-alert strong, .evr-alert span { display: block; }
    .evr-alert strong { font-size: 10px; }
    .evr-alert span { font-size: 9px; line-height: 1.5; margin-top: 2px; }
    .evr-alert--warning { background: #fff8e8; border-color: #efd391; color: #88520a; }
    .evr-section-head { align-items: end; display: flex; gap: 20px; justify-content: space-between; margin-bottom: 12px; }
    .evr-section-head h2, .evr-section-head h3 { color: var(--evr-ink); font-size: 19px; font-weight: 800; margin: 4px 0 0; }
    .evr-section-head p { color: var(--evr-muted); font-size: 11px; margin: 4px 0 0; }

    .evr-method-grid { display: grid; gap: 16px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .evr-method-card {
        --method: var(--evr-teal);
        --method-soft: #eaf8f5;
        background: #fff;
        border: 1px solid var(--evr-line);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(31, 52, 70, .06);
        color: inherit;
        display: flex;
        flex-direction: column;
        min-height: 310px;
        overflow: hidden;
        position: relative;
        transition: border-color .22s ease, box-shadow .22s ease, transform .22s ease;
    }
    .evr-method-card::before { background: var(--method); content: ""; height: 4px; inset: 0 0 auto; position: absolute; }
    .evr-method-card--services { --method: #1769aa; --method-soft: #eaf3fb; }
    .evr-method-card--goods { --method: #b66809; --method-soft: #fff5e6; }
    .evr-method-card--eoi { --method: #6941c6; --method-soft: #f2edff; }
    .evr-method-card:hover { border-color: color-mix(in srgb, var(--method), #fff 55%); box-shadow: 0 20px 44px rgba(24, 48, 66, .14); color: inherit; transform: translateY(-6px); }
    .evr-method-card:focus-visible, .evr-btn:focus-visible, .evr-field input:focus, .evr-field select:focus { box-shadow: 0 0 0 4px rgba(15,118,110,.2); outline: 2px solid #0f766e; outline-offset: 2px; }
    .evr-shell a:focus-visible { border-radius: 4px; outline: 3px solid #18a999; outline-offset: 3px; }
    .evr-method-card__body { display: flex; flex: 1; flex-direction: column; padding: 23px; }
    .evr-method-card__top { align-items: flex-start; display: flex; justify-content: space-between; }
    .evr-method-icon { align-items: center; background: var(--method-soft); border-radius: 13px; color: var(--method); display: inline-flex; font-size: 22px; height: 52px; justify-content: center; width: 52px; }
    .evr-method-number { color: #96a2ae; font-size: 10px; font-weight: 800; letter-spacing: .12em; }
    .evr-method-card h2 { color: var(--evr-ink); font-size: 20px; font-weight: 800; margin: 18px 0 4px; }
    .evr-method-card__mode { color: var(--method); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .evr-method-card p { color: #536779; font-size: 12px; line-height: 1.55; margin: 10px 0 18px; }
    .evr-method-card__stats { border-top: 1px solid #edf1f4; display: grid; grid-template-columns: 1fr 1fr; margin-top: auto; padding-top: 15px; }
    .evr-method-card__stats div + div { border-left: 1px solid #e6ebef; padding-left: 15px; }
    .evr-method-card__stats span { color: #536779; display: block; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .evr-method-card__stats strong { color: var(--evr-ink); display: block; font-size: 21px; margin-top: 2px; }
    .evr-method-card__foot { align-items: center; background: var(--method-soft); color: var(--method); display: flex; font-size: 11px; font-weight: 800; justify-content: space-between; padding: 13px 23px; }
    .evr-method-card:hover .evr-method-card__foot i { transform: translateX(4px); }
    .evr-method-card__foot i { transition: transform .2s ease; }

    .evr-kpi-grid { display: grid; gap: 12px; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-top: 16px; }
    .evr-kpi { align-items: center; background: #fff; border: 1px solid var(--evr-line); border-radius: 12px; display: flex; gap: 11px; min-width: 0; padding: 14px; }
    .evr-kpi__icon { align-items: center; background: #edf7f5; border-radius: 9px; color: var(--evr-teal); display: inline-flex; flex: 0 0 38px; height: 38px; justify-content: center; }
    .evr-kpi span:not(.evr-kpi__icon) { color: #536779; display: block; font-size: 10px; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
    .evr-kpi strong { color: var(--evr-ink); display: block; font-size: 18px; line-height: 1.15; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; }
    .evr-kpi small { color: #5b6f80; display: block; font-size: 10px; margin-top: 2px; }
    .evr-kpi-grid--summary { grid-template-columns: repeat(5, minmax(0, 1fr)); }

    .evr-panel { background: #fff; border: 1px solid var(--evr-line); border-radius: 15px; box-shadow: 0 8px 24px rgba(31,52,70,.05); overflow: hidden; }
    .evr-panel__head { align-items: center; border-bottom: 1px solid var(--evr-line); display: flex; gap: 18px; justify-content: space-between; padding: 17px 19px; }
    .evr-panel__head h2, .evr-panel__head h3 { font-size: 15px; font-weight: 800; margin: 0; }
    .evr-panel__head p { color: #536779; font-size: 11px; margin: 3px 0 0; }
    .evr-panel__body { padding: 18px; }

    .evr-toolbar { align-items: end; background: #f7f9fb; border-bottom: 1px solid var(--evr-line); display: grid; gap: 10px; grid-template-columns: minmax(240px, 1.4fr) repeat(2, minmax(150px, .55fr)) auto; padding: 13px 18px; }
    .evr-field label, .evr-field > span { color: #536779; display: block; font-size: 10px; font-weight: 800; letter-spacing: .05em; margin-bottom: 5px; text-transform: uppercase; }
    .evr-input { position: relative; }
    .evr-input i { color: #84919e; left: 11px; position: absolute; top: 50%; transform: translateY(-50%); }
    .evr-field input, .evr-field select { background: #fff; border: 1px solid #cfd9e2; border-radius: 8px; color: #344759; font-size: 12px; height: 39px; padding: 0 11px; width: 100%; }
    .evr-field .evr-input input { padding-left: 34px; }
    .evr-results { align-items: center; color: #536779; display: flex; font-size: 11px; justify-content: space-between; padding: 10px 19px 0; }

    .evr-procurement-list { display: grid; gap: 11px; padding: 13px 18px 18px; }
    .evr-procurement-card { border: 1px solid #dce4eb; border-radius: 11px; display: grid; gap: 16px; grid-template-columns: minmax(0, 1.35fr) minmax(360px, .9fr) auto; padding: 16px; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .evr-procurement-card:hover { border-color: #9fcfc6; box-shadow: 0 10px 24px rgba(24,55,68,.08); transform: translateY(-2px); }
    .evr-procurement-card[hidden] { display: none !important; }
    .evr-procurement-card__identity { min-width: 0; }
    .evr-reference-line { align-items: center; display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 6px; }
    .evr-reference { color: #536779; font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .evr-procurement-card h3 { font-size: 14px; font-weight: 800; line-height: 1.4; margin: 0; }
    .evr-procurement-card h3 a { color: var(--evr-ink); }
    .evr-procurement-card h3 a:hover { color: var(--evr-teal); }
    .evr-card-meta, .evr-tags { align-items: center; display: flex; flex-wrap: wrap; gap: 6px 13px; margin-top: 9px; }
    .evr-card-meta span { align-items: center; color: #536779; display: inline-flex; font-size: 11px; gap: 5px; }
    .evr-tags span { background: #f0f4f7; border-radius: 5px; color: #43596b; font-size: 10px; font-weight: 700; padding: 4px 6px; }
    .evr-procurement-card__metrics { align-items: center; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .evr-mini-metric { min-width: 0; padding: 0 10px; }
    .evr-mini-metric + .evr-mini-metric { border-left: 1px solid #e3e9ee; }
    .evr-mini-metric span { color: #536779; display: block; font-size: 10px; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
    .evr-mini-metric strong { display: block; font-size: 14px; margin-top: 3px; }
    .evr-mini-metric small { color: #596d7f; display: block; font-size: 10px; }
    .evr-procurement-card__action { align-items: flex-end; display: flex; flex-direction: column; gap: 8px; justify-content: center; }

    .evr-status { align-items: center; border: 1px solid #d7e0e7; border-radius: 999px; color: #536779; display: inline-flex; font-size: 9px; font-weight: 800; gap: 5px; padding: 4px 7px; text-transform: uppercase; }
    .evr-status::before { background: currentColor; border-radius: 50%; content: ""; height: 5px; width: 5px; }
    .evr-status--ready, .evr-outcome--positive { background: #eaf8f2; border-color: #c2eadc; color: #08745f; }
    .evr-status--in_progress, .evr-outcome--attention { background: #fff7e8; border-color: #f7dea8; color: #9a5b07; }
    .evr-status--awaiting, .evr-outcome--neutral { background: #f2f4f7; color: #667085; }

    .evr-empty { align-items: center; color: var(--evr-muted); display: flex; flex-direction: column; justify-content: center; min-height: 210px; padding: 30px; text-align: center; }
    .evr-empty[hidden] { display: none !important; }
    .evr-empty__icon { align-items: center; background: #eaf6f3; border-radius: 14px; color: var(--evr-teal); display: inline-flex; font-size: 20px; height: 52px; justify-content: center; width: 52px; }
    .evr-empty h3 { color: var(--evr-ink); font-size: 14px; font-weight: 800; margin: 10px 0 3px; }
    .evr-empty p { font-size: 11px; margin: 0; }

    .evr-detail-grid { display: grid; gap: 15px; grid-template-columns: minmax(0, 1.55fr) minmax(280px, .6fr); margin-top: 16px; }
    .evr-graph-section { border-top: 1px solid var(--evr-line); padding: 15px 15px 18px; }
    .evr-graph-section + .evr-graph-section { border-top: 1px solid #e8edf1; }
    .evr-graph-section__head { align-items: center; display: flex; justify-content: space-between; margin-bottom: 10px; }
    .evr-graph-section__head h3 { font-size: 14px; margin: 0; }
    .evr-graph-section__head small { color: #5f7386; display: block; margin-top: 2px; }
    .evr-graph-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
    .evr-podium { align-items: end; display: grid; gap: 10px; grid-template-columns: repeat(3, minmax(0, 1fr)); padding: 18px; }
    .evr-podium-card { background: #fafcfd; border: 1px solid var(--evr-line); border-radius: 12px; min-width: 0; padding: 15px; text-align: center; }
    .evr-podium-card--first { background: linear-gradient(180deg, #fffbeb, #fff); border-color: #f3d98b; order: 2; padding-top: 22px; }
    .evr-podium-card--second { order: 1; }
    .evr-podium-card--third { background: linear-gradient(180deg, #fff5ed, #fff); border-color: #ecc9ae; order: 3; }
    .evr-medal { align-items: center; background: #eef1f4; border-radius: 50%; color: #7b8793; display: inline-flex; font-size: 19px; height: 42px; justify-content: center; margin-bottom: 8px; width: 42px; }
    .evr-podium-card--first .evr-medal { background: #fff0b3; color: #b77905; }
    .evr-podium-card--third .evr-medal { background: #fae1cd; color: #a65d2d; }
    .evr-podium-card small { color: #536779; display: block; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .evr-podium-card h3 { font-size: 12px; font-weight: 800; line-height: 1.4; margin: 6px 0 2px; overflow-wrap: anywhere; }
    .evr-podium-card p { color: #536779; font-size: 10px; margin: 0; }
    .evr-podium-score { color: var(--evr-teal); display: block; font-size: 22px; font-weight: 800; margin-top: 9px; }

    .evr-table-wrap { max-width: 100%; overflow-x: auto; }
    .evr-table { border-collapse: collapse; min-width: 760px; width: 100%; }
    .evr-table th { background: #f5f8fa; color: #4d6275; font-size: 10px; font-weight: 800; letter-spacing: .05em; padding: 9px 11px; text-align: left; text-transform: uppercase; }
    .evr-table td { border-top: 1px solid #e6ebf0; color: #41586b; font-size: 11px; padding: 10px 11px; vertical-align: middle; }
    .evr-criteria-charts { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: 14px; }
    .evr-submission-stack { display: grid; gap: 12px; }
    .evr-submission-card { border: 1px solid var(--evr-line); border-radius: 11px; overflow: hidden; }
    .evr-submission-card__head { align-items: center; border-bottom: 1px solid var(--evr-line); display: flex; justify-content: space-between; gap: 10px; padding: 13px 15px; }
    .evr-submission-card__head ul { list-style: none; margin: 0; padding: 0; }
    .evr-submission-card__head li { color: #5f7386; display: block; font-size: 10px; margin-left: 0; }
    .evr-submission-card__head li + li { margin-top: 4px; }
    .evr-submission-card__head li strong { color: var(--evr-ink); display: block; font-size: 11px; }
    .evr-submission-card__head span { color: #647586; display: block; margin-top: 2px; }
    .evr-submission-card .evr-table { margin-top: 0; }
    .evr-submission-card .evr-table td,
    .evr-submission-card .evr-table th { vertical-align: top; }
    .evr-empty-line { color: #66758c; margin: 0; padding: 24px 6px; text-align: center; }
    .evr-ranking-badge { border-radius: 12px; font-size: 10px; font-weight: 800; padding: 4px 7px; }
    .evr-ranking-badge--gold { background: #fff0b8; color: #8b6205; }
    .evr-ranking-badge--silver { background: #edf0f3; color: #5e6e7c; }
    .evr-ranking-badge--bronze { background: #ffe5cc; color: #8e4c2b; }
    .evr-podium-card--gold { border-color: #ead37c; }
    .evr-podium-card--silver { border-color: #c9d7e1; }
    .evr-podium-card--bronze { border-color: #e2b18a; }
    .evr-podium-card--gold .evr-medal { background: #fff0b8; color: #8c6106; }
    .evr-podium-card--silver .evr-medal { background: #ebeff2; color: #64717a; }
    .evr-podium-card--bronze .evr-medal { background: #f4cba6; color: #a65d2d; }
    .evr-chart-card { background: #fbfdff; border-top: 1px solid var(--evr-line); padding: 12px; }
    .evr-chart-card__head { align-items: center; display: flex; justify-content: space-between; margin-bottom: 10px; }
    .evr-chart-card__head h4 { color: var(--evr-ink); font-size: 11px; margin: 0; }
    .evr-chart-card__head span { color: #5a6f81; display: inline-flex; font-size: 10px; font-weight: 800; }
    .evr-chart-wrap { height: 220px; position: relative; }
    .evr-chart-wrap canvas { width: 100% !important; height: 100% !important; }
    .evr-chart-empty { align-items: center; background: #f7fbfc; border: 1px dashed #dbe5ec; border-radius: 10px; color: #5e6f80; display: flex; font-size: 11px; justify-content: center; min-height: 165px; padding: 16px; text-align: center; }
    .evr-table tbody tr:hover { background: #fbfdfd; }
    .evr-table td strong { color: var(--evr-ink); display: block; font-size: 11px; }
    .evr-table td small { color: #596d7f; display: block; font-size: 10px; margin-top: 2px; }
    .evr-rank { align-items: center; background: #edf2f5; border-radius: 50%; display: inline-flex; font-size: 9px; font-weight: 800; height: 27px; justify-content: center; width: 27px; }
    .evr-rank--1 { background: #fff1b8; color: #9b6800; }
    .evr-rank--2 { background: #edf0f3; color: #64717d; }
    .evr-rank--3 { background: #f8dfcc; color: #9f5729; }
    .evr-outcome { border: 1px solid #d9e2e9; border-radius: 999px; display: inline-flex; font-size: 9px; font-weight: 800; padding: 4px 7px; }
    .evr-score { color: var(--evr-teal); font-size: 13px; font-weight: 800; }

    .evr-evaluator-list { display: grid; }
    .evr-evaluator { align-items: center; border-bottom: 1px solid #e8edf1; display: grid; gap: 9px; grid-template-columns: auto minmax(0, 1fr) auto; padding: 11px 0; }
    .evr-evaluator:last-child { border-bottom: 0; }
    .evr-avatar { align-items: center; background: #eaf6f3; border-radius: 9px; color: var(--evr-teal); display: inline-flex; font-size: 10px; font-weight: 800; height: 34px; justify-content: center; width: 34px; }
    .evr-evaluator strong { display: block; font-size: 11px; }
    .evr-evaluator small { color: #596d7f; display: block; font-size: 10px; }
    .evr-evaluator__count { color: #465d70; font-size: 10px; font-weight: 800; text-align: right; }

    .evr-evaluation-stack { display: grid; gap: 12px; }
    .evr-evaluation-card { border: 1px solid var(--evr-line); border-radius: 11px; overflow: hidden; }
    .evr-evaluation-card__head { align-items: center; background: #f7fafb; border-bottom: 1px solid var(--evr-line); display: flex; gap: 12px; justify-content: space-between; padding: 13px 15px; }
    .evr-evaluation-card__head h3 { font-size: 12px; font-weight: 800; margin: 0; }
    .evr-evaluation-card__head p { color: #536779; font-size: 10px; margin: 2px 0 0; }
    .evr-evaluation-card__badge { background: #e9f5f2; border-radius: 999px; color: var(--evr-teal); font-size: 9px; font-weight: 800; padding: 5px 8px; }

    .evr-steps { background: #fff; border: 1px solid var(--evr-line); border-radius: 14px; display: grid; grid-template-columns: repeat(3, 1fr); overflow: hidden; }
    .evr-step { align-items: flex-start; display: flex; gap: 10px; padding: 16px 18px; }
    .evr-step + .evr-step { border-left: 1px solid var(--evr-line); }
    .evr-step__number { align-items: center; background: #eaf6f3; border-radius: 8px; color: var(--evr-teal); display: inline-flex; flex: 0 0 30px; font-size: 10px; font-weight: 800; height: 30px; justify-content: center; }
    .evr-step strong { display: block; font-size: 11px; }
    .evr-step p { color: #536779; font-size: 10px; line-height: 1.45; margin: 2px 0 0; }

    @media (max-width: 1200px) {
        .evr-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .evr-procurement-card { grid-template-columns: minmax(0, 1fr) minmax(350px, .9fr); }
        .evr-procurement-card__action { align-items: center; flex-direction: row; grid-column: 1 / -1; justify-content: flex-end; }
    }

    @media (max-width: 920px) {
        .evr-method-grid, .evr-detail-grid { grid-template-columns: 1fr; }
        .evr-method-card { min-height: 270px; }
        .evr-procurement-card { grid-template-columns: 1fr; }
        .evr-toolbar { grid-template-columns: 1fr 1fr; }
        .evr-field--search { grid-column: 1 / -1; }
        .evr-procurement-card__action { grid-column: auto; justify-content: flex-start; }
        .evr-criteria-charts { grid-template-columns: 1fr; }
    }

    @media (max-width: 680px) {
        .evr-hero { align-items: stretch; flex-direction: column; padding: 23px 20px; }
        .evr-hero__actions .evr-btn { flex: 1 1 135px; }
        .evr-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .evr-kpi-grid--summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .evr-section-head, .evr-panel__head { align-items: flex-start; flex-direction: column; }
        .evr-toolbar { grid-template-columns: 1fr; }
        .evr-field--search { grid-column: auto; }
        .evr-procurement-card__metrics { grid-template-columns: repeat(2, 1fr); row-gap: 13px; }
        .evr-mini-metric:nth-child(3) { border-left: 0; }
        .evr-podium { align-items: stretch; grid-template-columns: 1fr; }
        .evr-podium-card, .evr-podium-card--first, .evr-podium-card--second, .evr-podium-card--third { order: initial; padding-top: 15px; }
        .evr-steps { grid-template-columns: 1fr; }
        .evr-step + .evr-step { border-left: 0; border-top: 1px solid var(--evr-line); }
    }

    @media (max-width: 430px) {
        .evr-kpi-grid { grid-template-columns: 1fr; }
        .evr-kpi-grid--summary { grid-template-columns: 1fr; }
        .evr-procurement-card__metrics { grid-template-columns: 1fr; }
        .evr-mini-metric + .evr-mini-metric { border-left: 0; border-top: 1px solid #e3e9ee; padding-top: 9px; }
        .evr-procurement-card__action, .evr-procurement-card__action .evr-btn { align-items: stretch; flex-direction: column; width: 100%; }
    }

    @media print {
        .content-wrapper { margin-left: 0 !important; }
        .content-wrapper > header, .nxl-navigation, .evr-no-print, footer { display: none !important; }
        .content-wrapper .content { padding: 0 !important; }
        .evr-shell { max-width: none; padding: 0; }
        .evr-hero { background: #fff !important; border: 1px solid #b8c3cc; box-shadow: none; color: #111; padding: 16px; }
        .evr-hero h1, .evr-hero p, .evr-hero .evr-eyebrow, .evr-hero__meta span { color: #111 !important; }
        .evr-kpi, .evr-panel, .evr-evaluation-card, .evr-podium-card { break-inside: avoid; box-shadow: none; }
        .evr-panel { overflow: visible; }
        .evr-table-wrap { overflow: visible; }
        .evr-table { font-size: 8px; min-width: 0; }
        .evr-criteria-charts { display: none; }
        .evr-section { margin-top: 12px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .evr-method-card, .evr-method-card__foot i, .evr-procurement-card, .evr-btn { transition: none; }
        .evr-method-card:hover, .evr-procurement-card:hover, .evr-btn:hover { transform: none; }
    }
</style>

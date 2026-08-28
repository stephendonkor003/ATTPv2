<style>
    .pev-shell {
        --pev-ink: #172430;
        --pev-muted: #687989;
        --pev-line: #dfe8e6;
        --pev-soft: #f4f8f7;
        --pev-green: #126b57;
        --pev-green-dark: #0a493d;
        --pev-mint: #dff4ec;
        --pev-blue: #1769aa;
        --pev-amber: #b66b0b;
        --pev-violet: #6941c6;
        color: var(--pev-ink);
        margin: 0 auto;
        max-width: 1540px;
        padding-bottom: 38px;
    }

    .pev-shell *, .pev-shell *::before, .pev-shell *::after { box-sizing: border-box; }
    .pev-shell a { text-decoration: none; }
    .pev-shell button, .pev-shell input, .pev-shell select { font: inherit; }

    .pev-eyebrow {
        color: var(--pev-green);
        display: block;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .13em;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .pev-hero {
        align-items: center;
        background:
            radial-gradient(circle at 88% 16%, rgba(255,255,255,.16), transparent 27%),
            linear-gradient(132deg, #093e35 0%, #126b57 58%, #1b8065 100%);
        border-radius: 24px;
        box-shadow: 0 22px 48px rgba(9, 62, 53, .19);
        color: #fff;
        display: flex;
        gap: 34px;
        justify-content: space-between;
        margin-bottom: 22px;
        overflow: hidden;
        padding: clamp(26px, 4vw, 48px);
        position: relative;
    }

    .pev-hero::after {
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 999px;
        content: '';
        height: 260px;
        position: absolute;
        right: -86px;
        top: -114px;
        width: 260px;
    }

    .pev-hero__copy { max-width: 900px; position: relative; z-index: 1; }
    .pev-hero .pev-eyebrow { color: #bcebdc; }
    .pev-hero h1 { color: #fff; font-size: clamp(30px, 4vw, 48px); font-weight: 800; letter-spacing: -.035em; line-height: 1.08; margin: 0 0 12px; }
    .pev-hero p { color: rgba(255,255,255,.8); font-size: 16px; line-height: 1.7; margin: 0; max-width: 820px; }
    .pev-hero__meta { display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 22px; }
    .pev-hero__meta span { align-items: center; color: rgba(255,255,255,.9); display: inline-flex; font-size: 13px; font-weight: 700; gap: 7px; }
    .pev-hero__meta i { color: #bcebdc; }

    .pev-hero__signal {
        align-items: center;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.17);
        border-radius: 20px;
        display: flex;
        gap: 15px;
        min-width: 205px;
        padding: 18px;
        position: relative;
        z-index: 1;
    }

    .pev-hero__signal-ring, .pev-now-card__orb {
        align-items: center;
        background: #fff;
        border-radius: 50%;
        color: var(--pev-green);
        display: inline-flex;
        flex: 0 0 auto;
        height: 52px;
        justify-content: center;
        position: relative;
        width: 52px;
    }

    .pev-hero__signal-ring::before, .pev-now-card__orb::before {
        animation: pev-pulse-ring 2.2s ease-out infinite;
        border: 2px solid rgba(255,255,255,.55);
        border-radius: inherit;
        content: '';
        inset: -8px;
        position: absolute;
    }

    .pev-hero__signal div { display: grid; }
    .pev-hero__signal strong { color: #fff; font-size: 25px; line-height: 1; }
    .pev-hero__signal span:not(.pev-hero__signal-ring) { color: rgba(255,255,255,.72); font-size: 12px; margin-top: 5px; }

    .pev-kpi-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        margin-bottom: 22px;
    }

    .pev-kpi {
        align-items: center;
        background: #fff;
        border: 1px solid var(--pev-line);
        border-radius: 17px;
        box-shadow: 0 8px 20px rgba(28, 55, 48, .055);
        display: flex;
        gap: 12px;
        min-height: 102px;
        padding: 17px;
    }

    .pev-kpi__icon {
        align-items: center;
        background: var(--pev-mint);
        border-radius: 13px;
        color: var(--pev-green);
        display: inline-flex;
        flex: 0 0 auto;
        height: 43px;
        justify-content: center;
        width: 43px;
    }

    .pev-kpi div { display: grid; min-width: 0; }
    .pev-kpi div > span { color: var(--pev-muted); font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .pev-kpi strong { color: var(--pev-ink); font-size: 23px; line-height: 1.15; margin-top: 3px; }
    .pev-kpi small { color: var(--pev-muted); font-size: 11px; margin-top: 3px; }

    .pev-panel {
        background: #fff;
        border: 1px solid var(--pev-line);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(24, 52, 45, .055);
        margin-bottom: 22px;
        overflow: hidden;
    }

    .pev-panel__head, .pev-section__head {
        align-items: flex-end;
        display: flex;
        gap: 20px;
        justify-content: space-between;
        padding: 24px 26px;
    }

    .pev-section__head { padding-left: 0; padding-right: 0; }
    .pev-panel__head h2, .pev-section__head h2 { color: var(--pev-ink); font-size: 23px; font-weight: 800; letter-spacing: -.02em; margin: 0 0 6px; }
    .pev-panel__head p, .pev-section__head p { color: var(--pev-muted); margin: 0; }
    .pev-panel__body { border-top: 1px solid var(--pev-line); padding: 28px 26px; }
    .pev-count, .pev-trust-note { align-items: center; background: var(--pev-soft); border: 1px solid var(--pev-line); border-radius: 999px; color: var(--pev-green-dark); display: inline-flex; font-size: 12px; font-weight: 800; gap: 7px; padding: 8px 13px; white-space: nowrap; }

    .pev-toolbar {
        align-items: end;
        background: var(--pev-soft);
        border-bottom: 1px solid var(--pev-line);
        border-top: 1px solid var(--pev-line);
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(250px, 2fr) repeat(3, minmax(145px, 1fr)) auto;
        padding: 17px 26px;
    }

    .pev-field { display: grid; gap: 6px; }
    .pev-field > span { color: var(--pev-muted); font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .pev-field input, .pev-field select { background: #fff; border: 1px solid #d5dfdc; border-radius: 10px; color: var(--pev-ink); height: 42px; outline: none; padding: 0 12px; width: 100%; }
    .pev-field input:focus, .pev-field select:focus { border-color: var(--pev-green); box-shadow: 0 0 0 3px rgba(18,107,87,.11); }
    .pev-input { position: relative; }
    .pev-input i { color: var(--pev-muted); left: 12px; position: absolute; top: 13px; }
    .pev-input input { padding-left: 36px; }

    .pev-btn {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 7px;
        justify-content: center;
        min-height: 40px;
        padding: 9px 14px;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .pev-btn:hover { transform: translateY(-1px); }
    .pev-btn--primary { background: var(--pev-green); color: #fff; }
    .pev-btn--primary:hover { box-shadow: 0 8px 18px rgba(18,107,87,.2); color: #fff; }
    .pev-btn--outline { background: #fff; border-color: #cddbd7; color: var(--pev-green-dark); }
    .pev-btn--outline:hover { background: var(--pev-mint); color: var(--pev-green-dark); }
    .pev-btn--disabled { background: #edf1f0; color: #8a9995; cursor: not-allowed; }
    .pev-btn--disabled:hover { transform: none; }

    .pev-procurement-grid { display: grid; gap: 17px; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 24px 26px 28px; }
    .pev-proc-card {
        background: #fff;
        border: 1px solid var(--pev-line);
        border-radius: 17px;
        box-shadow: 0 8px 20px rgba(25, 57, 48, .045);
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
        padding: 19px;
        position: relative;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .pev-proc-card::before { background: var(--pev-green); content: ''; inset: 0 auto 0 0; position: absolute; width: 3px; }
    .pev-proc-card--in_progress::before { background: var(--pev-blue); }
    .pev-proc-card--awaiting::before { background: var(--pev-amber); }
    .pev-proc-card--setup_required::before { background: #899794; }
    .pev-proc-card:hover { border-color: #b9d3cb; box-shadow: 0 18px 34px rgba(20, 70, 57, .11); transform: translateY(-3px); }
    .pev-proc-card[hidden] { display: none !important; }

    .pev-proc-card__top, .pev-reference-line { align-items: center; display: flex; flex-wrap: wrap; gap: 9px; justify-content: space-between; }
    .pev-reference { align-items: center; color: var(--pev-green); display: inline-flex; font-size: 11px; font-weight: 800; gap: 5px; letter-spacing: .035em; text-transform: uppercase; }
    .pev-reference--light { color: #c9eee2; }
    .pev-status { border-radius: 999px; display: inline-flex; font-size: 10px; font-weight: 800; padding: 6px 9px; text-transform: uppercase; white-space: nowrap; }
    .pev-status--ready { background: #dff5ec; color: #0d694f; }
    .pev-status--in_progress { background: #e5f1fb; color: #175f91; }
    .pev-status--awaiting { background: #fff1d9; color: #9a5a0c; }
    .pev-status--setup_required { background: #edf1f0; color: #60706c; }

    .pev-proc-card__identity { margin: 17px 0 12px; }
    .pev-proc-card__identity h3 { color: var(--pev-ink); font-size: 18px; font-weight: 800; line-height: 1.4; margin: 0 0 9px; }
    .pev-card-meta { display: flex; flex-wrap: wrap; gap: 7px 15px; }
    .pev-card-meta span { align-items: center; color: var(--pev-muted); display: inline-flex; font-size: 12px; gap: 6px; }

    .pev-method-pills, .pev-template-list { display: flex; flex-wrap: wrap; gap: 7px; }
    .pev-method-pill, .pev-template-list span { align-items: center; background: var(--pev-soft); border: 1px solid var(--pev-line); border-radius: 8px; color: #49605a; display: inline-flex; font-size: 10px; font-weight: 800; gap: 5px; padding: 6px 8px; }
    .pev-method-pill--eoi { background: #f0ebff; border-color: #e1d7ff; color: #6540b4; }
    .pev-method-pill--services { background: #e3f4f1; border-color: #cfeae4; color: #0f766e; }
    .pev-method-pill--goods { background: #fff1d9; border-color: #f3dfbc; color: #9a5a0c; }

    .pev-card-metrics, .pev-method-stats { display: grid; gap: 7px; grid-template-columns: repeat(4, 1fr); margin: 17px 0; }
    .pev-card-metrics div, .pev-method-stats div { background: var(--pev-soft); border-radius: 9px; display: grid; padding: 9px; }
    .pev-card-metrics span, .pev-method-stats span { color: var(--pev-muted); font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .pev-card-metrics strong, .pev-method-stats strong { color: var(--pev-ink); font-size: 17px; margin-top: 2px; }

    .pev-progress { margin-top: auto; }
    .pev-progress__label { align-items: center; color: var(--pev-muted); display: flex; font-size: 10px; font-weight: 700; justify-content: space-between; margin-bottom: 6px; }
    .pev-progress__label strong { color: var(--pev-green-dark); }
    .pev-progress__track { background: #e7eeec; border-radius: 99px; display: block; height: 6px; overflow: hidden; }
    .pev-progress__track > span { background: linear-gradient(90deg, var(--pev-green), #35a484); border-radius: inherit; display: block; height: 100%; }
    .pev-proc-card__foot { align-items: center; border-top: 1px solid var(--pev-line); display: flex; gap: 12px; justify-content: space-between; margin-top: 16px; padding-top: 14px; }
    .pev-proc-card__foot small, .pev-method-card__foot small { align-items: center; color: var(--pev-muted); display: inline-flex; font-size: 10px; gap: 5px; }

    .pev-empty { align-items: center; display: flex; flex-direction: column; padding: 52px 24px; text-align: center; }
    .pev-empty__icon { align-items: center; background: var(--pev-mint); border-radius: 50%; color: var(--pev-green); display: inline-flex; height: 54px; justify-content: center; width: 54px; }
    .pev-empty h3 { font-size: 18px; font-weight: 800; margin: 13px 0 5px; }
    .pev-empty p { color: var(--pev-muted); margin: 0; }

    .pev-breadcrumb { align-items: center; color: var(--pev-muted); display: flex; font-size: 12px; gap: 9px; margin-bottom: 12px; }
    .pev-breadcrumb a { align-items: center; color: var(--pev-green); display: inline-flex; font-weight: 800; gap: 6px; }
    .pev-hero--journey { align-items: stretch; }
    .pev-now-card { align-items: center; align-self: stretch; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16); border-radius: 18px; display: flex; flex-direction: column; justify-content: center; min-width: 230px; padding: 21px; position: relative; text-align: center; z-index: 1; }
    .pev-now-card__orb { height: 47px; margin-bottom: 13px; width: 47px; }
    .pev-now-card small { color: #bcebdc; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
    .pev-now-card strong { color: #fff; font-size: 15px; line-height: 1.4; margin: 5px 0; }
    .pev-now-card > span:last-child { color: rgba(255,255,255,.68); font-size: 11px; }

    .pev-journey { display: grid; list-style: none; margin: 0; padding: 0; }
    .pev-step { display: grid; gap: 17px; grid-template-columns: 52px minmax(0, 1fr); min-height: 127px; }
    .pev-step__rail { align-items: center; display: flex; flex-direction: column; height: 100%; }
    .pev-step__orb { align-items: center; background: #edf2f1; border: 2px solid #dae4e1; border-radius: 50%; color: #899893; display: inline-flex; flex: 0 0 auto; height: 44px; justify-content: center; position: relative; width: 44px; z-index: 1; }
    .pev-step__line { background: #e0e8e6; flex: 1; margin: 5px 0; width: 2px; }
    .pev-step__content { border: 1px solid var(--pev-line); border-radius: 14px; margin-bottom: 17px; padding: 16px 18px; }
    .pev-step__heading { align-items: flex-start; display: flex; gap: 15px; justify-content: space-between; }
    .pev-step__index { color: var(--pev-muted); display: block; font-size: 9px; font-weight: 800; letter-spacing: .08em; margin-bottom: 3px; text-transform: uppercase; }
    .pev-step h3 { color: var(--pev-ink); font-size: 15px; font-weight: 800; margin: 0; }
    .pev-step p { color: var(--pev-muted); line-height: 1.6; margin: 7px 0; }
    .pev-step__content > small { align-items: center; color: #7a8985; display: inline-flex; font-size: 10px; gap: 5px; }
    .pev-step__state { background: #edf2f1; border-radius: 999px; color: #697873; font-size: 9px; font-weight: 800; padding: 5px 8px; text-transform: uppercase; }
    .pev-step--complete .pev-step__orb { background: var(--pev-green); border-color: var(--pev-green); color: #fff; }
    .pev-step--complete .pev-step__line { background: #8bcbb9; }
    .pev-step--complete .pev-step__state { background: var(--pev-mint); color: var(--pev-green); }
    .pev-step--current .pev-step__orb { background: #fff; border-color: var(--pev-green); color: var(--pev-green); box-shadow: 0 0 0 6px rgba(18,107,87,.1); }
    .pev-step--current .pev-step__orb::before { animation: pev-current-ring 1.8s ease-out infinite; border: 2px solid rgba(18,107,87,.42); border-radius: inherit; content: ''; inset: -8px; position: absolute; }
    .pev-step--current .pev-step__content { background: linear-gradient(120deg, #f4fbf8, #fff); border-color: #a9d3c7; box-shadow: 0 8px 20px rgba(18,107,87,.08); }
    .pev-step--current .pev-step__state { background: var(--pev-green); color: #fff; }
    .pev-step--upcoming .pev-step__content { background: #fafcfc; }

    .pev-section { margin-bottom: 22px; }
    .pev-method-grid { display: grid; gap: 17px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pev-method-grid > .pev-empty { grid-column: 1 / -1; }
    .pev-method-card { background: #fff; border: 1px solid var(--pev-line); border-radius: 19px; box-shadow: 0 10px 27px rgba(25,57,48,.06); display: flex; flex-direction: column; min-width: 0; overflow: hidden; position: relative; transition: box-shadow .2s ease, transform .2s ease; }
    .pev-method-card::before { background: var(--pev-green); content: ''; height: 4px; inset: 0 0 auto; position: absolute; }
    .pev-method-card--goods::before { background: var(--pev-amber); }
    .pev-method-card--eoi::before { background: var(--pev-violet); }
    .pev-method-card:hover { box-shadow: 0 18px 35px rgba(25,57,48,.12); transform: translateY(-3px); }
    .pev-method-card__head { align-items: center; display: flex; justify-content: space-between; padding: 21px 21px 0; }
    .pev-method-card__icon { align-items: center; background: var(--pev-mint); border-radius: 13px; color: var(--pev-green); display: inline-flex; height: 44px; justify-content: center; width: 44px; }
    .pev-method-card--goods .pev-method-card__icon { background: #fff1d9; color: var(--pev-amber); }
    .pev-method-card--eoi .pev-method-card__icon { background: #f0ebff; color: var(--pev-violet); }
    .pev-method-card__body { display: flex; flex: 1; flex-direction: column; padding: 17px 21px; }
    .pev-method-card h3 { font-size: 21px; font-weight: 800; margin: 0 0 7px; }
    .pev-method-card p { color: var(--pev-muted); line-height: 1.6; margin: 0 0 15px; }
    .pev-template-list { margin-bottom: 5px; }
    .pev-template-list span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pev-method-stats { grid-template-columns: repeat(3, 1fr); }
    .pev-method-card__foot { align-items: center; background: var(--pev-soft); border-top: 1px solid var(--pev-line); display: flex; gap: 10px; justify-content: space-between; padding: 14px 21px; }

    .pev-handoff-grid { border-top: 1px solid var(--pev-line); display: grid; gap: 0; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .pev-handoff-grid article { align-items: center; border-right: 1px solid var(--pev-line); display: flex; gap: 12px; padding: 22px; }
    .pev-handoff-grid article:last-child { border-right: 0; }
    .pev-handoff-grid article > div { display: grid; min-width: 0; }
    .pev-handoff-grid small { color: var(--pev-muted); font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .pev-handoff-grid strong { font-size: 24px; line-height: 1.2; }
    .pev-handoff-grid article > div > span { color: var(--pev-muted); font-size: 10px; line-height: 1.4; }
    .pev-handoff-icon { align-items: center; background: var(--pev-soft); border-radius: 12px; color: var(--pev-green); display: inline-flex; flex: 0 0 auto; height: 42px; justify-content: center; width: 42px; }
    .pev-handoff-icon--success { background: var(--pev-mint); color: var(--pev-green); }
    .pev-handoff-icon--warning { background: #fff1d9; color: var(--pev-amber); }
    .pev-handoff-icon--violet { background: #f0ebff; color: var(--pev-violet); }
    .pev-handoff-icon--blue { background: #e5f1fb; color: var(--pev-blue); }

    @keyframes pev-current-ring {
        0% { opacity: .9; transform: scale(.88); }
        75%, 100% { opacity: 0; transform: scale(1.28); }
    }

    @keyframes pev-pulse-ring {
        0% { opacity: .75; transform: scale(.88); }
        75%, 100% { opacity: 0; transform: scale(1.3); }
    }

    @media (max-width: 1199px) {
        .pev-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pev-toolbar { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pev-field--search { grid-column: span 2; }
        .pev-method-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pev-handoff-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pev-handoff-grid article:nth-child(2) { border-right: 0; }
        .pev-handoff-grid article:nth-child(-n+2) { border-bottom: 1px solid var(--pev-line); }
    }

    @media (max-width: 820px) {
        .pev-hero { align-items: stretch; flex-direction: column; }
        .pev-hero__signal, .pev-now-card { align-self: stretch; min-width: 0; }
        .pev-now-card { align-items: flex-start; text-align: left; }
        .pev-kpi-grid, .pev-procurement-grid, .pev-method-grid { grid-template-columns: 1fr; }
        .pev-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pev-procurement-grid, .pev-panel__head, .pev-panel__body { padding-left: 18px; padding-right: 18px; }
    }

    @media (max-width: 560px) {
        .pev-hero { border-radius: 18px; padding: 24px 20px; }
        .pev-hero h1 { font-size: 29px; }
        .pev-kpi-grid, .pev-toolbar, .pev-handoff-grid { grid-template-columns: 1fr; }
        .pev-field--search { grid-column: auto; }
        .pev-panel__head, .pev-section__head, .pev-proc-card__foot, .pev-method-card__foot { align-items: stretch; flex-direction: column; }
        .pev-card-metrics { grid-template-columns: repeat(2, 1fr); }
        .pev-step { gap: 10px; grid-template-columns: 42px minmax(0, 1fr); }
        .pev-step__orb { height: 36px; width: 36px; }
        .pev-step__heading { align-items: flex-start; flex-direction: column; gap: 7px; }
        .pev-handoff-grid article { border-bottom: 1px solid var(--pev-line); border-right: 0; }
        .pev-handoff-grid article:last-child { border-bottom: 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .pev-shell *, .pev-shell *::before, .pev-shell *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }

        .pev-step--current .pev-step__orb::before,
        .pev-hero__signal-ring::before,
        .pev-now-card__orb::before { display: none; }
    }

    @media print {
        .pev-breadcrumb, .pev-toolbar, .pev-btn { display: none !important; }
        .pev-shell { max-width: none; }
        .pev-hero { box-shadow: none; }
        .pev-proc-card, .pev-method-card, .pev-panel { break-inside: avoid; box-shadow: none; }
    }
</style>

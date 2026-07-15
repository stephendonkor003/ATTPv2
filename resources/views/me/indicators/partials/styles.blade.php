<style>
    .me-results-framework {
        --me-green-950: #07382b;
        --me-green-800: #0b5c45;
        --me-green-700: #117a59;
        --me-green-100: #e8f5ef;
        --me-gold: #d3a229;
        --me-ink: #17251f;
        --me-muted: #62716a;
        --me-border: #dce6e1;
        --me-surface: #f6f9f7;
        color: var(--me-ink);
    }

    .me-results-framework .me-hero {
        position: relative;
        overflow: hidden;
        padding: clamp(1.4rem, 3vw, 2.25rem);
        border: 1px solid #cfe3d8;
        border-radius: 1rem;
        background:
            radial-gradient(circle at 90% 15%, rgba(211, 162, 41, .18), transparent 25%),
            linear-gradient(130deg, #f7fcf9 0%, #e9f5ef 100%);
        box-shadow: 0 12px 32px rgba(7, 56, 43, .07);
    }

    .me-results-framework .me-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .65rem;
        color: var(--me-green-700);
        font-size: .73rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .me-results-framework .me-hero h1 {
        margin: 0 0 .5rem;
        color: var(--me-green-950);
        font-size: clamp(1.55rem, 3vw, 2.25rem);
        font-weight: 800;
        letter-spacing: -.025em;
    }

    .me-results-framework .me-hero p {
        max-width: 760px;
        margin: 0;
        color: var(--me-muted);
        font-size: .95rem;
        line-height: 1.65;
    }

    .me-results-framework .me-primary-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 42px;
        padding: .68rem 1rem;
        border: 1px solid var(--me-green-800);
        border-radius: .65rem;
        background: var(--me-green-800);
        color: #fff;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(11, 92, 69, .18);
    }

    .me-results-framework .me-primary-action:hover,
    .me-results-framework .me-primary-action:focus {
        background: var(--me-green-950);
        border-color: var(--me-green-950);
        color: #fff;
    }

    .me-results-framework .me-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .85rem;
        margin: 1rem 0;
    }

    .me-results-framework .me-summary-card {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-width: 0;
        padding: .95rem 1rem;
        border: 1px solid var(--me-border);
        border-radius: .8rem;
        background: #fff;
    }

    .me-results-framework .me-summary-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.45rem;
        height: 2.45rem;
        border-radius: .7rem;
        background: var(--me-green-100);
        color: var(--me-green-700);
        font-size: 1rem;
    }

    .me-results-framework .me-summary-value {
        color: var(--me-green-950);
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1;
    }

    .me-results-framework .me-summary-label {
        margin-top: .25rem;
        color: var(--me-muted);
        font-size: .73rem;
        font-weight: 600;
    }

    .me-results-framework .me-panel {
        margin-bottom: 1rem;
        border: 1px solid var(--me-border);
        border-radius: .9rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(20, 42, 33, .05);
    }

    .me-results-framework .me-panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--me-border);
    }

    .me-results-framework .me-panel-title {
        margin: 0;
        color: var(--me-green-950);
        font-size: 1rem;
        font-weight: 800;
    }

    .me-results-framework .me-panel-subtitle {
        margin: .25rem 0 0;
        color: var(--me-muted);
        font-size: .78rem;
    }

    .me-results-framework .me-panel-body {
        padding: 1.15rem;
    }

    .me-results-framework .me-required-note {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        margin-bottom: 1rem;
        padding: .75rem .85rem;
        border: 1px solid #cfe3d8;
        border-radius: .65rem;
        background: var(--me-green-100);
        color: #245c48;
        font-size: .78rem;
        line-height: 1.5;
    }

    .me-results-framework .me-scope-card {
        padding: .9rem;
        border: 1px solid #bfd8cb;
        border-radius: .7rem;
        background: #f5faf7;
    }

    .me-results-framework .me-scope-card .form-select {
        background-color: #fff;
    }

    .me-results-framework .me-scope-status {
        min-height: 1.05rem;
        margin-top: .55rem;
        color: var(--me-green-700);
        font-size: .7rem;
        font-weight: 650;
    }

    .me-results-framework [data-indicator-portfolio-dependent]:disabled,
    .me-results-framework [data-indicator-owner]:disabled {
        background-color: #f3f5f4;
        color: #8a948f;
        cursor: not-allowed;
    }

    .me-results-framework .me-inline-create-link[aria-disabled="true"] {
        color: #8b9690;
        cursor: not-allowed;
        opacity: .72;
    }

    .me-results-framework .me-form-section + .me-form-section {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--me-border);
    }

    .me-results-framework .me-form-section-title {
        margin-bottom: .85rem;
        color: var(--me-green-950);
        font-size: .82rem;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .me-results-framework .form-label {
        margin-bottom: .4rem;
        color: #294239;
        font-size: .78rem;
        font-weight: 700;
    }

    .me-results-framework .form-control,
    .me-results-framework .form-select {
        min-height: 42px;
        border-color: #cfdcd6;
        border-radius: .55rem;
        color: var(--me-ink);
        font-size: .84rem;
    }

    .me-results-framework textarea.form-control {
        min-height: 96px;
        resize: vertical;
    }

    .me-results-framework .form-control:focus,
    .me-results-framework .form-select:focus {
        border-color: var(--me-green-700);
        box-shadow: 0 0 0 .18rem rgba(17, 122, 89, .12);
    }

    .me-results-framework .me-field-help {
        display: block;
        margin-top: .35rem;
        color: var(--me-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .me-results-framework .me-field-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .4rem;
    }

    .me-results-framework .me-field-label-row .form-label {
        margin-bottom: 0;
    }

    .me-results-framework .me-inline-create-link {
        display: inline-flex;
        align-items: center;
        flex: 0 0 auto;
        gap: .25rem;
        padding: .22rem .42rem;
        border: 1px solid #b8d7c9;
        border-radius: .4rem;
        background: #f6fbf8;
        color: var(--me-green-700);
        font-size: .66rem;
        font-weight: 800;
        line-height: 1.25;
        text-decoration: none;
    }

    .me-results-framework .me-inline-create-link:hover,
    .me-results-framework .me-inline-create-link:focus-visible {
        border-color: var(--me-green-700);
        background: var(--me-green-100);
        color: var(--me-green-950);
    }

    .me-results-framework .me-inline-create-link:focus-visible {
        outline: 3px solid rgba(17, 122, 89, .16);
        outline-offset: 2px;
    }

    .me-results-framework .me-inline-selection-status {
        display: none;
        margin-top: .35rem;
        color: var(--me-green-700);
        font-size: .69rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .me-results-framework .me-inline-selection-status:not(:empty) {
        display: block;
    }

    .me-results-framework .me-optional-details {
        margin-top: 1rem;
        border: 1px solid var(--me-border);
        border-radius: .65rem;
        background: var(--me-surface);
    }

    .me-results-framework .me-optional-details summary {
        padding: .75rem .85rem;
        color: var(--me-green-800);
        cursor: pointer;
        font-size: .78rem;
        font-weight: 700;
    }

    .me-results-framework .me-optional-details-content {
        padding: 0 .85rem .85rem;
    }

    .me-results-framework .me-form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .6rem;
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid var(--me-border);
    }

    .me-results-framework .me-filter-bar {
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .me-results-framework .me-search-wrap {
        position: relative;
        flex: 1 1 320px;
        max-width: 440px;
    }

    .me-results-framework .me-search-wrap i {
        position: absolute;
        top: 50%;
        left: .8rem;
        color: #839189;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .me-results-framework .me-search-wrap input {
        padding-left: 2.35rem;
    }

    .me-results-framework .me-register-table {
        margin: 0;
        table-layout: fixed;
    }

    .me-results-framework .me-register-table th {
        padding: .72rem .8rem;
        border-bottom-color: var(--me-border);
        background: var(--me-surface);
        color: #52635b;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .me-results-framework .me-register-table td {
        padding: .85rem .8rem;
        border-color: #edf2ef;
        color: #32453d;
        font-size: .76rem;
        vertical-align: top;
    }

    .me-results-framework .me-code {
        display: inline-flex;
        margin-bottom: .35rem;
        padding: .24rem .42rem;
        border-radius: .35rem;
        background: var(--me-green-100);
        color: var(--me-green-800);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .65rem;
        font-weight: 800;
    }

    .me-results-framework .me-indicator-name {
        color: var(--me-green-950);
        font-size: .82rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .me-results-framework .me-definition {
        display: -webkit-box;
        overflow: hidden;
        margin-top: .3rem;
        color: var(--me-muted);
        font-size: .7rem;
        line-height: 1.45;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .me-results-framework .me-metric-line {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .35rem;
        margin-bottom: .4rem;
    }

    .me-results-framework .me-metric-value {
        color: var(--me-green-950);
        font-weight: 800;
    }

    .me-results-framework .me-muted {
        color: var(--me-muted);
    }

    .me-results-framework .me-chip {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .25rem .42rem;
        border: 1px solid var(--me-border);
        border-radius: 999px;
        background: #fff;
        color: #53665d;
        font-size: .65rem;
        font-weight: 700;
    }

    .me-results-framework .me-row-actions {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: .35rem;
    }

    .me-results-framework .me-empty-state {
        padding: 3rem 1.25rem;
        text-align: center;
    }

    .me-results-framework .me-empty-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3.2rem;
        height: 3.2rem;
        margin-bottom: .8rem;
        border-radius: 1rem;
        background: var(--me-green-100);
        color: var(--me-green-700);
        font-size: 1.25rem;
    }

    .me-results-framework .me-mobile-register {
        display: none;
    }

    .me-results-framework .me-mobile-card {
        padding: 1rem;
        border-bottom: 1px solid var(--me-border);
    }

    .me-results-framework .me-mobile-card:last-child {
        border-bottom: 0;
    }

    .me-results-framework .me-mobile-facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
        margin: .8rem 0;
    }

    .me-results-framework .me-mobile-fact {
        padding: .6rem;
        border-radius: .55rem;
        background: var(--me-surface);
    }

    .me-results-framework .me-mobile-fact small {
        display: block;
        margin-bottom: .2rem;
        color: var(--me-muted);
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Inline configuration modals render outside the page wrapper. */
    .me-inline-config-modal {
        --me-modal-green-950: #07382b;
        --me-modal-green-800: #0b5c45;
        --me-modal-green-700: #117a59;
        --me-modal-green-100: #e8f5ef;
        --me-modal-ink: #17251f;
        --me-modal-muted: #62716a;
        --me-modal-border: #dce6e1;
    }

    .me-inline-config-modal .modal-dialog {
        max-width: 680px;
    }

    .me-inline-config-modal .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 1rem;
        background: #fff;
        color: var(--me-modal-ink);
        box-shadow: 0 24px 70px rgba(7, 56, 43, .22);
    }

    .me-inline-config-modal .modal-header {
        align-items: flex-start;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid var(--me-modal-border);
        background:
            radial-gradient(circle at 90% 10%, rgba(211, 162, 41, .14), transparent 28%),
            linear-gradient(130deg, #f7fcf9 0%, #edf7f2 100%);
    }

    .me-inline-config-modal .me-inline-modal-heading {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        min-width: 0;
        padding-right: .75rem;
    }

    .me-inline-config-modal .me-inline-modal-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.45rem;
        height: 2.45rem;
        border-radius: .7rem;
        background: var(--me-modal-green-100);
        color: var(--me-modal-green-700);
        font-size: 1rem;
    }

    .me-inline-config-modal .modal-title {
        margin: 0;
        color: var(--me-modal-green-950);
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .me-inline-config-modal .me-inline-modal-heading p {
        margin: .2rem 0 0;
        color: var(--me-modal-muted);
        font-size: .75rem;
        line-height: 1.45;
    }

    .me-inline-config-modal .btn-close {
        flex: 0 0 auto;
        margin: .15rem 0 0 auto;
    }

    .me-inline-config-modal .btn-close:focus-visible {
        box-shadow: 0 0 0 .2rem rgba(17, 122, 89, .16);
    }

    .me-inline-config-modal .modal-body {
        padding: 1.25rem;
    }

    .me-inline-config-modal .form-label {
        margin-bottom: .4rem;
        color: #294239;
        font-size: .76rem;
        font-weight: 800;
    }

    .me-inline-config-modal .form-control,
    .me-inline-config-modal .form-select {
        min-height: 42px;
        border-color: #cfdcd6;
        border-radius: .55rem;
        color: var(--me-modal-ink);
        font-size: .82rem;
    }

    .me-inline-config-modal textarea.form-control {
        min-height: 88px;
        resize: vertical;
    }

    .me-inline-config-modal .form-control:focus,
    .me-inline-config-modal .form-select:focus {
        border-color: var(--me-modal-green-700);
        box-shadow: 0 0 0 .18rem rgba(17, 122, 89, .12);
    }

    .me-inline-config-modal .me-inline-modal-help {
        display: block;
        margin-top: .35rem;
        color: var(--me-modal-muted);
        font-size: .68rem;
        line-height: 1.45;
    }

    .me-inline-config-modal .me-inline-optional {
        margin-left: .25rem;
        color: #7d8a84;
        font-size: .62rem;
        font-weight: 600;
    }

    .me-inline-config-modal .me-inline-locked-value {
        display: flex;
        align-items: center;
        gap: .5rem;
        min-height: 42px;
        padding: .6rem .75rem;
        border: 1px solid var(--me-modal-border);
        border-radius: .55rem;
        background: #f6f9f7;
        color: var(--me-modal-green-950);
        font-size: .82rem;
        font-weight: 700;
    }

    .me-inline-config-modal .me-inline-locked-value i {
        color: var(--me-modal-green-700);
    }

    .me-inline-config-modal .me-inline-interval-hint {
        margin-top: -.25rem;
        padding: .55rem .65rem;
        border: 1px solid #d5e8df;
        border-radius: .5rem;
        background: #f5faf7;
        color: #3a6453;
    }

    .me-inline-config-modal .invalid-feedback {
        font-size: .68rem;
        line-height: 1.4;
    }

    .me-inline-config-modal .me-inline-modal-errors {
        margin-bottom: 1rem;
        padding: .75rem .85rem;
        border: 1px solid #efc6c8;
        border-radius: .65rem;
        font-size: .72rem;
        line-height: 1.45;
    }

    .me-inline-config-modal .modal-footer {
        gap: .5rem;
        padding: .9rem 1.25rem;
        border-top: 1px solid var(--me-modal-border);
        background: #fbfdfc;
    }

    .me-inline-config-modal .modal-footer .btn,
    .me-inline-config-modal .me-inline-modal-submit {
        min-height: 40px;
        border-radius: .55rem;
        font-size: .78rem;
        font-weight: 800;
    }

    .me-inline-config-modal .me-inline-modal-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        padding: .58rem .9rem;
        border: 1px solid var(--me-modal-green-800);
        background: var(--me-modal-green-800);
        color: #fff;
        box-shadow: 0 7px 16px rgba(11, 92, 69, .16);
    }

    .me-inline-config-modal .me-inline-modal-submit:hover,
    .me-inline-config-modal .me-inline-modal-submit:focus-visible {
        border-color: var(--me-modal-green-950);
        background: var(--me-modal-green-950);
        color: #fff;
    }

    .me-inline-config-modal .me-inline-modal-submit:focus-visible {
        outline: 3px solid rgba(17, 122, 89, .18);
        outline-offset: 2px;
    }

    .me-inline-config-modal .me-inline-modal-submit:disabled {
        cursor: wait;
        opacity: .72;
    }

    @media (max-width: 991.98px) {
        .me-results-framework .me-summary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .me-results-framework .me-hero-actions,
        .me-results-framework .me-filter-bar,
        .me-results-framework .me-form-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .me-results-framework .me-primary-action,
        .me-results-framework .me-form-actions .btn {
            width: 100%;
        }

        .me-results-framework .me-search-wrap {
            max-width: none;
            flex-basis: auto;
        }

        .me-results-framework .me-register-desktop {
            display: none;
        }

        .me-results-framework .me-mobile-register {
            display: block;
        }
    }

    @media (max-width: 575.98px) {
        .me-inline-config-modal .modal-content {
            border-radius: 0;
        }

        .me-inline-config-modal .modal-header,
        .me-inline-config-modal .modal-body,
        .me-inline-config-modal .modal-footer {
            padding-right: 1rem;
            padding-left: 1rem;
        }

        .me-inline-config-modal .modal-footer {
            display: grid;
            grid-template-columns: 1fr;
        }

        .me-inline-config-modal .modal-footer .btn,
        .me-inline-config-modal .me-inline-modal-submit {
            width: 100%;
            margin: 0;
        }
    }
</style>

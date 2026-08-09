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
        min-width: 0;
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

    .me-results-framework .me-panel-header > * {
        min-width: 0;
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
        min-width: 0;
        padding: 1.15rem;
    }

    .me-results-framework .me-indicator-editor,
    .me-results-framework .me-indicator-editor .row,
    .me-results-framework .me-indicator-editor .row > * {
        min-width: 0;
    }

    .me-results-framework .me-indicator-editor .me-form-section {
        min-width: 0;
        scroll-margin-top: 1rem;
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
        max-width: 100%;
        margin-bottom: .4rem;
        color: #294239;
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .me-results-framework .form-control,
    .me-results-framework .form-select {
        width: 100%;
        max-width: 100%;
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
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem;
        margin-bottom: .4rem;
    }

    .me-results-framework .me-field-label-row .form-label {
        flex: 1 1 180px;
        min-width: 0;
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
        justify-content: flex-end;
        flex-wrap: wrap;
        min-width: 0;
        gap: .65rem;
    }

    .me-results-framework .me-filter-bar .btn {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .me-results-framework .me-component-filter {
        flex: 1 1 230px;
        width: auto;
        min-width: 220px;
        max-width: 330px;
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

    .me-results-framework .me-register-export-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .5rem;
    }

    .me-results-framework .me-register-toolbar {
        display: grid;
        grid-template-columns: minmax(240px, 1.7fr) repeat(4, minmax(150px, 1fr)) 90px auto;
        align-items: end;
        gap: .75rem;
        padding: 1rem;
        border-top: 1px solid var(--me-border);
        border-bottom: 1px solid var(--me-border);
        background: linear-gradient(180deg, #fbfdfc 0%, #f6faf8 100%);
    }

    .me-results-framework .me-register-filter {
        min-width: 0;
    }

    .me-results-framework .me-register-filter > label {
        display: block;
        margin-bottom: .32rem;
        color: #53665d;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .025em;
    }

    .me-results-framework .me-register-filter .form-control,
    .me-results-framework .me-register-filter .form-select {
        min-height: 38px;
        border-color: #cfded6;
        border-radius: .55rem;
        background-color: #fff;
        font-size: .73rem;
    }

    .me-results-framework .me-register-filter .form-control:focus,
    .me-results-framework .me-register-filter .form-select:focus {
        border-color: var(--me-green-700);
        box-shadow: 0 0 0 .2rem rgba(17, 122, 89, .12);
    }

    .me-results-framework .me-register-filter-search .me-search-wrap {
        max-width: none;
    }

    .me-results-framework .me-register-clear {
        min-height: 38px;
        white-space: nowrap;
    }

    .me-results-framework .me-register-match-count {
        grid-column: 1 / -1;
        color: var(--me-green-800);
        font-size: .71rem;
        font-weight: 800;
    }

    .me-results-framework .me-table-statusbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding: .7rem 1rem;
        border-bottom: 1px solid var(--me-border);
        background: #fbfcfb;
        color: var(--me-muted);
        font-size: .71rem;
    }

    .me-results-framework .me-table-statusbar span {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    .me-results-framework .me-table-statusbar strong {
        color: var(--me-green-950);
    }

    .me-results-framework .me-scroll-hint {
        color: var(--me-green-700);
        font-weight: 700;
    }

    .me-results-framework .me-register-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        overscroll-behavior: contain;
        scrollbar-color: #8faea0 #edf3f0;
        scrollbar-gutter: stable;
    }

    .me-results-framework .me-register-scroll:focus-visible {
        outline: 3px solid rgba(17, 122, 89, .17);
        outline-offset: -3px;
    }

    .me-results-framework .me-register-scroll::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }

    .me-results-framework .me-register-scroll::-webkit-scrollbar-track {
        background: #edf3f0;
    }

    .me-results-framework .me-register-scroll::-webkit-scrollbar-thumb {
        border: 3px solid #edf3f0;
        border-radius: 999px;
        background: #8faea0;
    }

    .me-results-framework .me-register-scroll::-webkit-scrollbar-thumb:hover {
        background: #638b78;
    }

    .me-results-framework .me-register-table {
        width: 1240px !important;
        min-width: 1240px;
        max-width: none !important;
        margin: 0;
        table-layout: fixed !important;
    }

    .me-results-framework .me-register-table .me-col-indicator {
        width: 340px;
    }

    .me-results-framework .me-register-table .me-col-measurement {
        width: 165px;
    }

    .me-results-framework .me-register-table .me-col-reporting {
        width: 345px;
    }

    .me-results-framework .me-register-table .me-col-responsible {
        width: 175px;
    }

    .me-results-framework .me-register-table .me-col-actions {
        width: 215px;
    }

    .me-results-framework .me-register-table th {
        padding: .72rem .8rem;
        border-bottom-color: var(--me-border);
        background: #f4f8f6;
        color: #52635b;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .me-results-framework .me-register-table td {
        min-width: 0;
        padding: .85rem .8rem;
        border-color: #edf2ef;
        color: #32453d;
        font-size: .76rem;
        vertical-align: top;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .me-results-framework .me-register-table td > * {
        max-width: 100%;
    }

    .me-results-framework .me-register-table td a {
        overflow-wrap: anywhere;
    }

    .me-results-framework .me-register-table tbody tr:hover td {
        background: #fbfdfc;
    }

    .me-results-framework .me-register-table .me-actions-cell {
        background: #fff;
    }

    .me-results-framework .me-register-table th.me-actions-cell {
        background: #f4f8f6;
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
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .35rem;
        margin-bottom: .4rem;
    }

    .me-results-framework .me-metric-value {
        color: var(--me-green-950);
        font-weight: 800;
    }

    .me-results-framework .me-reporting-chips {
        display: flex;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: .35rem;
        margin-bottom: .65rem;
    }

    .me-results-framework .me-person {
        display: flex;
        align-items: flex-start;
        min-width: 0;
        gap: .55rem;
    }

    .me-results-framework .me-person-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--me-green-100);
        color: var(--me-green-800);
    }

    .me-results-framework .me-person > span:last-child {
        min-width: 0;
    }

    .me-results-framework .me-person strong,
    .me-results-framework .me-person small {
        display: block;
        overflow-wrap: anywhere;
    }

    .me-results-framework .me-person strong {
        color: var(--me-green-950);
        font-size: .74rem;
    }

    .me-results-framework .me-person small {
        margin-top: .16rem;
        color: var(--me-muted);
        font-size: .64rem;
    }

    .me-results-framework .me-muted {
        color: var(--me-muted);
    }

    .me-results-framework .me-chip {
        display: inline-flex;
        align-items: center;
        min-width: 0;
        max-width: 100%;
        gap: .25rem;
        padding: .25rem .42rem;
        border: 1px solid var(--me-border);
        border-radius: 999px;
        background: #fff;
        color: #53665d;
        font-size: .65rem;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .me-results-framework .me-row-actions {
        display: flex;
        justify-content: flex-end;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: .35rem;
    }

    .me-results-framework .me-row-actions > form {
        flex: 0 0 auto;
        margin: 0;
    }

    .me-results-framework .me-row-actions .btn {
        white-space: nowrap;
    }

    .me-results-framework .dataTables_wrapper {
        width: 100%;
        color: var(--me-muted);
    }

    .me-results-framework .dataTables_wrapper .me-dt-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .8rem;
        padding: .85rem 1rem;
        border-top: 1px solid var(--me-border);
        background: #fbfdfc;
    }

    .me-results-framework .dataTables_wrapper .dataTables_info {
        padding: 0 !important;
        color: var(--me-muted);
        font-size: .72rem;
    }

    .me-results-framework .dataTables_wrapper .dataTables_paginate {
        padding: 0 !important;
    }

    .me-results-framework .dataTables_wrapper .pagination {
        margin: 0;
    }

    .me-results-framework .dataTables_wrapper .page-link {
        min-width: 34px;
        border-color: #d8e4de;
        color: var(--me-green-800);
        text-align: center;
    }

    .me-results-framework .dataTables_wrapper .page-item.active .page-link {
        border-color: var(--me-green-700);
        background: var(--me-green-700);
        color: #fff;
    }

    .me-results-framework .dataTables_wrapper table.dataTable {
        border-collapse: collapse !important;
    }

    .me-results-framework .dataTables_wrapper table.dataTable thead th.sorting,
    .me-results-framework .dataTables_wrapper table.dataTable thead th.sorting_asc,
    .me-results-framework .dataTables_wrapper table.dataTable thead th.sorting_desc {
        padding-right: 1.9rem;
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

    /*
     * Render the register modal at document level and above the admin chrome.
     * The theme's default navy backdrop is intentionally softened here so the
     * white dialog remains the clear focal point.
     */
    .modal.me-disaggregation-modal {
        --me-disaggregation-green-950: #07382b;
        --me-disaggregation-green-800: #0b5c45;
        --me-disaggregation-green-700: #117a59;
        --me-disaggregation-green-100: #e8f5ef;
        --me-disaggregation-ink: #17251f;
        --me-disaggregation-muted: #62716a;
        --me-disaggregation-border: #d8e5df;
        z-index: 3200;
    }

    body.me-disaggregation-modal-open .modal-backdrop {
        z-index: 3190 !important;
        background-color: #071a14 !important;
    }

    body.me-disaggregation-modal-open .modal-backdrop.show {
        opacity: .58 !important;
    }

    .me-disaggregation-modal .modal-dialog {
        width: min(1180px, calc(100vw - 2rem));
        max-width: 1180px;
        height: min(860px, calc(100vh - 2rem));
        margin: 1rem auto;
    }

    .me-disaggregation-modal .modal-content {
        width: 100%;
        height: 100%;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .72);
        border-radius: 1.05rem;
        background: #fff;
        color: var(--me-disaggregation-ink);
        box-shadow:
            0 32px 90px rgba(2, 20, 14, .38),
            0 0 0 1px rgba(7, 56, 43, .1);
    }

    .me-disaggregation-modal form {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
    }

    .me-disaggregation-modal .modal-header {
        align-items: flex-start;
        padding: 1.3rem 1.4rem;
        border-bottom: 1px solid var(--me-disaggregation-border);
        background:
            radial-gradient(circle at 92% 8%, rgba(211, 162, 41, .2), transparent 30%),
            linear-gradient(130deg, #f7fcf9 0%, #eaf6f0 100%);
    }

    .me-disaggregation-modal .me-disaggregation-heading {
        display: flex;
        align-items: flex-start;
        gap: .8rem;
        min-width: 0;
        padding-right: .75rem;
    }

    .me-disaggregation-modal .me-disaggregation-heading-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.65rem;
        height: 2.65rem;
        border: 1px solid #cce4d8;
        border-radius: .75rem;
        background: #fff;
        color: var(--me-disaggregation-green-700);
        font-size: 1.05rem;
        box-shadow: 0 8px 18px rgba(11, 92, 69, .1);
    }

    .me-disaggregation-modal .modal-title {
        margin: 0;
        color: var(--me-disaggregation-green-950);
        font-size: 1.08rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .me-disaggregation-modal .me-disaggregation-heading p {
        margin: .2rem 0 0;
        color: var(--me-disaggregation-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .me-disaggregation-modal .me-disaggregation-indicator {
        display: none;
        margin-top: .5rem;
        padding: .3rem .5rem;
        border: 1px solid #cfe3d8;
        border-radius: .45rem;
        background: rgba(255, 255, 255, .78);
        color: var(--me-disaggregation-green-800);
        font-size: .7rem;
        font-weight: 750;
        line-height: 1.35;
    }

    .me-disaggregation-modal .me-disaggregation-indicator:not(:empty) {
        display: inline-flex;
    }

    .me-disaggregation-modal .btn-close {
        flex: 0 0 auto;
        margin: .1rem 0 0 auto;
        padding: .55rem;
        border-radius: .5rem;
        background-color: rgba(255, 255, 255, .82);
    }

    .me-disaggregation-modal .btn-close:focus-visible {
        box-shadow: 0 0 0 .2rem rgba(17, 122, 89, .17);
    }

    .me-disaggregation-modal .modal-body {
        min-height: 0;
        padding: 1.35rem 1.4rem 1.45rem;
        overflow-y: auto;
        background: #fff;
    }

    .me-disaggregation-modal .table-responsive {
        overflow: auto;
        border-color: var(--me-disaggregation-border) !important;
    }

    .me-disaggregation-modal .table {
        min-width: 820px;
    }

    .me-disaggregation-modal .table th,
    .me-disaggregation-modal .table td {
        padding: .8rem .9rem;
    }

    .me-disaggregation-modal .table th:nth-child(2) {
        width: 24%;
    }

    .me-disaggregation-modal .table th:nth-child(3) {
        width: 48%;
    }

    .me-disaggregation-modal .me-disaggregation-note {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        margin-bottom: 1.2rem;
        padding: .72rem .8rem;
        border: 1px solid #d3e7dd;
        border-radius: .65rem;
        background: #f4faf7;
        color: #3c6252;
        font-size: .72rem;
        line-height: 1.5;
    }

    .me-disaggregation-modal .me-disaggregation-note i {
        flex: 0 0 auto;
        margin-top: .15rem;
        color: var(--me-disaggregation-green-700);
    }

    .me-disaggregation-modal .me-disaggregation-level {
        position: relative;
        display: grid;
        grid-template-columns: 2rem minmax(0, 1fr);
        gap: .75rem;
    }

    .me-disaggregation-modal .me-disaggregation-level + .me-disaggregation-level {
        margin-top: .9rem;
        padding-top: .9rem;
        border-top: 1px solid #edf2ef;
    }

    .me-disaggregation-modal .me-disaggregation-step {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        align-self: start;
        width: 2rem;
        height: 2rem;
        border: 1px solid #c9e0d5;
        border-radius: 999px;
        background: var(--me-disaggregation-green-100);
        color: var(--me-disaggregation-green-800);
        font-size: .72rem;
        font-weight: 850;
    }

    .me-disaggregation-modal .me-disaggregation-field {
        min-width: 0;
    }

    .me-disaggregation-modal .form-label {
        margin-bottom: .42rem;
        color: #294239;
        font-size: .78rem;
        font-weight: 800;
    }

    .me-disaggregation-modal .me-disaggregation-optional {
        margin-left: .3rem;
        color: #7d8a84;
        font-size: .62rem;
        font-weight: 650;
    }

    .me-disaggregation-modal .form-control {
        min-height: 44px;
        border-color: #c8d8d1;
        border-radius: .58rem;
        background-color: #fff;
        color: var(--me-disaggregation-ink);
        font-size: .82rem;
    }

    .me-disaggregation-modal .form-control:focus {
        border-color: var(--me-disaggregation-green-700);
        box-shadow: 0 0 0 .2rem rgba(17, 122, 89, .13);
    }

    .me-disaggregation-modal .form-control:disabled {
        border-color: #e0e7e3;
        background: #f2f5f3;
        color: #8b9690;
        cursor: not-allowed;
    }

    .me-disaggregation-modal .form-text {
        margin-top: .32rem;
        color: var(--me-disaggregation-muted);
        font-size: .67rem;
        line-height: 1.4;
    }

    .me-disaggregation-modal .modal-footer {
        gap: .55rem;
        padding: .95rem 1.4rem;
        border-top: 1px solid var(--me-disaggregation-border);
        background: #f9fcfa;
    }

    .me-disaggregation-modal .modal-footer .btn,
    .me-disaggregation-modal .me-disaggregation-save {
        min-height: 42px;
        border-radius: .58rem;
        font-size: .78rem;
        font-weight: 800;
    }

    .me-disaggregation-modal .me-disaggregation-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        padding: .62rem 1rem;
        border: 1px solid var(--me-disaggregation-green-800);
        background: var(--me-disaggregation-green-800);
        color: #fff;
        box-shadow: 0 9px 20px rgba(11, 92, 69, .2);
    }

    .me-disaggregation-modal .me-disaggregation-save:hover,
    .me-disaggregation-modal .me-disaggregation-save:focus-visible {
        border-color: var(--me-disaggregation-green-950);
        background: var(--me-disaggregation-green-950);
        color: #fff;
    }

    .me-disaggregation-modal .me-disaggregation-save:focus-visible {
        outline: 3px solid rgba(17, 122, 89, .18);
        outline-offset: 2px;
    }

    @media (max-width: 991.98px) {
        .me-results-framework .me-summary-grid {
            grid-template-columns: 1fr;
        }

        .me-results-framework .me-register-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .me-results-framework .me-register-filter-search {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 767.98px) {
        .me-results-framework .me-panel-header {
            align-items: stretch;
            flex-direction: column;
        }

        .me-results-framework .me-panel-header > .btn {
            align-self: flex-start;
        }

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

        .me-results-framework .me-component-filter {
            width: 100%;
            min-width: 0;
            max-width: none;
        }

        .me-results-framework .me-register-export-actions {
            width: 100%;
        }

        .me-results-framework .me-register-export-actions .btn {
            flex: 1 1 0;
        }

        .me-results-framework .me-register-toolbar {
            grid-template-columns: 1fr;
        }

        .me-results-framework .me-register-filter-search,
        .me-results-framework .me-register-match-count {
            grid-column: auto;
        }

        .me-results-framework .me-register-clear {
            width: 100%;
        }

        .me-results-framework .me-register-page-size {
            display: none;
        }

        .me-results-framework .me-indicator-form-panel .me-panel-body {
            padding: .9rem;
        }

        .me-results-framework .me-register-desktop {
            display: none;
        }

        .me-results-framework .me-mobile-register {
            display: block;
        }
    }

    @media (max-width: 575.98px) {
        .me-disaggregation-modal .modal-dialog {
            width: 100vw;
            max-width: none;
            height: 100vh;
            margin: 0;
        }

        .me-disaggregation-modal .modal-content {
            border: 0;
            border-radius: 0;
        }

        .me-disaggregation-modal .modal-header,
        .me-disaggregation-modal .modal-body,
        .me-disaggregation-modal .modal-footer {
            padding-right: 1rem;
            padding-left: 1rem;
        }

        .me-disaggregation-modal .modal-footer {
            display: grid;
            grid-template-columns: 1fr;
        }

        .me-disaggregation-modal .modal-footer .btn,
        .me-disaggregation-modal .me-disaggregation-save {
            width: 100%;
            margin: 0;
        }

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

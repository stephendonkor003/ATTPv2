<style>
    .funders-workspace {
        color: #0f172a;
    }

    .funders-hero {
        border-radius: 8px;
        padding: 20px;
        color: #ffffff;
        background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
        box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
    }

    .funders-hero h4,
    .funders-hero p {
        color: #ffffff;
    }

    .funders-kicker {
        color: #d9fff4;
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .funders-hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        color: #effff9;
        background: rgba(255, 255, 255, 0.1);
        font-size: 0.82rem;
        font-weight: 700;
    }

    .funders-stat-grid,
    .funders-insight-grid {
        display: grid;
        gap: 12px;
    }

    .funders-stat-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .funders-insight-grid {
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1.1fr);
    }

    .partner-page-stat {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #dbe3ea;
        border-radius: 8px;
        padding: 15px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }

    .partner-page-stat .label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        font-weight: 800;
    }

    .partner-page-stat .value {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }

    .partner-page-stat .value-money {
        font-size: 1.18rem;
    }

    .partner-page-stat small {
        color: #64748b;
    }

    .funders-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        color: #065f46;
        background: #d1fae5;
        font-size: 1.05rem;
    }

    .funders-stat-icon.amber {
        color: #92400e;
        background: #fef3c7;
    }

    .funders-stat-icon.wine {
        color: #522b39;
        background: #f8e8ef;
    }

    .funders-stat-icon.blue {
        color: #075985;
        background: #e0f2fe;
    }

    .funders-panel,
    .funders-table-card {
        border: 1px solid #dbe3ea;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .funders-panel {
        padding: 16px;
    }

    .funders-progress {
        height: 8px;
        border-radius: 999px;
        overflow: hidden;
        background: #e2e8f0;
    }

    .funders-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
    }

    .funders-status-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .funders-status-cloud span {
        border: 1px solid #dbe3ea;
        border-radius: 999px;
        padding: 0.45rem 0.7rem;
        color: #334155;
        background: #f8fafc;
        font-size: 0.82rem;
    }

    .funders-status-cloud strong {
        color: #063f36;
    }

    .funders-type-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .funders-type-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        background: #ffffff;
    }

    .funders-type-card span {
        color: #64748b;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .funders-type-card strong {
        color: #0f172a;
        font-size: 1.1rem;
    }

    .funders-engagement-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        background: #f8fafc;
    }

    .funders-engagement-row span {
        color: #065f46;
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .funders-partner-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 260px;
    }

    .funders-avatar {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        overflow: hidden;
        color: #ffffff;
        background: #063f36;
        font-size: 0.88rem;
        font-weight: 800;
    }

    .funders-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .funders-table-card .table td {
        vertical-align: middle;
    }

    .funders-table-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        white-space: nowrap;
    }

    .funders-icon-action {
        width: 30px;
        height: 30px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #1d4ed8;
        background: #eff6ff;
        font-size: 0.9rem;
        line-height: 1;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .funders-icon-action:hover {
        border-color: #1d4ed8;
        color: #ffffff;
        background: #1d4ed8;
    }

    .funders-icon-action.warning {
        border-color: #fde68a;
        color: #92400e;
        background: #fffbeb;
    }

    .funders-icon-action.warning:hover {
        border-color: #d97706;
        color: #ffffff;
        background: #d97706;
    }

    .min-w-0 {
        min-width: 0;
    }

    @media (max-width: 1199.98px) {
        .funders-stat-grid,
        .funders-insight-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .funders-stat-grid,
        .funders-insight-grid,
        .funders-type-grid {
            grid-template-columns: 1fr;
        }

        .funders-hero {
            padding: 16px;
        }

        .funders-engagement-row {
            flex-direction: column;
        }
    }

    .partner-crm-shell {
        color: #0f172a;
    }

    .partner-crm-hero {
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        padding: 1.5rem;
        background: linear-gradient(135deg, #0f172a 0%, #0f766e 42%, #0ea5e9 100%);
        color: #f8fafc;
    }

    .partner-crm-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 30%),
            radial-gradient(circle at bottom left, rgba(255, 255, 255, 0.14), transparent 35%);
        pointer-events: none;
    }

    .partner-crm-hero > * {
        position: relative;
        z-index: 1;
    }

    .partner-crm-hero h1,
    .partner-crm-hero h2,
    .partner-crm-hero h3,
    .partner-crm-hero h4,
    .partner-crm-hero h5,
    .partner-crm-hero h6,
    .partner-crm-hero .fw-bold,
    .partner-crm-hero .fw-semibold {
        color: #f8fafc !important;
    }

    .partner-crm-avatar {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.18);
        font-size: 1.5rem;
        font-weight: 700;
    }

    .partner-crm-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .partner-crm-kicker {
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.8;
    }

    .partner-crm-metric {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        height: 100%;
    }

    .partner-crm-metric .metric-label {
        color: #64748b;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .partner-crm-metric .metric-value {
        color: #0f172a;
        font-size: 1.55rem;
        font-weight: 700;
    }

    .partner-crm-card {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
    }

    .partner-crm-card .card-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .partner-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
    }

    .partner-detail-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .partner-detail-row span {
        color: #64748b;
        font-size: 0.88rem;
    }

    .partner-detail-row strong {
        color: #0f172a;
        text-align: right;
        max-width: 60%;
    }

    .partner-mini-note {
        background: #f8fafc;
        border-radius: 16px;
        padding: 1rem;
        color: #334155;
    }

    .partner-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .partner-timeline li {
        position: relative;
        padding-left: 1.35rem;
        margin-bottom: 1rem;
    }

    .partner-timeline li:last-child {
        margin-bottom: 0;
    }

    .partner-timeline li::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0.4rem;
        width: 0.65rem;
        height: 0.65rem;
        border-radius: 50%;
        background: #0ea5e9;
        box-shadow: 0 0 0 5px rgba(14, 165, 233, 0.12);
    }

    .partner-timeline .timeline-label {
        color: #64748b;
        font-size: 0.82rem;
    }

    .partner-timeline .timeline-value {
        color: #0f172a;
        font-weight: 600;
    }

    .partner-status-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .partner-status-pills .pill {
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #0f172a;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .partner-crm-modal .modal-dialog {
        max-width: min(1500px, 96vw);
    }

    .partner-crm-modal .modal-content {
        border-radius: 22px;
        background: #f8fafc;
    }

    .partner-crm-modal .modal-header {
        background: #ffffff;
        border-bottom: 1px solid rgba(148, 163, 184, 0.22) !important;
        border-top-left-radius: 22px;
        border-top-right-radius: 22px;
    }

    .partner-crm-modal .modal-title,
    .partner-crm-modal [data-partner-modal-title] {
        color: #0f172a !important;
    }

    .partner-crm-loader {
        min-height: 360px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
    }

    .partner-empty-state {
        border: 1px dashed rgba(148, 163, 184, 0.45);
        border-radius: 18px;
        padding: 1rem;
        color: #64748b;
        background: #f8fafc;
    }

    @media (max-width: 991.98px) {
        .partner-detail-row {
            flex-direction: column;
        }

        .partner-detail-row strong {
            max-width: 100%;
            text-align: left;
        }
    }
</style>

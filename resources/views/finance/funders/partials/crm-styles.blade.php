<style>
    .partner-page-stat {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }

    .partner-page-stat .label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
    }

    .partner-page-stat .value {
        font-size: 1.7rem;
        font-weight: 700;
        color: #0f172a;
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

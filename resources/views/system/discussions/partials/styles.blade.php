<style>
    .discussion-admin {
        --forum-green: #006b3f;
        --forum-green-dark: #003e2d;
        --forum-green-soft: #eaf7f1;
        --forum-gold: #d8a928;
        --forum-ink: #17251f;
        --forum-muted: #65736d;
    }

    .discussion-admin .forum-hero {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(125deg, #003e2d 0%, #006b3f 58%, #18835c 100%);
        box-shadow: 0 14px 35px rgba(0, 62, 45, .18);
    }

    .discussion-admin .forum-hero::after {
        position: absolute;
        top: -85px;
        right: -50px;
        width: 280px;
        height: 280px;
        content: '';
        border: 50px solid rgba(216, 169, 40, .2);
        border-radius: 50%;
    }

    .discussion-admin .forum-hero .card-body {
        position: relative;
        z-index: 1;
        padding: 1.65rem 1.75rem;
    }

    .discussion-admin .forum-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .45rem;
        color: #ffe49a;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .discussion-admin .forum-hero h1 {
        margin-bottom: .4rem;
        color: #fff;
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 800;
    }

    .discussion-admin .forum-hero p {
        max-width: 760px;
        margin: 0;
        color: rgba(255, 255, 255, .78);
    }

    .discussion-admin .forum-primary-btn {
        border: 1px solid var(--forum-gold);
        color: #203026;
        background: #f4ca55;
        font-weight: 700;
        box-shadow: 0 7px 18px rgba(29, 30, 22, .18);
    }

    .discussion-admin .forum-primary-btn:hover,
    .discussion-admin .forum-primary-btn:focus {
        border-color: #ffe39a;
        color: #17251f;
        background: #ffe08a;
    }

    .discussion-admin .forum-nav {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        padding: .55rem;
        border: 1px solid #e3ebe7;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(27, 58, 45, .06);
    }

    .discussion-admin .forum-nav a {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .6rem .85rem;
        border-radius: 9px;
        color: #52615b;
        font-size: .84rem;
        font-weight: 700;
        transition: .2s ease;
    }

    .discussion-admin .forum-nav a:hover,
    .discussion-admin .forum-nav a.active {
        color: var(--forum-green-dark);
        background: var(--forum-green-soft);
    }

    .discussion-admin .forum-stat {
        height: 100%;
        padding: 1rem 1.1rem;
        border: 1px solid #e5ece8;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(27, 58, 45, .055);
    }

    .discussion-admin .forum-stat .stat-icon {
        display: inline-grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 12px;
        color: var(--forum-green);
        background: var(--forum-green-soft);
        font-size: 1.1rem;
    }

    .discussion-admin .forum-stat .stat-value {
        margin-top: .7rem;
        color: var(--forum-ink);
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1;
    }

    .discussion-admin .forum-stat .stat-label {
        margin-top: .32rem;
        color: var(--forum-muted);
        font-size: .78rem;
        font-weight: 600;
    }

    .discussion-admin .forum-panel {
        overflow: hidden;
        border: 1px solid #e5ece8;
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 6px 22px rgba(27, 58, 45, .06);
    }

    .discussion-admin .forum-panel .card-header {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #edf1ef;
        background: #fff;
    }

    .discussion-admin .forum-panel .card-header h2,
    .discussion-admin .forum-panel .card-header h3 {
        margin: 0;
        color: var(--forum-ink);
        font-size: 1rem;
        font-weight: 800;
    }

    .discussion-admin .forum-section-title {
        color: var(--forum-ink);
        font-size: 1rem;
        font-weight: 800;
    }

    .discussion-admin .forum-muted {
        color: var(--forum-muted);
    }

    .discussion-admin .forum-table thead th {
        padding: .85rem 1rem;
        border-top: 0;
        border-bottom: 1px solid #e4ebe7;
        color: #65736d;
        background: #f7faf8;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .discussion-admin .forum-table tbody td {
        padding: .95rem 1rem;
        border-color: #edf2ef;
        vertical-align: middle;
    }

    .discussion-admin .forum-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .34rem .58rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: capitalize;
    }

    .discussion-admin .forum-status::before {
        width: 6px;
        height: 6px;
        content: '';
        border-radius: 50%;
        background: currentColor;
    }

    .discussion-admin .status-open,
    .discussion-admin .status-active,
    .discussion-admin .status-published {
        color: #08734a;
        background: #e5f7ef;
    }

    .discussion-admin .status-pending,
    .discussion-admin .status-draft {
        color: #986900;
        background: #fff4d5;
    }

    .discussion-admin .status-closed,
    .discussion-admin .status-archived {
        color: #5c6771;
        background: #edf1f3;
    }

    .discussion-admin .status-blocked,
    .discussion-admin .status-rejected,
    .discussion-admin .status-removed {
        color: #b42332;
        background: #fdecef;
    }

    .discussion-admin .forum-filter {
        padding: 1.05rem 1.2rem;
        border: 1px solid #e4ebe7;
        border-radius: 14px;
        background: #fff;
    }

    .discussion-admin .form-label {
        margin-bottom: .35rem;
        color: #3a4942;
        font-size: .78rem;
        font-weight: 700;
    }

    .discussion-admin .form-control,
    .discussion-admin .form-select {
        border-color: #dce6e1;
        border-radius: 9px;
    }

    .discussion-admin .form-control:focus,
    .discussion-admin .form-select:focus {
        border-color: #3a9a74;
        box-shadow: 0 0 0 .2rem rgba(0, 107, 63, .1);
    }

    .discussion-admin .forum-avatar {
        display: inline-grid;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        place-items: center;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(145deg, var(--forum-green), #159267);
        font-weight: 800;
        text-transform: uppercase;
    }

    .discussion-admin .forum-empty {
        padding: 3.5rem 1rem;
        text-align: center;
    }

    .discussion-admin .forum-empty .empty-icon {
        display: inline-grid;
        width: 58px;
        height: 58px;
        margin-bottom: .8rem;
        place-items: center;
        border-radius: 16px;
        color: var(--forum-green);
        background: var(--forum-green-soft);
        font-size: 1.35rem;
    }

    .discussion-admin .forum-contribution {
        padding: 1rem;
        border: 1px solid #e3ebe7;
        border-radius: 13px;
        background: #fff;
    }

    .discussion-admin .forum-contribution + .forum-contribution {
        margin-top: .85rem;
    }

    .discussion-admin .forum-resource-group {
        padding: 1rem;
        border: 1px solid #e1ebe6;
        border-radius: 13px;
        background: #f9fbfa;
    }

    .discussion-admin .forum-resource-group + .forum-resource-group {
        margin-top: 1rem;
    }

    .discussion-admin .forum-resource-group h3 {
        color: var(--forum-green-dark);
        font-size: .9rem;
        font-weight: 800;
    }

    .discussion-admin .resource-editor-row {
        padding: .9rem;
        border: 1px solid #dfe9e4;
        border-radius: 11px;
        background: #fff;
    }

    .discussion-admin .resource-editor-row + .resource-editor-row {
        margin-top: .75rem;
    }

    .discussion-admin .forum-upload-dropzone {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .8rem;
        align-items: center;
        padding: 1rem;
        border: 1px dashed #93bbaa;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
    }

    .discussion-admin .forum-upload-dropzone__icon,
    .discussion-admin .forum-uploaded-document__icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 11px;
        background: var(--forum-green-soft);
        color: var(--forum-green);
        font-size: 1.15rem;
    }

    .discussion-admin .forum-upload-dropzone span:nth-child(2) {
        display: grid;
        gap: .2rem;
    }

    .discussion-admin .forum-upload-dropzone small,
    .discussion-admin .forum-uploaded-document small {
        color: var(--forum-muted);
    }

    .discussion-admin .forum-upload-dropzone .form-control {
        grid-column: 1 / -1;
        cursor: pointer;
    }

    .discussion-admin .forum-uploaded-document {
        padding: 1rem;
        border: 1px solid #dfe9e4;
        border-radius: 12px;
        background: #fff;
    }

    .discussion-admin .forum-uploaded-document + .forum-uploaded-document {
        margin-top: .8rem;
    }

    .discussion-admin .forum-uploaded-document__header {
        display: flex;
        gap: .7rem;
        align-items: center;
    }

    .discussion-admin .forum-uploaded-document__header > span:nth-child(2) {
        display: grid;
        gap: .15rem;
        overflow: hidden;
    }

    .discussion-admin .forum-uploaded-document__header > span:nth-child(2) strong {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .discussion-admin .forum-body-preview {
        color: #314039;
        line-height: 1.65;
        white-space: pre-line;
    }

    .discussion-admin .theme-swatch {
        display: inline-block;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, .04);
    }

    .discussion-admin .sticky-actions {
        position: sticky;
        top: 118px;
    }

    @media (max-width: 767.98px) {
        .discussion-admin .forum-hero .card-body {
            padding: 1.3rem;
        }

        .discussion-admin .forum-nav a {
            flex: 1 1 calc(50% - .45rem);
            justify-content: center;
        }

        .discussion-admin .sticky-actions {
            position: static;
        }

        .discussion-admin .forum-uploaded-document__header {
            align-items: flex-start;
            flex-wrap: wrap;
        }
    }
</style>

<style>
    .basv-page {
        --basv-ink: #102a27;
        --basv-muted: #61736f;
        --basv-green: #08765f;
        --basv-green-dark: #075446;
        --basv-green-soft: #e8f5f1;
        --basv-gold: #d7a528;
        --basv-border: #dce7e3;
        --basv-surface: #ffffff;
        color: var(--basv-ink);
    }

    .basv-page .basv-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 1.2rem;
        padding: 1.6rem 1.75rem;
        border-radius: 1.1rem;
        background:
            radial-gradient(circle at 92% 18%, rgba(215, 165, 40, .28), transparent 24%),
            linear-gradient(128deg, #063e35 0%, #08765f 68%, #0a8b70 100%);
        color: #fff;
        box-shadow: 0 18px 42px rgba(7, 84, 70, .2);
    }

    .basv-page .basv-hero::after {
        position: absolute;
        right: -35px;
        bottom: -60px;
        width: 190px;
        height: 190px;
        border: 26px solid rgba(255, 255, 255, .08);
        border-radius: 50%;
        content: "";
    }

    .basv-page .basv-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .5rem;
        color: #dff8ef;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .11em;
        text-transform: uppercase;
    }

    .basv-page .basv-hero h1 {
        margin: 0;
        color: #fff;
        font-size: clamp(1.35rem, 2vw, 2rem);
        font-weight: 800;
        letter-spacing: -.025em;
    }

    .basv-page .basv-hero p {
        max-width: 720px;
        margin: .5rem 0 0;
        color: rgba(255, 255, 255, .82);
        font-size: .86rem;
        line-height: 1.6;
    }

    .basv-page .basv-hero-actions {
        position: relative;
        z-index: 2;
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
    }

    .basv-page .basv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 40px;
        padding: .58rem .9rem;
        border: 1px solid transparent;
        border-radius: .65rem;
        font-size: .76rem;
        font-weight: 800;
        text-decoration: none;
        transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .basv-page .basv-btn:hover:not(:disabled) {
        transform: translateY(-1px);
    }

    .basv-page .basv-btn:disabled {
        cursor: not-allowed;
        opacity: .52;
        box-shadow: none;
        transform: none;
    }

    .basv-page .basv-btn-primary {
        border-color: var(--basv-green);
        background: var(--basv-green);
        color: #fff;
        box-shadow: 0 7px 16px rgba(8, 118, 95, .2);
    }

    .basv-page .basv-btn-primary:hover {
        background: var(--basv-green-dark);
        color: #fff;
    }

    .basv-page .basv-btn-light {
        border-color: rgba(255, 255, 255, .3);
        background: #fff;
        color: var(--basv-green-dark);
    }

    .basv-page .basv-btn-ghost {
        border-color: var(--basv-border);
        background: #fff;
        color: var(--basv-ink);
    }

    .basv-page .basv-btn-danger {
        border-color: #f2d0d0;
        background: #fff5f5;
        color: #a13d3d;
    }

    .basv-page .basv-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .8rem;
        margin-bottom: 1rem;
    }

    .basv-page .basv-stat,
    .basv-page .basv-card {
        border: 1px solid var(--basv-border);
        border-radius: .9rem;
        background: var(--basv-surface);
        box-shadow: 0 8px 24px rgba(15, 42, 39, .055);
    }

    .basv-page .basv-stat {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1rem;
    }

    .basv-page .basv-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 42px;
        height: 42px;
        border-radius: .75rem;
        background: var(--basv-green-soft);
        color: var(--basv-green);
        font-size: 1rem;
    }

    .basv-page .basv-stat strong {
        display: block;
        color: var(--basv-ink);
        font-size: 1.25rem;
        line-height: 1;
    }

    .basv-page .basv-stat span {
        display: block;
        margin-top: .25rem;
        color: var(--basv-muted);
        font-size: .7rem;
        font-weight: 700;
    }

    .basv-page .basv-card {
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .basv-page .basv-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--basv-border);
        background: #fbfdfc;
    }

    .basv-page .basv-card-head h2,
    .basv-page .basv-card-head h3 {
        margin: 0;
        color: var(--basv-ink);
        font-size: .94rem;
        font-weight: 800;
    }

    .basv-page .basv-card-body {
        padding: 1.15rem;
    }

    .basv-page .basv-table {
        width: 100%;
        min-width: 900px;
        margin: 0;
        border-collapse: collapse;
    }

    .basv-page .basv-table th {
        padding: .75rem .9rem;
        border-bottom: 1px solid var(--basv-border);
        background: #f7faf9;
        color: #586b66;
        font-size: .65rem;
        font-weight: 850;
        letter-spacing: .045em;
        text-align: left;
        text-transform: uppercase;
    }

    .basv-page .basv-table td {
        padding: .85rem .9rem;
        border-bottom: 1px solid #edf2f0;
        color: #394d48;
        font-size: .76rem;
        vertical-align: middle;
    }

    .basv-page .basv-table tbody tr:hover {
        background: #fbfdfc;
    }

    .basv-page .basv-record-title {
        color: var(--basv-ink);
        font-weight: 800;
        text-decoration: none;
    }

    .basv-page .basv-record-meta {
        display: block;
        margin-top: .2rem;
        color: var(--basv-muted);
        font-size: .68rem;
    }

    .basv-page .basv-badge {
        display: inline-flex;
        align-items: center;
        gap: .28rem;
        padding: .28rem .55rem;
        border-radius: 999px;
        background: #edf2f0;
        color: #50635e;
        font-size: .63rem;
        font-weight: 850;
        letter-spacing: .03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .basv-page .basv-badge.approved,
    .basv-page .basv-badge.published {
        background: #dcf3e9;
        color: #087052;
    }

    .basv-page .basv-badge.submitted {
        background: #e8efff;
        color: #315eae;
    }

    .basv-page .basv-badge.returned {
        background: #fff1d9;
        color: #925d08;
    }

    .basv-page .basv-badge.draft,
    .basv-page .basv-badge.in_progress {
        background: #eef2f7;
        color: #586577;
    }

    .basv-page .basv-progress {
        overflow: hidden;
        width: 100%;
        height: 7px;
        border-radius: 99px;
        background: #e8eeeb;
    }

    .basv-page .basv-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--basv-green), #15a984);
    }

    .basv-page .basv-empty {
        padding: 3.2rem 1rem;
        color: var(--basv-muted);
        text-align: center;
    }

    .basv-page .basv-empty i {
        display: block;
        margin-bottom: .65rem;
        color: #a9bbb5;
        font-size: 2rem;
    }

    .basv-page .basv-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .basv-page .basv-field-full {
        grid-column: 1 / -1;
    }

    .basv-page .form-label {
        margin-bottom: .38rem;
        color: #405650;
        font-size: .71rem;
        font-weight: 800;
    }

    .basv-page .form-control,
    .basv-page .form-select {
        min-height: 43px;
        border-color: #d5e1dd;
        border-radius: .65rem;
        color: #263c37;
        font-size: .78rem;
    }

    .basv-page textarea.form-control {
        min-height: 96px;
    }

    .basv-page .form-control:focus,
    .basv-page .form-select:focus {
        border-color: #55ae98;
        box-shadow: 0 0 0 .2rem rgba(8, 118, 95, .11);
    }

    .basv-page .basv-help {
        margin-top: .32rem;
        color: var(--basv-muted);
        font-size: .67rem;
        line-height: 1.45;
    }

    .basv-page .basv-alert {
        margin-bottom: 1rem;
        padding: .85rem 1rem;
        border: 1px solid #eed8a9;
        border-radius: .75rem;
        background: #fff9e9;
        color: #725617;
        font-size: .76rem;
    }

    .basv-page .basv-alert.success {
        border-color: #bfe4d7;
        background: #eefaf6;
        color: #116249;
    }

    .basv-page .basv-alert.danger {
        border-color: #efc9c9;
        background: #fff5f5;
        color: #8f3333;
    }

    .basv-page .basv-register-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .45rem;
        white-space: nowrap;
    }

    .basv-team-modal .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 24px 70px rgba(15, 42, 39, .24);
    }

    .basv-team-modal .modal-header {
        gap: .8rem;
        padding: 1.05rem 1.2rem;
        border-bottom: 1px solid #dce7e3;
        background:
            radial-gradient(circle at 92% 10%, rgba(215, 165, 40, .2), transparent 28%),
            linear-gradient(125deg, #f4fbf8 0%, #fff 75%);
    }

    .basv-team-modal .modal-title {
        margin: .08rem 0;
        color: #102a27;
        font-size: 1rem;
        font-weight: 850;
    }

    .basv-team-modal .basv-modal-heading-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 42px;
        height: 42px;
        border-radius: .75rem;
        background: #e8f5f1;
        color: #08765f;
        font-size: 1rem;
    }

    .basv-team-modal .basv-modal-kicker {
        display: block;
        color: #08765f;
        font-size: .62rem;
        font-weight: 850;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .basv-team-modal .basv-modal-meta {
        color: #61736f;
        font-size: .69rem;
        font-weight: 700;
    }

    .basv-team-modal .modal-body {
        padding: 1rem 1.2rem 1.2rem;
        background: #f8fbfa;
    }

    .basv-team-modal .modal-footer {
        padding: .85rem 1.2rem;
        border-top-color: #dce7e3;
        background: #fff;
    }

    .basv-team-modal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .8rem;
    }

    .basv-member-search {
        position: relative;
        flex: 1;
    }

    .basv-member-search > i {
        position: absolute;
        top: 50%;
        left: .85rem;
        z-index: 2;
        color: #738680;
        transform: translateY(-50%);
    }

    .basv-page .basv-member-search .form-control {
        padding-left: 2.45rem;
        background: #fff;
    }

    .basv-selection-count {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: .35rem .7rem;
        border: 1px solid #bfe1d6;
        border-radius: 999px;
        background: #eaf7f3;
        color: #075446;
        font-size: .68rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .basv-assignment-note {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        margin-bottom: .8rem;
        padding: .68rem .75rem;
        border: 1px solid #d5e6df;
        border-radius: .65rem;
        background: #fff;
        color: #526762;
        font-size: .68rem;
        line-height: 1.45;
    }

    .basv-assignment-note i {
        margin-top: .1rem;
        color: #08765f;
    }

    .basv-member-options {
        display: grid;
        gap: .55rem;
    }

    .basv-member-option {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(225px, .55fr);
        align-items: center;
        gap: .8rem;
        padding: .72rem;
        border: 1px solid #dce7e3;
        border-radius: .75rem;
        background: #fff;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .basv-member-option:has(input:checked) {
        border-color: #55ae98;
        box-shadow: 0 0 0 2px rgba(8, 118, 95, .08);
    }

    .basv-member-identity {
        display: flex;
        align-items: center;
        gap: .65rem;
        min-width: 0;
        margin: 0;
        cursor: pointer;
    }

    .basv-member-identity .form-check-input {
        flex: 0 0 auto;
        width: 1.05rem;
        height: 1.05rem;
        margin: 0;
        border-color: #9cafaa;
    }

    .basv-member-identity .form-check-input:checked {
        border-color: #08765f;
        background-color: #08765f;
    }

    .basv-member-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #e8f5f1;
        color: #075446;
        font-size: .72rem;
        font-weight: 850;
    }

    .basv-member-identity > span:last-child {
        min-width: 0;
    }

    .basv-member-identity strong,
    .basv-member-identity small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .basv-member-identity strong {
        color: #253d37;
        font-size: .75rem;
        font-weight: 820;
    }

    .basv-member-identity small {
        color: #6b7d78;
        font-size: .63rem;
        line-height: 1.45;
    }

    .basv-member-option .form-select {
        min-height: 39px;
        background-color: #fff;
        font-size: .69rem;
    }

    .basv-member-empty {
        padding: 2rem 1rem;
        color: #6b7d78;
        text-align: center;
    }

    .basv-member-empty i,
    .basv-member-empty strong,
    .basv-member-empty span {
        display: block;
    }

    .basv-member-empty i {
        margin-bottom: .45rem;
        color: #9caeaa;
        font-size: 1.4rem;
    }

    .basv-member-empty strong {
        color: #405650;
        font-size: .77rem;
    }

    .basv-member-empty span {
        margin-top: .25rem;
        font-size: .67rem;
    }

    .basv-management-label {
        color: #314b44;
        font-size: .74rem;
        font-weight: 850;
    }

    .basv-manage-team-list {
        display: grid;
        gap: .55rem;
    }

    .basv-manage-member {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr) 105px;
        align-items: center;
        gap: .75rem;
        padding: .72rem;
        border: 1px solid #dce7e3;
        border-radius: .75rem;
        background: #fff;
        transition: border-color .15s ease, background .15s ease, opacity .15s ease;
    }

    .basv-manage-member:has([data-managed-leader]:checked) {
        border-color: #55ae98;
        box-shadow: inset 4px 0 0 #08765f;
    }

    .basv-manage-member.is-removing {
        border-color: #edcccc;
        background: #fff7f7;
        opacity: .7;
        box-shadow: none;
    }

    .basv-leader-choice,
    .basv-remove-choice {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        min-height: 36px;
        margin: 0;
        padding: .35rem .58rem;
        border: 1px solid #d4e2dd;
        border-radius: .58rem;
        background: #f7faf9;
        color: #536762;
        font-size: .67rem;
        font-weight: 820;
        cursor: pointer;
    }

    .basv-leader-choice:has(input:checked) {
        border-color: #9bd0bf;
        background: #e8f5f1;
        color: #075446;
    }

    .basv-leader-choice input,
    .basv-remove-choice input {
        width: .95rem;
        height: .95rem;
        margin: 0;
        accent-color: #08765f;
    }

    .basv-remove-choice {
        border-color: #efd3d3;
        background: #fff8f8;
        color: #9b4444;
    }

    .basv-remove-choice:has(input:checked) {
        border-color: #dc9e9e;
        background: #fbeaea;
        color: #8d2929;
    }

    .basv-remove-choice:has(input:disabled) {
        cursor: not-allowed;
        opacity: .48;
    }

    .basv-managed-identity {
        display: flex;
        align-items: center;
        gap: .65rem;
        min-width: 0;
    }

    .basv-managed-identity > span:last-child {
        min-width: 0;
    }

    .basv-managed-identity strong,
    .basv-managed-identity small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .basv-managed-identity strong {
        color: #253d37;
        font-size: .75rem;
        font-weight: 820;
    }

    .basv-managed-identity small {
        color: #6b7d78;
        font-size: .63rem;
        line-height: 1.45;
    }

    .basv-manage-member.is-removing .basv-managed-identity strong {
        text-decoration: line-through;
    }

    .basv-page .basv-team-grid {
        display: grid;
        gap: .65rem;
    }

    .basv-page .basv-team-builder {
        overflow: hidden;
        border: 1px solid var(--basv-border);
        border-radius: .9rem;
        background: #f7fbf9;
    }

    .basv-page .basv-team-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--basv-border);
        background: #fff;
    }

    .basv-page .basv-team-toolbar strong {
        color: var(--basv-ink);
        font-size: .8rem;
        font-weight: 850;
    }

    .basv-page .basv-team-progress {
        height: 5px;
        border-radius: 0;
    }

    .basv-page .basv-team-grid {
        padding: .8rem;
    }

    .basv-page .basv-team-row {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) minmax(150px, .45fr) 38px;
        align-items: center;
        gap: .7rem;
        padding: .75rem;
        border: 1px solid var(--basv-border);
        border-radius: .75rem;
        background: #fff;
        box-shadow: 0 3px 12px rgba(21, 61, 51, .035);
    }

    .basv-page .basv-team-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--basv-green-soft);
        color: var(--basv-green-dark);
        font-size: .72rem;
        font-weight: 850;
    }

    .basv-page .basv-team-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 1px solid #eccdcd;
        border-radius: .6rem;
        background: #fff7f7;
        color: #a84848;
        transition: all .18s ease;
    }

    .basv-page .basv-team-remove:hover:not(:disabled) {
        border-color: #d99f9f;
        background: #fceaea;
        color: #8e2929;
    }

    .basv-page .basv-team-remove:disabled {
        cursor: not-allowed;
        opacity: .38;
    }

    .basv-page .basv-new-staff {
        padding: 1rem;
        border: 1px solid #bfe1d6;
        border-radius: .85rem;
        background: #f1faf7;
        box-shadow: inset 4px 0 0 var(--basv-green);
    }

    .basv-page .basv-new-staff > div:first-child > strong {
        color: var(--basv-green-dark);
        font-size: .82rem;
        font-weight: 850;
    }

    .basv-page .basv-section-nav {
        position: sticky;
        top: 118px;
        display: grid;
        gap: .35rem;
    }

    .basv-page .basv-section-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .62rem .7rem;
        border-radius: .55rem;
        color: #4f635e;
        font-size: .7rem;
        font-weight: 750;
        text-decoration: none;
    }

    .basv-page .basv-section-link:hover {
        background: var(--basv-green-soft);
        color: var(--basv-green-dark);
    }

    .basv-page .basv-question {
        margin-bottom: .8rem;
        padding: 1rem;
        border: 1px solid var(--basv-border);
        border-radius: .85rem;
        background: #fff;
    }

    .basv-page .basv-question-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .85rem;
    }

    .basv-page .basv-question-key {
        display: inline-flex;
        padding: .2rem .4rem;
        border-radius: .35rem;
        background: var(--basv-green-soft);
        color: var(--basv-green-dark);
        font-size: .61rem;
        font-weight: 850;
    }

    .basv-page .basv-question-title {
        margin: .4rem 0 0;
        color: var(--basv-ink);
        font-size: .82rem;
        font-weight: 750;
        line-height: 1.55;
    }

    .basv-page .basv-rating {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .45rem;
    }

    .basv-page .basv-rating label {
        cursor: pointer;
    }

    .basv-page .basv-rating input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .basv-page .basv-rating label > span {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: .45rem;
        border: 1px solid var(--basv-border);
        border-radius: .6rem;
        color: #536762;
        font-size: .67rem;
        font-weight: 800;
        text-align: center;
    }

    .basv-page .basv-rating-title {
        display: block;
    }

    .basv-page .basv-rating-description {
        display: block;
        margin-top: .25rem;
        color: #74837f;
        font-size: .61rem;
        font-weight: 500;
        line-height: 1.35;
    }

    .basv-page .basv-rating input:checked + span {
        border-color: var(--basv-green);
        background: var(--basv-green-soft);
        color: var(--basv-green-dark);
        box-shadow: 0 0 0 2px rgba(8, 118, 95, .09);
    }

    .basv-page .basv-topic {
        margin-bottom: 1rem;
        scroll-margin-top: 125px;
    }

    .basv-page .basv-topic-head {
        padding: .9rem 1rem;
        border-left: 4px solid var(--basv-gold);
        border-radius: .7rem;
        background: #f8fbfa;
    }

    .basv-page .basv-topic-head h3 {
        margin: 0;
        color: var(--basv-ink);
        font-size: .9rem;
        font-weight: 850;
    }

    .basv-page .basv-topic-head p {
        margin: .35rem 0 0;
        color: var(--basv-muted);
        font-size: .7rem;
        line-height: 1.5;
    }

    @media (max-width: 991.98px) {
        .basv-page .basv-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .basv-page .basv-section-nav {
            position: static;
        }
    }

    @media (max-width: 767.98px) {
        .basv-page .basv-hero {
            align-items: flex-start;
            flex-direction: column;
            padding: 1.25rem;
        }

        .basv-page .basv-stats,
        .basv-page .basv-form-grid,
        .basv-page .basv-rating {
            grid-template-columns: 1fr;
        }

        .basv-page .basv-team-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .basv-page .basv-team-toolbar .basv-btn {
            justify-content: center;
            width: 100%;
        }

        .basv-team-modal-toolbar,
        .basv-page .basv-register-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .basv-page .basv-register-actions .basv-btn {
            width: 100%;
        }

        .basv-member-option {
            grid-template-columns: 1fr;
        }

        .basv-manage-member {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .basv-managed-identity {
            grid-column: 1 / -1;
            grid-row: 1;
        }

        .basv-leader-choice,
        .basv-remove-choice {
            grid-row: 2;
        }

        .basv-page .basv-team-row {
            grid-template-columns: 36px minmax(0, 1fr) 36px;
        }

        .basv-page .basv-team-row .team-role {
            grid-column: 2;
        }

        .basv-page .basv-team-remove {
            grid-column: 3;
            grid-row: 1;
        }
    }
</style>

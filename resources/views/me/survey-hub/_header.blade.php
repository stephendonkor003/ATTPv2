@php
    $active = $active ?? 'overview';
    $title = $title ?? 'Survey Workspace';
    $subtitle = $subtitle ?? 'Manage questionnaires, responses, and QR-based sharing from one place.';
    $eyebrow = $eyebrow ?? 'M&E Survey Workspace';
    $heroActions = $heroActions ?? [];
@endphp

@once
    @push('styles')
        <style>
            .survey-shell {
                display: grid;
                gap: 1rem;
            }

            .survey-hero {
                position: relative;
                overflow: hidden;
                border: 0;
                border-radius: 24px;
                padding: 1.5rem;
                color: #f8fafc;
                background:
                    radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 28%),
                    linear-gradient(135deg, #0b132b 0%, #0f766e 52%, #d97706 100%);
                box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18);
            }

            .survey-hero__eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                margin-bottom: 0.7rem;
                padding: 0.35rem 0.8rem;
                border-radius: 999px;
                background: rgba(248, 250, 252, 0.12);
                border: 1px solid rgba(248, 250, 252, 0.22);
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .survey-hero h3 {
                margin-bottom: 0.5rem;
                font-size: 1.9rem;
                font-weight: 700;
                letter-spacing: -0.02em;
            }

            .survey-hero p {
                margin-bottom: 0;
                max-width: 68ch;
                color: rgba(248, 250, 252, 0.88);
            }

            .survey-hero__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-top: 1.1rem;
            }

            .survey-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 0.7rem;
                padding: 0.85rem;
                border: 1px solid #dbe4ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
            }

            .survey-nav__link {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                padding: 0.72rem 1rem;
                border-radius: 14px;
                color: #475569;
                font-size: 0.88rem;
                font-weight: 600;
                text-decoration: none;
                border: 1px solid transparent;
                transition: all 0.18s ease;
            }

            .survey-nav__link:hover {
                color: #0f172a;
                background: #f8fafc;
                border-color: #dbe4ef;
            }

            .survey-nav__link.is-active {
                color: #ffffff;
                background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
                box-shadow: 0 10px 20px rgba(15, 23, 42, 0.16);
            }

            .survey-stat {
                height: 100%;
                border: 1px solid #dbe4ef;
                border-radius: 18px;
                background: #ffffff;
                box-shadow: 0 14px 26px rgba(15, 23, 42, 0.05);
            }

            .survey-stat__label {
                color: #64748b;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .survey-stat__value {
                color: #0f172a;
                font-size: 1.6rem;
                font-weight: 700;
                line-height: 1.08;
            }

            .survey-stat__meta {
                color: #64748b;
                font-size: 0.84rem;
            }

            .survey-panel {
                border: 1px solid #dbe4ef;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            .survey-panel__header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
                padding: 1.15rem 1.2rem 0.8rem;
            }

            .survey-panel__title {
                color: #0f172a;
                font-size: 1rem;
                font-weight: 700;
                margin-bottom: 0.2rem;
            }

            .survey-panel__subtitle {
                color: #64748b;
                font-size: 0.86rem;
                margin-bottom: 0;
            }

            .survey-search {
                border: 1px solid #dbe4ef;
                border-radius: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
            }

            .survey-table {
                margin-bottom: 0;
            }

            .survey-table thead th {
                background: #f8fafc;
                color: #475569;
                font-size: 0.74rem;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .survey-table td {
                vertical-align: top;
                font-size: 0.88rem;
            }

            .survey-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.3rem 0.68rem;
                border-radius: 999px;
                font-size: 0.74rem;
                font-weight: 700;
                border: 1px solid transparent;
            }

            .survey-chip.success {
                color: #166534;
                background: #dcfce7;
                border-color: #86efac;
            }

            .survey-chip.warning {
                color: #92400e;
                background: #fef3c7;
                border-color: #fcd34d;
            }

            .survey-chip.danger {
                color: #991b1b;
                background: #fee2e2;
                border-color: #fca5a5;
            }

            .survey-chip.secondary {
                color: #334155;
                background: #e2e8f0;
                border-color: #cbd5e1;
            }

            .survey-muted {
                color: #64748b;
            }

            .survey-action-tile {
                border: 1px solid #dbe4ef;
                border-radius: 18px;
                padding: 1rem;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                height: 100%;
            }

            .survey-action-tile__icon {
                width: 2.5rem;
                height: 2.5rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 14px;
                background: rgba(15, 118, 110, 0.1);
                color: #0f766e;
                font-size: 1.1rem;
                margin-bottom: 0.8rem;
            }

            .survey-action-tile__title {
                color: #0f172a;
                font-weight: 700;
                margin-bottom: 0.25rem;
            }

            .survey-action-tile__text {
                color: #64748b;
                font-size: 0.86rem;
                margin-bottom: 0.9rem;
            }

            .survey-qr-card {
                height: 100%;
                border: 1px solid #dbe4ef;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            .survey-qr-card__image {
                background: linear-gradient(180deg, #f8fafc 0%, #eef6ff 100%);
                padding: 1.2rem;
                text-align: center;
                border-bottom: 1px solid #e2e8f0;
            }

            .survey-qr-card__image img {
                width: 100%;
                max-width: 220px;
                aspect-ratio: 1;
                object-fit: contain;
                border-radius: 18px;
                background: #ffffff;
                padding: 0.65rem;
                border: 1px solid #dbe4ef;
            }

            .survey-empty {
                padding: 2rem 1rem;
                text-align: center;
                color: #64748b;
            }

            @media (max-width: 991.98px) {
                .survey-hero {
                    padding: 1.2rem;
                    border-radius: 18px;
                }

                .survey-hero h3 {
                    font-size: 1.45rem;
                }

                .survey-nav {
                    padding: 0.7rem;
                    border-radius: 16px;
                }

                .survey-nav__link {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
    @endpush
@endonce

<div class="survey-shell mb-4">
    <div class="survey-hero">
        <span class="survey-hero__eyebrow">
            <i class="feather-clipboard"></i> {{ $eyebrow }}
        </span>
        <h3>{{ $title }}</h3>
        <p>{{ $subtitle }}</p>

        @if (!empty($heroActions))
            <div class="survey-hero__actions">
                @foreach ($heroActions as $action)
                    <a href="{{ $action['href'] ?? '#' }}" class="{{ $action['class'] ?? 'btn btn-light btn-sm' }}">
                        @if (!empty($action['icon']))
                            <i class="{{ $action['icon'] }} me-1"></i>
                        @endif
                        {{ $action['label'] ?? 'Open' }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="survey-nav">
        <a href="{{ route('budget.me.surveys.index') }}" class="survey-nav__link {{ $active === 'overview' ? 'is-active' : '' }}">
            <i class="feather-home"></i> Overview
        </a>
        <a href="{{ route('budget.me.surveys.responses') }}" class="survey-nav__link {{ $active === 'responses' ? 'is-active' : '' }}">
            <i class="feather-inbox"></i> Responses
        </a>
        <a href="{{ route('budget.me.surveys.reports') }}" class="survey-nav__link {{ $active === 'reports' ? 'is-active' : '' }}">
            <i class="feather-bar-chart-2"></i> Reports
        </a>
        <a href="{{ route('budget.me.surveys.questionnaires') }}" class="survey-nav__link {{ $active === 'questionnaires' ? 'is-active' : '' }}">
            <i class="feather-book-open"></i> Questionnaire Library
        </a>
        @can('me.configuration.manage')
            <a href="{{ route('budget.me.surveys.questionnaires.create') }}" class="survey-nav__link {{ $active === 'create' ? 'is-active' : '' }}">
                <i class="feather-plus-square"></i> Add Questionnaire
            </a>
        @endcan
        <a href="{{ route('budget.me.surveys.qr') }}" class="survey-nav__link {{ $active === 'qr' ? 'is-active' : '' }}">
            <i class="feather-grid"></i> Generate QR Code
        </a>
    </div>
</div>

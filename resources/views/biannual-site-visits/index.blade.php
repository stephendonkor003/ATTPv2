@extends('layouts.app')

@section('title', 'Bi-Annual Site Visits')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-page .basv-stats.basv-stats-five {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .basv-page .basv-inactive-row {
            background: #fbfbfc;
        }

        .basv-page .basv-inactive-row > td:not(:last-child) {
            opacity: .72;
        }

        .basv-page .basv-template-ready {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid #cfe2d7;
            border-left: 4px solid #198754;
            border-radius: 14px;
            background: linear-gradient(135deg, #f4fbf7 0%, #fff 72%);
            box-shadow: 0 8px 24px rgba(24, 90, 58, .06);
        }

        .basv-page .basv-template-ready-main {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            min-width: 0;
        }

        .basv-page .basv-template-ready-icon {
            display: grid;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            background: #198754;
            color: #fff;
            font-size: 1.05rem;
        }

        .basv-page .basv-template-ready h2 {
            margin: .1rem 0 .25rem;
            color: #183b2b;
            font-size: 1rem;
            font-weight: 800;
        }

        .basv-page .basv-template-ready-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .75rem;
            color: var(--basv-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .basv-page .basv-template-ready-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .45rem;
        }

        #add-team-members-modal .basv-member-picker-dialog {
            width: calc(100vw - 2rem);
            max-width: 1480px;
            margin: 1rem auto;
        }

        #add-team-members-modal .modal-content {
            height: calc(100vh - 2rem);
            max-height: 920px;
        }

        #add-team-members-modal .modal-body {
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow-y: auto;
        }

        #add-team-members-modal .basv-member-directory {
            display: flex;
            flex: 1 1 440px;
            flex-direction: column;
            min-height: 320px;
            overflow: hidden;
            border: 1px solid #d7e4df;
            border-radius: .9rem;
            background: #f5f9f7;
        }

        #add-team-members-modal .basv-member-directory-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            border-bottom: 1px solid #d7e4df;
            background: #fff;
        }

        #add-team-members-modal .basv-member-directory-head strong,
        #add-team-members-modal .basv-member-directory-head span {
            display: block;
        }

        #add-team-members-modal .basv-member-directory-head strong {
            color: #203d35;
            font-size: .8rem;
            font-weight: 850;
        }

        #add-team-members-modal .basv-member-directory-head span {
            margin-top: .12rem;
            color: #687b75;
            font-size: .66rem;
        }

        #add-team-members-modal .basv-directory-count {
            flex: 0 0 auto;
            padding: .4rem .7rem;
            border-radius: 999px;
            background: #e8f5f1;
            color: #075446;
            font-size: .68rem;
            font-weight: 850;
            white-space: nowrap;
        }

        #add-team-members-modal .basv-member-directory-scroll {
            flex: 1 1 auto;
            min-height: 0;
            padding: .75rem;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        #add-team-members-modal .basv-member-options {
            grid-template-columns: repeat(auto-fill, minmax(390px, 1fr));
        }

        #add-team-members-modal .basv-member-option {
            grid-template-columns: minmax(0, 1fr);
            align-content: start;
        }

        #add-team-members-modal .basv-member-option.is-assigned {
            border-color: #c9d4d0;
            background: #f1f4f3;
        }

        #add-team-members-modal .basv-member-role-control {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        #add-team-members-modal .basv-member-role-control .form-control {
            min-width: 0;
        }

        #add-team-members-modal .basv-account-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem;
            margin-top: .3rem;
        }

        #add-team-members-modal .basv-account-badge {
            display: inline-flex;
            align-items: center;
            width: max-content;
            padding: .18rem .42rem;
            border-radius: 999px;
            background: #edf2f0;
            color: #536761;
            font-size: .56rem;
            font-weight: 850;
            line-height: 1.2;
        }

        #add-team-members-modal .basv-account-badge.is-active {
            background: #e7f7ef;
            color: #147348;
        }

        #add-team-members-modal .basv-account-badge.is-disabled,
        #add-team-members-modal .basv-account-badge.is-deactivated {
            background: #fff2d6;
            color: #8a5a09;
        }

        #add-team-members-modal .basv-account-badge.is-blacklisted {
            background: #fdeaea;
            color: #a33838;
        }

        #add-team-members-modal .basv-account-badge.is-assigned-badge {
            background: #e5e9e7;
            color: #43554f;
        }

        @media (max-width: 1199.98px) {
            .basv-page .basv-stats.basv-stats-five {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .basv-page .basv-template-ready {
                align-items: stretch;
                flex-direction: column;
            }

            .basv-page .basv-template-ready-actions {
                justify-content: flex-start;
            }

            #add-team-members-modal .basv-member-picker-dialog {
                width: calc(100vw - 1rem);
                margin: .5rem auto;
            }

            #add-team-members-modal .modal-content {
                height: calc(100vh - 1rem);
            }

            #add-team-members-modal .basv-member-options {
                grid-template-columns: 1fr;
            }

            #add-team-members-modal .basv-member-directory-head {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-activity"></i> Monitoring &amp; Evaluation</span>
                    <h1>Bi-Annual Site Visits</h1>
                    <p>Plan H1 and H2 monitoring visits, coordinate flexible assessment teams, and complete the
                        configurable Think Tank questionnaire in one auditable workflow.</p>
                </div>
                <div class="basv-hero-actions">
                    @canany(['biannual_site_visits.view', 'biannual_site_visits.approve', 'biannual_site_visits.export'])
                        <a href="{{ route('biannual-site-visits.reports.submitted') }}" class="basv-btn basv-btn-light">
                            <i class="feather-file-text"></i> Submitted Reports
                        </a>
                    @endcanany
                    @can('biannual_site_visits.templates.manage')
                        <a href="{{ route('biannual-site-visits.templates.index') }}" class="basv-btn basv-btn-light">
                            <i class="feather-sliders"></i> Questionnaire Builder
                        </a>
                    @endcan
                    @can('biannual_site_visits.create')
                        <a href="{{ route('biannual-site-visits.create') }}" class="basv-btn basv-btn-light">
                            <i class="feather-plus"></i> Schedule Visit
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('success'))
                <div class="basv-alert success"><i class="feather-check-circle me-1"></i>{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="basv-stats basv-stats-five">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-calendar"></i></span>
                    <div><strong>{{ number_format($stats['total'] ?? 0) }}</strong><span>Total visits</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-edit-3"></i></span>
                    <div><strong>{{ number_format($stats['active'] ?? 0) }}</strong><span>In progress</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-clock"></i></span>
                    <div><strong>{{ number_format($stats['submitted'] ?? 0) }}</strong><span>Awaiting review</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-circle"></i></span>
                    <div><strong>{{ number_format($stats['approved'] ?? 0) }}</strong><span>Approved</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-slash"></i></span>
                    <div><strong>{{ number_format($stats['inactive'] ?? 0) }}</strong><span>Deactivated</span></div>
                </div>
            </div>

            @if ($defaultTemplate)
                <section class="basv-template-ready" aria-label="Default questionnaire">
                    <div class="basv-template-ready-main">
                        <span class="basv-template-ready-icon"><i class="feather-check-square"></i></span>
                        <div>
                            <span class="basv-eyebrow">Default questionnaire ready</span>
                            <h2>{{ $defaultTemplate->name }} · v{{ $defaultTemplate->version }}</h2>
                            <div class="basv-template-ready-meta">
                                <span><i class="feather-layers me-1"></i>{{ number_format($defaultTemplate->sections_count) }} sections</span>
                                <span><i class="feather-help-circle me-1"></i>{{ number_format($defaultTemplate->questions_count) }} questions</span>
                                <span><i class="feather-lock me-1"></i>Published and ready to schedule</span>
                            </div>
                        </div>
                    </div>
                    <div class="basv-template-ready-actions">
                        @canany(['biannual_site_visits.create', 'biannual_site_visits.templates.manage'])
                            <a href="{{ route('biannual-site-visits.templates.preview', $defaultTemplate) }}"
                                class="basv-btn basv-btn-ghost">
                                <i class="feather-eye"></i> Preview
                            </a>
                        @endcanany
                        @can('biannual_site_visits.templates.manage')
                            <form method="POST"
                                action="{{ route('biannual-site-visits.templates.editable-draft', $defaultTemplate) }}">
                                @csrf
                                <button type="submit" class="basv-btn basv-btn-ghost">
                                    <i class="feather-edit-2"></i> Edit questionnaire
                                </button>
                            </form>
                            <a href="{{ route('biannual-site-visits.templates.index') }}"
                                class="basv-btn basv-btn-ghost">
                                <i class="feather-sliders"></i> Template library
                            </a>
                        @endcan
                        @can('biannual_site_visits.create')
                            <a href="{{ route('biannual-site-visits.create') }}" class="basv-btn basv-btn-primary">
                                <i class="feather-calendar"></i> Schedule with this template
                            </a>
                        @endcan
                    </div>
                </section>
            @endif

            @if ($canManageTemplates && $questionnaireTemplates->isNotEmpty())
                <section class="basv-card" aria-labelledby="questionnaire-templates-title">
                    <div class="basv-card-head">
                        <div>
                            <h2 id="questionnaire-templates-title">
                                <i class="feather-file-text me-2"></i>Questionnaire templates
                            </h2>
                            <div class="basv-help">Edit drafts directly. Each published or archived version opens or creates its own editable draft.</div>
                        </div>
                        <a href="{{ route('biannual-site-visits.templates.index') }}" class="basv-btn basv-btn-ghost">
                            Full template library <i class="feather-arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="basv-table">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Version</th>
                                    <th>Structure</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($questionnaireTemplates as $questionnaireTemplate)
                                    <tr>
                                        <td>
                                            <strong class="basv-record-title">{{ $questionnaireTemplate->name }}</strong>
                                            <span class="basv-record-meta">{{ $questionnaireTemplate->code }}</span>
                                        </td>
                                        <td>
                                            <strong>v{{ $questionnaireTemplate->version }}</strong>
                                            @if ($questionnaireTemplate->is_default)
                                                <span class="basv-record-meta">Default</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ number_format($questionnaireTemplate->sections_count) }} sections</strong>
                                            <span class="basv-record-meta">{{ number_format($questionnaireTemplate->questions_count) }} questions</span>
                                        </td>
                                        <td>
                                            <span class="basv-badge {{ $questionnaireTemplate->status }}">
                                                {{ ucfirst($questionnaireTemplate->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="basv-register-actions">
                                                <a href="{{ route('biannual-site-visits.templates.preview', $questionnaireTemplate) }}"
                                                    class="basv-btn basv-btn-ghost">
                                                    <i class="feather-eye"></i> Preview
                                                </a>
                                                @if ($questionnaireTemplate->isDraft())
                                                    <a href="{{ route('biannual-site-visits.templates.edit', $questionnaireTemplate) }}"
                                                        class="basv-btn basv-btn-primary">
                                                        <i class="feather-edit-2"></i> Edit &amp; update
                                                    </a>
                                                @else
                                                    <form method="POST"
                                                        action="{{ route('biannual-site-visits.templates.editable-draft', $questionnaireTemplate) }}">
                                                        @csrf
                                                        <button type="submit" class="basv-btn basv-btn-primary">
                                                            <i class="feather-edit-2"></i> Edit as new version
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div class="basv-card">
                <div class="basv-card-head">
                    <h2><i class="feather-map-pin me-2"></i>Monitoring visit register</h2>
                    <form method="GET" class="d-flex gap-2">
                        <select name="lifecycle" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach (['active' => 'Active records', 'inactive' => 'Deactivated records', 'all' => 'All records'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('lifecycle', 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach (['draft' => 'Draft', 'returned' => 'Returned', 'in_progress' => 'In progress', 'submitted' => 'Submitted', 'approved' => 'Approved'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="cycle_year" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All years</option>
                            @foreach ($years ?? [] as $year)
                                <option value="{{ $year }}" @selected((string) request('cycle_year') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    @if ($visits->isEmpty())
                        <div class="basv-empty">
                            <i class="feather-map"></i>
                            <strong>No bi-annual visits found</strong>
                            <div class="mt-1">
                                @if ($defaultTemplate)
                                    The questionnaire is ready. Schedule the first H1 or H2 visit to create a visit record.
                                @else
                                    Publish a questionnaire, then schedule the first H1 or H2 monitoring visit.
                                @endif
                            </div>
                        </div>
                    @else
                        <table class="basv-table">
                            <thead>
                                <tr>
                                    <th>Visit</th>
                                    <th>Think Tank</th>
                                    <th>Cycle</th>
                                    <th>Team</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visits as $visit)
                                    @php
                                        $status = $visit->siteVisit?->status ?: 'draft';
                                        $isActive = (bool) $visit->is_active;
                                        $isMutable = in_array(
                                            $status,
                                            \App\Models\BiAnnualSiteVisitProfile::MUTABLE_WORKFLOW_STATUSES,
                                            true
                                        );
                                        $progress = (float) ($visit->completion_percentage ?? 0);
                                        $teamSpecialisms = (array) data_get($visit->settings, 'team_specialisms', []);
                                        $teamRoster = $visit->siteVisit?->group?->members
                                            ?->map(fn ($member) => [
                                                'id' => (string) $member->user_id,
                                                'name' => $member->user?->name ?: 'Monitoring team member',
                                                'email' => $member->user?->email ?: 'No email recorded',
                                                'specialism' => $teamSpecialisms[(string) $member->user_id] ?? '',
                                                'is_leader' => (string) $member->user_id
                                                    === (string) $visit->siteVisit?->group?->leader_id,
                                            ])
                                            ->values() ?? collect();
                                    @endphp
                                    <tr @class(['basv-inactive-row' => ! $isActive])>
                                        <td>
                                            <a class="basv-record-title"
                                                href="{{ route('biannual-site-visits.show', $visit) }}">
                                                {{ $visit->title ?: 'Monitoring Site Visit' }}
                                            </a>
                                            <span class="basv-record-meta">{{ $visit->reference_number }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->thinkTank?->name ?? '—' }}</strong>
                                            <span class="basv-record-meta">{{ $visit->thinkTank?->country }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->cycleLabel() }}</strong>
                                            <span class="basv-record-meta">
                                                {{ optional($visit->starts_on)->format('d M Y') }}
                                                @if ($visit->ends_on)
                                                    – {{ $visit->ends_on->format('d M Y') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->siteVisit?->group?->members?->count() ?? 0 }} members</strong>
                                            <span class="basv-record-meta">
                                                Lead: {{ $visit->siteVisit?->group?->leader?->name ?? 'Not set' }}
                                            </span>
                                        </td>
                                        <td style="min-width: 130px">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>{{ round($progress) }}%</small>
                                            </div>
                                            <div class="basv-progress"><span style="width: {{ min(100, $progress) }}%"></span></div>
                                        </td>
                                        <td>
                                            @if ($isActive)
                                                <span class="basv-badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span>
                                            @else
                                                <span class="basv-badge inactive">Inactive</span>
                                                <span class="basv-record-meta">Workflow: {{ str_replace('_', ' ', $status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="basv-register-actions">
                                                @if ($canManageVisits && $isMutable)
                                                    @if ($isActive)
                                                        <a href="{{ route('biannual-site-visits.edit', $visit) }}"
                                                            class="basv-btn basv-btn-ghost">
                                                            <i class="feather-edit-3"></i> Edit
                                                        </a>
                                                        <button type="button" class="basv-btn basv-btn-danger"
                                                            data-deactivate-visit
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deactivate-visit-modal"
                                                            data-action="{{ route('biannual-site-visits.deactivate', $visit) }}"
                                                            data-visit-id="{{ $visit->id }}"
                                                            data-visit-title="{{ $visit->title ?: 'Monitoring Site Visit' }}"
                                                            data-visit-reference="{{ $visit->reference_number }}">
                                                            <i class="feather-slash"></i> Deactivate
                                                        </button>
                                                    @else
                                                        <form method="POST" action="{{ route('biannual-site-visits.reactivate', $visit) }}"
                                                            onsubmit="return confirm('Reactivate this scheduled site visit with all previous responses?')">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="basv-btn basv-btn-primary">
                                                                <i class="feather-refresh-cw"></i> Reactivate
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                                @if ($canManageTeams && $isActive && $isMutable)
                                                    <button type="button" class="basv-btn basv-btn-primary"
                                                        data-add-team-members
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#add-team-members-modal"
                                                        data-action="{{ route('biannual-site-visits.team-members.store', $visit) }}"
                                                        data-visit-id="{{ $visit->id }}"
                                                        data-visit-title="{{ $visit->title ?: 'Monitoring Site Visit' }}"
                                                        data-visit-reference="{{ $visit->reference_number }}"
                                                        data-existing-members="{{ $visit->siteVisit?->group?->members?->pluck('user_id')->values()->toJson() ?: '[]' }}">
                                                        <i class="feather-user-plus"></i> Add members
                                                    </button>
                                                    <button type="button" class="basv-btn basv-btn-ghost"
                                                        data-manage-team
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#manage-team-modal"
                                                        data-action="{{ route('biannual-site-visits.team.update', $visit) }}"
                                                        data-visit-id="{{ $visit->id }}"
                                                        data-visit-title="{{ $visit->title ?: 'Monitoring Site Visit' }}"
                                                        data-visit-reference="{{ $visit->reference_number }}"
                                                        data-team-roster="{{ $teamRoster->toJson() }}">
                                                        <i class="feather-settings"></i> Manage team
                                                    </button>
                                                @endif
                                                <a href="{{ route('biannual-site-visits.show', $visit) }}"
                                                    class="basv-btn basv-btn-ghost">
                                                    Open <i class="feather-arrow-right"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (method_exists($visits, 'links'))
                            <div class="p-3">{{ $visits->withQueryString()->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </main>

    @if ($canManageVisits)
        <div class="modal fade basv-team-modal" id="deactivate-visit-modal" tabindex="-1"
            aria-labelledby="deactivate-visit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="#" class="modal-content basv-page" id="deactivate-visit-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_deactivate_visit_id" id="deactivate-visit-id"
                        value="{{ old('_deactivate_visit_id') }}">
                    <div class="modal-header">
                        <div class="basv-modal-heading-icon"><i class="feather-slash" aria-hidden="true"></i></div>
                        <div class="flex-grow-1">
                            <span class="basv-modal-kicker">Reversible lifecycle change</span>
                            <h2 class="modal-title" id="deactivate-visit-title">Deactivate scheduled visit</h2>
                            <div class="basv-modal-meta" id="deactivate-visit-reference"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="basv-assignment-note mb-3">
                            <i class="feather-shield" aria-hidden="true"></i>
                            <span>The visit will become read-only. Its team, questionnaire responses, and audit history will not be deleted.</span>
                        </div>
                        <label class="form-label" for="deactivation_reason">Reason for deactivation</label>
                        <textarea class="form-control" id="deactivation_reason" name="deactivation_reason"
                            maxlength="1000" required placeholder="Explain why this scheduled visit should no longer be active.">{{ old('deactivation_reason') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="basv-btn basv-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="basv-btn basv-btn-danger">
                            <i class="feather-slash"></i> Deactivate visit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canManageTeams)
        <div class="modal fade basv-team-modal" id="add-team-members-modal" tabindex="-1"
            aria-labelledby="add-team-members-title" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered basv-member-picker-dialog">
                <form method="POST" action="#" class="modal-content basv-page" id="add-team-members-form">
                    @csrf
                    <input type="hidden" name="_team_visit_id" id="team-assignment-visit-id"
                        value="{{ old('_team_visit_id') }}">

                    <div class="modal-header">
                        <div class="basv-modal-heading-icon">
                            <i class="feather-users" aria-hidden="true"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="basv-modal-kicker">Monitoring team assignment</span>
                            <h2 class="modal-title" id="add-team-members-title">Add team members</h2>
                            <div class="basv-modal-meta" id="team-assignment-reference">
                                Browse every system user account or create an additional monitoring-team member.
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close team member assignment"></button>
                    </div>

                    <div class="modal-body">
                        @if (old('_team_visit_id') && $errors->any())
                            <div class="basv-alert danger mb-3" id="team-member-server-errors"
                                role="alert" tabindex="-1">
                                <strong>These members could not be added:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="basv-team-modal-toolbar">
                            <div class="basv-member-search">
                                <i class="feather-search" aria-hidden="true"></i>
                                <label class="visually-hidden" for="team-member-search">Search monitoring-team members</label>
                                <input type="search" class="form-control" id="team-member-search"
                                    aria-controls="team-member-options"
                                    placeholder="Live search by name, email, role, account type, or status">
                            </div>
                            <span class="basv-selection-count" id="team-selection-count"
                                aria-live="polite" aria-atomic="true">0 selected</span>
                        </div>

                        <div class="basv-assignment-note">
                            <i class="feather-info" aria-hidden="true"></i>
                            <span>All system accounts are included regardless of type or account status. Assignment does not reactivate or otherwise change an account.</span>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                            <button type="button" class="basv-btn basv-btn-primary" id="show-additional-member-form"
                                aria-expanded="false" aria-controls="additional-member-panel">
                                <i class="feather-user-plus" aria-hidden="true"></i>
                                Create monitoring-team member
                            </button>
                        </div>

                        <div class="basv-new-staff mb-3" id="additional-member-panel" hidden>
                            <div>
                                <strong>Create a member without leaving this visit</strong>
                                <div class="basv-help">
                                    A limited monitoring-team account will be created when you save. The member will receive temporary login details and the visit assignment by email.
                                </div>
                            </div>
                            <div class="basv-form-grid mt-3">
                                <div>
                                    <label class="form-label" for="additional_member_name">Full name</label>
                                    <input class="form-control" id="additional_member_name" maxlength="255"
                                        autocomplete="name" placeholder="Member's full name">
                                </div>
                                <div>
                                    <label class="form-label" for="additional_member_email">Email address</label>
                                    <input class="form-control" id="additional_member_email" type="email"
                                        maxlength="255" autocomplete="email" placeholder="name@example.org">
                                </div>
                                <div>
                                    <label class="form-label" for="additional_member_specialism">Specialist role</label>
                                    <input class="form-control" id="additional_member_specialism"
                                        list="biannual-specialist-role-options" maxlength="255" autocomplete="off"
                                        placeholder="Choose or enter a role">
                                </div>
                            </div>
                            <div class="basv-help text-danger" id="additional-member-error" role="alert" hidden></div>
                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                <button type="button" class="basv-btn basv-btn-ghost"
                                    id="cancel-additional-member">Cancel</button>
                                <button type="button" class="basv-btn basv-btn-primary"
                                    id="add-additional-member-to-selection">
                                    <i class="feather-user-check" aria-hidden="true"></i>
                                    Add member to selection
                                </button>
                            </div>
                        </div>

                        <div class="basv-member-directory">
                            <div class="basv-member-directory-head">
                                <div>
                                    <strong>Complete system user directory</strong>
                                    <span>Active and inactive accounts—including disabled, deactivated, blacklisted, and every account type—are shown.</span>
                                </div>
                                <div class="basv-directory-count" id="team-directory-count"
                                    aria-live="polite" aria-atomic="true">
                                    {{ number_format($teamAssignableUsers->count()) }} of
                                    {{ number_format($teamAssignableUsers->count()) }} accounts shown
                                </div>
                            </div>
                            <div class="basv-member-directory-scroll">
                                <div class="basv-member-options" id="team-member-options">
                                    @forelse ($teamAssignableUsers as $staff)
                                        @php
                                            $staffName = trim((string) $staff->name) ?: 'Unnamed account';
                                            $staffEmail = trim((string) $staff->email) ?: 'No email address';
                                            $staffRole = trim((string) $staff->role?->name) ?: 'No system role';
                                            $staffType = \Illuminate\Support\Str::headline(
                                                trim((string) $staff->user_type) ?: 'Unspecified account'
                                            );
                                            if ($staff->is_disabled) {
                                                if ($staff->disabled_until?->isFuture()) {
                                                    $staffStatus = 'Temporarily disabled';
                                                    $staffStatusClass = 'is-disabled';
                                                } elseif ($staff->disabled_until) {
                                                    $staffStatus = 'Disabled (block expired)';
                                                    $staffStatusClass = 'is-disabled';
                                                } else {
                                                    $staffStatus = 'Deactivated';
                                                    $staffStatusClass = 'is-deactivated';
                                                }
                                            } else {
                                                $staffStatus = 'Active';
                                                $staffStatusClass = 'is-active';
                                            }
                                            $staffSearch = \Illuminate\Support\Str::lower(implode(' ', [
                                                $staffName,
                                                $staffEmail,
                                                $staffRole,
                                                $staffType,
                                                $staffStatus,
                                                $staff->is_disabled ? 'inactive disabled' : '',
                                                $staff->is_blacklisted ? 'blacklisted' : '',
                                            ]));
                                        @endphp
                                        <div class="basv-member-option" data-member-option
                                            data-search="{{ $staffSearch }}" data-user-id="{{ $staff->id }}"
                                            data-email="{{ \Illuminate\Support\Str::lower((string) $staff->email) }}"
                                            data-account-type="{{ \Illuminate\Support\Str::lower($staffType) }}"
                                            data-account-status="{{ \Illuminate\Support\Str::lower($staffStatus) }}">
                                            <label class="basv-member-identity" for="team-member-{{ $staff->id }}">
                                                <input class="form-check-input" type="checkbox"
                                                    name="team_members[]" value="{{ $staff->id }}"
                                                    id="team-member-{{ $staff->id }}" data-team-member-checkbox>
                                                <span class="basv-member-avatar">
                                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($staffName, 0, 1)) }}
                                                </span>
                                                <span>
                                                    <strong>{{ $staffName }}</strong>
                                                    <small>{{ $staffEmail }}</small>
                                                    <small>{{ $staffRole }}</small>
                                                    <span class="basv-account-badges">
                                                        <span class="basv-account-badge">{{ $staffType }}</span>
                                                        <span class="basv-account-badge {{ $staffStatusClass }}">{{ $staffStatus }}</span>
                                                        @if ($staff->is_blacklisted)
                                                            <span class="basv-account-badge is-blacklisted">Blacklisted</span>
                                                        @endif
                                                        <span class="basv-account-badge is-assigned-badge"
                                                            data-assigned-label hidden>Already assigned</span>
                                                    </span>
                                                </span>
                                            </label>
                                            <div class="basv-member-role-control">
                                                <label class="visually-hidden"
                                                    for="team-specialism-{{ $staff->id }}">Specialist role for {{ $staffName }}</label>
                                                <input class="form-control" name="team_specialisms[{{ $staff->id }}]"
                                                    id="team-specialism-{{ $staff->id }}"
                                                    list="biannual-specialist-role-options" maxlength="255"
                                                    autocomplete="off" placeholder="Choose or enter a specialist role"
                                                    data-team-specialism disabled>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="basv-member-empty" data-no-staff-accounts>
                                            <i class="feather-user-x"></i>
                                            <strong>No user accounts exist yet</strong>
                                            <span>Create a monitoring-team member above to continue.</span>
                                        </div>
                                    @endforelse
                                </div>

                                <div class="basv-member-empty" id="team-member-no-results" hidden>
                                    <i class="feather-search"></i>
                                    <strong data-no-results-title>No user accounts match this search</strong>
                                    <span data-no-results-help>Try a name, email, role, account type, or status.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="basv-btn basv-btn-ghost"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="basv-btn basv-btn-primary"
                            id="save-team-members" disabled>
                            <i class="feather-user-plus"></i>
                            Add selected members
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <datalist id="biannual-specialist-role-options">
            @foreach ($specialistRoles as $specialistRole)
                <option value="{{ $specialistRole }}"></option>
            @endforeach
        </datalist>

        <div class="modal fade basv-team-modal" id="manage-team-modal" tabindex="-1"
            aria-labelledby="manage-team-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="#" class="modal-content basv-page" id="manage-team-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_team_manage_visit_id" id="team-management-visit-id"
                        value="{{ old('_team_manage_visit_id') }}">

                    <div class="modal-header">
                        <div class="basv-modal-heading-icon">
                            <i class="feather-user-check" aria-hidden="true"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="basv-modal-kicker">Leadership &amp; membership</span>
                            <h2 class="modal-title" id="manage-team-title">Manage monitoring team</h2>
                            <div class="basv-modal-meta" id="team-management-reference">
                                Change the team leader or remove assigned members.
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close team management"></button>
                    </div>

                    <div class="modal-body">
                        <div class="basv-assignment-note">
                            <i class="feather-info" aria-hidden="true"></i>
                            <span>Edit each specialist role and select exactly one team leader. Members marked for removal cannot be selected as leader, and at least one member must remain assigned.</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                            <strong class="basv-management-label">Current monitoring team</strong>
                            <span class="basv-selection-count" id="team-remaining-count">0 remaining</span>
                        </div>

                        <div class="basv-manage-team-list" id="manage-team-members"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="basv-btn basv-btn-ghost"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="basv-btn basv-btn-primary"
                            id="save-team-management">
                            <i class="feather-save"></i>
                            Save team changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($canManageTeams)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('add-team-members-modal');
                const form = document.getElementById('add-team-members-form');
                const title = document.getElementById('add-team-members-title');
                const reference = document.getElementById('team-assignment-reference');
                const visitIdInput = document.getElementById('team-assignment-visit-id');
                const searchInput = document.getElementById('team-member-search');
                const countLabel = document.getElementById('team-selection-count');
                const saveButton = document.getElementById('save-team-members');
                const noResults = document.getElementById('team-member-no-results');
                const directoryCount = document.getElementById('team-directory-count');
                const serverErrors = document.getElementById('team-member-server-errors');
                const optionsContainer = document.getElementById('team-member-options');
                const noStaffAccounts = document.querySelector('[data-no-staff-accounts]');
                const additionalMemberPanel = document.getElementById('additional-member-panel');
                const showAdditionalMemberButton = document.getElementById('show-additional-member-form');
                const cancelAdditionalMemberButton = document.getElementById('cancel-additional-member');
                const addAdditionalMemberButton = document.getElementById('add-additional-member-to-selection');
                const additionalMemberName = document.getElementById('additional_member_name');
                const additionalMemberEmail = document.getElementById('additional_member_email');
                const additionalMemberSpecialism = document.getElementById('additional_member_specialism');
                const additionalMemberError = document.getElementById('additional-member-error');
                let options = [...document.querySelectorAll('[data-member-option]')];
                const triggers = [...document.querySelectorAll('[data-add-team-members]')];
                let pendingMemberCounter = 0;

                if (!modalElement || !form || !optionsContainer) return;

                const updateSelection = () => {
                    const selected = options.filter(option => {
                        const checkbox = option.querySelector('[data-team-member-checkbox]');
                        return checkbox && checkbox.checked && !checkbox.disabled;
                    });

                    countLabel.textContent = `${selected.length} ${selected.length === 1 ? 'member' : 'members'} selected`;
                    const allHaveRoles = selected.every(option =>
                        option.querySelector('[data-team-specialism]')?.value.trim()
                    );
                    saveButton.disabled = selected.length === 0 || !allHaveRoles;
                };

                const refreshNoStaffState = () => {
                    if (!noStaffAccounts) return;

                    const hasPendingMembers = options.some(option =>
                        option.dataset.pendingMemberOption === '1'
                    );
                    const hasSearch = Boolean((searchInput.value || '').trim());
                    noStaffAccounts.hidden = hasPendingMembers || hasSearch;
                };

                const filterOptions = () => {
                    const query = (searchInput.value || '').trim().toLowerCase();
                    let visible = 0;

                    options.forEach(option => {
                        const matches = !query || option.dataset.search.includes(query);
                        option.hidden = !matches;
                        if (matches) visible += 1;
                    });

                    refreshNoStaffState();
                    directoryCount.textContent = `${visible.toLocaleString()} of ${options.length.toLocaleString()} accounts shown`;
                    noResults.hidden = visible > 0 || (noStaffAccounts && !noStaffAccounts.hidden);
                };

                const setAdditionalMemberPanel = (open, restoreFocus = false) => {
                    additionalMemberPanel.hidden = !open;
                    showAdditionalMemberButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                    additionalMemberError.hidden = true;
                    additionalMemberError.textContent = '';

                    if (open) {
                        additionalMemberName.focus();
                        return;
                    }

                    additionalMemberName.value = '';
                    additionalMemberEmail.value = '';
                    additionalMemberSpecialism.value = '';
                    if (restoreFocus) showAdditionalMemberButton.focus();
                };

                const showAdditionalMemberError = (message, field) => {
                    additionalMemberError.textContent = message;
                    additionalMemberError.hidden = false;
                    field?.focus();
                };

                const bindOption = option => {
                    const checkbox = option.querySelector('[data-team-member-checkbox]');
                    const specialism = option.querySelector('[data-team-specialism]');

                    checkbox?.addEventListener('change', () => {
                        specialism.disabled = !checkbox.checked;
                        specialism.required = checkbox.checked;
                        if (!checkbox.checked) specialism.value = '';
                        updateSelection();
                    });
                    specialism?.addEventListener('input', updateSelection);
                };

                const addPendingMemberOption = (key, member, specialism = '', selected = true) => {
                    const referenceId = `new:${key}`;
                    const existingOption = options.find(option =>
                        String(option.dataset.userId) === referenceId
                    );
                    if (existingOption) return existingOption;

                    const option = document.createElement('div');
                    option.className = 'basv-member-option';
                    option.dataset.memberOption = '';
                    option.dataset.pendingMemberOption = '1';
                    option.dataset.userId = referenceId;
                    option.dataset.email = member.email;
                    option.dataset.search = `${member.name} ${member.email} ${specialism} new monitoring team member`
                        .toLowerCase();

                    const identity = document.createElement('label');
                    identity.className = 'basv-member-identity';
                    identity.htmlFor = `team-member-${key}`;

                    const checkbox = document.createElement('input');
                    checkbox.className = 'form-check-input';
                    checkbox.type = 'checkbox';
                    checkbox.name = 'team_members[]';
                    checkbox.value = referenceId;
                    checkbox.id = `team-member-${key}`;
                    checkbox.dataset.teamMemberCheckbox = '';
                    checkbox.checked = selected;

                    const avatar = document.createElement('span');
                    avatar.className = 'basv-member-avatar';
                    avatar.textContent = (member.name || 'M').charAt(0).toUpperCase();

                    const details = document.createElement('span');
                    const memberName = document.createElement('strong');
                    memberName.textContent = member.name;
                    const memberEmail = document.createElement('small');
                    memberEmail.textContent = member.email;
                    const accountType = document.createElement('small');
                    accountType.textContent = 'New monitoring-team account';
                    const accountBadges = document.createElement('span');
                    accountBadges.className = 'basv-account-badges';
                    const typeBadge = document.createElement('span');
                    typeBadge.className = 'basv-account-badge';
                    typeBadge.textContent = 'Staff';
                    const statusBadge = document.createElement('span');
                    statusBadge.className = 'basv-account-badge is-active';
                    statusBadge.textContent = 'New account';
                    accountBadges.append(typeBadge, statusBadge);
                    details.append(memberName, memberEmail, accountType, accountBadges);
                    identity.append(checkbox, avatar, details);

                    const roleContainer = document.createElement('div');
                    roleContainer.className = 'basv-member-role-control';
                    const roleLabel = document.createElement('label');
                    roleLabel.className = 'visually-hidden';
                    roleLabel.htmlFor = `team-specialism-${key}`;
                    roleLabel.textContent = `Specialist role for ${member.name}`;

                    const roleInput = document.createElement('input');
                    roleInput.className = 'form-control';
                    roleInput.name = `team_specialisms[${referenceId}]`;
                    roleInput.id = `team-specialism-${key}`;
                    roleInput.setAttribute('list', 'biannual-specialist-role-options');
                    roleInput.maxLength = 255;
                    roleInput.autocomplete = 'off';
                    roleInput.placeholder = 'Choose or enter a role';
                    roleInput.dataset.teamSpecialism = '';
                    roleInput.value = specialism;
                    roleInput.disabled = !selected;
                    roleInput.required = selected;

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'basv-team-remove';
                    removeButton.title = `Remove ${member.name}`;
                    removeButton.setAttribute('aria-label', `Remove new member ${member.name}`);
                    const removeMark = document.createElement('span');
                    removeMark.setAttribute('aria-hidden', 'true');
                    removeMark.textContent = '\u00d7';
                    const removeText = document.createElement('span');
                    removeText.className = 'visually-hidden';
                    removeText.textContent = 'Remove';
                    removeButton.append(removeMark, removeText);
                    roleContainer.append(roleLabel, roleInput, removeButton);

                    const submittedDetails = document.createElement('span');
                    submittedDetails.hidden = true;
                    const nameInput = document.createElement('input');
                    nameInput.type = 'hidden';
                    nameInput.name = `new_team_members[${key}][name]`;
                    nameInput.value = member.name;
                    const emailInput = document.createElement('input');
                    emailInput.type = 'hidden';
                    emailInput.name = `new_team_members[${key}][email]`;
                    emailInput.value = member.email;
                    submittedDetails.append(nameInput, emailInput);

                    option.append(identity, roleContainer, submittedDetails);
                    optionsContainer.append(option);
                    options.push(option);
                    bindOption(option);
                    removeButton.addEventListener('click', () => {
                        option.remove();
                        options = options.filter(candidate => candidate !== option);
                        filterOptions();
                        updateSelection();
                        showAdditionalMemberButton.focus();
                    });

                    return option;
                };

                const clearPendingMemberOptions = () => {
                    options
                        .filter(option => option.dataset.pendingMemberOption === '1')
                        .forEach(option => option.remove());
                    options = options.filter(option => option.dataset.pendingMemberOption !== '1');
                };

                const addAdditionalMember = () => {
                    const member = {
                        name: additionalMemberName.value.trim(),
                        email: additionalMemberEmail.value.trim().toLowerCase(),
                    };
                    const specialism = additionalMemberSpecialism.value.trim();

                    if (!member.name) {
                        showAdditionalMemberError('Enter the member\'s full name.', additionalMemberName);
                        return;
                    }

                    if (!member.email || !additionalMemberEmail.checkValidity()) {
                        showAdditionalMemberError('Enter a valid member email address.', additionalMemberEmail);
                        return;
                    }

                    if (!specialism) {
                        showAdditionalMemberError(
                            'Choose or enter the member\'s specialist role.',
                            additionalMemberSpecialism
                        );
                        return;
                    }

                    const emailAlreadyExists = options.some(option =>
                        String(option.dataset.email || '').toLowerCase() === member.email
                    );
                    if (emailAlreadyExists) {
                        showAdditionalMemberError(
                            'This email is already listed. Select the existing account instead.',
                            additionalMemberEmail
                        );
                        return;
                    }

                    pendingMemberCounter += 1;
                    const key = `member_${Date.now().toString(36)}_${pendingMemberCounter.toString(36)}`;
                    const pendingOption = addPendingMemberOption(key, member, specialism);
                    searchInput.value = '';
                    setAdditionalMemberPanel(false);
                    filterOptions();
                    updateSelection();
                    window.requestAnimationFrame(() => {
                        pendingOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        pendingOption.querySelector('[data-team-member-checkbox]')?.focus();
                    });
                };

                const configureModal = (trigger, restoreErrors = false) => {
                    let existingMembers = [];
                    try {
                        existingMembers = JSON.parse(trigger.dataset.existingMembers || '[]').map(String);
                    } catch (error) {
                        existingMembers = [];
                    }

                    const assigned = new Set(existingMembers);
                    form.action = trigger.dataset.action;
                    visitIdInput.value = trigger.dataset.visitId;
                    title.textContent = `Add members · ${trigger.dataset.visitTitle}`;
                    reference.textContent = trigger.dataset.visitReference;
                    searchInput.value = '';
                    saveButton.removeAttribute('aria-busy');
                    if (serverErrors) serverErrors.hidden = !restoreErrors;
                    clearPendingMemberOptions();
                    setAdditionalMemberPanel(false);

                    options.forEach(option => {
                        const checkbox = option.querySelector('[data-team-member-checkbox]');
                        const specialism = option.querySelector('[data-team-specialism]');
                        const assignedLabel = option.querySelector('[data-assigned-label]');
                        const isAssigned = assigned.has(String(option.dataset.userId));

                        option.dataset.unavailable = isAssigned ? '1' : '0';
                        option.classList.toggle('is-assigned', isAssigned);
                        if (assignedLabel) assignedLabel.hidden = !isAssigned;
                        checkbox.checked = false;
                        checkbox.disabled = isAssigned;
                        specialism.value = '';
                        specialism.disabled = true;
                        specialism.required = false;
                    });

                    filterOptions();
                    updateSelection();
                };

                options.forEach(bindOption);

                triggers.forEach(trigger => {
                    trigger.addEventListener('click', () => configureModal(trigger));
                });
                searchInput.addEventListener('input', filterOptions);
                showAdditionalMemberButton.addEventListener('click', () =>
                    setAdditionalMemberPanel(additionalMemberPanel.hidden)
                );
                cancelAdditionalMemberButton.addEventListener('click', () =>
                    setAdditionalMemberPanel(false, true)
                );
                addAdditionalMemberButton.addEventListener('click', addAdditionalMember);
                form.addEventListener('submit', () => {
                    saveButton.disabled = true;
                    saveButton.setAttribute('aria-busy', 'true');
                });
                [additionalMemberName, additionalMemberEmail, additionalMemberSpecialism].forEach(field => {
                    field.addEventListener('keydown', event => {
                        if (event.key !== 'Enter') return;
                        event.preventDefault();
                        addAdditionalMember();
                    });
                });

                modalElement.addEventListener('shown.bs.modal', () =>
                    (serverErrors && !serverErrors.hidden ? serverErrors : searchInput).focus()
                );

                const failedVisitId = @json((string) old('_team_visit_id'));
                const oldSelected = @json(array_values((array) old('team_members', [])));
                const oldSpecialisms = @json((array) old('team_specialisms', []));
                const oldNewMembers = @json((array) old('new_team_members', []));

                if (failedVisitId) {
                    const trigger = triggers.find(item => item.dataset.visitId === failedVisitId);
                    if (trigger) {
                        configureModal(trigger, true);

                        Object.entries(oldNewMembers).forEach(([key, member]) => {
                            const normalizedMember = {
                                name: String(member?.name || '').trim(),
                                email: String(member?.email || '').trim().toLowerCase(),
                            };
                            if (!normalizedMember.name || !normalizedMember.email) return;

                            const referenceId = `new:${key}`;
                            addPendingMemberOption(
                                key,
                                normalizedMember,
                                oldSpecialisms[referenceId] || '',
                                false
                            );
                        });

                        oldSelected.map(String).forEach(userId => {
                            const option = options.find(item => String(item.dataset.userId) === userId);
                            const checkbox = option?.querySelector('[data-team-member-checkbox]');
                            const specialism = option?.querySelector('[data-team-specialism]');

                            if (!checkbox || checkbox.disabled || !specialism) return;
                            checkbox.checked = true;
                            specialism.disabled = false;
                            specialism.required = true;
                            specialism.value = oldSpecialisms[userId] || '';
                        });

                        filterOptions();
                        updateSelection();
                        window.bootstrap?.Modal.getOrCreateInstance(modalElement).show();
                    }
                }

                const manageModalElement = document.getElementById('manage-team-modal');
                const manageForm = document.getElementById('manage-team-form');
                const manageTitle = document.getElementById('manage-team-title');
                const manageReference = document.getElementById('team-management-reference');
                const manageVisitIdInput = document.getElementById('team-management-visit-id');
                const manageList = document.getElementById('manage-team-members');
                const remainingCount = document.getElementById('team-remaining-count');
                const manageSaveButton = document.getElementById('save-team-management');
                const manageTriggers = [...document.querySelectorAll('[data-manage-team]')];

                const updateManagedTeam = () => {
                    const rows = [...manageList.querySelectorAll('[data-managed-member]')];
                    const activeRows = rows.filter(row => {
                        const remove = row.querySelector('[data-remove-managed-member]');
                        return !remove.checked;
                    });
                    let selectedLeader = activeRows.find(row =>
                        row.querySelector('[data-managed-leader]').checked
                    );

                    if (!selectedLeader && activeRows.length) {
                        activeRows[0].querySelector('[data-managed-leader]').checked = true;
                        selectedLeader = activeRows[0];
                    }

                    rows.forEach(row => {
                        const remove = row.querySelector('[data-remove-managed-member]');
                        const leader = row.querySelector('[data-managed-leader]');
                        const specialism = row.querySelector('[data-managed-specialism]');
                        const removed = remove.checked;

                        row.classList.toggle('is-removing', removed);
                        leader.disabled = removed;
                        specialism.disabled = removed;
                        specialism.required = !removed;
                        remove.disabled = !removed && activeRows.length <= 1;
                    });

                    const hasBlankSpecialism = activeRows.some(row =>
                        !row.querySelector('[data-managed-specialism]').value.trim()
                    );
                    remainingCount.textContent = `${activeRows.length} ${activeRows.length === 1 ? 'member' : 'members'} remaining`;
                    manageSaveButton.disabled = activeRows.length === 0
                        || !selectedLeader
                        || hasBlankSpecialism;
                };

                const buildManagedMember = member => {
                    const row = document.createElement('div');
                    row.className = 'basv-manage-member';
                    row.dataset.managedMember = '1';
                    row.dataset.userId = String(member.id);

                    const leaderChoice = document.createElement('label');
                    leaderChoice.className = 'basv-leader-choice';
                    const leaderRadio = document.createElement('input');
                    leaderRadio.type = 'radio';
                    leaderRadio.name = 'group_leader_id';
                    leaderRadio.value = String(member.id);
                    leaderRadio.required = true;
                    leaderRadio.checked = Boolean(member.is_leader);
                    leaderRadio.dataset.managedLeader = '1';
                    const leaderText = document.createElement('span');
                    leaderText.textContent = 'Leader';
                    leaderChoice.append(leaderRadio, leaderText);

                    const identity = document.createElement('div');
                    identity.className = 'basv-managed-identity';
                    const avatar = document.createElement('span');
                    avatar.className = 'basv-member-avatar';
                    avatar.textContent = String(member.name || 'M').trim().charAt(0).toUpperCase();
                    const details = document.createElement('span');
                    const name = document.createElement('strong');
                    name.textContent = member.name || 'Monitoring team member';
                    const email = document.createElement('small');
                    email.textContent = member.email || 'No email recorded';
                    details.append(name, email);
                    identity.append(avatar, details);

                    const roleField = document.createElement('div');
                    roleField.className = 'basv-managed-role';
                    const roleLabel = document.createElement('label');
                    roleLabel.className = 'form-label mb-1';
                    roleLabel.htmlFor = `managed-specialism-${member.id}`;
                    roleLabel.textContent = 'Specialist role';
                    const roleInput = document.createElement('input');
                    roleInput.type = 'text';
                    roleInput.className = 'form-control';
                    roleInput.id = `managed-specialism-${member.id}`;
                    roleInput.name = `team_specialisms[${member.id}]`;
                    roleInput.setAttribute('list', 'biannual-specialist-role-options');
                    roleInput.maxLength = 255;
                    roleInput.autocomplete = 'off';
                    roleInput.placeholder = 'Choose or enter a role';
                    roleInput.required = true;
                    roleInput.value = member.specialism || '';
                    roleInput.dataset.managedSpecialism = '1';
                    roleField.append(roleLabel, roleInput);

                    const removeChoice = document.createElement('label');
                    removeChoice.className = 'basv-remove-choice';
                    const removeCheckbox = document.createElement('input');
                    removeCheckbox.type = 'checkbox';
                    removeCheckbox.name = 'remove_members[]';
                    removeCheckbox.value = String(member.id);
                    removeCheckbox.dataset.removeManagedMember = '1';
                    const removeIcon = document.createElement('i');
                    removeIcon.className = 'feather-user-minus';
                    const removeText = document.createElement('span');
                    removeText.textContent = 'Remove';
                    removeChoice.append(removeCheckbox, removeIcon, removeText);

                    leaderRadio.addEventListener('change', updateManagedTeam);
                    removeCheckbox.addEventListener('change', updateManagedTeam);
                    roleInput.addEventListener('input', updateManagedTeam);
                    row.append(leaderChoice, identity, roleField, removeChoice);

                    return row;
                };

                const configureManageModal = trigger => {
                    let roster = [];
                    try {
                        roster = JSON.parse(trigger.dataset.teamRoster || '[]');
                    } catch (error) {
                        roster = [];
                    }

                    manageForm.action = trigger.dataset.action;
                    manageVisitIdInput.value = trigger.dataset.visitId;
                    manageTitle.textContent = `Manage team · ${trigger.dataset.visitTitle}`;
                    manageReference.textContent = trigger.dataset.visitReference;
                    manageList.replaceChildren(...roster.map(buildManagedMember));
                    updateManagedTeam();
                };

                manageTriggers.forEach(trigger => {
                    trigger.addEventListener('click', () => configureManageModal(trigger));
                });

                const failedManageVisitId = @json((string) old('_team_manage_visit_id'));
                const oldManagedLeader = @json((string) old('group_leader_id'));
                const oldRemovedMembers = @json(array_values((array) old('remove_members', [])));
                const oldManagedSpecialisms = @json((array) old('team_specialisms', []));

                if (failedManageVisitId && manageModalElement && manageForm) {
                    const trigger = manageTriggers.find(
                        item => item.dataset.visitId === failedManageVisitId
                    );

                    if (trigger) {
                        configureManageModal(trigger);
                        const removed = new Set(oldRemovedMembers.map(String));

                        manageList.querySelectorAll('[data-managed-member]').forEach(row => {
                            const userId = String(row.dataset.userId);
                            const leader = row.querySelector('[data-managed-leader]');
                            const remove = row.querySelector('[data-remove-managed-member]');
                            const specialism = row.querySelector('[data-managed-specialism]');
                            leader.checked = userId === oldManagedLeader;
                            remove.checked = removed.has(userId);
                            if (Object.prototype.hasOwnProperty.call(oldManagedSpecialisms, userId)) {
                                specialism.value = oldManagedSpecialisms[userId] || '';
                            }
                        });

                        updateManagedTeam();
                        window.bootstrap?.Modal.getOrCreateInstance(manageModalElement).show();
                    }
                }
            });
        </script>
    @endpush
@endif

@if ($canManageVisits)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('deactivate-visit-modal');
                const form = document.getElementById('deactivate-visit-form');
                const title = document.getElementById('deactivate-visit-title');
                const reference = document.getElementById('deactivate-visit-reference');
                const visitId = document.getElementById('deactivate-visit-id');
                const reason = document.getElementById('deactivation_reason');
                const triggers = [...document.querySelectorAll('[data-deactivate-visit]')];

                if (!modal || !form) return;

                const configure = trigger => {
                    form.action = trigger.dataset.action;
                    visitId.value = trigger.dataset.visitId || '';
                    title.textContent = `Deactivate ${trigger.dataset.visitTitle}`;
                    reference.textContent = trigger.dataset.visitReference || '';
                };

                triggers.forEach(trigger => {
                    trigger.addEventListener('click', () => configure(trigger));
                });
                modal.addEventListener('shown.bs.modal', () => reason.focus());

                const failedVisitId = @json((string) old('_deactivate_visit_id'));
                if (failedVisitId) {
                    const trigger = triggers.find(item => item.dataset.visitId === failedVisitId);
                    if (trigger) {
                        configure(trigger);
                        window.bootstrap?.Modal.getOrCreateInstance(modal).show();
                    }
                }
            });
        </script>
    @endpush
@endif

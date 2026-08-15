@extends('layouts.app')

@section('title', 'Edit '.$visit->reference_number)
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-edit-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 1rem;
            align-items: start;
        }

        .basv-locked-list {
            display: grid;
            gap: .85rem;
        }

        .basv-locked-item {
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--basv-border);
        }

        .basv-locked-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .basv-locked-item strong,
        .basv-locked-item span {
            display: block;
        }

        .basv-locked-item span {
            margin-bottom: .2rem;
            color: var(--basv-muted);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .basv-character-count {
            color: var(--basv-muted);
            font-size: .68rem;
            font-weight: 700;
            text-align: right;
        }

        @media (max-width: 991.98px) {
            .basv-edit-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-edit-3"></i> Schedule management</span>
                    <h1>Edit scheduled site visit</h1>
                    <p>Update the visit brief and logistics while retaining its questionnaire, responses, audit reference, and monitoring cycle.</p>
                </div>
                <div class="basv-hero-actions">
                    <a href="{{ route('biannual-site-visits.show', $visit) }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Back to visit
                    </a>
                </div>
            </div>

            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>Please resolve the following before saving:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="basv-alert">
                <i class="feather-shield me-1"></i>
                The Think Tank, monitoring cycle, questionnaire version, and reference remain locked so existing responses keep their original audit context.
            </div>

            <form method="POST" action="{{ route('biannual-site-visits.update', $visit) }}" id="site-visit-edit-form">
                @csrf
                @method('PUT')

                <div class="basv-edit-layout">
                    <div>
                        <div class="basv-card">
                            <div class="basv-card-head">
                                <h2><i class="feather-calendar me-2"></i>Visit brief and logistics</h2>
                                <span class="basv-badge {{ $visit->siteVisit?->status }}">
                                    {{ str_replace('_', ' ', $visit->siteVisit?->status ?: 'draft') }}
                                </span>
                            </div>
                            <div class="basv-card-body">
                                <div class="basv-form-grid">
                                    <div class="basv-field-full">
                                        <label class="form-label" for="title">Visit title</label>
                                        <input class="form-control" id="title" name="title" maxlength="255"
                                            value="{{ old('title', $visit->title) }}" required autofocus>
                                    </div>
                                    <div>
                                        <label class="form-label" for="starts_on">Start date</label>
                                        <input class="form-control" id="starts_on" name="starts_on" type="date"
                                            value="{{ old('starts_on', optional($visit->starts_on)->toDateString()) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="ends_on">End date</label>
                                        <input class="form-control" id="ends_on" name="ends_on" type="date"
                                            value="{{ old('ends_on', optional($visit->ends_on)->toDateString()) }}" required>
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label" for="location">Visit location</label>
                                        <input class="form-control" id="location" name="location" maxlength="255"
                                            value="{{ old('location', $visit->location) }}"
                                            placeholder="City, office, or site address">
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label" for="group_name">Monitoring team name</label>
                                        <input class="form-control" id="group_name" name="group_name" maxlength="255"
                                            value="{{ old('group_name', $visit->siteVisit?->group?->group_name) }}" required>
                                        <div class="basv-help">Team membership and leadership continue to be managed from the visit register.</div>
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label" for="objectives">Objectives and preparation notes</label>
                                        <textarea class="form-control" id="objectives" name="objectives" maxlength="10000"
                                            placeholder="Describe the purpose, scope, and documents the team should prepare.">{{ old('objectives', $visit->objectives) }}</textarea>
                                        <div class="basv-character-count" id="objectives-count" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
                            <a href="{{ route('biannual-site-visits.show', $visit) }}" class="basv-btn basv-btn-ghost">Cancel</a>
                            <button type="submit" class="basv-btn basv-btn-primary" id="save-schedule-button">
                                <i class="feather-save"></i> Save schedule changes
                            </button>
                        </div>
                    </div>

                    <aside class="basv-card">
                        <div class="basv-card-head">
                            <h2><i class="feather-lock me-2"></i>Locked audit identity</h2>
                        </div>
                        <div class="basv-card-body basv-locked-list">
                            <div class="basv-locked-item">
                                <span>Reference</span>
                                <strong>{{ $visit->reference_number }}</strong>
                            </div>
                            <div class="basv-locked-item">
                                <span>Think Tank</span>
                                <strong>{{ $visit->thinkTank?->name ?? 'Not available' }}</strong>
                            </div>
                            <div class="basv-locked-item">
                                <span>Monitoring cycle</span>
                                <strong>{{ $visit->cycleLabel() }}</strong>
                            </div>
                            <div class="basv-locked-item">
                                <span>Questionnaire</span>
                                <strong>{{ $visit->template?->name ?? 'Monitoring questionnaire' }}</strong>
                                <small class="basv-help">Version {{ $visit->template_version }}</small>
                            </div>
                            <div class="basv-locked-item">
                                <span>Responses retained</span>
                                <strong>{{ number_format($visit->answers->count()) }}</strong>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('site-visit-edit-form');
            const startDate = document.getElementById('starts_on');
            const endDate = document.getElementById('ends_on');
            const objectives = document.getElementById('objectives');
            const count = document.getElementById('objectives-count');
            const saveButton = document.getElementById('save-schedule-button');

            const syncDates = () => {
                endDate.min = startDate.value || '';
                if (startDate.value && endDate.value && endDate.value < startDate.value) {
                    endDate.value = startDate.value;
                }
            };
            const syncCount = () => {
                count.textContent = `${objectives.value.length.toLocaleString()} / 10,000 characters`;
            };

            startDate.addEventListener('change', syncDates);
            objectives.addEventListener('input', syncCount);
            form.addEventListener('submit', () => {
                saveButton.disabled = true;
                saveButton.innerHTML = '<i class="feather-loader"></i> Saving changes';
            });
            syncDates();
            syncCount();
        })();
    </script>
@endpush

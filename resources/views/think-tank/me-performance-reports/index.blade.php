@php
    $statusLabels = [
        \App\Models\MePerformanceReport::STATUS_DRAFT => 'Draft Reports',
        \App\Models\MePerformanceReport::STATUS_SUBMITTED => 'Submitted Reports',
        \App\Models\MePerformanceReport::STATUS_REVIEWED => 'Reviewed Reports',
        \App\Models\MePerformanceReport::STATUS_VERIFIED => 'Verified Reports',
        \App\Models\MePerformanceReport::STATUS_APPROVED => 'Approved Reports',
        \App\Models\MePerformanceReport::STATUS_ARCHIVED => 'Archived Reports',
    ];
    $statusIcons = [
        'draft' => 'feather-edit-3',
        'submitted' => 'feather-send',
        'reviewed' => 'feather-check-circle',
        'verified' => 'feather-shield',
        'approved' => 'feather-award',
        'archived' => 'feather-archive',
    ];
@endphp

<x-think-tank.partials.shell :member="$member" title="M&E Performance Reports">
    <style>
        .tt-pr {
            --pr-green: #176b4b;
            --pr-deep: #0d4d36;
            --pr-ink: #1c2b23;
            --pr-muted: #66756c;
            --pr-line: #dbe6df;
        }
        .tt-pr .pr-hero {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.2rem;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(120deg, var(--pr-deep), #20815d);
        }
        .tt-pr .pr-hero h1 { margin: .2rem 0; color: #fff; font-size: 1.45rem; font-weight: 800; }
        .tt-pr .pr-hero p { max-width: 680px; margin: 0; color: rgba(255,255,255,.76); font-size: .82rem; }
        .tt-pr .pr-owner {
            align-self: flex-start;
            padding: .5rem .7rem;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px;
            background: rgba(255,255,255,.1);
            font-size: .72rem;
            font-weight: 750;
        }
        .tt-pr .pr-lifecycle {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .7rem;
            margin: 1rem 0;
        }
        .tt-pr .pr-stage {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .85rem;
            border: 1px solid var(--pr-line);
            border-radius: 10px;
            color: inherit;
            background: #fff;
            text-decoration: none;
        }
        .tt-pr .pr-stage.active { border-color: #91bea9; box-shadow: 0 0 0 2px rgba(23,107,75,.08); }
        .tt-pr .pr-stage-icon {
            width: 34px; height: 34px; display: grid; place-items: center;
            border-radius: 9px; color: var(--pr-green); background: #edf6f1;
        }
        .tt-pr .pr-stage strong { display: block; color: var(--pr-ink); font-size: 1rem; }
        .tt-pr .pr-stage small { color: var(--pr-muted); font-size: .68rem; font-weight: 700; }
        .tt-pr .pr-panel {
            margin-top: 1rem;
            border: 1px solid var(--pr-line);
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }
        .tt-pr .pr-panel-head {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .95rem 1rem; border-bottom: 1px solid var(--pr-line); background: #fbfdfc;
        }
        .tt-pr .pr-panel-head h2 { margin: 0; color: var(--pr-ink); font-size: .95rem; font-weight: 800; }
        .tt-pr .pr-panel-head p { margin: .18rem 0 0; color: var(--pr-muted); font-size: .72rem; }
        .tt-pr .pr-body { padding: 1rem; }
        .tt-pr .form-label { color: var(--pr-ink); font-size: .74rem; font-weight: 750; }
        .tt-pr .form-control, .tt-pr .form-select { min-height: 42px; border-color: #cfddd5; border-radius: 8px; }
        .tt-pr .pr-table { margin: 0; font-size: .76rem; }
        .tt-pr .pr-table th { padding: .7rem .8rem; color: #597066; background: #f7faf8; font-size: .65rem; text-transform: uppercase; }
        .tt-pr .pr-table td { padding: .8rem; vertical-align: middle; border-color: #edf1ef; }
        .tt-pr .pr-code { display: block; color: var(--pr-green); font-size: .63rem; font-weight: 800; }
        .tt-pr .pr-title { color: var(--pr-ink); font-weight: 750; }
        .tt-pr .pr-status {
            display: inline-flex; padding: .28rem .5rem; border-radius: 999px;
            color: #4f6157; background: #eef2f0; font-size: .63rem; font-weight: 800;
        }
        .tt-pr .pr-status.submitted { color: #5b4b9a; background: #f0edfb; }
        .tt-pr .pr-status.reviewed { color: #166534; background: #dcfce7; }
        .tt-pr .pr-status.verified { color: #155e75; background: #cffafe; }
        .tt-pr .pr-status.approved { color: #166534; background: #dcfce7; }
        .tt-pr .pr-status.archived { color: #475569; background: #e9eef3; }
        .tt-pr .pr-empty { padding: 2rem; color: var(--pr-muted); text-align: center; }
        @media (max-width: 820px) {
            .tt-pr .pr-lifecycle { grid-template-columns: repeat(2, 1fr); }
            .tt-pr .pr-hero { flex-direction: column; }
        }
        @media (max-width: 520px) { .tt-pr .pr-lifecycle { grid-template-columns: 1fr; } }
    </style>

    <div class="tt-pr">
        <header class="pr-hero">
            <div>
                <small class="text-uppercase fw-bold opacity-75">Monitoring &amp; Evaluation</small>
                <h1>Performance Report Lifecycle</h1>
                <p>Prepare reports as drafts, submit them to the Secretariat/M&amp;E Officer, follow review decisions, and retain finalized reports in the historical archive.</p>
            </div>
            <div class="pr-owner">
                <i class="feather-briefcase me-1" aria-hidden="true"></i>
                {{ $member->name }} · {{ \Illuminate\Support\Str::headline($member->role ?: 'think tank') }}
            </div>
        </header>

        @if (session('success'))<div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
                <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <nav class="pr-lifecycle" aria-label="Report lifecycle">
            @foreach ($statusLabels as $value => $label)
                <a href="{{ route('think-tank.performance-reports.index', array_merge($portalRouteParams, ['status' => $value])) }}" class="pr-stage {{ $statusFilter === $value ? 'active' : '' }}">
                    <span class="pr-stage-icon"><i class="{{ $statusIcons[$value] }}" aria-hidden="true"></i></span>
                    <span><strong>{{ number_format($summary[$value] ?? 0) }}</strong><small>{{ $label }}</small></span>
                </a>
            @endforeach
        </nav>

        @if ($canAuthor)
            <section class="pr-panel">
                <div class="pr-panel-head">
                    <div><h2>Create a Draft Report</h2><p>Only forms assigned to this organization are available.</p></div>
                    <span class="pr-status draft">Stage 1 · Draft</span>
                </div>
                <div class="pr-body">
                    @if ($assignments->isEmpty())
                        <div class="pr-empty">No published M&amp;E reporting form is currently assigned to this organization.</div>
                    @else
                        <form method="POST" action="{{ route('think-tank.performance-reports.store', $portalRouteParams) }}" class="row g-3">
                            @csrf
                            <div class="col-lg-5">
                                <label class="form-label" for="report-assignment">Assigned reporting form</label>
                                <select name="assignment_id" id="report-assignment" class="form-select @error('assignment_id') is-invalid @enderror" required>
                                    <option value="">Choose an assigned reporting form</option>
                                    @foreach ($assignments as $assignment)
                                        @php $assignedForm = $assignment->collection?->form; @endphp
                                        <option value="{{ $assignment->id }}" @selected(old('assignment_id') === (string) $assignment->id)>
                                            {{ $assignedForm?->code }} · {{ $assignedForm?->title }} · {{ $assignedForm?->projectComponent?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="report-period-type">Frequency</label>
                                <select name="reporting_period_type" id="report-period-type" class="form-select" required>
                                    @foreach ($periodTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('reporting_period_type', 'quarter') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="report-period-label">Period</label>
                                <select name="reporting_period_label" id="report-period-label" class="form-select" required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="report-year">Reporting year</label>
                                <input type="number" name="reporting_year" id="report-year" min="2000" max="2100" value="{{ old('reporting_year', now()->year) }}" class="form-control" required>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-success fw-bold"><i class="feather-plus-circle me-1" aria-hidden="true"></i>Create Draft Report</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="pr-panel">
            <div class="pr-panel-head">
                <div><h2>{{ $statusFilter && isset($statusLabels[$statusFilter]) ? $statusLabels[$statusFilter] : 'All Performance Reports' }}</h2><p>Organization-owned reports and their current lifecycle stage.</p></div>
                <form method="GET" action="{{ route('think-tank.performance-reports.index') }}" class="d-flex gap-2">
                    @foreach ($portalRouteParams as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <input name="q" value="{{ $search }}" class="form-control" placeholder="Search reports">
                    <button class="btn btn-light border" type="submit"><i class="feather-search" aria-hidden="true"></i></button>
                </form>
            </div>
            @if ($reports->isEmpty())
                <div class="pr-empty"><i class="feather-file-text d-block fs-3 mb-2" aria-hidden="true"></i>No report matches this view.</div>
            @else
                <div class="table-responsive">
                    <table class="table pr-table">
                        <thead><tr><th>Report</th><th>Component / Directorate</th><th>Coverage</th><th>Stage</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($reports as $report)
                                <tr>
                                    <td><span class="pr-code">{{ $report->periodLabel() }} · {{ $report->form?->code }}</span><span class="pr-title">{{ $report->form?->title }}</span></td>
                                    <td><span class="pr-title">{{ $report->projectComponent?->name }}</span><div class="text-muted small">{{ $report->responsibleDirectorate?->name ?: 'Directorate not assigned' }}</div></td>
                                    <td>{{ $report->indicator_results_count }} indicators<br><span class="text-muted">{{ $report->documents_count }} evidence files</span></td>
                                    <td><span class="pr-status {{ $report->status }}">{{ $report->lifecycleLabel() }}</span></td>
                                    <td>{{ $report->updated_at?->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('think-tank.performance-reports.edit', array_merge(['report' => $report], $portalRouteParams)) }}" class="btn btn-sm btn-light border">
                                            <i class="{{ $report->isEditable() ? 'feather-edit-2' : 'feather-eye' }} me-1" aria-hidden="true"></i>{{ $report->isEditable() ? 'Continue' : 'View' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $reports->links() }}</div>
            @endif
        </section>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const type = document.getElementById('report-period-type');
            const label = document.getElementById('report-period-label');
            const labels = @json($periodLabels);
            const oldLabel = @json(old('reporting_period_label', 'Q1'));
            const refresh = () => {
                if (!label || !type) return;
                const selected = label.value || oldLabel;
                label.innerHTML = '';
                Object.entries(labels[type.value] || {}).forEach(([value, text]) => {
                    label.add(new Option(text, value, false, value === selected));
                });
            };
            type?.addEventListener('change', refresh);
            refresh();
        });
    </script>
</x-think-tank.partials.shell>

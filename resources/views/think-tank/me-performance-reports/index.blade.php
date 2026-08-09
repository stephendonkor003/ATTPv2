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
                            <div class="col-lg-9">
                                <label class="form-label" for="report-assignment">Assigned reporting form</label>
                                <select name="assignment_id" id="report-assignment" class="form-select @error('assignment_id') is-invalid @enderror" required>
                                    <option value="">Choose an assigned reporting form</option>
                                    @foreach ($assignments as $assignment)
                                        @php $assignedForm = $assignment->collection?->form; @endphp
                                        <option value="{{ $assignment->id }}" @selected(old('assignment_id') === (string) $assignment->id)>
                                            {{ $assignedForm?->code }} · {{ $assignedForm?->title }} · {{ $assignment->collection?->reportingPeriod?->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 d-flex align-items-end justify-content-end">
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
</x-think-tank.partials.shell>

@extends('layouts.app')

@section('title', $consortium->name . ' - Operations')

@section('content')
    <div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-grid text-primary me-2"></i>
                    {{ $consortium->name }}
                </h4>
                <p class="text-muted mb-0">{{ $consortium->code }} | {{ $consortium->funder?->name ?? 'No partner linked' }} | {{ ucfirst($consortium->status) }}</p>
            </div>
            <a href="{{ route('consortium-operations.index') }}" class="btn btn-primary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to Consortia
            </a>
    </div>

    <ul class="nav nav-tabs attp-management-tabs mb-4">
        <li class="nav-item"><a class="nav-link" href="{{ route('consortium-operations.index') }}">Consortium Operations</a></li>
        <li class="nav-item"><span class="nav-link active" aria-current="page">Oversight Detail</span></li>
        <li class="nav-item"><a class="nav-link" href="#research-procurement">Research & Procurement</a></li>
    </ul>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total distributed' => 'USD ' . number_format($consortium->fundAllocations->sum('amount_disbursed'), 2),
            'Funds spent' => 'USD ' . number_format($consortium->fundAllocations->sum('amount_spent'), 2),
            'Open risks' => $consortium->riskFlags->where('status', 'open')->count(),
            'Research submitted' => $consortium->researchOutputs->count(),
            'Procurement plans' => $consortium->procurementPlans->count(),
            'Applications received' => $consortium->procurements->sum(fn($procurement) => $procurement->submissions->count()),
        ] as $label => $value)
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h3 class="mb-0">{{ $value }}</h3></div></div>
            </div>
        @endforeach
    </div>

    @can('consortiums.manage')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0"><h5 class="mb-0">Think Tank Members</h5></div>
            <div class="card-body">
                <form class="row g-3 mb-4" method="POST" action="{{ route('consortium-operations.members.store', $consortium) }}">
                    @csrf
                    <div class="col-md-3"><input class="form-control" name="name" placeholder="Think tank name" required></div>
                    <div class="col-md-2">
                        <select class="form-select" name="country">
                            <option value="">Select AU member state</option>
                            @foreach($memberStates as $state)
                                <option value="{{ $state->name }}" @selected(old('country') === $state->name)>
                                    {{ $state->name }}{{ $state->code ? ' (' . $state->code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="Email"></div>
                    <div class="col-md-2"><select class="form-select" name="role"><option value="member">Member</option><option value="lead">Lead</option><option value="implementing_partner">Implementing partner</option></select></div>
                    <div class="col-md-2"><input class="form-control" name="initial_disbursed_amount" type="number" step="0.01" min="0" placeholder="Disbursed USD"></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Add Member</button></div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0"><h5 class="mb-0">Think Tank Portal Oversight</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead style="background:#e2e8f0;color:#0f172a;"><tr><th>Think Tank</th><th>Allocated</th><th>Disbursed</th><th>Reports</th><th>Research</th><th>Procurements</th><th>Applications</th><th>Portal User</th></tr></thead>
                    <tbody>
                    @forelse ($consortium->members as $member)
                        @php $memberProcurements = $consortium->procurements->where('think_tank_member_id', $member->id); @endphp
                        <tr>
                            <td><strong>{{ $member->name }}</strong><br><span class="text-muted small">{{ $member->country }} | {{ $member->role }}</span></td>
                            <td>USD {{ number_format($member->fundAllocations->sum('amount_allocated') + $member->budget_allocated, 2) }}</td>
                            <td>USD {{ number_format($member->fundAllocations->sum('amount_disbursed'), 2) }}</td>
                            <td>{{ $member->reports->count() }}</td>
                            <td>{{ $member->researchOutputs->count() }}</td>
                            <td>{{ $memberProcurements->count() }}</td>
                            <td>{{ $memberProcurements->sum(fn($procurement) => $procurement->submissions->count()) }}</td>
                            <td>{{ $member->portalUser?->email ?? 'No portal user' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No think tanks added yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @can('consortiums.manage')
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0"><h5 class="mb-0">Add Workplan</h5></div>
                    <div class="card-body">
                        <form class="vstack gap-3" method="POST" action="{{ route('consortium-operations.workplans.store', $consortium) }}">
                            @csrf
                            <input class="form-control" name="title" placeholder="Workplan title" required>
                            <input class="form-control" name="period_label" placeholder="Period label">
                            <input class="form-control" name="planned_budget" type="number" step="0.01" placeholder="Planned budget">
                            <textarea class="form-control" name="objectives" placeholder="Objectives"></textarea>
                            <button class="btn btn-primary" type="submit">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        @can('consortiums.finance.manage')
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0"><h5 class="mb-0">Add Allocation</h5></div>
                    <div class="card-body">
                        <form class="vstack gap-3" method="POST" action="{{ route('consortium-operations.allocations.store', $consortium) }}">
                            @csrf
                            <select class="form-select" name="think_tank_member_id"><option value="">Consortium level</option>@foreach($consortium->members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
                            <input class="form-control" name="budget_line" placeholder="Budget line" required>
                            <input class="form-control" name="amount_allocated" type="number" step="0.01" placeholder="Amount" required>
                            <button class="btn btn-primary" type="submit">Allocate</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        @can('consortiums.risks.manage')
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0"><h5 class="mb-0">Flag Risk</h5></div>
                    <div class="card-body">
                        <form class="vstack gap-3" method="POST" action="{{ route('consortium-operations.risks.store', $consortium) }}">
                            @csrf
                            <input class="form-control" name="title" placeholder="Risk title" required>
                            <select class="form-select" name="severity"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select>
                            <textarea class="form-control" name="description" placeholder="Description"></textarea>
                            <textarea class="form-control" name="mitigation_plan" placeholder="Mitigation plan"></textarea>
                            <button class="btn btn-primary" type="submit">Add Risk</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    <div class="card border-0 shadow-sm my-4" id="research-procurement">
        <div class="card-header bg-white border-0"><h5 class="mb-0">Research Submitted to Secretariat</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Title</th><th>Think Tank</th><th>Type</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    @forelse ($consortium->researchOutputs->sortByDesc('created_at') as $output)
                        <tr><td>{{ $output->title }}</td><td>{{ $output->member?->name }}</td><td>{{ str_replace('_', ' ', $output->output_type) }}</td><td><span class="badge bg-primary-subtle text-primary">{{ $output->status }}</span></td><td>{{ optional($output->submitted_at)->format('Y-m-d') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No research outputs submitted yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0"><h5 class="mb-0">Think Tank Procurement Oversight</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Opportunity</th><th>Think Tank</th><th>Status</th><th>Applications</th><th>Selected</th><th>Public</th></tr></thead>
                    <tbody>
                    @forelse ($consortium->procurements->sortByDesc('created_at') as $procurement)
                        <tr>
                            <td>{{ $procurement->title }}</td>
                            <td>{{ $procurement->thinkTankMember?->name }}</td>
                            <td><span class="badge bg-primary-subtle text-primary">{{ $procurement->status }}</span></td>
                            <td>{{ $procurement->submissions->count() }}</td>
                            <td>{{ $procurement->awardedSubmission?->submitter?->name ?? 'Pending' }}</td>
                            <td>@if($procurement->status === 'published')<a href="{{ route('public.procurement.show', $procurement) }}">View</a>@else Draft/closed @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No think tank procurement opportunities yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @canany(['consortiums.reports.submit', 'consortiums.reports.review'])
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0"><h5 class="mb-0">Activity Reports</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Report</th><th>Member</th><th>Progress</th><th>Status</th><th>Review</th></tr></thead>
                        <tbody>
                        @forelse ($consortium->activityReports->sortByDesc('created_at') as $report)
                            <tr>
                                <td>{{ $report->title }}<br><small class="text-muted">{{ $report->created_at?->format('Y-m-d') }}</small></td>
                                <td>{{ $report->member?->name ?? 'Consortium' }}</td>
                                <td>{{ $report->progress_percent }}%</td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $report->status }}</span></td>
                                <td>
                                    @can('consortiums.reports.review')
                                        <form class="d-flex gap-2" method="POST" action="{{ route('consortium-operations.reports.review', $report) }}">
                                            @csrf
                                            <select class="form-select form-select-sm" name="status"><option value="approved">Approve</option><option value="revisions_requested">Request revisions</option><option value="rejected">Reject</option></select>
                                            <input class="form-control form-control-sm" name="review_notes" placeholder="Notes">
                                            <button class="btn btn-sm btn-light border" type="submit">Save</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No activity reports yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endcanany
    </div>
@endsection

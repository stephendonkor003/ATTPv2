@extends($awpLayout ?? 'layouts.app')

@section('title', $awpTitle ?? 'Work Plans Registry')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">{{ $awpTitle ?? 'Work Plans Registry' }}</h4>
                <p class="text-muted mb-0">{{ $awpSubtitle ?? 'World Bank item-by-item no-objection view across projects, activities, and sub-activities.' }}</p>
            </div>

            @if ($program)
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-primary" type="button" onclick="window.print()">
                        <i class="feather-printer me-1"></i> Print
                    </button>
                </div>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
        @endif

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <form method="GET" action="{{ route($awpIndexRoute ?? 'finance.awp.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Program</label>
                        <select name="program_id" class="form-select" required>
                            <option value="">Select Program</option>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}" @selected((request('program_id') ?: ($selectedProgramId ?? null)) == $p->id)>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Filter Type</label>
                        <select name="filter_mode" id="filter_mode" class="form-select">
                            <option value="multi_year" @selected($filters['mode'] === 'multi_year')>Multi Year</option>
                            <option value="yearly" @selected($filters['mode'] === 'yearly')>Yearly</option>
                            <option value="quarterly" @selected($filters['mode'] === 'quarterly')>Quarterly</option>
                            <option value="semiannual" @selected($filters['mode'] === 'semiannual')>6 Months</option>
                            <option value="range" @selected($filters['mode'] === 'range')>Date Range</option>
                        </select>
                    </div>

                    <div class="col-md-2 filter-field filter-multi-year">
                        <label class="form-label">Start Year</label>
                        <input type="number" name="start_year" class="form-control" value="{{ request('start_year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-multi-year">
                        <label class="form-label">End Year</label>
                        <input type="number" name="end_year" class="form-control" value="{{ request('end_year', $filters['end_year']) }}">
                    </div>

                    <div class="col-md-2 filter-field filter-yearly d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>

                    <div class="col-md-2 filter-field filter-quarterly d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-quarterly d-none">
                        <label class="form-label">Quarter</label>
                        <select name="quarter" class="form-select">
                            @for ($q = 1; $q <= 4; $q++)
                                <option value="{{ $q }}" @selected((int) request('quarter', 1) === $q)>Q{{ $q }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-2 filter-field filter-semiannual d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-semiannual d-none">
                        <label class="form-label">Half Year</label>
                        <select name="half" class="form-select">
                            <option value="1" @selected((int) request('half', 1) === 1)>H1 (Jan-Jun)</option>
                            <option value="2" @selected((int) request('half', 1) === 2)>H2 (Jul-Dec)</option>
                        </select>
                    </div>

                    <div class="col-md-3 filter-field filter-range d-none">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 filter-field filter-range d-none">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="feather-filter me-1"></i> Run View
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if (!$program)
            <div class="alert alert-info mt-4">Select a program to view the work plans registry and World Bank item approvals.</div>
        @else
            @php
                $currentUser = auth()->user();
                $currency = $program->currency
                    ?? $program->approvedFundings?->first()?->currency
                    ?? $program->fundings?->first()?->currency
                    ?? 'USD';
                $canReview = $awpCanReview ?? ($currentUser?->hasPermission('finance.awp.approve') || $currentUser?->isFundingPartner());
                $canEdit = $awpCanEdit ?? ($currentUser?->hasPermission('finance.awp.edit') && ! ($currentUser?->isFundingPartner()));
                $showActions = $canReview || $canEdit;
                $columnCount = $showActions ? 15 : 14;
                $documentRoute = $awpDocumentRoute ?? 'finance.awp.items.document';
                $reviewRoute = $awpReviewRoute ?? 'finance.awp.items.review';
                $updateRoute = $awpUpdateRoute ?? 'finance.awp.items.update';
                $allowDocumentUpload = $awpAllowDocumentUpload ?? true;
                $canEditSubActivityAllocation = $currentUser?->hasPermission('subactivity.edit') || $currentUser?->hasPermission('subactivities.edit');
                $monthLabels = [
                    'jan' => 'Jan',
                    'feb' => 'Feb',
                    'mar' => 'Mar',
                    'apr' => 'Apr',
                    'may' => 'May',
                    'jun' => 'Jun',
                    'jul' => 'Jul',
                    'aug' => 'Aug',
                    'sep' => 'Sep',
                    'oct' => 'Oct',
                    'nov' => 'Nov',
                    'dec' => 'Dec',
                ];
                $paymentBasisLabels = [
                    'one_off' => 'One-off / milestone',
                    'scheduled' => 'Scheduled activity',
                    'monthly' => 'Monthly / person-month',
                ];
                $subActivityGroups = [];
                foreach (($report ?? []) as $projectRow) {
                    foreach (($projectRow['activities'] ?? []) as $activityRow) {
                        $options = [];

                        foreach (($activityRow['subActivities'] ?? []) as $subRow) {
                            $allocationYears = [];
                            foreach (($subRow['allocation_years'] ?? []) as $yearRow) {
                                $amount = (float) ($yearRow['amount'] ?? 0);
                                $allocationYears[] = [
                                    'year' => (int) ($yearRow['year'] ?? 0),
                                    'amount' => $amount,
                                    'display' => $currency . ' ' . number_format($amount, 2),
                                ];
                            }

                            $options[] = [
                                'id' => (string) $subRow['subActivity']->id,
                                'name' => $subRow['subActivity']->name,
                                'allocation_years' => $allocationYears,
                            ];
                        }

                        if ($options) {
                            $subActivityGroups[] = [
                                'label' => $projectRow['project']->name . ' / ' . $activityRow['activity']->name,
                                'options' => $options,
                            ];
                        }
                    }
                }
            @endphp

            <div class="row g-3 mt-3">
                @foreach ([
                    'Total Items' => number_format($summary['total_items']),
                    'Approved by Bank' => number_format($summary['approved']),
                    'Not Approved by World Bank' => number_format($summary['pending']),
                    'Needs Revision' => number_format($summary['needs_revision']),
                    'Approval Rate' => number_format($summary['approval_rate'], 2) . '%',
                    'Work Plan Amount' => $currency . ' ' . number_format($summary['amount'], 2),
                ] as $label => $value)
                    <div class="col-md-6 col-xl-2">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="small text-muted fw-semibold">{{ $label }}</div>
                                <div class="fs-5 fw-bold mt-1">{{ $value }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <div class="section-title">Work Plans Registry - World Bank No Objection</div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $program->name }}</h5>
                            <div class="text-muted">AWP item review - {{ $filters['label'] }}</div>
                            <div class="text-muted mt-1">
                                Funding Partner:
                                @if ($funders->isEmpty())
                                    <span class="text-muted">N/A</span>
                                @else
                                    {{ $funders->pluck('name')->implode(', ') }}
                                @endif
                            </div>
                        </div>
                        <div class="text-md-end">
                            <div class="fw-semibold">Total Work Plan: {{ $currency }} {{ number_format($summary['amount'], 2) }}</div>
                            <div class="text-muted small">Each item is reviewed independently by the funding partner.</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle awp-table awp-excel-table">
                            <thead>
                                <tr>
                                    <th style="width: 56px;">S.no</th>
                                    <th style="min-width: 360px;">Activity</th>
                                    <th>Implemented By</th>
                                    <th>Budget Codes</th>
                                    <th class="text-end">Commitment Amount</th>
                                    <th style="min-width: 180px;">Time Frame</th>
                                    <th style="min-width: 150px;">Audience / Units</th>
                                    <th style="min-width: 170px;">Payment Schedule</th>
                                    <th style="min-width: 260px;">Intermediate Indicator</th>
                                    <th>Object</th>
                                    <th style="min-width: 260px;">Result Indicator</th>
                                    <th>Bank Status</th>
                                    <th style="min-width: 260px;">World Bank Comments</th>
                                    <th>Attachments</th>
                                    @if ($showActions)
                                        <th style="min-width: 150px;">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($report as $projectRow)
                                    <tr class="project-row">
                                        <td></td>
                                        <td colspan="3" class="project-label">{{ $projectRow['project']->name }}</td>
                                        <td class="text-end fw-bold">{{ number_format($projectRow['total'], 2) }}</td>
                                        <td colspan="{{ $showActions ? 10 : 9 }}">
                                            @include('finance.awp.partials.status-counts', ['counts' => $projectRow['counts']])
                                        </td>
                                    </tr>

                                    @foreach ($projectRow['activities'] as $activityRow)
                                        <tr class="activity-row">
                                            <td colspan="{{ $columnCount }}" class="activity-label">{{ $activityRow['activity']->name }}</td>
                                        </tr>

                                        @foreach ($activityRow['subActivities'] as $subRow)
                                            @foreach ($subRow['items'] as $itemRow)
                                                @php
                                                    $review = $itemRow['review'];
                                                    $status = $itemRow['status'];
                                                    $isPlaceholder = $itemRow['is_placeholder'] ?? false;
                                                    $reviewModalId = $itemRow['item'] ? 'awpReviewModal' : null;
                                                    $editModalId = $itemRow['item'] ? 'awpEditModal' : null;
                                                    $canEditItem = $canEdit && $itemRow['item'] && $status !== 'approved';
                                                @endphp
                                                <tr class="item-row {{ $isPlaceholder ? 'placeholder-row' : '' }}">
                                                    <td class="text-center">
                                                        {{ $itemRow['item']?->work_plan_serial ?: '-' }}
                                                    </td>
                                                    <td class="item-label">
                                                        {{ $itemRow['label'] }}
                                                        @if ($itemRow['item']?->observations)
                                                            <div class="small text-muted mt-1">{{ $itemRow['item']->observations }}</div>
                                                        @endif
                                                        @if ($itemRow['purchaseRequest']?->reference_no)
                                                            <div class="small text-muted mt-1">{{ $itemRow['purchaseRequest']->reference_no }}</div>
                                                        @endif
                                                        @if (($subRow['allocation_years'] ?? collect())->isNotEmpty())
                                                            <div class="sub-allocation mt-2">
                                                                <div class="small fw-semibold">Sub-Activity Management allocation</div>
                                                                <div class="allocation-years">
                                                                    @foreach ($subRow['allocation_years'] as $allocationYear)
                                                                        <span>{{ $allocationYear['year'] }}: {{ number_format($allocationYear['amount'], 2) }}</span>
                                                                    @endforeach
                                                                </div>
                                                                @if ($canEditSubActivityAllocation)
                                                                    <a class="small fw-semibold" href="{{ route('budget.subactivities.allocations.edit', $subRow['subActivity']->id) }}">
                                                                        Edit sub-activity years
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $itemRow['item']?->implemented_by ?: 'N/A' }}</td>
                                                    <td>
                                                        @if ($itemRow['item']?->budget_code)
                                                            <span class="budget-code">{{ $itemRow['item']->budget_code }}</span>
                                                        @else
                                                            <span class="text-muted">N/A</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end fw-semibold">{{ number_format($itemRow['amount'], 2) }}</td>
                                                    <td class="small">
                                                        @if (! empty($itemRow['month_labels']))
                                                            <div class="month-chip-wrap">
                                                                @foreach ($itemRow['month_labels'] as $monthLabel)
                                                                    <span class="month-chip">{{ $monthLabel }}</span>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <span class="text-muted">No month schedule</span>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        @if ($itemRow['item']?->work_plan_audience)
                                                            <div>{{ $itemRow['item']->work_plan_audience }}</div>
                                                        @endif
                                                        @if ($itemRow['item']?->work_plan_units)
                                                            <div class="text-muted">Units: {{ $itemRow['item']->work_plan_units }}</div>
                                                        @endif
                                                        @if (! $itemRow['item']?->work_plan_audience && ! $itemRow['item']?->work_plan_units)
                                                            <span class="text-muted">N/A</span>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        <span class="schedule-badge {{ $itemRow['payment_basis'] }}">{{ $itemRow['payment_basis_label'] }}</span>
                                                        @if ($itemRow['payment_basis'] === 'monthly')
                                                            <div class="text-muted mt-1">
                                                                {{ number_format($itemRow['person_months'] ?? count($itemRow['month_keys'] ?? [])) }} person-month{{ (($itemRow['person_months'] ?? count($itemRow['month_keys'] ?? [])) == 1) ? '' : 's' }}
                                                            </div>
                                                            @if ($itemRow['monthly_amount'] !== null)
                                                                <div class="fw-semibold">{{ $currency }} {{ number_format($itemRow['monthly_amount'], 2) }} / month</div>
                                                            @endif
                                                        @else
                                                            <div class="text-muted mt-1">{{ $itemRow['month_text'] ?: 'Milestone based' }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        {{ $itemRow['item']?->intermediate_indicator ?: ($itemRow['item']?->milestone ?: 'N/A') }}
                                                    </td>
                                                    <td>{{ $itemRow['item']?->object_type ?: ($itemRow['item']?->resourceCategory?->name ?? 'N/A') }}</td>
                                                    <td class="small">{{ $itemRow['item']?->result_indicator ?: 'N/A' }}</td>
                                                    <td>
                                                        <span class="awp-status {{ $status }}">{{ $itemRow['status_label'] }}</span>
                                                        @if ($review?->reviewed_at)
                                                            <div class="small text-muted">{{ $review->reviewed_at->format('M d, Y H:i') }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        {{ $itemRow['item']?->world_bank_comments ?: ($review?->review_notes ?: 'N/A') }}
                                                        @if ($review?->reviewer?->name)
                                                            <div class="text-muted mt-1">Reviewed by {{ $review->reviewer->name }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        @if ($review?->tor_path || $review?->concept_note_path || $review?->document_path)
                                                            @if ($review?->tor_path)
                                                                <a href="{{ route($documentRoute, ['item' => $itemRow['item'], 'type' => 'tor']) }}"
                                                                    class="attachment-link">
                                                                    <i class="feather-download me-1"></i> TOR
                                                                </a>
                                                            @endif
                                                            @if ($review?->concept_note_path)
                                                                <a href="{{ route($documentRoute, ['item' => $itemRow['item'], 'type' => 'concept_note']) }}"
                                                                    class="attachment-link">
                                                                    <i class="feather-download me-1"></i> Concept Note
                                                                </a>
                                                            @endif
                                                            @if ($review?->document_path && ! $review?->tor_path && ! $review?->concept_note_path)
                                                            <a href="{{ route($documentRoute, $itemRow['item']) }}"
                                                                class="btn btn-sm btn-outline-primary">
                                                                <i class="feather-download me-1"></i>
                                                                {{ $review->document_name ?: 'Download document' }}
                                                            </a>
                                                            <div class="text-muted mt-1">
                                                                {{ ucwords(str_replace('_', ' ', $review->document_type ?? 'document')) }}
                                                                @if ($review->document_uploaded_at)
                                                                    · {{ $review->document_uploaded_at->format('M d, Y') }}
                                                                @endif
                                                            </div>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">No TOR or concept note attached</span>
                                                        @endif
                                                    </td>
                                                    @if ($showActions)
                                                        <td>
                                                            @if ($canEditItem)
                                                                <button type="button" class="btn btn-sm btn-outline-secondary awp-action-btn mb-2"
                                                                    data-awp-edit data-item-id="{{ $itemRow['item']->id }}"
                                                                    data-bs-toggle="modal" data-bs-target="#{{ $editModalId }}">
                                                                    <i class="feather-edit-2 me-1"></i>
                                                                    Edit
                                                                </button>
                                                            @elseif ($canEdit && $itemRow['item'])
                                                                <span class="locked-note">Locked</span>
                                                            @endif
                                                            @if ($canReview && $itemRow['item'])
                                                                <button type="button" class="btn btn-sm btn-outline-primary awp-review-btn"
                                                                    data-awp-review data-item-id="{{ $itemRow['item']->id }}"
                                                                    data-bs-toggle="modal" data-bs-target="#{{ $reviewModalId }}">
                                                                    <i class="feather-check-square me-1"></i>
                                                                    Review
                                                                    <span>Decision & comments</span>
                                                                </button>
                                                            @elseif (! $itemRow['item'])
                                                                <span class="text-muted small">Create a work plan item before review.</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        @endforeach

                                        <tr class="subtotal-row">
                                            <td colspan="3"></td>
                                            <td class="fw-bold text-center">SUB-TOTAL</td>
                                            <td class="text-end fw-bold">{{ number_format($activityRow['total'], 2) }}</td>
                                            <td colspan="{{ $showActions ? 10 : 9 }}">
                                                @include('finance.awp.partials.status-counts', ['counts' => $activityRow['counts']])
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="{{ $columnCount }}" class="text-center text-muted py-4">
                                            No work plan items found for this program and filter range.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($showActions)
                        @php
                            $modalItems = [];
                            foreach (($report ?? []) as $projectRow) {
                                foreach (($projectRow['activities'] ?? []) as $activityRow) {
                                    foreach (($activityRow['subActivities'] ?? []) as $subRow) {
                                        foreach (($subRow['items'] ?? []) as $itemRow) {
                                            if (! $itemRow['item']) {
                                                continue;
                                            }

                                            $item = $itemRow['item'];
                                            $review = $itemRow['review'];
                                            $modalItems[(string) $item->id] = [
                                                'label' => $itemRow['label'],
                                                'edit_action' => route($updateRoute, $item),
                                                'review_action' => route($reviewRoute, $item),
                                                'sub_activity_id' => (string) ($itemRow['purchaseRequest']?->allocation_id ?? ''),
                                                'work_plan_serial' => $item->work_plan_serial,
                                                'implemented_by' => $item->implemented_by,
                                                'budget_code' => $item->budget_code,
                                                'estimated_amount' => (float) $itemRow['amount'],
                                                'object_type' => $item->object_type,
                                                'work_plan_payment_basis' => $itemRow['payment_basis'],
                                                'work_plan_person_months' => $itemRow['person_months'],
                                                'work_plan_monthly_amount' => $itemRow['monthly_amount'],
                                                'work_plan_months' => $itemRow['month_keys'] ?? [],
                                                'work_plan_audience' => $item->work_plan_audience,
                                                'work_plan_units' => $item->work_plan_units,
                                                'intermediate_indicator' => $item->intermediate_indicator,
                                                'result_indicator' => $item->result_indicator,
                                                'observations' => $item->observations,
                                                'attp_secretariat_comments' => $item->attp_secretariat_comments,
                                                'world_bank_comments' => $item->world_bank_comments ?: $review?->review_notes,
                                                'review_status' => $itemRow['status'],
                                                'review_notes' => $review?->review_notes,
                                                'tor_name' => $review?->tor_name,
                                                'concept_note_name' => $review?->concept_note_name,
                                            ];
                                        }
                                    }
                                }
                            }
                        @endphp

                        <script type="application/json" id="awpModalItemsData">@json($modalItems)</script>

                        @if ($canEdit)
                            <template id="awpSubActivityOptionsTemplate">
                                <option value="">Select the correct sub-activity</option>
                                @foreach ($subActivityGroups as $group)
                                    <optgroup label="{{ $group['label'] }}">
                                        @foreach ($group['options'] as $option)
                                            <option value="{{ $option['id'] }}"
                                                data-subactivity-name="{{ $option['name'] }}"
                                                data-allocation-years='@json($option['allocation_years'])'>
                                                {{ $option['name'] }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </template>

                            <div class="modal fade awp-upload-modal" id="awpEditModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="" data-awp-edit-form>
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Edit Work Plan Item</h5>
                                                    <div class="small text-muted">Editable until the World Bank marks this item approved.</div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-2">
                                                        <label class="form-label">S.no</label>
                                                        <input type="text" name="work_plan_serial" class="form-control" data-field="work_plan_serial">
                                                    </div>
                                                    <div class="col-md-10">
                                                        <label class="form-label">Project / Activity / Sub-Activity</label>
                                                        <select name="sub_activity_id" class="form-select awp-subactivity-select" required></select>
                                                        <div class="small text-muted mt-1">This moves the record to the selected Sub-Activities Management line.</div>
                                                        <div class="subactivity-reference mt-2" data-allocation-preview>
                                                            <div class="fw-semibold">Selected Sub-Activity Year Allocations</div>
                                                            <div class="small text-muted" data-selected-subactivity-name></div>
                                                            <div class="allocation-years year-allocation-grid mt-2" data-allocation-years></div>
                                                            <div class="text-muted small mt-1 d-none" data-no-allocation-years>No yearly allocation has been recorded for this sub-activity yet.</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Implemented By</label>
                                                        <input type="text" name="implemented_by" class="form-control" data-field="implemented_by">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Budget Codes</label>
                                                        <input type="text" name="budget_code" class="form-control" data-field="budget_code">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Commitment Amount</label>
                                                        <input type="number" step="0.01" min="0" name="estimated_amount" class="form-control" data-field="estimated_amount" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Object</label>
                                                        <select name="object_type" class="form-select" data-field="object_type">
                                                            @foreach (['Consulting', 'Workshop', 'Goods', 'IOC', 'Staff, communication, translation', 'Applications'] as $objectType)
                                                                <option value="{{ $objectType }}">{{ $objectType }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Payment Schedule</label>
                                                        <select name="work_plan_payment_basis" class="form-select" data-field="work_plan_payment_basis">
                                                            @foreach ($paymentBasisLabels as $basisValue => $basisLabel)
                                                                <option value="{{ $basisValue }}">{{ $basisLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Person-Months</label>
                                                        <input type="number" min="0" max="120" name="work_plan_person_months" class="form-control" data-field="work_plan_person_months">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Monthly Amount</label>
                                                        <input type="number" step="0.01" min="0" name="work_plan_monthly_amount" class="form-control" data-field="work_plan_monthly_amount">
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label">Time Frame</label>
                                                        <div class="month-toggle-grid">
                                                            @foreach ($monthLabels as $monthKey => $monthLabel)
                                                                @php $monthInputId = 'awpEditMonth' . $monthKey; @endphp
                                                                <input class="btn-check" type="checkbox" name="work_plan_months[]" value="{{ $monthKey }}" id="{{ $monthInputId }}" data-month-field>
                                                                <label class="month-toggle" for="{{ $monthInputId }}">{{ $monthLabel }}</label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Audience</label>
                                                        <input type="text" name="work_plan_audience" class="form-control" data-field="work_plan_audience">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">No. of Units</label>
                                                        <input type="text" name="work_plan_units" class="form-control" data-field="work_plan_units">
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label">Intermediate Indicator</label>
                                                        <textarea name="intermediate_indicator" class="form-control" rows="2" data-field="intermediate_indicator"></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Result Indicator</label>
                                                        <textarea name="result_indicator" class="form-control" rows="3" data-field="result_indicator"></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Observations</label>
                                                        <textarea name="observations" class="form-control" rows="3" data-field="observations"></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">ATTP Secretariat Comments</label>
                                                        <textarea name="attp_secretariat_comments" class="form-control" rows="3" data-field="attp_secretariat_comments"></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">World Bank Comments</label>
                                                        <textarea class="form-control" rows="3" disabled data-field="world_bank_comments"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather-save me-1"></i> Save Work Plan Item
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($canReview)
                            <div class="modal fade awp-upload-modal" id="awpReviewModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="" enctype="multipart/form-data" data-awp-review-form>
                                            @csrf
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Review Work Plan Item</h5>
                                                    <div class="small text-muted" data-review-label></div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">World Bank Status</label>
                                                    <select name="status" class="form-select" data-review-status>
                                                        @foreach (['pending' => 'Not approved by World Bank', 'approved' => 'Approve by World Bank', 'rejected' => 'Reject by World Bank', 'needs_revision' => 'Needs Revision'] as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Review Notes</label>
                                                    <textarea name="review_notes" class="form-control" rows="3" placeholder="World Bank notes" data-review-notes></textarea>
                                                </div>
                                                @if ($allowDocumentUpload)
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Upload TOR</label>
                                                            <input type="file" name="tor_file" class="form-control"
                                                                accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                                                            <div class="small text-muted mt-1 d-none" data-current-tor></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Upload Concept Note</label>
                                                            <input type="file" name="concept_note_file" class="form-control"
                                                                accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                                                            <div class="small text-muted mt-1 d-none" data-current-concept-note></div>
                                                        </div>
                                                    </div>
                                                    <div class="small text-muted mt-3">
                                                        You may upload TOR, Concept Note, or both. Accepted files: PDF, Word, PowerPoint, JPG, PNG.
                                                    </div>
                                                @else
                                                    <div class="alert alert-info mb-0">
                                                        Record the World Bank no-objection decision and comments. TOR and Concept Note uploads are managed by the back office.
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather-save me-1"></i> Save Review
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>

    <style>
        .section-title {
            background: linear-gradient(90deg, #0f172a 0%, #1f3a8a 100%);
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px 8px 0 0;
            margin: -16px -16px 16px -16px;
            font-weight: 700;
        }
        .awp-table thead th {
            font-size: 12px;
            vertical-align: middle;
            white-space: nowrap;
            color: #0f172a;
            letter-spacing: 0;
            text-transform: none;
            background: #dc9898;
            border-color: #111827;
            font-weight: 800;
        }
        .awp-table tbody td {
            vertical-align: middle;
            border-color: #111827;
            color: #0f172a;
        }
        .awp-excel-table {
            border-color: #111827;
            table-layout: auto;
        }
        .awp-table tbody tr.item-row:hover {
            background: #fffdf2;
        }
        .project-row {
            background: #dc9898;
            font-weight: 700;
        }
        .activity-row {
            background: #b7a7c8;
            font-weight: 600;
        }
        .subtotal-row {
            background: #f8fafc;
        }
        .placeholder-row {
            background: #fffbeb;
        }
        .project-label {
            font-size: 15px;
            font-weight: 800;
        }
        .activity-label {
            font-size: 13px;
            font-weight: 700;
            padding: 4px 8px !important;
        }
        .item-label {
            min-width: 280px;
        }
        .budget-code {
            display: inline-flex;
            border-radius: 6px;
            padding: 3px 7px;
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .month-chip-wrap,
        .allocation-years {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .month-chip,
        .allocation-years span {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 7px;
            background: #dcfce7;
            color: #14532d;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }
        .allocation-years span {
            background: #f1f5f9;
            color: #334155;
        }
        .year-allocation-grid span {
            flex-direction: column;
            align-items: flex-start;
            min-width: 115px;
            border-radius: 8px;
            gap: 1px;
            padding: 6px 8px;
        }
        .year-allocation-grid strong {
            color: #0f172a;
            font-size: 12px;
        }
        .year-allocation-grid small {
            color: #475569;
            font-weight: 700;
        }
        .sub-allocation,
        .subactivity-reference {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            background: #f8fafc;
        }
        .schedule-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }
        .schedule-badge.monthly {
            background: #e0f2fe;
            color: #075985;
        }
        .schedule-badge.scheduled {
            background: #fef3c7;
            color: #92400e;
        }
        .schedule-badge.one_off {
            background: #f1f5f9;
            color: #475569;
        }
        .month-toggle-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(48px, 1fr));
            gap: 6px;
        }
        .month-toggle {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 7px 8px;
            text-align: center;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            background: #fff;
        }
        .btn-check:checked + .month-toggle {
            border-color: #16a34a;
            background: #dcfce7;
            color: #14532d;
        }
        .awp-upload-modal .select2-container {
            width: 100% !important;
        }
        .awp-upload-modal .select2-container--default .select2-selection--single {
            min-height: 42px;
            border-color: #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        .awp-upload-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #0f172a;
            line-height: 40px;
            padding-left: 12px;
            padding-right: 28px;
        }
        .awp-upload-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .select2-container--open {
            z-index: 20080;
        }
        .awp-status {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .awp-status.pending {
            background: #fef3c7;
            color: #92400e;
        }
        .awp-status.approved {
            background: #dcfce7;
            color: #166534;
        }
        .awp-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .awp-status.needs_revision {
            background: #e0f2fe;
            color: #075985;
        }
        .status-count {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 3px 8px;
            margin: 2px 4px 2px 0;
            font-size: 12px;
            background: #fff;
        }
        .attachment-link {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
            border-radius: 999px;
            background: #eff6ff;
            padding: 4px 9px;
        }
        .attachment-link:hover {
            color: #1e40af;
            background: #dbeafe;
        }
        .awp-review-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1px;
            line-height: 1.15;
            border-radius: 8px;
            font-weight: 700;
            text-align: left;
        }
        .awp-review-btn span {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
        }
        .awp-action-btn {
            border-radius: 8px;
            font-weight: 700;
            width: 100%;
        }
        .locked-note {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 9px;
            margin-bottom: 8px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
        }
        .awp-upload-modal {
            z-index: 20060 !important;
        }
        .modal-backdrop {
            z-index: 20050 !important;
        }
        @media print {
            .page-header .btn, .card form, nav, footer, .nxl-navigation, .nxl-header { display: none !important; }
            .nxl-container { padding: 0 !important; margin: 0 !important; }
            .card { box-shadow: none !important; border: 0 !important; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mode = document.getElementById('filter_mode');
            const fields = document.querySelectorAll('.filter-field');
            const modalItems = JSON.parse(document.getElementById('awpModalItemsData')?.textContent || '{}');

            function setField(form, field, value) {
                const input = form.querySelector('[data-field="' + field + '"]');
                if (input) {
                    input.value = value ?? '';
                }
            }

            function populateEditModal(modal, item) {
                if (!item) {
                    return;
                }

                const form = modal.querySelector('[data-awp-edit-form]');
                if (!form) {
                    return;
                }

                form.action = item.edit_action || '';
                [
                    'work_plan_serial',
                    'implemented_by',
                    'budget_code',
                    'estimated_amount',
                    'object_type',
                    'work_plan_payment_basis',
                    'work_plan_person_months',
                    'work_plan_monthly_amount',
                    'work_plan_audience',
                    'work_plan_units',
                    'intermediate_indicator',
                    'result_indicator',
                    'observations',
                    'attp_secretariat_comments',
                    'world_bank_comments',
                ].forEach(field => setField(form, field, item[field]));

                modal.querySelectorAll('[data-month-field]').forEach(input => {
                    input.checked = (item.work_plan_months || []).includes(input.value);
                });

                const select = modal.querySelector('.awp-subactivity-select');
                if (select) {
                    select.dataset.currentSubactivityId = item.sub_activity_id || '';
                    hydrateSubActivitySelect(select);
                    select.value = item.sub_activity_id || '';
                    if (window.jQuery && jQuery.fn.select2 && jQuery(select).data('select2')) {
                        jQuery(select).val(select.value).trigger('change');
                    } else {
                        updateAllocationPreview(select);
                    }
                }
            }

            function setCurrentDocument(target, label, value) {
                if (!target) {
                    return;
                }

                if (value) {
                    target.textContent = label + ': ' + value;
                    target.classList.remove('d-none');
                    return;
                }

                target.textContent = '';
                target.classList.add('d-none');
            }

            function populateReviewModal(modal, item) {
                if (!item) {
                    return;
                }

                const form = modal.querySelector('[data-awp-review-form]');
                if (!form) {
                    return;
                }

                form.action = item.review_action || '';
                const label = modal.querySelector('[data-review-label]');
                if (label) {
                    label.textContent = item.label || '';
                }

                const status = modal.querySelector('[data-review-status]');
                if (status) {
                    status.value = item.review_status || 'pending';
                }

                const notes = modal.querySelector('[data-review-notes]');
                if (notes) {
                    notes.value = item.review_notes || '';
                }

                modal.querySelectorAll('input[type="file"]').forEach(input => {
                    input.value = '';
                });
                setCurrentDocument(modal.querySelector('[data-current-tor]'), 'Current TOR', item.tor_name);
                setCurrentDocument(modal.querySelector('[data-current-concept-note]'), 'Current Concept Note', item.concept_note_name);
            }

            function updateAllocationPreview(selectElement) {
                const modalBody = selectElement.closest('.modal-body');
                if (!modalBody) {
                    return;
                }

                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const nameTarget = modalBody.querySelector('[data-selected-subactivity-name]');
                const yearsTarget = modalBody.querySelector('[data-allocation-years]');
                const emptyTarget = modalBody.querySelector('[data-no-allocation-years]');
                if (!selectedOption || !yearsTarget || !emptyTarget) {
                    return;
                }

                let years = [];
                try {
                    years = JSON.parse(selectedOption.dataset.allocationYears || '[]');
                } catch (error) {
                    years = [];
                }

                if (nameTarget) {
                    nameTarget.textContent = selectedOption.dataset.subactivityName || selectedOption.textContent.trim();
                }

                yearsTarget.innerHTML = '';
                if (!years.length) {
                    emptyTarget.classList.remove('d-none');
                    return;
                }

                emptyTarget.classList.add('d-none');
                years.forEach(yearRow => {
                    const chip = document.createElement('span');
                    const year = document.createElement('strong');
                    const amount = document.createElement('small');
                    year.textContent = yearRow.year;
                    amount.textContent = yearRow.display;
                    chip.appendChild(year);
                    chip.appendChild(amount);
                    yearsTarget.appendChild(chip);
                });
            }

            function hydrateSubActivitySelect(selectElement) {
                if (selectElement.dataset.hydrated === '1') {
                    return;
                }

                const template = document.getElementById('awpSubActivityOptionsTemplate');
                if (!template) {
                    return;
                }

                selectElement.appendChild(template.content.cloneNode(true));
                selectElement.value = selectElement.dataset.currentSubactivityId || '';
                selectElement.dataset.hydrated = '1';
            }

            function initializeSubActivityPicker(modal) {
                if (!window.jQuery || !jQuery.fn.select2) {
                    modal.querySelectorAll('.awp-subactivity-select').forEach(select => {
                        hydrateSubActivitySelect(select);
                        updateAllocationPreview(select);
                        select.addEventListener('change', () => updateAllocationPreview(select));
                    });
                    return;
                }

                jQuery(modal).find('.awp-subactivity-select').each(function () {
                    hydrateSubActivitySelect(this);
                    const select = jQuery(this);
                    if (select.data('select2')) {
                        updateAllocationPreview(this);
                        return;
                    }

                    select.select2({
                        dropdownParent: jQuery(modal),
                        width: '100%',
                        placeholder: 'Select the correct sub-activity',
                    });

                    select.on('change', function () {
                        updateAllocationPreview(this);
                    });

                    updateAllocationPreview(this);
                });
            }

            function updateFields() {
                fields.forEach(field => field.classList.add('d-none'));
                document.querySelectorAll('.filter-' + mode.value).forEach(field => field.classList.remove('d-none'));
            }

            if (mode) {
                mode.addEventListener('change', updateFields);
                updateFields();
            }

            document.querySelectorAll('.awp-upload-modal').forEach(modal => {
                modal.addEventListener('show.bs.modal', function (event) {
                    const item = modalItems[event.relatedTarget?.dataset?.itemId || ''];
                    if (modal.id === 'awpEditModal') {
                        populateEditModal(modal, item);
                    }
                    if (modal.id === 'awpReviewModal') {
                        populateReviewModal(modal, item);
                    }

                    if (modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                });

                modal.addEventListener('shown.bs.modal', function () {
                    modal.style.display = 'block';
                    modal.style.zIndex = '20060';
                    initializeSubActivityPicker(modal);
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                        backdrop.style.zIndex = '20050';
                    });
                });

                modal.addEventListener('hidden.bs.modal', function () {
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                });
            });
        });
    </script>
@endsection

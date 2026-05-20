@extends('layouts.app')

@section('title', 'Create Work Plan')

@section('content')
    @php
        $currentUser = auth()->user();
        $canEditSheet = (bool) $currentUser?->hasPermission('finance.awp.edit');
        $currency = $currency ?? 'USD';
        $existingSheetItems = collect();
        $manualLinkOptions = [];

        foreach (($sheet['projects'] ?? collect()) as $projectRow) {
            foreach (($projectRow['activities'] ?? collect()) as $activityRow) {
                foreach (($activityRow['subActivities'] ?? collect()) as $subRow) {
                    $manualLinkOptions[] = [
                        'id' => (string) $subRow['subActivity']->id,
                        'label' => $subRow['subActivity']->name,
                        'group' => $projectRow['project']->name . ' / ' . $activityRow['activity']->name,
                        'project' => $projectRow['project']->name,
                        'activity' => $activityRow['activity']->name,
                        'allocation' => (float) ($subRow['allocation'] ?? 0),
                        'committed' => (float) ($subRow['committed'] ?? 0),
                        'available' => (float) ($subRow['available'] ?? 0),
                    ];

                    foreach (($subRow['existing_items'] ?? collect()) as $existingItem) {
                        $existingSheetItems->push([
                            ...$existingItem,
                            'subActivity' => $subRow['subActivity'],
                        ]);
                    }
                }
            }
        }
    @endphp

    <style>
        .awp-builder-shell {
            background: #f6f8fb;
            min-height: calc(100vh - 120px);
            padding-bottom: 32px;
        }

        .awp-builder-header {
            background: linear-gradient(135deg, #102a43 0%, #176b87 58%, #f4b942 100%);
            color: #fff;
            border-radius: 0 0 22px 22px;
            padding: 28px 30px;
            box-shadow: 0 18px 42px rgba(16, 42, 67, .18);
        }

        .awp-builder-header .eyebrow {
            color: #f8d77a;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .78rem;
        }

        .awp-builder-panel,
        .awp-folder-bar,
        .awp-sheet-surface {
            background: #fff;
            border: 1px solid #e7edf5;
            border-radius: 8px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
        }

        .awp-builder-panel {
            padding: 20px;
            margin-top: -24px;
            position: relative;
            z-index: 2;
        }

        .awp-stat {
            border-left: 4px solid #176b87;
            background: #fff;
            border-radius: 8px;
            padding: 14px 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
            min-height: 92px;
        }

        .awp-stat.gold { border-color: #f4b942; }
        .awp-stat.green { border-color: #1d8f6f; }
        .awp-stat.red { border-color: #bf4e30; }

        .awp-folder-bar {
            padding: 18px 20px;
        }

        .awp-year-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .awp-year-tabs a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            height: 38px;
            border-radius: 8px;
            border: 1px solid #d8e2ef;
            color: #25364d;
            font-weight: 700;
            text-decoration: none;
            background: #fff;
        }

        .awp-year-tabs a.active {
            background: #102a43;
            color: #fff;
            border-color: #102a43;
        }

        .awp-sheet-surface {
            overflow: hidden;
        }

        .awp-sheet-toolbar {
            padding: 18px 20px;
            border-bottom: 1px solid #e7edf5;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .awp-sheet-table {
            margin-bottom: 0;
            font-size: .89rem;
        }

        .awp-sheet-table th {
            background: #102a43;
            color: #fff;
            border-color: #183b5b;
            white-space: nowrap;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .awp-sheet-table td {
            vertical-align: middle;
        }

        .awp-project-row td {
            background: #eaf3f8;
            color: #102a43;
            font-weight: 800;
        }

        .awp-activity-row td {
            background: #fff6dc;
            color: #6f4d00;
            font-weight: 800;
        }

        .awp-existing-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            background: #edf7f2;
            color: #176348;
            padding: 5px 10px;
            font-size: .76rem;
            font-weight: 800;
        }

        .awp-wb-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .74rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .awp-wb-status.pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .awp-wb-status.approved {
            background: #dcfce7;
            color: #14532d;
        }

        .awp-wb-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .awp-wb-status.needs_revision {
            background: #fef3c7;
            color: #92400e;
        }

        .awp-amount-input {
            min-width: 150px;
        }

        .awp-line-title {
            max-width: 440px;
            white-space: normal;
        }

        .awp-muted {
            color: #637083;
        }

        .awp-switch-box {
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            padding: 12px 14px;
            background: #f9fbfd;
        }

        .awp-method-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .awp-method-tabs .nav-link {
            border: 1px solid #d8e2ef;
            color: #25364d;
            font-weight: 800;
            border-radius: 8px;
            background: #fff;
            padding: 10px 14px;
        }

        .awp-method-tabs .nav-link.active {
            background: #102a43;
            border-color: #102a43;
            color: #fff;
        }

        .awp-manual-row {
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            padding: 16px;
            background: #fbfdff;
            position: relative;
        }

        .awp-row-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #102a43;
            color: #fff;
            font-weight: 800;
        }

        .awp-allocation-preview {
            background: #eef7fb;
            border: 1px solid #cfe4ef;
            border-radius: 8px;
            padding: 10px 12px;
            min-height: 74px;
        }

        .awp-form-help {
            font-size: .78rem;
            color: #637083;
        }

        body.awp-workplan-modal-open .modal-backdrop.show {
            z-index: 3040 !important;
            opacity: .42;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            filter: none !important;
        }

        .awp-workplan-modal {
            z-index: 3050 !important;
        }

        .awp-workplan-modal .modal-dialog {
            max-width: min(960px, calc(100vw - 32px));
        }

        .awp-workplan-modal .modal-content {
            border: 0;
            border-radius: 10px;
            box-shadow: 0 32px 80px rgba(15, 23, 42, .36);
            overflow: hidden;
        }

        .awp-workplan-modal .modal-body {
            background: #f8fafc;
        }

        @media (max-width: 768px) {
            .awp-builder-header {
                padding: 22px 18px;
            }

            .awp-builder-panel {
                margin-top: -12px;
            }
        }
    </style>

    <div class="awp-builder-shell">
        <div class="nxl-container">
            <div class="awp-builder-header">
                <div class="eyebrow mb-2">Work Plans Registry</div>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h3 class="fw-bold text-white mb-2">Create Work Plan</h3>
                        <div class="fs-6 text-white-50">
                            Yearly sheets are generated from the program allocation structure and held inside one named work-plan folder.
                        </div>
                    </div>
                    @if ($program)
                        <div class="text-lg-end">
                            <div class="text-white-50 small">Active Folder</div>
                            <div class="h5 fw-bold mb-0">{{ $folderName }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success mt-3">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
            @endif

            <div class="awp-builder-panel">
                <form method="GET" action="{{ route('finance.awp.create') }}" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Program</label>
                        <select name="program_id" class="form-select" required>
                            @foreach ($programs as $programOption)
                                <option value="{{ $programOption->id }}" @selected((string) $selectedProgramId === (string) $programOption->id)>
                                    {{ $programOption->program_id ? $programOption->program_id . ' - ' : '' }}{{ $programOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Year Sheet</label>
                        <select name="year" class="form-select">
                            @foreach ($years as $yearOption)
                                <option value="{{ $yearOption }}" @selected((int) $selectedYear === (int) $yearOption)>{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Folder Name</label>
                        <input type="text" name="folder_name" class="form-control" value="{{ $folderName }}" list="awpFolderOptions" required>
                        <datalist id="awpFolderOptions">
                            @foreach ($folderOptions as $folderOption)
                                <option value="{{ $folderOption }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="feather-search me-1"></i> Open Sheet
                        </button>
                    </div>
                </form>
            </div>

            @if ($program)
                <div class="awp-folder-bar mt-4">
                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                        <div>
                            <div class="small text-uppercase fw-bold awp-muted">Folder</div>
                            <h5 class="fw-bold mb-1">{{ $folderName }}</h5>
                            <div class="awp-muted">{{ $program->name }}</div>
                        </div>
                        <div class="awp-year-tabs">
                            @foreach ($years as $yearOption)
                                <a href="{{ route('finance.awp.create', ['program_id' => $program->id, 'year' => $yearOption, 'folder_name' => $folderName]) }}"
                                    class="{{ (int) $selectedYear === (int) $yearOption ? 'active' : '' }}">
                                    {{ $yearOption }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @if ($canEditSheet && $folderOptions->contains($folderName))
                        <form method="POST" action="{{ route('finance.awp.folder.rename') }}" class="row g-2 align-items-end mt-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="program_id" value="{{ $program->id }}">
                            <input type="hidden" name="year" value="{{ $selectedYear }}">
                            <input type="hidden" name="old_folder_name" value="{{ $folderName }}">
                            <div class="col-md-8 col-lg-5">
                                <label class="form-label fw-semibold">Rename Folder</label>
                                <input type="text" name="folder_name" class="form-control" value="{{ $folderName }}" required>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <button class="btn btn-outline-primary w-100" type="submit">
                                    <i class="feather-edit-2 me-1"></i> Rename
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="row g-3 mt-1">
                    @foreach ([
                        ['label' => 'Year Allocation', 'value' => $currency . ' ' . number_format($sheet['totals']['allocation'] ?? 0, 2), 'class' => ''],
                        ['label' => 'Committed', 'value' => $currency . ' ' . number_format($sheet['totals']['committed'] ?? 0, 2), 'class' => 'gold'],
                        ['label' => 'Current Work Plan', 'value' => $currency . ' ' . number_format($sheet['totals']['planned'] ?? 0, 2), 'class' => 'green'],
                        ['label' => 'Available To Pull', 'value' => $currency . ' ' . number_format($sheet['totals']['available'] ?? 0, 2), 'class' => 'red'],
                    ] as $stat)
                        <div class="col-md-6 col-xl-3">
                            <div class="awp-stat {{ $stat['class'] }}">
                                <div class="small text-uppercase fw-bold awp-muted">{{ $stat['label'] }}</div>
                                <div class="fs-5 fw-bold mt-2">{{ $stat['value'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (! $funding)
                    <div class="alert alert-warning mt-4">
                        This program does not have an approved funding source yet, so a work plan sheet cannot be committed.
                    </div>
                @endif

                <ul class="nav awp-method-tabs mt-4" id="awpMethodTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="awpPullTab" data-bs-toggle="tab" data-bs-target="#awpPullPanel"
                            type="button" role="tab" aria-controls="awpPullPanel" aria-selected="true">
                            <i class="feather-download-cloud me-1"></i> Pull From Allocations
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="awpManualTab" data-bs-toggle="tab" data-bs-target="#awpManualPanel"
                            type="button" role="tab" aria-controls="awpManualPanel" aria-selected="false">
                            <i class="feather-edit-3 me-1"></i> Manual Work Plan
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="awpMethodPanels">
                    <div class="tab-pane fade show active" id="awpPullPanel" role="tabpanel" aria-labelledby="awpPullTab">
                <div class="awp-sheet-surface">
                    <form method="POST" action="{{ route('finance.awp.store-from-allocations') }}" id="awpAllocationPullForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="program_id" value="{{ $program->id }}">
                        <input type="hidden" name="year" value="{{ $selectedYear }}">
                        <input type="hidden" name="folder_name" value="{{ $folderName }}">

                        <div class="awp-sheet-toolbar">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $selectedYear }} Sheet</h5>
                                <div class="awp-muted">Program, project, activity, and sub-activity lines stay in one yearly sheet.</div>
                            </div>
                            <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <label class="awp-switch-box mb-0">
                                    <input class="form-check-input me-2" type="checkbox" name="use_allocations" value="1" checked>
                                    <span class="fw-bold">Pull data from allocations completely</span>
                                </label>
                                <button class="btn btn-success" type="submit" @disabled(! $funding || (($sheet['totals']['available'] ?? 0) <= 0))>
                                    <i class="feather-download-cloud me-1"></i> Use Allocations
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered awp-sheet-table">
                                <thead>
                                    <tr>
                                        <th style="width: 54px;">Use</th>
                                        <th>Structure</th>
                                        <th class="text-end">Allocation</th>
                                        <th class="text-end">Committed</th>
                                        <th class="text-end">Available</th>
                                        <th class="text-end">Work Plan Amount</th>
                                        <th>Documents</th>
                                        <th>Existing Lines</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($sheet['projects'] ?? collect()) as $projectRow)
                                        <tr class="awp-project-row">
                                            <td></td>
                                            <td colspan="6">
                                                <i class="feather-folder me-1"></i>
                                                {{ $projectRow['project']->name }}
                                            </td>
                                            <td class="text-end">{{ $currency }} {{ number_format($projectRow['totals']['planned'] ?? 0, 2) }}</td>
                                        </tr>

                                        @foreach ($projectRow['activities'] as $activityRow)
                                            <tr class="awp-activity-row">
                                                <td></td>
                                            <td colspan="6">
                                                    <i class="feather-list me-1"></i>
                                                    {{ $activityRow['activity']->name }}
                                                </td>
                                                <td class="text-end">{{ $currency }} {{ number_format($activityRow['totals']['planned'] ?? 0, 2) }}</td>
                                            </tr>

                                            @foreach ($activityRow['subActivities'] as $subRow)
                                                @php
                                                    $subActivity = $subRow['subActivity'];
                                                    $available = (float) ($subRow['available'] ?? 0);
                                                    $allocation = (float) ($subRow['allocation'] ?? 0);
                                                    $committed = (float) ($subRow['committed'] ?? 0);
                                                    $planned = (float) ($subRow['planned'] ?? 0);
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <input class="form-check-input" type="checkbox" name="include[{{ $subActivity->id }}]"
                                                            value="1" @checked($available > 0) @disabled($available <= 0)>
                                                    </td>
                                                    <td class="awp-line-title">
                                                        <div class="fw-bold">{{ $subActivity->name }}</div>
                                                        @if ($subActivity->description)
                                                            <div class="small awp-muted mt-1">{{ Str::limit($subActivity->description, 150) }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-end fw-semibold">{{ $currency }} {{ number_format($allocation, 2) }}</td>
                                                    <td class="text-end">{{ $currency }} {{ number_format($committed, 2) }}</td>
                                                    <td class="text-end">
                                                        <span class="badge {{ $available > 0 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                                            {{ $currency }} {{ number_format($available, 2) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <input class="form-control form-control-sm text-end awp-amount-input ms-auto" type="number"
                                                            step="0.01" min="0" max="{{ $available }}" name="amounts[{{ $subActivity->id }}]"
                                                            value="{{ number_format($available, 2, '.', '') }}" @disabled($available <= 0)>
                                                    </td>
                                                    <td style="min-width: 230px;">
                                                        <div class="d-flex flex-column gap-2">
                                                            <div>
                                                                <label class="form-label small fw-bold mb-1">TOR</label>
                                                                <input class="form-control form-control-sm" type="file"
                                                                    name="documents[{{ $subActivity->id }}][tor_file]"
                                                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                                                                    @disabled($available <= 0)>
                                                            </div>
                                                            <div>
                                                                <label class="form-label small fw-bold mb-1">Concept Note</label>
                                                                <input class="form-control form-control-sm" type="file"
                                                                    name="documents[{{ $subActivity->id }}][concept_note_file]"
                                                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                                                                    @disabled($available <= 0)>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if (($subRow['existing_items'] ?? collect())->isEmpty())
                                                            <span class="text-muted small">No line yet</span>
                                                        @else
                                                            <div class="d-flex flex-column gap-2">
                                                                @foreach ($subRow['existing_items'] as $existingItem)
                                                                    @php
                                                                        $modalId = 'awpSheetItemModal' . str_replace('-', '', $existingItem['item']->id);
                                                                        $deleteFormId = 'awpDeleteItemForm' . str_replace('-', '', $existingItem['item']->id);
                                                                        $wbStatus = $existingItem['status'] ?? 'pending';
                                                                        $wbStatusLabel = $existingItem['status_label'] ?? 'Not approved by World Bank';
                                                                    @endphp
                                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                                        <span class="awp-existing-pill">
                                                                            <i class="feather-check-circle"></i>
                                                                            {{ $currency }} {{ number_format($existingItem['amount'], 2) }}
                                                                        </span>
                                                                        <span class="awp-wb-status {{ $wbStatus }}">
                                                                            <i class="{{ $wbStatus === 'approved' ? 'feather-check-circle' : ($wbStatus === 'rejected' ? 'feather-x-circle' : 'feather-clock') }}"></i>
                                                                            {{ $wbStatusLabel }}
                                                                        </span>
                                                                        <span class="small fw-semibold">{{ Str::limit($existingItem['label'], 55) }}</span>
                                                                        @if ($canEditSheet && ! $existingItem['locked'])
                                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                                                                <i class="feather-edit-2"></i>
                                                                            </button>
                                                                            <button type="submit" form="{{ $deleteFormId }}" class="btn btn-sm btn-outline-danger"
                                                                                onclick="return confirm('Remove this work plan line?')">
                                                                                <i class="feather-trash-2"></i>
                                                                            </button>
                                                                        @else
                                                                            <span class="badge bg-light text-muted">Locked after World Bank approval</span>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                    </div>

                    <div class="tab-pane fade" id="awpManualPanel" role="tabpanel" aria-labelledby="awpManualTab">
                        <div class="awp-sheet-surface">
                            <form method="POST" action="{{ route('finance.awp.store-manual') }}" id="awpManualWorkPlanForm" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="program_id" value="{{ $program->id }}">
                                <input type="hidden" name="year" value="{{ $selectedYear }}">
                                <input type="hidden" name="folder_name" value="{{ $folderName }}">

                                <div class="awp-sheet-toolbar">
                                    <div>
                                        <h5 class="fw-bold mb-1">Manual Work Plan Builder</h5>
                                        <div class="awp-muted">Add items one by one, link each item to an allocation line, then save the full year sheet.</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-outline-primary" type="button" id="awpAddManualRow" @disabled(! $funding)>
                                            <i class="feather-plus me-1"></i> Add Item
                                        </button>
                                        <button class="btn btn-primary" type="submit" @disabled(! $funding)>
                                            <i class="feather-save me-1"></i> Save Work Plan
                                        </button>
                                    </div>
                                </div>

                                <div class="p-3 p-lg-4">
                                    <div class="alert alert-info mb-3">
                                        Select the item link first. The allocation and remaining available amount will appear automatically. Use
                                        <strong>Same as allocation</strong> to copy the available amount into Actual Amount, or type a lower amount.
                                    </div>
                                    <div id="awpManualRows" class="d-flex flex-column gap-3"></div>
                                    <div class="text-muted text-center py-4 d-none" id="awpManualEmptyState">
                                        Click Add Item to begin the manual work plan sheet.
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @foreach ($existingSheetItems as $existingItem)
                    @php
                        $item = $existingItem['item'];
                        $request = $existingItem['request'];
                        $subActivity = $existingItem['subActivity'];
                        $review = $existingItem['review'] ?? null;
                        $wbStatus = $existingItem['status'] ?? 'pending';
                        $wbStatusLabel = $existingItem['status_label'] ?? 'Not approved by World Bank';
                        $modalId = 'awpSheetItemModal' . str_replace('-', '', $item->id);
                        $deleteFormId = 'awpDeleteItemForm' . str_replace('-', '', $item->id);
                    @endphp

                    @if ($canEditSheet && ! $existingItem['locked'])
                        <form method="POST" action="{{ route('finance.awp.items.destroy', $item) }}" id="{{ $deleteFormId }}">
                            @csrf
                            @method('DELETE')
                        </form>

                        <div class="modal fade awp-workplan-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('finance.awp.items.sheet.update', $item) }}" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header" style="background:#102a43;color:#fff;">
                                            <div>
                                                <h5 class="modal-title fw-bold">Update Work Plan Line</h5>
                                                <div class="small" style="color:#f8d77a;">{{ $subActivity->name }}</div>
                                                <div class="mt-2">
                                                    <span class="awp-wb-status {{ $wbStatus }}">{{ $wbStatusLabel }}</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label fw-semibold">Line Name</label>
                                                    <input type="text" name="activity" class="form-control"
                                                        value="{{ $item->resource?->name ?: $item->milestone ?: $subActivity->name }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Amount</label>
                                                    <input type="number" step="0.01" min="0" name="estimated_amount"
                                                        class="form-control text-end"
                                                        value="{{ number_format($existingItem['amount'], 2, '.', '') }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Implemented By</label>
                                                    <input type="text" name="implemented_by" class="form-control" value="{{ $item->implemented_by }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Budget Code</label>
                                                    <input type="text" name="budget_code" class="form-control" value="{{ $item->budget_code }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Object</label>
                                                    <input type="text" name="object_type" class="form-control" value="{{ $item->object_type }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Result Indicator</label>
                                                    <textarea name="result_indicator" class="form-control" rows="3">{{ $item->result_indicator }}</textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Notes</label>
                                                    <textarea name="observations" class="form-control" rows="3">{{ $item->observations }}</textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">TOR Document</label>
                                                    <input type="file" name="tor_file" class="form-control"
                                                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                                                    @if ($review?->tor_name)
                                                        <div class="small text-muted mt-1">Current TOR: {{ $review->tor_name }}</div>
                                                    @endif
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Concept Note Document</label>
                                                    <input type="file" name="concept_note_file" class="form-control"
                                                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                                                    @if ($review?->concept_note_name)
                                                        <div class="small text-muted mt-1">Current Concept Note: {{ $review->concept_note_name }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather-save me-1"></i> Save Line
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="alert alert-info mt-4">
                    Select a program to create its yearly work-plan folder.
                </div>
            @endif
        </div>
    </div>

    @if ($program)
        <script type="application/json" id="awpManualOptionsData">@json($manualLinkOptions)</script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const optionsNode = document.getElementById('awpManualOptionsData');
                const rowsContainer = document.getElementById('awpManualRows');
                const addButton = document.getElementById('awpAddManualRow');
                const emptyState = document.getElementById('awpManualEmptyState');
                const manualForm = document.getElementById('awpManualWorkPlanForm');
                const options = optionsNode ? JSON.parse(optionsNode.textContent || '[]') : [];
                let rowIndex = 0;

                document.querySelectorAll('.awp-workplan-modal').forEach((modal) => {
                    document.body.appendChild(modal);

                    modal.addEventListener('show.bs.modal', () => {
                        document.body.classList.add('awp-workplan-modal-open');
                    });

                    modal.addEventListener('hidden.bs.modal', () => {
                        if (!document.querySelector('.awp-workplan-modal.show')) {
                            document.body.classList.remove('awp-workplan-modal-open');
                        }
                    });
                });

                const formatter = new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const optionById = (id) => options.find((option) => option.id === id);

                const groupedOptionsHtml = () => {
                    const groups = new Map();
                    options.forEach((option) => {
                        if (!groups.has(option.group)) {
                            groups.set(option.group, []);
                        }
                        groups.get(option.group).push(option);
                    });

                    let html = '<option value="">Select project / activity / sub-activity</option>';
                    groups.forEach((groupOptions, groupName) => {
                        html += `<optgroup label="${escapeHtml(groupName)}">`;
                        groupOptions.forEach((option) => {
                            const available = Number(option.available || 0);
                            html += `<option value="${escapeHtml(option.id)}" ${available <= 0 ? 'disabled' : ''}>${escapeHtml(option.label)} - Available {{ $currency }} ${formatter.format(available)}</option>`;
                        });
                        html += '</optgroup>';
                    });

                    return html;
                };

                const refreshEmptyState = () => {
                    if (!emptyState || !rowsContainer) {
                        return;
                    }

                    emptyState.classList.toggle('d-none', rowsContainer.children.length > 0);
                    rowsContainer.querySelectorAll('[data-row-number]').forEach((node, index) => {
                        node.textContent = index + 1;
                    });
                };

                const updateRowFromSelection = (row) => {
                    const select = row.querySelector('[data-manual-link]');
                    const actual = row.querySelector('[data-manual-actual]');
                    const same = row.querySelector('[data-manual-same]');
                    const allocation = row.querySelector('[data-manual-allocation]');
                    const committed = row.querySelector('[data-manual-committed]');
                    const available = row.querySelector('[data-manual-available]');
                    const title = row.querySelector('[data-manual-title]');
                    const preview = row.querySelector('[data-manual-preview]');
                    const selected = optionById(select.value);

                    if (!selected) {
                        allocation.value = '';
                        committed.value = '';
                        available.value = '';
                        actual.value = '';
                        actual.removeAttribute('max');
                        preview.textContent = 'Select a linked allocation line to load its year budget.';
                        return;
                    }

                    const allocationAmount = Number(selected.allocation || 0);
                    const committedAmount = Number(selected.committed || 0);
                    const availableAmount = Number(selected.available || 0);

                    allocation.value = formatter.format(allocationAmount);
                    committed.value = formatter.format(committedAmount);
                    available.value = formatter.format(availableAmount);
                    actual.max = availableAmount.toFixed(2);
                    actual.placeholder = availableAmount.toFixed(2);
                    preview.textContent = `${selected.project} / ${selected.activity}`;

                    if (!title.value.trim()) {
                        title.value = selected.label;
                    }

                    if (same.checked) {
                        actual.value = availableAmount.toFixed(2);
                    }

                    if (Number(actual.value || 0) > availableAmount) {
                        actual.value = availableAmount.toFixed(2);
                    }
                };

                const addManualRow = () => {
                    if (!rowsContainer || options.length === 0) {
                        return;
                    }

                    const index = rowIndex++;
                    const row = document.createElement('div');
                    row.className = 'awp-manual-row';
                    row.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="awp-row-number" data-row-number></span>
                                <div>
                                    <div class="fw-bold">Manual Work Plan Item</div>
                                    <div class="awp-form-help" data-manual-preview>Select a linked allocation line to load its year budget.</div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-remove-manual-row>
                                <i class="feather-trash-2 me-1"></i> Remove
                            </button>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Item Link</label>
                                <select class="form-select" name="items[${index}][sub_activity_id]" data-manual-link required>
                                    ${groupedOptionsHtml()}
                                </select>
                                <div class="awp-form-help mt-1">Grouped by project and activity.</div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Item Name</label>
                                <input type="text" class="form-control" name="items[${index}][title]" data-manual-title required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Year Allocation</label>
                                <input type="text" class="form-control text-end" data-manual-allocation readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Already Committed</label>
                                <input type="text" class="form-control text-end" data-manual-committed readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Available Allocation</label>
                                <input type="text" class="form-control text-end" data-manual-available readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Actual Amount</label>
                                <input type="number" step="0.01" min="0.01" class="form-control text-end" name="items[${index}][actual_amount]" data-manual-actual required>
                                <div class="awp-form-help">Cannot exceed available allocation.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Use Same Amount</label>
                                <label class="awp-switch-box w-100">
                                    <input class="form-check-input me-2" type="checkbox" data-manual-same>
                                    <span class="fw-bold">Same as allocation</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Implemented By</label>
                                <input type="text" class="form-control" name="items[${index}][implemented_by]" value="ATTP Secretariat">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Budget Code</label>
                                <input type="text" class="form-control" name="items[${index}][budget_code]">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Object</label>
                                <input type="text" class="form-control" name="items[${index}][object_type]" value="Manual Work Plan">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Result Indicator</label>
                                <input type="text" class="form-control" name="items[${index}][result_indicator]">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">TOR Document</label>
                                <input type="file" class="form-control" name="items[${index}][tor_file]" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Concept Note Document</label>
                                <input type="file" class="form-control" name="items[${index}][concept_note_file]" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea class="form-control" rows="2" name="items[${index}][notes]"></textarea>
                            </div>
                        </div>
                    `;

                    rowsContainer.appendChild(row);
                    row.querySelector('[data-manual-link]').addEventListener('change', () => updateRowFromSelection(row));
                    row.querySelector('[data-manual-same]').addEventListener('change', () => updateRowFromSelection(row));
                    row.querySelector('[data-manual-actual]').addEventListener('input', (event) => {
                        const max = Number(event.target.max || 0);
                        const value = Number(event.target.value || 0);
                        if (max > 0 && value > max) {
                            event.target.value = max.toFixed(2);
                        }
                    });
                    row.querySelector('[data-remove-manual-row]').addEventListener('click', () => {
                        row.remove();
                        refreshEmptyState();
                    });

                    refreshEmptyState();
                };

                addButton?.addEventListener('click', addManualRow);
                manualForm?.addEventListener('submit', (event) => {
                    if (!rowsContainer || rowsContainer.children.length === 0) {
                        event.preventDefault();
                        addManualRow();
                    }
                });

                if (options.length > 0) {
                    addManualRow();
                }

                refreshEmptyState();
            });
        </script>
    @endif
@endsection

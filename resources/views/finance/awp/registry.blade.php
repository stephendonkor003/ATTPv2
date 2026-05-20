@extends('layouts.app')

@section('title', 'Work Plans Registry')

@section('content')
    <style>
        .awp-registry-shell {
            background: #f6f8fb;
            min-height: calc(100vh - 120px);
            padding-bottom: 34px;
        }

        .awp-registry-header {
            background: linear-gradient(135deg, #102a43 0%, #176b87 68%, #f4b942 100%);
            color: #fff;
            border-radius: 0 0 22px 22px;
            padding: 28px 30px;
            box-shadow: 0 18px 42px rgba(16, 42, 67, .18);
        }

        .awp-registry-header .eyebrow {
            color: #f8d77a;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .78rem;
        }

        .awp-filter-panel,
        .awp-folder-card {
            background: #fff;
            border: 1px solid #e7edf5;
            border-radius: 8px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
        }

        .awp-filter-panel {
            padding: 18px 20px;
            margin-top: -22px;
            position: relative;
            z-index: 2;
        }

        .awp-stat {
            background: #fff;
            border-left: 4px solid #176b87;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
            padding: 14px 16px;
            min-height: 88px;
        }

        .awp-stat.gold { border-color: #f4b942; }
        .awp-stat.green { border-color: #1d8f6f; }
        .awp-stat.red { border-color: #bf4e30; }

        .awp-folder-card {
            height: 100%;
            padding: 18px;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .awp-folder-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .1);
        }

        .awp-folder-icon {
            width: 54px;
            height: 44px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff4cc;
            color: #9b6900;
            font-size: 1.45rem;
            box-shadow: inset 0 -8px 0 rgba(244, 185, 66, .22);
        }

        .awp-item-chip {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            border-radius: 999px;
            background: #eef7fb;
            color: #17435f;
            padding: 5px 10px;
            font-size: .76rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .awp-muted {
            color: #637083;
        }

        .awp-status-pill {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 10px;
            background: #edf7f2;
            color: #176348;
            font-size: .75rem;
            font-weight: 800;
        }

        @media (max-width: 768px) {
            .awp-registry-header {
                padding: 22px 18px;
            }

            .awp-filter-panel {
                margin-top: -12px;
            }
        }
    </style>

    <div class="awp-registry-shell">
        <div class="nxl-container">
            <div class="awp-registry-header">
                <div class="eyebrow mb-2">Work Plans Registry</div>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <h3 class="fw-bold text-white mb-2">Saved Work Plans</h3>
                        <div class="fs-6 text-white-50">Each folder contains the yearly sheet and all work-plan items saved under it.</div>
                    </div>
                    <a href="{{ route('finance.awp.create') }}" class="btn btn-warning fw-bold">
                        <i class="feather-folder-plus me-1"></i> Create New Work Plan
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success mt-3">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
            @endif

            <div class="awp-filter-panel">
                <form method="GET" action="{{ route('finance.awp.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold">Program</label>
                        <select name="program_id" class="form-select">
                            <option value="">All Programs</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected((string) $selectedProgramId === (string) $program->id)>
                                    {{ $program->program_id ? $program->program_id . ' - ' : '' }}{{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            @foreach ($years as $year)
                                <option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="feather-filter me-1"></i> Filter
                        </button>
                    </div>
                    <div class="col-lg-2">
                        <a href="{{ route('finance.awp.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>

            <div class="row g-3 mt-1">
                @foreach ([
                    ['label' => 'Folders', 'value' => number_format($summary['folders'] ?? 0), 'class' => ''],
                    ['label' => 'Programs', 'value' => number_format($summary['programs'] ?? 0), 'class' => 'gold'],
                    ['label' => 'Items Saved', 'value' => number_format($summary['items'] ?? 0), 'class' => 'green'],
                    ['label' => 'Work Plan Amount', 'value' => 'USD ' . number_format($summary['amount'] ?? 0, 2), 'class' => 'red'],
                ] as $stat)
                    <div class="col-md-6 col-xl-3">
                        <div class="awp-stat {{ $stat['class'] }}">
                            <div class="small text-uppercase fw-bold awp-muted">{{ $stat['label'] }}</div>
                            <div class="fs-5 fw-bold mt-2">{{ $stat['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mt-2">
                @forelse ($folders as $folder)
                    <div class="col-xl-4 col-lg-6">
                        <div class="awp-folder-card">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="d-flex gap-3">
                                    <div class="awp-folder-icon">
                                        <i class="feather-folder"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1">{{ $folder['folder_name'] }}</h5>
                                        <div class="awp-muted small">{{ $folder['program']?->name ?? 'Program not linked' }}</div>
                                    </div>
                                </div>
                                <span class="awp-status-pill">{{ $folder['year'] ?: 'Year' }}</span>
                            </div>

                            <div class="row g-2 mt-3">
                                <div class="col-6">
                                    <div class="small awp-muted fw-semibold">Items Saved</div>
                                    <div class="fw-bold">{{ number_format($folder['items_count']) }}</div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="small awp-muted fw-semibold">Amount</div>
                                    <div class="fw-bold">{{ $folder['currency'] }} {{ number_format($folder['planned_amount'], 2) }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small awp-muted fw-semibold">Committed</div>
                                    <div class="fw-bold">{{ $folder['currency'] }} {{ number_format($folder['committed_amount'], 2) }}</div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="small awp-muted fw-semibold">Approved</div>
                                    <div class="fw-bold">{{ number_format($folder['approved_count']) }}</div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="small text-uppercase fw-bold awp-muted mb-2">Items In Folder</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse ($folder['items_preview'] as $itemName)
                                        <span class="awp-item-chip">{{ $itemName }}</span>
                                    @empty
                                        <span class="text-muted small">No item saved yet</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="small awp-muted">
                                    Updated {{ $folder['latest_update'] ? \Illuminate\Support\Carbon::parse($folder['latest_update'])->diffForHumans() : 'recently' }}
                                </div>
                                @if ($folder['program_id'])
                                    <a href="{{ route('finance.awp.create', ['program_id' => $folder['program_id'], 'year' => $folder['year'], 'folder_name' => $folder['folder_name']]) }}"
                                        class="btn btn-sm btn-outline-primary fw-bold">
                                        Open Folder
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="awp-folder-card text-center py-5">
                            <div class="awp-folder-icon mx-auto mb-3">
                                <i class="feather-folder-plus"></i>
                            </div>
                            <h5 class="fw-bold">No saved work plan folder yet</h5>
                            <p class="awp-muted mb-3">Create the first yearly work plan from allocations or manually item by item.</p>
                            <a href="{{ route('finance.awp.create') }}" class="btn btn-primary fw-bold">
                                <i class="feather-folder-plus me-1"></i> Create New Work Plan
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Executive Budget Dashboard')


<style>
    /* ===== Executive Dashboard Enhanced ===== */
    .summary-card {
        border-left: 5px solid transparent;
        transition: all 0.3s ease-in-out;
        border-radius: 10px;
    }

    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .tree-branch {
        border-left: 2px dashed #dcdcdc;
        padding-left: 1rem;
        margin-left: 0.5rem;
    }

    .tree-item {
        cursor: pointer;
        margin: 0.3rem 0;
        position: relative;
    }

    .tree-item::before {
        content: "";
        position: absolute;
        left: -1rem;
        top: 0.75rem;
        width: 8px;
        height: 8px;
        background: #bbb;
        border-radius: 50%;
    }

    .tree-item:hover {
        color: var(--bs-primary);
        font-weight: 500;
    }

    .progress {
        height: 7px;
    }

    .chart-container {
        width: 120px;
        height: 120px;
        margin: auto;
    }

    .expand-icon {
        transition: transform 0.3s ease;
    }

    .expand-icon.rotate {
        transform: rotate(90deg);
    }

    .search-bar {
        max-width: 250px;
    }
</style>


@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- PAGE HEADER -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">Budget Allocation Executive Dashboard</h4>
                    <p class="text-muted mb-0">Comprehensive tree-view and data analytics across Sectors, Programs, Projects,
                        and Activities.</p>
                </div>

                <div class="d-flex gap-2">
                    <input type="text" id="searchTree" class="form-control form-control-sm search-bar"
                        placeholder="Search...">

                    <!-- ✅ Add this line -->
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#performanceModal">
                        <i class="bi bi-bar-chart-line me-1"></i> Analytics
                    </button>

                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>


            <!-- STATISTICS -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card summary-card p-3 border-start border-primary border-3">
                        <h6 class="text-muted mb-1">Programs</h6>
                        <h3 class="fw-bold text-primary">{{ $stats['programs'] ?? 0 }}</h3>
                        <small class="text-muted">across {{ $stats['sectors'] ?? 0 }} sectors</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card p-3 border-start border-success border-3">
                        <h6 class="text-muted mb-1">Projects</h6>
                        <h3 class="fw-bold text-success">{{ $stats['projects'] ?? 0 }}</h3>
                        <small class="text-muted">linked to programs</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card p-3 border-start border-warning border-3">
                        <h6 class="text-muted mb-1">Activities</h6>
                        <h3 class="fw-bold text-warning">{{ $stats['activities'] ?? 0 }}</h3>
                        <small class="text-muted">supporting project execution</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card p-3 border-start border-danger border-3">
                        <h6 class="text-muted mb-1">Sub-Activities</h6>
                        <h3 class="fw-bold text-danger">{{ $stats['sub_activities'] ?? 0 }}</h3>
                        <small class="text-muted">granular implementation tasks</small>
                    </div>
                </div>
            </div>

            <!-- FINANCIAL SNAPSHOT -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i> Financial Overview</h5>
                </div>
                <div class="card-body text-center">
                    <div class="row g-4">
                        <div class="col-md-4 border-end">
                            <h6 class="text-muted">Total Approved Budget</h6>
                            <h4 class="fw-bold text-primary">USD {{ number_format($summary['total_budget'] ?? 0, 2) }}</h4>
                        </div>
                        <div class="col-md-4 border-end">
                            <h6 class="text-muted">Total Allocated</h6>
                            <h4 class="fw-bold text-success">USD {{ number_format($summary['total_allocated'] ?? 0, 2) }}
                            </h4>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Unallocated Balance</h6>
                            <h4 class="fw-bold text-danger">USD
                                {{ number_format(($summary['total_budget'] ?? 0) - ($summary['total_allocated'] ?? 0), 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTOR DISTRIBUTION -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i> Sectoral Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($sectors as $sector)
                            <div class="col-md-3 text-center mb-3">
                                <h6 class="fw-semibold">{{ $sector->name }}</h6>
                                <div class="chart-container">
                                    <canvas id="chart{{ $sector->id }}"></canvas>
                                </div>
                                <small class="text-muted">
                                    USD {{ number_format($sector->total_budget, 2) }} <br>
                                    {{ $sector->share_percent }}% of total
                                </small>
                            </div>
                        @empty
                            <p class="text-center text-muted py-3">No sector data available.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- TREE STRUCTURE -->
            <div class="card shadow-sm mb-5">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i> Hierarchical View (Sector → Program → Project →
                        Activity → Sub-Activity)</h5>
                </div>
                <div class="card-body">
                    @forelse($sectors as $sector)
                        <div class="mb-4">
                            <div class="tree-item sector-header" data-bs-toggle="collapse"
                                data-bs-target="#sector{{ $sector->id }}">
                                <i class="bi bi-chevron-right expand-icon me-1"></i>
                                <strong>{{ $sector->name }}</strong>
                                <span class="text-muted small">({{ $sector->programs->count() }} programs)</span>
                            </div>
                            <div class="collapse tree-branch" id="sector{{ $sector->id }}">
                                @foreach ($sector->programs as $program)
                                    <div class="tree-item" data-bs-toggle="collapse"
                                        data-bs-target="#prog{{ $program->id }}">
                                        <i class="bi bi-chevron-right expand-icon me-1"></i>
                                        <i class="bi bi-folder2 text-warning me-1"></i> {{ $program->name }}
                                        <span class="text-muted small">(Projects: {{ $program->projects->count() }})</span>
                                    </div>
                                    <div class="collapse tree-branch" id="prog{{ $program->id }}">
                                        @foreach ($program->projects as $project)
                                            <div class="tree-item" data-bs-toggle="collapse"
                                                data-bs-target="#proj{{ $project->id }}">
                                                <i class="bi bi-chevron-right expand-icon me-1"></i>
                                                <i class="bi bi-diagram-2 text-success me-1"></i> {{ $project->name }}
                                                <span class="text-muted small">(USD
                                                    {{ number_format($project->total_budget, 2) }})</span>
                                            </div>
                                            <div class="collapse tree-branch" id="proj{{ $project->id }}">
                                                @foreach ($project->activities as $activity)
                                                    <div class="tree-item" data-bs-toggle="collapse"
                                                        data-bs-target="#act{{ $activity->id }}">
                                                        <i class="bi bi-chevron-right expand-icon me-1"></i>
                                                        <i class="bi bi-diagram-3 text-info me-1"></i>
                                                        {{ $activity->name }}
                                                    </div>
                                                    <div class="collapse tree-branch" id="act{{ $activity->id }}">
                                                        @foreach ($activity->subActivities as $sub)
                                                            <div class="tree-item text-muted" data-bs-toggle="modal"
                                                                data-bs-target="#subModal{{ $sub->id }}">
                                                                <i class="bi bi-dot text-secondary me-1"></i>
                                                                {{ $sub->name }}
                                                                <small>(USD
                                                                    {{ number_format($sub->total_budget, 2) }})</small>
                                                            </div>
                                                            <!-- SubActivity Modal -->
                                                            <div class="modal fade" id="subModal{{ $sub->id }}"
                                                                tabindex="-1">
                                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header bg-light">
                                                                            <h5 class="modal-title">Sub-Activity Details
                                                                            </h5>
                                                                            <button type="button" class="btn-close"
                                                                                data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <h5>{{ $sub->name }}</h5>
                                                                            <p class="text-muted">
                                                                                {{ $sub->description ?? 'No description available.' }}
                                                                            </p>
                                                                            <div class="row">
                                                                                <div class="col-md-4"><strong>ID:</strong>
                                                                                    {{ $sub->sub_activity_id }}</div>
                                                                                <div class="col-md-4">
                                                                                    <strong>Budget:</strong> USD
                                                                                    {{ number_format($sub->total_budget, 2) }}
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <strong>Created:</strong>
                                                                                    {{ $sub->created_at->format('d M, Y') }}
                                                                                </div>
                                                                            </div>
                                                                            <hr>
                                                                            <h6>Yearly Allocations</h6>
                                                                            <table class="table table-sm table-bordered">
                                                                                <thead class="table-light">
                                                                                    <tr>
                                                                                        <th>Year</th>
                                                                                        <th>Amount (USD)</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @forelse($sub->budgetAllocations as $alloc)
                                                                                        <tr>
                                                                                            <td>Year
                                                                                                {{ $alloc->year_number }}
                                                                                            </td>
                                                                                            <td>{{ number_format($alloc->amount, 2) }}
                                                                                            </td>
                                                                                        </tr>
                                                                                    @empty
                                                                                        <tr>
                                                                                            <td colspan="2"
                                                                                                class="text-center text-muted">
                                                                                                No data</td>
                                                                                        </tr>
                                                                                    @endforelse
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button class="btn btn-light border"
                                                                                data-bs-dismiss="modal">Close</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @empty
                            <p class="text-center text-muted py-3">No data available.</p>
                        @endforelse
                    </div>
                </div>

                <!-- EXPORT MODAL -->
                <div class="modal fade" id="exportModal" tabindex="-1">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title"><i class="bi bi-file-earmark-pdf me-1"></i> Export Summary</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <p class="text-muted">Choose your export format:</p>
                                <div class="d-flex justify-content-center gap-3 mt-3">
                                    <a href="{{ route('budget.summary.export', 'pdf') }}" class="btn btn-danger">
                                        <i class="bi bi-filetype-pdf me-1"></i> PDF
                                    </a>
                                    <a href="{{ route('budget.summary.export', 'excel') }}" class="btn btn-success">
                                        <i class="bi bi-filetype-xlsx me-1"></i> Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- PERFORMANCE ANALYTICS MODAL -->
            <div class="modal fade" id="performanceModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title">
                                <i class="bi bi-graph-up me-2"></i> Program Performance Analytics
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">
                                This chart shows the total allocations per program over the years. Each bar represents one
                                program,
                                segmented by yearly budget distribution.
                            </p>
                            <canvas id="performanceChart" height="120"></canvas>
                            <div class="mt-3 text-end">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Hover over a bar to view detailed yearly allocations.
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>


        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // ==== TREE ICON ANIMATION ====
            document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const icon = this.querySelector('.expand-icon');
                    if (icon) icon.classList.toggle('rotate');
                });
            });

            // ==== TREE SEARCH ====
            document.getElementById('searchTree').addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                document.querySelectorAll('.tree-item').forEach(item => {
                    item.style.display = item.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });

            // ==== CHARTS FOR SECTORS ====
            @foreach ($sectors as $sector)
                const ctx{{ $sector->id }} = document.getElementById('chart{{ $sector->id }}').getContext('2d');
                new Chart(ctx{{ $sector->id }}, {
                    type: 'doughnut',
                    data: {
                        labels: ['Allocated', 'Unallocated'],
                        datasets: [{
                            data: [{{ $sector->allocated_budget ?? 0 }},
                                {{ ($sector->total_budget ?? 0) - ($sector->allocated_budget ?? 0) }}
                            ],
                            backgroundColor: ['#28a745', '#dee2e6']
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            @endforeach


            // ==== PERFORMANCE ANALYTICS CHART ====
            document.addEventListener('DOMContentLoaded', function() {
                const ctxPerf = document.getElementById('performanceChart').getContext('2d');

                const labels = [
                    @foreach ($programs as $program)
                        "{{ $program->name }}",
                    @endforeach
                ];

                const datasets = [
                    @foreach ($years as $year)
                        {
                            label: "Year {{ $year }}",
                            backgroundColor: "{{ ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'][$loop->index % 5] }}",
                            data: [
                                @foreach ($programs as $program)
                                    {{ $program->allocationsByYear[$year] ?? 0 }},
                                @endforeach
                            ]
                        },
                    @endforeach
                ];

                new Chart(ctxPerf, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Program Yearly Allocations (Stacked)'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                title: {
                                    display: true,
                                    text: 'Programs'
                                }
                            },
                            y: {
                                stacked: true,
                                title: {
                                    display: true,
                                    text: 'Allocated Budget (USD)'
                                }
                            }
                        }
                    }
                });
            });
        </script>


    @endsection

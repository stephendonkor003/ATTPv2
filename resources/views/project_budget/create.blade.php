@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header mb-4">
            <h4 class="fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Create New Project</h4>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <form action="{{ route('project_budget.store') }}" method="POST">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Project Name</label>
                            <input type="text" name="project_name" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Project ID (optional)</label>
                            <input type="text" name="project_id" class="form-control" placeholder="Auto if empty">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Total Budget (USD)</label>
                            <input type="number" name="total_budget" class="form-control" step="0.01" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Year</label>
                            <input type="number" name="start_year" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Year</label>
                            <input type="number" name="end_year" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="2" class="form-control"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Yearly Allocation Percentages</label>
                        <small class="text-muted d-block mb-2">Define allocations for each project year (optional)</small>
                        <div id="yearlyAllocations" class="row g-2"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Save Project
                    </button>
                    <a href="{{ route('project_budget.index') }}" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('[name="end_year"]').addEventListener('change', generateYearFields);
        document.querySelector('[name="start_year"]').addEventListener('change', generateYearFields);

        function generateYearFields() {
            let start = parseInt(document.querySelector('[name="start_year"]').value);
            let end = parseInt(document.querySelector('[name="end_year"]').value);
            let container = document.getElementById('yearlyAllocations');
            container.innerHTML = '';

            if (!isNaN(start) && !isNaN(end) && end >= start) {
                for (let year = start; year <= end; year++) {
                    let col = document.createElement('div');
                    col.classList.add('col-md-2');
                    col.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text">${year}</span>
                    <input type="number" name="yearly_allocations[${year}]" step="0.01" min="0" max="100" class="form-control" placeholder="%">
                </div>
            `;
                    container.appendChild(col);
                }
            }
        }
    </script>
@endsection

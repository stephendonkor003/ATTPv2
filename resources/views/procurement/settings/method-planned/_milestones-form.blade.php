@push('styles')
    <style>
        .milestone-row-tone-0 {
            background: linear-gradient(135deg, #fef9c3, #fff8c7);
        }

        .milestone-row-tone-1 {
            background: linear-gradient(135deg, #d1fae5, #ecfdf5);
        }

        .milestone-row-tone-2 {
            background: linear-gradient(135deg, #dbf4ff, #eef8ff);
        }

        .milestone-row-tone-3 {
            background: linear-gradient(135deg, #ffe4e6, #fff1f2);
        }

        .milestone-row-gradient-strip {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0.65;
        }

        .milestone-row-content {
            position: relative;
            z-index: 1;
        }
    </style>
@endpush

<div class="border rounded-3 p-3 mt-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1">Milestones</h5>
            <p class="text-muted small mb-0">Add each milestone with the number of target days assigned to it. The sort order controls the sequence shown to teams.</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addMilestoneRow">
            <i class="feather-plus me-1"></i> Add milestone
        </button>
    </div>

    <div id="milestoneRows" class="d-flex flex-column gap-3" data-next-index="{{ count($milestoneRows) }}">
        @foreach ($milestoneRows as $index => $milestone)
            <div class="milestone-row border rounded-3 position-relative" data-index="{{ $index }}">
                <div class="milestone-row-gradient-strip"></div>
                <input type="hidden" name="milestones[{{ $index }}][id]" value="{{ $milestone['id'] ?? '' }}">
                <div class="row gy-3 milestone-row-content">
                    <div class="col-md-4">
                        <label class="form-label mb-1">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="milestones[{{ $index }}][title]"
                            value="{{ $milestone['title'] ?? '' }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Description</label>
                        <input type="text" class="form-control" name="milestones[{{ $index }}][description]"
                            value="{{ $milestone['description'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Target Days <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" name="milestones[{{ $index }}][target_days]"
                            value="{{ $milestone['target_days'] ?? '' }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Sort Order</label>
                        <input type="number" min="0" class="form-control" readonly name="milestones[{{ $index }}][sort_order]"
                            value="{{ $milestone['sort_order'] ?? '' }}">
                    </div>
                </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                            name="milestones[{{ $index }}][is_active]" value="1"
                            {{ isset($milestone['is_active']) && !$milestone['is_active'] ? '' : 'checked' }}>
                        <label class="form-check-label">Mark milestone as active</label>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-milestone">
                        <i class="feather-trash-2"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('milestoneRows');
            const template = document.getElementById('milestoneRowTemplate');
            const addButton = document.getElementById('addMilestoneRow');

            if (!container || !template || !addButton) {
                return;
            }

            let nextIndex = parseInt(container.dataset.nextIndex, 10) || 0;

            const refreshSortOrders = () => {
                container.querySelectorAll('.milestone-row').forEach((row, idx) => {
                    const sortInput = row.querySelector('[data-name="sort_order"]') || row.querySelector('input[name*="[sort_order]"]');
                    if (sortInput) {
                        sortInput.value = idx + 1;
                    }
                    row.dataset.index = idx;
                    row.classList.remove('milestone-row-tone-0','milestone-row-tone-1','milestone-row-tone-2','milestone-row-tone-3');
                    row.classList.add(`milestone-row-tone-${idx % 4}`);
                });
            };

            const buildRow = (data = {}) => {
                const clone = template.content.cloneNode(true);
                const index = nextIndex++;

                clone.querySelectorAll('[data-name]').forEach((element) => {
                    const field = element.getAttribute('data-name');
                    element.name = `milestones[${index}][${field}]`;

                    if (element.type === 'checkbox') {
                        element.value = '1';
                        element.checked = data[field] ?? true;
                    } else if (data[field] !== undefined) {
                        element.value = data[field];
                    }
                });

                container.appendChild(clone);
                refreshSortOrders();
            };

            addButton.addEventListener('click', () => buildRow());

            container.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action="remove-milestone"]');
                if (!button) {
                    return;
                }

                const row = button.closest('.milestone-row');
                if (row) {
                    row.remove();
                    refreshSortOrders();
                }
            });

            refreshSortOrders();
        });
    </script>

    <template id="milestoneRowTemplate">
        <div class="milestone-row border rounded-3 bg-white p-3">
            <input type="hidden" data-name="id">
            <div class="row gy-3">
                <div class="col-md-4">
                    <label class="form-label mb-1">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" data-name="title" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Description</label>
                    <input type="text" class="form-control" data-name="description">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Target Days <span class="text-danger">*</span></label>
                    <input type="number" min="0" class="form-control" data-name="target_days" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Sort Order</label>
                    <input type="number" min="0" class="form-control" data-name="sort_order" readonly>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center pt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" data-name="is_active" value="1" checked>
                    <label class="form-check-label">Mark milestone as active</label>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-milestone">
                    <i class="feather-trash-2"></i>
                </button>
            </div>
        </div>
    </template>
@endpush

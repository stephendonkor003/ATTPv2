<div class="card mb-3">
    <div class="card-body">
        <strong>Applicant:</strong>
        {{ $visit->submission?->display_name ?? '-' }} <br>

        <strong>Status:</strong>
        <span class="badge bg-secondary">{{ ucfirst($visit->status) }}</span>
    </div>
</div>

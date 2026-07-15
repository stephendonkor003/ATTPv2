@php
    $rule = $rule ?? null;
@endphp

<div class="row g-3">
    <div class="col-lg-4">
        <label class="form-label fw-semibold">Program Scope</label>
        <select name="program_id" class="form-select" @if (! $canCreateGlobal && ! $rule) required @endif>
            @if ($canCreateGlobal)
                <option value="">Global default</option>
            @else
                <option value="">Select program</option>
            @endif
            @foreach ($programs as $program)
                <option value="{{ $program->id }}" @selected(old('program_id', $rule?->program_id) === $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4">
        <label class="form-label fw-semibold">Level Scope</label>
        <select name="level_id" class="form-select">
            <option value="">All levels</option>
            @foreach ($levels as $level)
                <option value="{{ $level->id }}" @selected(old('level_id', $rule?->level_id) === $level->id)>
                    {{ $level->name }}{{ $level->program ? ' - ' . $level->program->name : ' - Global' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4">
        <label class="form-label fw-semibold">Escalation Email</label>
        <input type="email" name="escalation_email" value="{{ old('escalation_email', $rule?->escalation_email) }}" class="form-control" placeholder="officer@example.org">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Response Due Hours *</label>
        <input type="number" name="response_due_hours" value="{{ old('response_due_hours', $rule?->response_due_hours ?? 24) }}" min="1" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Resolution Due Hours *</label>
        <input type="number" name="resolution_due_hours" value="{{ old('resolution_due_hours', $rule?->resolution_due_hours ?? 120) }}" min="1" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">First Reminder Hours *</label>
        <input type="number" name="reminder_after_hours" value="{{ old('reminder_after_hours', $rule?->reminder_after_hours ?? 24) }}" min="1" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Escalate After Hours *</label>
        <input type="number" name="escalate_after_hours" value="{{ old('escalate_after_hours', $rule?->escalate_after_hours ?? 72) }}" min="1" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Repeat Reminder Hours *</label>
        <input type="number" name="reminder_interval_hours" value="{{ old('reminder_interval_hours', $rule?->reminder_interval_hours ?? 24) }}" min="1" class="form-control" required>
    </div>
    <div class="col-md-9">
        <label class="form-label fw-semibold">Auto Response Subject</label>
        <input type="text" name="auto_response_subject" value="{{ old('auto_response_subject', $rule?->auto_response_subject) }}" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Auto Response Body</label>
        <textarea name="auto_response_body" rows="4" class="form-control">{{ old('auto_response_body', $rule?->auto_response_body) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Reminder Body</label>
        <textarea name="reminder_body" rows="4" class="form-control">{{ old('reminder_body', $rule?->reminder_body) }}</textarea>
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold">Reminder Subject</label>
        <input type="text" name="reminder_subject" value="{{ old('reminder_subject', $rule?->reminder_subject) }}" class="form-control">
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="activeRule{{ $rule?->id ?? 'new' }}" @checked(old('is_active', $rule?->is_active ?? true))>
            <label class="form-check-label" for="activeRule{{ $rule?->id ?? 'new' }}">Active rule</label>
        </div>
    </div>
</div>

@php
    $editing = $editing ?? false;
    $fieldValue = fn (string $key, mixed $default = null) => old($key, $default);
@endphp
<div class="dg-modal-body"><div class="dg-form-grid">
    <div class="dg-field"><label for="{{ $prefix }}-type">Action type *</label><select class="form-select" id="{{ $prefix }}-type" name="action_type" required>@foreach($actionTypes as $key => $label)<option value="{{ $key }}" @selected($fieldValue('action_type', 'remediation') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="dg-field"><label for="{{ $prefix }}-priority">Priority *</label><select class="form-select" id="{{ $prefix }}-priority" name="priority" required>@foreach($actionPriorities as $key => $label)<option value="{{ $key }}" @selected($fieldValue('priority', 'medium') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="dg-field dg-field-full"><label for="{{ $prefix }}-title">Action title *</label><input class="form-control" id="{{ $prefix }}-title" name="title" value="{{ $fieldValue('title') }}" maxlength="240" required placeholder="Specific outcome that must be completed"></div>
    <div class="dg-field"><label for="{{ $prefix }}-owner">Action owner</label><select class="form-select" id="{{ $prefix }}-owner" name="owner_user_id"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) $fieldValue('owner_user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
    <div class="dg-field"><label for="{{ $prefix }}-due">Due date</label><input class="form-control" id="{{ $prefix }}-due" type="date" name="due_date" value="{{ $fieldValue('due_date') }}"></div>
    <div class="dg-field"><label for="{{ $prefix }}-status">Work state *</label><select class="form-select" id="{{ $prefix }}-status" name="status" required><option value="open" @selected($fieldValue('status', 'open') === 'open')>Open</option><option value="in_progress" @selected($fieldValue('status') === 'in_progress')>In progress</option></select></div>
    <div class="dg-field dg-field-full"><label for="{{ $prefix }}-description">Work required</label><textarea class="form-control" id="{{ $prefix }}-description" name="description" rows="4" maxlength="5000" placeholder="Describe the gap, required work, expected evidence and completion criteria.">{{ $fieldValue('description') }}</textarea></div>
</div></div>

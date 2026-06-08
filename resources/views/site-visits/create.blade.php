@extends('layouts.app')
@section('title', 'Create Site Visit')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- HEADER --}}
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Create Site Visit</h5>
                </div>
            </div>

            <div class="main-content">
                <div class="card">
                    <div class="card-body">

                        {{-- ================= ERRORS ================= --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('site-visits.store') }}">
                            @csrf

                            {{-- ================= BASIC INFO ================= --}}
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Procurement</label>
                                    <select name="procurement_id" class="form-control" required>
                                        @foreach ($procurements as $procurement)
                                            <option value="{{ $procurement->id }}"
                                                {{ old('procurement_id') == $procurement->id ? 'selected' : '' }}>
                                                {{ $procurement->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Applicant</label>
                                    <select name="form_submission_id" class="form-control" required>
                                        @foreach ($submissions as $submission)
                                            <option value="{{ $submission->id }}"
                                                {{ old('form_submission_id') == $submission->id ? 'selected' : '' }}>
                                                {{ $submission->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Visit Date</label>
                                    <input type="date" name="visit_date" class="form-control"
                                        value="{{ old('visit_date') }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Assignment Type</label>
                                    <select name="assignment_type" id="assignment_type" class="form-control" required>
                                        <option value="individual"
                                            {{ old('assignment_type') === 'individual' ? 'selected' : '' }}>
                                            Individual
                                        </option>
                                        <option value="group" {{ old('assignment_type') === 'group' ? 'selected' : '' }}>
                                            Group
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <hr>

                            {{-- ================= INDIVIDUAL ================= --}}
                            <div id="individual_section">
                                <div class="mb-3">
                                    <label class="form-label">Assign User</label>
                                    <select name="assigned_user_id" class="form-control">
                                        <option value="">-- Select User --</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}"
                                                {{ old('assigned_user_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }} — {{ $user->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- ================= GROUP ================= --}}
                            <div id="group_section" style="display:none;">

                                <div class="mb-3">
                                    <label class="form-label">Group Name</label>
                                    <input type="text" name="group_name" class="form-control"
                                        value="{{ old('group_name') }}">
                                </div>

                                <div class="row align-items-end">
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label">Add Group Member</label>
                                        <select id="group_user_select" class="form-control">
                                            <option value="">-- Select User --</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}" data-name="{{ $user->name }}"
                                                    data-email="{{ $user->email }}">
                                                    {{ $user->name }} — {{ $user->email }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="addMember()">
                                            Add
                                        </button>
                                    </div>
                                </div>

                                {{-- GROUP TABLE --}}
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th class="text-center">Leader</th>
                                            <th width="80"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="group_table_body">
                                        <tr id="group_empty">
                                            <td colspan="3" class="text-center text-muted">
                                                No members added
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">
                                Create Site Visit
                            </button>

                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>

    {{-- ================= JAVASCRIPT ================= --}}
    <script>
        const assignmentType = document.getElementById('assignment_type');
        const individualSection = document.getElementById('individual_section');
        const groupSection = document.getElementById('group_section');
        const groupSelect = document.getElementById('group_user_select');
        const groupBody = document.getElementById('group_table_body');

        const addedUsers = new Set();

        function toggleAssignment() {
            individualSection.style.display =
                assignmentType.value === 'individual' ? 'block' : 'none';

            groupSection.style.display =
                assignmentType.value === 'group' ? 'block' : 'none';
        }

        assignmentType.addEventListener('change', toggleAssignment);
        toggleAssignment();

        function addMember() {
            const userId = groupSelect.value;
            if (!userId || addedUsers.has(userId)) return;

            const option = groupSelect.options[groupSelect.selectedIndex];
            const name = option.dataset.name;
            const email = option.dataset.email;

            addedUsers.add(userId);
            document.getElementById('group_empty')?.remove();

            const row = document.createElement('tr');
            row.setAttribute('data-user-id', userId);

            row.innerHTML = `
        <td>
            <strong>${name}</strong><br>
            <small class="text-muted">${email}</small>
            <input type="hidden" name="group_members[]" value="${userId}">
        </td>
        <td class="text-center">
            <input type="radio" name="group_leader_id" value="${userId}" required>
        </td>
        <td class="text-center">
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    onclick="removeMember('${userId}')">
                Remove
            </button>
        </td>
    `;

            groupBody.appendChild(row);
            groupSelect.value = '';
        }

        function removeMember(userId) {
            addedUsers.delete(userId);
            const row = document.querySelector(
                `tr[data-user-id="${userId}"]`
            );
            if (row) row.remove();

            if (addedUsers.size === 0) {
                groupBody.innerHTML = `
            <tr id="group_empty">
                <td colspan="3"
                    class="text-center text-muted">
                    No members added
                </td>
            </tr>
        `;
            }
        }
    </script>
@endsection

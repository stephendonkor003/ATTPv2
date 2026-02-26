@extends('layouts.app')

@section('title', 'AU Member States')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">AU Member States</h4>
                    <p class="text-muted mb-0">Manage African Union member states for program funding beneficiaries.</p>
                </div>
                @can('settings.au_master_data.create')
                    <a href="{{ route('settings.au.member-states.create') }}" class="btn btn-success">
                        <i class="feather-plus-circle me-1"></i> Add Member State
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <x-data-table id="memberStatesTable" :config="['order' => [[4, 'asc']], 'pageLength' => 25]">
                        <thead class="table-light">
                            <tr>
                                <th width="60">#</th>
                                <th width="70">Flag</th>
                                <th>Name</th>
                                <th>ISO Code</th>
                                <th width="80">Order</th>
                                <th width="80">Status</th>
                                <th width="120" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($memberStates as $state)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($state->flag_url)
                                            <img src="{{ $state->flag_url }}" alt="{{ $state->name }} flag"
                                                style="width: 42px; height: 28px; object-fit: cover; border:1px solid #d1d5db; border-radius:4px;">
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $state->name }}</td>
                                    <td>
                                        @if ($state->code)
                                            <span class="badge bg-light text-dark">{{ $state->code }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $state->sort_order }}</td>
                                    <td>
                                        @if ($state->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('settings.au_master_data.edit')
                                            <a href="{{ route('settings.au.member-states.edit', $state->id) }}"
                                                class="btn btn-sm btn-outline-warning">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                        @can('settings.au_master_data.delete')
                                            <form action="{{ route('settings.au.member-states.destroy', $state->id) }}"
                                                method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Delete this member state?')">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                </div>
            </div>
        </div>
    </main>
@endsection

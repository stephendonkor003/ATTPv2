@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header">
            <h4 class="fw-bold text-dark">Create Program</h4>
        </div>

        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('programs.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Sector</label>
                        <select name="sector_id" class="form-select" required>
                            <option value="">Select Sector</option>
                            @foreach ($sectors as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select" required>
                            <option value="USD">USD</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Year</label>
                            <input type="number" name="start_year" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Year</label>
                            <input type="number" name="end_year" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>

                    <button class="btn btn-primary">Save Program</button>
                </form>

            </div>
        </div>

    </div>
@endsection

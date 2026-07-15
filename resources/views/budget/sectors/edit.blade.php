@extends('layouts.app')
@section('title', 'Edit Portfolio')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Edit Portfolio</h4>
                    <p class="text-muted mb-0">Update the details for this portfolio.</p>
                </div>
                <a href="{{ route('sectors.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('sectors.update', $sector->id) }}" method="POST">
                        @csrf
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Portfolio Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $sector->name) }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" rows="4" class="form-control">{{ old('description', $sector->description) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <a href="{{ route('sectors.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-pencil-square me-1"></i> Update Portfolio
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
@endsection

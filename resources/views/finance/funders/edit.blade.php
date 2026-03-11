@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Edit Partner</h4>
                <p class="text-muted mb-0">
                    Update the partner CRM profile, assigned officer, communication tracker, and portal settings.
                </p>
            </div>

            <a href="{{ route('finance.funders.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.funders.update', $funder) }}" enctype="multipart/form-data" class="mt-3">
            @csrf
            @method('PUT')

            @include('finance.funders.partials.form-fields', ['users' => $users, 'funder' => $funder])

            <div class="alert alert-warning mt-4">
                Updating this partner changes the CRM snapshot used across funding, communication, and portal oversight views.
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('finance.funders.index') }}" class="btn btn-light">
                    Cancel
                </a>
                <button class="btn btn-primary">
                    <i class="feather-save me-1"></i> Update Partner
                </button>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.app')
@section('title','Create Definition')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-file-text text-primary me-2"></i>Create Indicator Definition</h4>
            <p class="text-muted mb-0">Drag variables into numerator/denominator to craft a formula.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('budget.me-configuration.definitions.store') }}" method="POST" id="definitionForm">
                @csrf
                @include('me.definitions.form')
            </form>
        </div>
    </div>
</div>
@endsection

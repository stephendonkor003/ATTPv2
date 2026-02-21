@extends('layouts.app')
@section('title','Edit Definition')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-file-text text-primary me-2"></i>Edit Indicator Definition</h4>
            <p class="text-muted mb-0">Update variables and formula layout.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('budget.me-configuration.definitions.update', $definition) }}" method="POST" id="definitionForm">
                @csrf
                @method('PUT')
                @include('me.definitions.form')
            </form>
        </div>
    </div>
</div>
@endsection

@if (session('success'))
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2" role="status">
        <i class="feather-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2" role="alert">
        <i class="feather-alert-circle"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-2 fw-bold mb-2">
            <i class="feather-alert-triangle"></i>
            <span>Please correct the highlighted information.</span>
        </div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

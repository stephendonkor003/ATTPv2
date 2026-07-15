@extends('layouts.app')

@section('title', 'Escalation Configuration')

@section('content')
    <style>
        .grm-hero { border-radius: 8px; padding: 22px; color: #fff; background: linear-gradient(135deg, #064e3b 0%, #0f766e 60%, #522b39 100%); box-shadow: 0 18px 36px rgba(6,78,59,.18); }
        .grm-hero h4, .grm-hero p { color: #fff; }
        .grm-card { border: 1px solid #dbe5df; border-radius: 8px; background: #fff; box-shadow: 0 12px 26px rgba(15,23,42,.06); }
        .grm-card-header { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px 8px 0 0; }
        .grm-card-body { padding: 18px; }
        .grm-rule { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #fff; }
        .grm-rule + .grm-rule { margin-top: 12px; }
    </style>

    <div class="container-fluid">
        <div class="grm-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-success mb-2">GRM Automation</span>
                    <h4 class="mb-1">Escalation Configuration</h4>
                    <p class="mb-0">Set response clocks, reminders, escalation timing, and default email content.</p>
                </div>
                <a href="{{ route('grm.levels.index') }}" class="btn btn-light text-success fw-bold">
                    <i class="feather-sliders me-1"></i> Levels
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div class="grm-card mb-3">
            <div class="grm-card-header">
                <h6 class="fw-bold mb-0">Create Escalation Rule</h6>
            </div>
            <form method="POST" action="{{ route('grm.escalations.store') }}" class="grm-card-body">
                @csrf
                @include('grm.configuration.partials.escalation-form', ['rule' => null])
                <div class="text-end mt-3">
                    <button class="btn btn-success"><i class="feather-save me-1"></i> Save Rule</button>
                </div>
            </form>
        </div>

        <div class="grm-card">
            <div class="grm-card-header">
                <h6 class="fw-bold mb-0">Configured Rules</h6>
            </div>
            <div class="grm-card-body">
                @forelse ($rules as $rule)
                    <div class="grm-rule">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                            <div>
                                <strong>{{ $rule->program?->name ?? 'Global rule' }}</strong>
                                <div class="text-muted small">{{ $rule->level?->name ?? 'All levels' }} | Response {{ $rule->response_due_hours }}h | Escalate {{ $rule->escalate_after_hours }}h</div>
                            </div>
                            <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <details>
                            <summary class="fw-semibold text-success">Edit rule</summary>
                            <form method="POST" action="{{ route('grm.escalations.update', $rule) }}" class="mt-3">
                                @csrf
                                @method('PUT')
                                @include('grm.configuration.partials.escalation-form', ['rule' => $rule])
                                <div class="text-end mt-3">
                                    <button class="btn btn-outline-success"><i class="feather-check me-1"></i> Update Rule</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No escalation rules configured.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

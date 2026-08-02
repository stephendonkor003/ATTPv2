@extends('layouts.app')

@section('title', 'Grievance Case ' . $grievance->case_number)

@section('content')
    <style>
        .grm-hero { border-radius: 8px; padding: 22px; color: #fff; background: linear-gradient(135deg, #064e3b 0%, #0f766e 60%, #522b39 100%); box-shadow: 0 18px 36px rgba(6,78,59,.18); }
        .grm-hero h4, .grm-hero p { color: #fff; }
        .grm-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
        .grm-card { border: 1px solid #dbe5df; border-radius: 8px; background: #fff; box-shadow: 0 12px 26px rgba(15,23,42,.06); }
        .grm-card-header { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px 8px 0 0; }
        .grm-card-body { padding: 18px; }
        .grm-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .grm-meta div { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc; }
        .grm-meta span { display: block; color: #64748b; font-size: .75rem; font-weight: 800; text-transform: uppercase; }
        .grm-timeline { border-left: 3px solid #dbe5df; padding-left: 16px; }
        .grm-event { position: relative; padding-bottom: 16px; }
        .grm-event::before { content: ""; position: absolute; left: -24px; top: 3px; width: 13px; height: 13px; border-radius: 999px; background: #0f766e; border: 2px solid #fff; box-shadow: 0 0 0 2px #0f766e; }
        .grm-doc-list { display: grid; gap: 10px; }
        .grm-doc-row { display: flex; justify-content: space-between; gap: 12px; align-items: center; border: 1px solid #dbe5df; border-radius: 8px; padding: 12px; background: #f8fafc; text-decoration: none; color: #0f172a; }
        .grm-doc-row:hover { border-color: #0f766e; color: #064e3b; background: #ecfdf5; }
        .grm-doc-row strong { display: block; }
        .grm-doc-row span { color: #64748b; font-size: .78rem; }
        @media (max-width: 991px) { .grm-grid { grid-template-columns: 1fr; } .grm-meta { grid-template-columns: 1fr; } }
    </style>

    <div class="container-fluid">
        <div class="grm-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-success mb-2">{{ $grievance->case_number }}</span>
                    <h4 class="mb-1">{{ $grievance->subject }}</h4>
                    <p class="mb-0">{{ $grievance->program?->name ?? 'No program linked' }} | {{ $grievance->status_label }}</p>
                </div>
                <a href="{{ route('grm.logs.index') }}" class="btn btn-light text-success fw-bold">
                    <i class="feather-arrow-left me-1"></i> Back to Logs
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div class="grm-grid">
            <div class="grm-card">
                <div class="grm-card-header">
                    <h6 class="fw-bold mb-0">Case Details</h6>
                </div>
                <div class="grm-card-body">
                    <div class="grm-meta mb-4">
                        <div><span>Program</span><strong>{{ $grievance->program?->name ?? 'No program' }}</strong></div>
                        <div><span>Portfolio</span><strong>{{ $grievance->program?->sector?->name ?? 'N/A' }}</strong></div>
                        <div><span>Level</span><strong>{{ $grievance->level?->name ?? 'Unclassified' }}</strong></div>
                        <div><span>Channel</span><strong>{{ $grievance->channel_label }}</strong></div>
                        <div><span>Response Due</span><strong>{{ $grievance->due_response_at?->format('d M Y H:i') ?? 'N/A' }}</strong></div>
                        <div><span>Resolution Due</span><strong>{{ $grievance->due_resolution_at?->format('d M Y H:i') ?? 'N/A' }}</strong></div>
                    </div>

                    <h6 class="fw-bold">Incident Details / Summary</h6>
                    <p class="mb-4" style="white-space: pre-wrap;">{{ $grievance->description }}</p>

                    <h6 class="fw-bold">Supporting Documents</h6>
                    <div class="grm-doc-list mb-4">
                        @forelse ($grievance->attachments as $attachment)
                            <a class="grm-doc-row" href="{{ route('grm.logs.attachments.download', [$grievance, $attachment]) }}" target="_blank" rel="noopener">
                                <span>
                                    <strong>{{ $attachment->title ?: $attachment->file_name }}</strong>
                                    {{ $attachment->file_name }} | {{ $attachment->file_size_bytes ? number_format($attachment->file_size_bytes / 1024, 1) . ' KB' : 'Size N/A' }}
                                </span>
                                <i class="feather-external-link"></i>
                            </a>
                        @empty
                            <div class="text-muted small border rounded-3 p-3 bg-light">No supporting documents were uploaded with this grievance.</div>
                        @endforelse
                    </div>

                    <h6 class="fw-bold">Complainant</h6>
                    <div class="grm-meta">
                        <div><span>Name</span><strong>{{ $grievance->is_anonymous ? 'Anonymous' : ($grievance->submitter_name ?: 'Not provided') }}</strong></div>
                        <div><span>Email</span><strong>{{ $grievance->is_anonymous ? 'Anonymous' : ($grievance->submitter_email ?: 'Not provided') }}</strong></div>
                        <div><span>Phone</span><strong>{{ $grievance->is_anonymous ? 'Anonymous' : ($grievance->submitter_phone ?: 'Not provided') }}</strong></div>
                        @if ($grievance->is_anonymous)
                            <div>
                                <span>Confidential Reply Method</span>
                                <strong>{{ \Illuminate\Support\Str::headline($grievance->anonymous_contact_method ?: 'Not provided') }}</strong>
                            </div>
                            <div>
                                <span>Confidential Reply Contact</span>
                                <strong>{{ $grievance->confidentialReplyContact() ?: 'Not provided' }}</strong>
                            </div>
                        @endif
                        <div><span>Submitted</span><strong>{{ $grievance->submitted_at?->format('d M Y H:i') ?? $grievance->created_at?->format('d M Y H:i') }}</strong></div>
                    </div>
                </div>
            </div>

            <aside class="grm-card">
                <div class="grm-card-header">
                    <h6 class="fw-bold mb-0">Update Case</h6>
                </div>
                <div class="grm-card-body">
                    <form method="POST" action="{{ route('grm.logs.status', $grievance) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Grievance Level / Category</label>
                            <select name="level_id" class="form-select" required>
                                <option value="">Select classification</option>
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}" @selected((string) old('level_id', $grievance->level_id) === (string) $level->id)>
                                        {{ $level->name }}{{ $level->program_id ? ' - Program' : ' - Global' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Assigned by the grievance officer after reviewing the submission.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($grievance->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Unassigned</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected($grievance->assigned_to === $assignee->id)>{{ $assignee->name }}{{ $assignee->role ? ' - ' . $assignee->role->name : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" rows="4" class="form-control" placeholder="Add response, action taken, or escalation note"></textarea>
                        </div>
                        <button class="btn btn-success w-100"><i class="feather-check me-1"></i> Save Update</button>
                    </form>
                </div>

                <div class="grm-card-header border-top">
                    <h6 class="fw-bold mb-0">Case Timeline</h6>
                </div>
                <div class="grm-card-body">
                    <div class="grm-timeline">
                        @forelse ($grievance->events as $event)
                            <div class="grm-event">
                                <strong>{{ \Illuminate\Support\Str::headline($event->event_type) }}</strong>
                                <div class="text-muted small">{{ $event->created_at?->format('d M Y H:i') }}{{ $event->user ? ' by ' . $event->user->name : '' }}</div>
                                @if ($event->notes)
                                    <p class="mb-0 mt-1 small">{{ $event->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">No case events yet.</div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/evaluation-report-rework.css') }}?v={{ filemtime(public_path('assets/css/evaluation-report-rework.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/js/evaluation-report-rework.js') }}?v={{ filemtime(public_path('assets/js/evaluation-report-rework.js')) }}" defer></script>
    @endpush
@endonce

@php
    $reworkGroups = $reworkPanel['groups'] ?? [];
    $reworkPrefix = ($managementAnchor ?? 'evaluation').'-rework';
    $reworkMessages = collect(($errors ?? new \Illuminate\Support\ViewErrorBag())->getBag('default')->getMessages())
        ->filter(fn ($messages, $key) => preg_match('/^(reason|evaluator_id|submission_ids|rework|override_proposal_round_lock|return_to)(\.|$)/', $key))
        ->flatten()->all();
@endphp

<section class="eval-rework-panel" id="{{ $reworkPrefix }}" data-evaluation-rework-panel aria-labelledby="{{ $reworkPrefix }}-title">
    <header class="eval-rework-head">
        <div>
            <span class="eval-rework-eyebrow">Evaluation quality review</span>
            <h2 id="{{ $reworkPrefix }}-title">Request evaluator rework</h2>
            <p>Choose an evaluator, select the submitted evaluations that need correction, and explain what should change. Each request keeps the original scores and your instructions in the audit history.</p>
        </div>
        <div class="eval-rework-totals" aria-label="Rework availability">
            <span><strong>{{ number_format($reworkPanel['available_count'] ?? 0) }}</strong>Available for review</span>
            <span class="is-pending"><strong>{{ number_format($reworkPanel['pending_count'] ?? 0) }}</strong>Awaiting rework</span>
        </div>
    </header>
    <div class="eval-rework-body">
        @if(session('success'))<div class="eval-rework-feedback is-success" role="status">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="eval-rework-feedback" role="status">{{ session('warning') }}</div>@endif
        @if($reworkMessages)
            <div class="eval-rework-feedback is-error" role="alert"><strong>The rework request could not be completed.</strong><ul>@foreach($reworkMessages as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        @if($reworkGroups)
            <div class="eval-rework-search">
                <label for="{{ $reworkPrefix }}-search">Find an evaluator</label>
                <input id="{{ $reworkPrefix }}-search" type="search" placeholder="Search name, email or procurement" data-rework-search autocomplete="off">
            </div>
        @endif
        @forelse($reworkGroups as $group)
            @php
                $groupId = $reworkPrefix.'-'.substr(hash('sha256', $group['id']), 0, 12);
                $available = collect($group['entries'])->where('can_request', true)->values();
                $unavailable = collect($group['entries'])->where('can_request', false)->values();
                $pendingCount = collect($group['entries'])->where('status', 'rework')->count();
                $hasPreviousInput = (string) old('rework_group_id') === (string) $group['id'];
                $previousIds = $hasPreviousInput ? (array) old('submission_ids', []) : [];
                $hasOverride = $available->contains('requires_override', true);
                $initials = collect(preg_split('/\s+/u', trim($group['name']), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($name) => mb_substr($name, 0, 1))->implode('');
            @endphp
            <details class="eval-rework-evaluator" data-rework-evaluator data-search="{{ $group['name'].' '.$group['email'].' '.$group['procurement'] }}" @if($hasPreviousInput) open @endif>
                <summary>
                    <span class="eval-rework-avatar" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
                    <span class="eval-rework-person"><strong>{{ $group['name'] }}</strong><small>{{ $group['email'] ?: 'Email unavailable' }}</small>@if(count($reworkGroups) > 0 && !isset($procurement))<small>{{ $group['procurement'] }}</small>@endif</span>
                    <span class="eval-rework-count">{{ $available->count() }} available</span>
                    @if($pendingCount)<span class="eval-rework-count is-pending">{{ $pendingCount }} awaiting rework</span>@endif
                    <i class="feather-chevron-down eval-rework-chevron" aria-hidden="true"></i>
                </summary>
                <div class="eval-rework-content">
                    @if($available->isNotEmpty())
                        <form method="POST" action="{{ $group['action_url'] }}" data-report-rework-form data-max-selections="50" aria-label="Request rework from {{ $group['name'] }}">
                            @csrf
                            <input type="hidden" name="rework_group_id" value="{{ $group['id'] }}">
                            <input type="hidden" name="evaluator_id" value="{{ $group['evaluator_id'] }}">
                            <input type="hidden" name="return_to" value="{{ $group['return_to'] }}">
                            <h3 id="{{ $groupId }}-records-label"><span class="eval-rework-step" aria-hidden="true">1</span>Select evaluations to return</h3>
                            <div class="eval-rework-tools">
                                <button type="button" class="eval-rework-small-button" data-rework-select-all>{{ $available->count() > 50 ? 'Select first 50' : 'Select all available' }}</button>
                                <button type="button" class="eval-rework-small-button" data-rework-clear>Clear selection</button>
                                <span data-rework-selection role="status">0 of {{ $available->count() }} evaluations selected</span>
                            </div>
                            @if($available->count() > 50)<p class="eval-note">You can return up to 50 evaluations in one request.</p>@endif
                            <div class="eval-rework-records" role="group" aria-labelledby="{{ $groupId }}-records-label">
                                @foreach($available as $entry)
                                    <label class="eval-rework-record">
                                        <input type="checkbox" name="submission_ids[]" value="{{ $entry['id'] }}" data-requires-override="{{ $entry['requires_override'] ? '1' : '0' }}" @checked(in_array($entry['id'], $previousIds, true))>
                                        <span class="eval-rework-record-copy"><strong>{{ $entry['applicant'] }}</strong><small>{{ $entry['evaluation'] }} &middot; {{ $entry['phase'] }}</small><small>Submitted {{ $entry['submitted_at'] ?: 'date not recorded' }}</small></span>
                                        <span class="eval-rework-record-tag {{ $entry['requires_override'] ? 'is-override' : '' }}">{{ $entry['requires_override'] ? 'Override required' : 'Submitted' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="eval-rework-reason">
                                <label for="{{ $groupId }}-reason"><span class="eval-rework-step" aria-hidden="true">2</span>Reason and correction instructions</label>
                                <textarea id="{{ $groupId }}-reason" name="reason" rows="4" minlength="10" maxlength="5000" required aria-describedby="{{ $groupId }}-reason-help" placeholder="For example: Please review the relevant experience scores and explain how each score is supported by the applicant's evidence. Clarify any differences between the score and your comments.">{{ $hasPreviousInput ? old('reason') : '' }}</textarea>
                                <div class="eval-rework-reason-help" id="{{ $groupId }}-reason-help"><span>Describe the correction clearly. These instructions will be emailed to {{ $group['name'] }}. Minimum 10 characters.</span><output data-rework-reason-count>0 / 5,000</output></div>
                            </div>
                            @if($hasOverride)
                                <div class="eval-rework-override" data-rework-override>
                                    <label><input type="checkbox" name="override_proposal_round_lock" value="1" @checked($hasPreviousInput && old('override_proposal_round_lock'))><span><strong>Administrator override</strong>A technical-proposal round has started for a selected evaluation. I authorize reopening that evaluation and understand this override will be recorded in the audit history.</span></label>
                                </div>
                            @endif
                            <div class="eval-rework-feedback is-error" data-rework-form-feedback role="alert" hidden></div>
                            <div class="eval-rework-submit-row">
                                <p>Selected evaluations will leave finalized results until the evaluator resubmits. The evaluator will receive one email listing the records and your reason.</p>
                                <button type="submit" class="eval-rework-send" data-rework-send>Request rework</button>
                            </div>
                        </form>
                    @else
                        <div class="eval-rework-empty"><strong>No evaluations available to return</strong>Pending requests and any restrictions are shown below. New submitted evaluations will become available here.</div>
                    @endif
                    @if($unavailable->isNotEmpty())
                        <details class="eval-rework-unavailable" @if($available->isEmpty()) open @endif>
                            <summary>Pending or unavailable evaluations ({{ $unavailable->count() }})</summary>
                            @foreach($unavailable as $entry)
                                <article>
                                    <span class="eval-rework-state">{{ $entry['status'] === 'rework' ? 'Awaiting evaluator rework' : 'Rework unavailable' }}</span>
                                    <h4>{{ $entry['applicant'] }}</h4><small>{{ $entry['evaluation'] }} &middot; {{ $entry['phase'] }}</small>
                                    @if($entry['reason'])<p><strong>Correction instructions:</strong> {{ $entry['reason'] }}</p>@endif
                                    @if($entry['blocking_reason'])<p>{{ $entry['blocking_reason'] }}</p>@endif
                                    @if($entry['view_url'])<a href="{{ $entry['view_url'] }}" class="eval-rework-small-button">View evaluation</a>@endif
                                </article>
                            @endforeach
                        </details>
                    @endif
                </div>
            </details>
        @empty
            <div class="eval-rework-empty"><strong>No submitted evaluations to review yet</strong>Evaluators and their records will appear here once submissions are available. Rework requests can then be sent with a reason.</div>
        @endforelse
        <div class="eval-rework-empty" data-rework-no-matches hidden>No evaluators match your search.</div>
    </div>
</section>

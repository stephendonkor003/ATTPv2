@php
    $isPortal = $isPortal ?? false;
    $canReview = $canReview ?? false;
    $canArchive = $canArchive ?? false;
    $submitAction = $isPortal
        ? route('think-tank.performance-reports.submit', $portalEditParams)
        : route('budget.me.performance-reports.submit', $report);
@endphp

<section class="report-stage-actions report-stage-actions--{{ $report->status }}" aria-label="Actions for {{ $report->lifecycleLabel() }}">
    <div class="stage-action-copy">
        <span class="stage-action-kicker">Current stage · {{ $report->lifecycleLabel() }}</span>

        @if ($report->isEditable())
            <h5>{{ $submissionReady ? 'This report is ready for review.' : 'Complete all mandatory sections before submission.' }}</h5>
            <p>Submission locks the report and sends the same seven-section report to the Secretariat/M&amp;E Officer.</p>
        @elseif ($report->isSubmitted())
            <h5>Secretariat / M&amp;E review decision</h5>
            <p>Return the report with correction notes or approve the complete report.</p>
        @elseif ($report->isReviewed())
            <h5>Review and approval completed</h5>
            <p>The approved report can now be archived as a permanent historical record.</p>
        @else
            <h5>Archived historical report</h5>
            <p>The finalized report is locked and retained with its complete lifecycle audit trail.</p>
        @endif
    </div>

    @if ($report->isEditable() && $editable)
        <form method="POST" action="{{ $submitAction }}" onsubmit="return confirm('Submit this complete report for review?');">
            @csrf
            <button
                type="submit"
                class="btn btn-primary lifecycle-action lifecycle-action--submit"
                @disabled(!$submissionReady)
                title="{{ $submissionReady ? 'Submit this report for review' : 'Complete and save all seven mandatory sections first' }}"
            >
                <i class="feather-send" aria-hidden="true"></i>Submit Report
            </button>
        </form>
    @elseif ($report->isSubmitted() && $canReview)
        <form method="POST" action="{{ route('budget.me.performance-reports.review', $report) }}" class="stage-review-form">
            @csrf
            <label class="form-label" for="review-notes">Review notes</label>
            <textarea name="review_notes" id="review-notes" rows="3" class="form-control" placeholder="Record approval notes or explain the corrections required.">{{ old('review_notes', $report->review_notes) }}</textarea>
            <div class="stage-action-buttons">
                <button type="submit" name="review_action" value="returned" class="btn btn-warning lifecycle-action lifecycle-action--return">
                    <i class="feather-corner-up-left" aria-hidden="true"></i>Return Report
                </button>
                <button type="submit" name="review_action" value="reviewed" class="btn btn-success lifecycle-action lifecycle-action--approve" onclick="return confirm('Approve this report?');">
                    <i class="feather-check-circle" aria-hidden="true"></i>Approve Report
                </button>
            </div>
        </form>
    @elseif ($report->isReviewed() && $canArchive)
        <form method="POST" action="{{ route('budget.me.performance-reports.archive', $report) }}" class="stage-review-form">
            @csrf
            <label class="form-label" for="archive-notes">Archival notes</label>
            <textarea name="archive_notes" id="archive-notes" rows="3" class="form-control" placeholder="Optional retention, closure or handover notes.">{{ old('archive_notes') }}</textarea>
            <div class="stage-action-buttons">
                <button type="submit" class="btn btn-dark lifecycle-action lifecycle-action--archive" onclick="return confirm('Archive this report as a historical record?');">
                    <i class="feather-archive" aria-hidden="true"></i>Archive Report
                </button>
            </div>
        </form>
    @elseif ($report->isArchived())
        <span class="stage-locked"><i class="feather-lock" aria-hidden="true"></i>No further actions</span>
    @else
        <span class="stage-locked"><i class="feather-clock" aria-hidden="true"></i>Awaiting authorized action</span>
    @endif
</section>

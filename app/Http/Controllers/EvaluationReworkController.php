<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationReworkRequested;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Services\EvaluationReworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EvaluationReworkController extends Controller
{
    use ScopesAssignedPortfolios;

    public function store(
        Request $request,
        Procurement $procurement,
        EvaluationSubmission $submission,
        EvaluationReworkService $reworkService
    ): RedirectResponse {
        abort_unless($request->user()?->can('evaluations.manage'), 403);
        abort_unless(
            (string) $submission->procurement_id === (string) $procurement->getKey(),
            404
        );

        if ($this->userHasAssignedPortfolioScope($request->user())) {
            abort_unless(
                $this->evaluationSubmissionIsInAssignedPortfolio($submission, $request->user()),
                403,
                'This evaluation is outside your assigned portfolio.'
            );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'override_proposal_round_lock' => ['sometimes', 'accepted'],
        ], [
            'reason.required' => 'Explain what the evaluator must correct before resubmitting.',
            'reason.min' => 'Provide at least 10 characters of clear rework guidance.',
            'reason.max' => 'The rework guidance may not exceed 5,000 characters.',
            'override_proposal_round_lock.accepted' => 'Confirm the administrator override before reopening an EOI evaluation after the technical-proposal round has started.',
        ]);

        $rework = $reworkService->request(
            $submission,
            $request->user(),
            trim($validated['reason']),
            $request->boolean('override_proposal_round_lock')
        );
        $rework->load([
            'assignment.technicalProposalRound:id,procurement_id,round_number,title,status',
            'applicant',
            'evaluation',
            'evaluator',
            'procurement',
            'requester',
        ]);

        $notificationWarning = null;
        $evaluatorEmail = trim((string) $rework->evaluator?->email);

        if ($evaluatorEmail === '') {
            $notificationWarning = 'The evaluation was reopened, but the evaluator account has no email address.';
            $this->recordNotificationFailure($rework, $notificationWarning);
        } else {
            try {
                Mail::to($evaluatorEmail)->send(new EvaluationReworkRequested($rework));
                $rework->forceFill([
                    'notified_at' => now(),
                    'notification_error' => null,
                ])->save();
            } catch (\Throwable $exception) {
                $notificationWarning = 'The evaluation was reopened, but its email notification could not be delivered.';
                $this->recordNotificationFailure($rework, $exception->getMessage());

                Log::error('Evaluation rework notification failed after the rework was committed.', [
                    'rework_request_id' => $rework->getKey(),
                    'evaluation_submission_id' => $submission->getKey(),
                    'evaluator_id' => $rework->evaluator_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('eval.panel.procurement', $procurement)
            ->with('success', 'The evaluation was returned to the evaluator for rework.')
            ->with('warning', $notificationWarning);
    }

    private function recordNotificationFailure(ReworkRequest $rework, string $message): void
    {
        try {
            $rework->forceFill([
                'notified_at' => null,
                'notification_error' => $message,
            ])->save();
        } catch (\Throwable $exception) {
            Log::error('Evaluation rework notification status could not be recorded.', [
                'rework_request_id' => $rework->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

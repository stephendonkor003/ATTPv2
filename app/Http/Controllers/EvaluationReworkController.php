<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationReworkBatchRequested;
use App\Mail\EvaluationReworkRequested;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Services\EvaluationReworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EvaluationReworkController extends Controller
{
    use ScopesAssignedPortfolios;

    public function batch(Request $request, Procurement $procurement, EvaluationReworkService $reworkService): RedirectResponse
    {
        abort_unless($request->user()?->can('evaluations.manage'), 403);
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            abort_unless($this->procurementIsInAssignedPortfolio($procurement, $request->user()), 403, 'This evaluation is outside your assigned portfolio.');
        }
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $validated = $request->validate([
            'evaluator_id' => ['required', 'uuid'],
            'submission_ids' => ['required', 'array', 'min:1', 'max:50'],
            'submission_ids.*' => ['required', 'uuid', 'distinct'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'override_proposal_round_lock' => ['sometimes', 'accepted'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ], [
            'submission_ids.required' => 'Select at least one submitted evaluation to return for correction.',
            'submission_ids.max' => 'Select up to 50 evaluations in one request.',
            'reason.min' => 'Provide at least 10 characters of clear rework guidance.',
        ]);
        $reworks = $reworkService->requestBatch($procurement, $validated['submission_ids'], $validated['evaluator_id'], $request->user(), $validated['reason'], $request->boolean('override_proposal_round_lock'));
        $notificationWarning = null;
        // This also defers delivery when a caller has wrapped the whole request in a transaction.
        DB::afterCommit(function () use ($reworks, &$notificationWarning): void {
            $notificationWarning = $this->notifyBatch($reworks);
        });

        $fallback = route('reports.evaluations.procurement', $procurement, false);
        $returnTo = (string) ($validated['return_to'] ?? '');
        $path = parse_url($returnTo, PHP_URL_PATH);
        $allowed = [
            $fallback,
            route('reports.evaluations.eoi.procurement', $procurement, false),
            route('reports.evaluations.consolidated', [], false),
            ...array_map(fn ($method) => route('reports.evaluations.method.procurement', [$method, $procurement], false), ['services', 'goods', 'eoi']),
        ];
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')
            || str_contains($returnTo, '\\') || preg_match('/[\x00-\x1f\x7f]/', $returnTo)
            || ! in_array($path, $allowed, true)) {
            $returnTo = $fallback;
        }

        return redirect()->to($returnTo)
            ->with('success', $reworks->count().' evaluations were returned to the evaluator for correction. Their results are excluded from reporting until resubmitted.')
            ->with('warning', $notificationWarning);
    }

    private function notifyBatch(Collection $reworks): ?string
    {
        try {
            (new \Illuminate\Database\Eloquent\Collection($reworks->all()))->load([
                'assignment.technicalProposalRound', 'applicant.submitter', 'applicant.values',
                'evaluation', 'evaluator', 'procurement', 'requester',
            ]);
            $email = trim((string) $reworks->first()?->evaluator?->email);
            if ($email === '') {
                throw new \RuntimeException('The evaluator account has no email address.');
            }
            Mail::mailer(config('evaluation_rework.mailer') ?: config('mail.default'))
                ->to($email)->send(new EvaluationReworkBatchRequested($reworks));
            foreach ($reworks as $rework) {
                $rework->forceFill(['notified_at' => now(), 'notification_error' => null])->save();
            }

            return null;
        } catch (\Throwable $exception) {
            foreach ($reworks as $rework) {
                $this->recordNotificationFailure($rework, $exception->getMessage());
            }
            Log::error('Grouped evaluation rework notification failed after the batch committed.', [
                'rework_request_ids' => $reworks->pluck('id')->all(),
                'exception' => $exception->getMessage(),
            ]);

            return 'The evaluations were reopened, but the evaluator email could not be delivered. The notification failure is recorded in the rework register.';
        }
    }

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
            $rework->refresh()->forceFill([
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

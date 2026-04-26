<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\PrescreeningEvaluation;
use App\Models\PrescreeningResult;
use App\Models\User;
use App\Mail\PrescreeningCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class PrescreeningEvaluationController extends Controller
{
    use GovernanceScope;

    private function canAccessSubmission(FormSubmission $submission): bool
    {
        if (Gate::allows('prescreening.view_all')) {
            return true;
        }

        if ($submission->assigned_prescreener_id === auth()->id()) {
            return true;
        }

        return $submission->procurement
            ? $submission->procurement
                ->prescreeningAssignments()
                ->where('user_id', auth()->id())
                ->exists()
            : false;
    }
    /**
     * ===============================
     * LIST PRESCREENING SUBMISSIONS
     * ===============================
     */
    public function index()
    {
        $query = FormSubmission::with([
            'procurement',
            'submitter',
            'prescreeningResult.evaluator',
            'values' => function ($query) {
                $query->whereIn('field_key', ['official_name', 'official_email']);
            },
        ]);

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to prescreening submissions.');
        }

        if ($scopedNodeIds !== null) {
            $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                $proc->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });
        }

        if (Gate::allows('prescreening.view_all')) {

            $submissions = $query->latest()->get();

        } else {

            // ✅ FIX: filter by ASSIGNMENT, not evaluation
            $submissions = $query
                ->where(function ($q) {
                    $q->whereHas('procurement.prescreeningAssignments', function ($q2) {
                        $q2->where('user_id', auth()->id());
                    })
                    ->orWhere('assigned_prescreener_id', auth()->id());
                })
                ->latest()
                ->get();
        }

        return view('prescreening.submissions.index', compact('submissions'));
    }

    /**
     * ===============================
     * SHOW PRESCREENING EVALUATION
     * ===============================
     */
    public function show(FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        if (!$this->canAccessSubmission($submission)) {
            abort(403);
        }

        $submission->loadMissing([
            'submitter',
            'form.fields',
            'values',
        ]);

        $template = $submission->procurement
            ->prescreeningTemplate
            ?->load('sections.criteria');

        abort_if(!$template, 404);

        // Result MAY be null (pending submission)
        $result = $submission->prescreeningResult;

        // Editable only if:
        // - assigned to user
        // - not locked
        $canEdit = $result
            ? !$result->is_locked && $result->evaluated_by === auth()->id()
            : true;

        $evaluations = PrescreeningEvaluation::where(
                'submission_id',
                $submission->id
            )
            ->get()
            ->keyBy('criterion_id');

        return view(
            'prescreening.submissions.show',
            compact('submission', 'template', 'result', 'canEdit', 'evaluations')
        );
    }

    /**
     * ===============================
     * STORE / UPDATE PRESCREENING
     * ===============================
     */
    public function store(Request $request, FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        if (!$this->canAccessSubmission($submission)) {
            abort(403);
        }

        $template = $submission->procurement->prescreeningTemplate;
        abort_if(!$template, 404);

        $template->load('sections.criteria');

        $result = $submission->prescreeningResult;

        // 🔒 Prevent edits when locked
        if ($result && $result->is_locked) {
            abort(403, 'Evaluation is locked. Rework must be requested.');
        }

        DB::transaction(function () use ($request, $submission, $template) {
            $criteria = $template->sections
                ->flatMap(fn ($section) => $section->criteria)
                ->values();

            $rules = [];
            foreach ($criteria as $criterion) {
                $rules["criteria.{$criterion->id}.passed"] = 'required|boolean';
                $rules["criteria.{$criterion->id}.remarks"] = 'nullable|string';
            }

            validator($request->all(), $rules, [
                'required' => 'Complete every prescreening item before saving the evaluation.',
            ])->validate();

            $passed = 0;
            $failed = 0;

            foreach ($criteria as $criterion) {

                $pass = (bool) $request->input(
                    "criteria.{$criterion->id}.passed"
                );

                PrescreeningEvaluation::updateOrCreate(
                    [
                        'submission_id' => $submission->id,
                        'criterion_id'  => $criterion->id,
                    ],
                    [
                        'prescreening_template_id' => $template->id,
                        'evaluator_id'             => auth()->id(),
                        'evaluation_value'         => $request->input(
                            "criteria.{$criterion->id}.value"
                        ),
                        'is_passed'                => $pass,
                        'remarks'                  => $request->input(
                            "criteria.{$criterion->id}.remarks"
                        ),
                        'evaluated_at'             => now(),
                    ]
                );

                $pass ? $passed++ : $failed++;
            }

            $finalStatus = $failed === 0 ? 'passed' : 'failed';

            PrescreeningResult::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'prescreening_template_id' => $template->id,
                    'total_criteria'           => $criteria->count(),
                    'passed_criteria'          => $passed,
                    'failed_criteria'          => $failed,
                    'final_status'             => $finalStatus,
                    'evaluated_by'             => auth()->id(),
                    'evaluated_at'             => now(),
                    'is_locked'                => true,
                ]
            );

            $submission->update([
                'status' => $finalStatus === 'passed'
                    ? 'prescreen_passed'
                    : 'prescreen_failed',
            ]);
        });

        $submission->load(['procurement', 'submitter', 'values', 'prescreeningResult.evaluator']);

        $admins = User::whereHas('role', function ($q) {
            $q->where('name', 'System Admin');
        })->get();

        $recipients = $admins->pluck('email')->filter()->all();

        $evaluatorEmail = $submission->prescreeningResult?->evaluator?->email;
        if ($evaluatorEmail) {
            $recipients[] = $evaluatorEmail;
        }

        $recipients = array_values(array_unique($recipients));

        if (!empty($recipients)) {
            foreach ($recipients as $email) {
                Mail::to($email)->send(new PrescreeningCompleted($submission));
            }
        }

        return redirect()
            ->route('prescreening.submissions.index')
            ->with('success', 'Prescreening evaluation saved successfully.');
    }

    /**
     * ===============================
     * REQUEST REWORK (UNLOCK)
     * ===============================
     */
    public function requestRework(FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        abort_if(Gate::denies('prescreening.request_rework'), 403);

        $result = $submission->prescreeningResult;
        abort_if(!$result, 404);

        $result->update([
            'is_locked'           => false,
            'rework_requested_by' => auth()->id(),
            'rework_requested_at' => now(),
        ]);

        return back()->with('success', 'Rework requested successfully.');
    }
}

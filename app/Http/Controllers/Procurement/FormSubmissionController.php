<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Services\ProcurementSubmissionScreeningAutomation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormSubmissionController extends Controller
{
    public function create(Request $request, DynamicForm $form)
    {
        $this->authorizeSubmissionOperation($request);
        $this->boundProcurement($form);
        $form->ensureGlobalFields();
        $form->load('fields');

        return view('procurement.submissions.create', compact('form'));
    }

    public function store(
        Request $request,
        DynamicForm $form,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
    ) {
        $this->authorizeSubmissionOperation($request);

        $submission = DB::transaction(function () use ($request, $form): FormSubmission {
            $form = DynamicForm::query()
                ->lockForUpdate()
                ->findOrFail($form->getKey());
            $procurement = $this->boundProcurement($form);

            $form->ensureGlobalFields();
            $form->load('fields');

            $submission = FormSubmission::create([
                'procurement_id' => $procurement->getKey(),
                'form_id' => $form->id,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                FormSubmissionValue::create([
                    'submission_id' => $submission->id,
                    'field_key' => $field->field_key,
                    'value' => $request->input($field->field_key),
                ]);
            }

            return $submission;
        });

        if (filled($submission->procurement_id)) {
            $screeningAutomation->queueSubmission($submission->id);
        }

        return redirect()->route('submissions.show', $submission);
    }

    public function show(FormSubmission $submission)
    {
        $submission->load('values');

        return view('procurement.submissions.show', compact('submission'));
    }

    private function boundProcurement(DynamicForm $form): Procurement
    {
        abort_if(
            blank($form->procurement_id),
            404,
            'This form is not attached to an active procurement.',
        );

        $procurement = Procurement::query()->find($form->procurement_id);

        abort_unless(
            $procurement,
            404,
            'This form is not attached to an active procurement.',
        );

        return $procurement;
    }

    private function authorizeSubmissionOperation(Request $request): void
    {
        abort_unless(
            $request->user()?->can('forms.submit') === true,
            403,
            'You do not have permission to submit procurement forms.',
        );
    }
}

<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\DynamicForm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormSubmissionController extends Controller
{
    public function create(DynamicForm $form)
    {
        $form->ensureGlobalFields();
        $form->load('fields');
        return view('procurement.submissions.create', compact('form'));
    }

    public function store(Request $request, DynamicForm $form)
    {
        $validated = $request->validate([
            'procurement_id' => [
                'nullable',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $form->ensureGlobalFields();
        $form->load('fields');

        $submission = FormSubmission::create([
            'procurement_id' => $validated['procurement_id'] ?? null,
            'form_id' => $form->id,
            'submitted_by' => auth()->id(),
            'submitted_at' => now()
        ]);

        foreach ($form->fields as $field) {
            FormSubmissionValue::create([
                'submission_id' => $submission->id,
                'field_key' => $field->field_key,
                'value' => $request->input($field->field_key)
            ]);
        }

        return redirect()->route('submissions.show', $submission);
    }

    public function show(FormSubmission $submission)
    {
        $submission->load('values');
        return view('procurement.submissions.show', compact('submission'));
    }
}

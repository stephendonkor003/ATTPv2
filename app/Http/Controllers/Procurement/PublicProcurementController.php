<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicProcurementController extends Controller
{
    /**
     * ===============================
     * PUBLIC PROCUREMENT LIST
     * ===============================
     */
    public function index()
    {
        $procurements = Procurement::where('status', 'published')
            ->latest()
            ->get();

        return view('public.procurements.index', compact('procurements'));
    }

    /**
     * ===============================
     * SHOW PROCUREMENT + FORM
     * ===============================
     */
    public function show(Procurement $procurement)
    {
        abort_if($procurement->status !== 'published', 404);

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->first(); // allow null for public view

        return view('public.procurements.show', compact('procurement', 'form'));
    }

    /**
     * ===============================
     * SUBMIT PROCUREMENT APPLICATION
     * ===============================
     */
    public function submit(Request $request, Procurement $procurement)
    {
        abort_if($procurement->status !== 'published', 404);

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | DYNAMIC VALIDATION (SELECT2 READY)
        |--------------------------------------------------------------------------
        */
        $rules = [];

        foreach ($form->fields as $field) {

            $key = $field->field_key;
            $required = $field->is_required ? 'required' : 'nullable';

            switch ($field->field_type) {

                case 'email':
                    $rules[$key] = "$required|email";
                    break;

                case 'file':
                    // NOTE: Laravel's "max" is in kilobytes. Keep public uploads small to reduce DoS risk.
                    $rules[$key] = "$required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480"; // 20MB
                    break;

                case 'checkbox':       // Select2 multi-select
                case 'multiselect':
                    $rules[$key] = "$required|array";
                    break;

                case 'number':
                    $rules[$key] = "$required|numeric";
                    break;

                case 'url':
                    $rules[$key] = "$required|url";
                    break;

                default:
                    $rules[$key] = $required;
            }
        }

        $validated = $request->validate($rules);

        /*
        |--------------------------------------------------------------------------
        | SAVE SUBMISSION + VALUES
        |--------------------------------------------------------------------------
        */
        DB::transaction(function () use ($request, $procurement, $form) {

            $submission = FormSubmission::create([
                'procurement_id' => $procurement->id,
                'form_id'        => $form->id,
                'submitted_by'   => null,
                'status'         => 'submitted',
                'submitted_at'   => now(),
            ]);

            foreach ($form->fields as $field) {

                $key = $field->field_key;
                $value = null;

                // FILE
                if ($field->field_type === 'file' && $request->hasFile($key)) {
                    $value = $request->file($key)
                        // Store submissions on the default (private) disk; access must be authorized.
                        ->store('procurement_submissions');
                }

                // MULTI SELECT (ARRAY FROM SELECT2)
                elseif (is_array($request->input($key))) {
                    $value = json_encode(array_values($request->input($key)));
                }

                // NORMAL INPUT
                else {
                    $value = $request->input($key);
                }

                FormSubmissionValue::create([
                    'submission_id' => $submission->id,
                    'field_key'     => $key,
                    'value'         => $value,
                ]);
            }
        });

        return back()->with('success', 'Application submitted successfully.');
    }
}

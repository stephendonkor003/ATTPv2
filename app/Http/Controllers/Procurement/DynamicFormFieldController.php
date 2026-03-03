<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DynamicFormFieldController extends Controller
{
    /**
     * Store a new field for a procurement form
     */
    public function store(Request $request, DynamicForm $form)
    {
        // ❗ Business rule: only editable forms can be modified
        if (!$form->canEdit()) {
            return back()->with('error', 'This form already has submissions and cannot be edited.');
        }

        $data = $request->validate([
            'label'       => 'required|string|max:255',
            'field_type'  => 'required|string|max:50',
            'is_required' => 'nullable|boolean',
            'options'     => 'nullable|string',
        ]);

        $data['form_id']     = $form->id; // route-model binding
        $data['field_key']   = Str::slug($data['label'], '_');
        $data['is_required'] = $request->boolean('is_required');
        $data['created_by']  = auth()->id();

        if ($form->fields()->where('field_key', $data['field_key'])->exists()) {
            return back()->with('error', 'A field with this label already exists.');
        }

        DynamicFormField::create($data);

        return back()->with('success', 'Field added successfully.');
    }

    /**
     * Remove a field from the form
     */
    public function destroy(DynamicFormField $field)
    {
        // ❗ Prevent deletion if parent form is locked
        if (!$field->form->canEdit()) {
            return back()->with('error', 'Fields cannot be removed after submissions exist for this form.');
        }

        if (in_array($field->field_key, DynamicForm::globalFieldKeys(), true)) {
            return back()->with('error', 'This is a required global field and cannot be removed.');
        }

        $field->delete();

        return back()->with('success', 'Field removed successfully.');
    }
}

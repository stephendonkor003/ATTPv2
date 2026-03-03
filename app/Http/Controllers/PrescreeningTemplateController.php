<?php

namespace App\Http\Controllers;

use App\Models\PrescreeningTemplate;
use App\Models\PrescreeningCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrescreeningTemplateController extends Controller
{
    /**
     * List templates
     */
    public function index()
    {
        $templates = PrescreeningTemplate::withCount('criteria')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('prescreening.templates.index', compact('templates'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('prescreening.templates.create');
    }

    /**
     * Store template + criteria
     */
    public function store(Request $request)
    {
        $validated = $this->validateTemplatePayload($request);

        DB::transaction(function () use ($validated) {

            $template = PrescreeningTemplate::create([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? false,
                'created_by'  => auth()->id(),
            ]);

            foreach ($validated['criteria'] as $index => $criterion) {
                PrescreeningCriterion::create([
                    'prescreening_template_id' => $template->id,
                    'name'            => $criterion['name'],
                    'field_key'       => $criterion['field_key'],
                    'evaluation_type' => $criterion['evaluation_type'],
                    'min_value'       => $criterion['min_value'] ?? null,
                    'is_mandatory'    => $criterion['is_mandatory'] ?? false,
                    'sort_order'      => $index + 1,
                ]);
            }
        });

        return redirect()
            ->route('prescreening.templates.index')
            ->with('success', 'Prescreening template created successfully.');
    }

    /**
     * Show template (read-only)
     */
    public function show(PrescreeningTemplate $template)
    {
        $template->load('criteria');

        return view('prescreening.templates.show', compact('template'));
    }

    /**
     * Show edit form
     */
    public function edit(PrescreeningTemplate $template)
    {
        $template->load('criteria');

        return view('prescreening.templates.edit', compact('template'));
    }

    /**
     * Update template + criteria
     */
    public function update(Request $request, PrescreeningTemplate $template)
    {
        $validated = $this->validateTemplatePayload($request);

        DB::transaction(function () use ($validated, $template) {

            // Update template
            $template->update([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? false,
            ]);

            // Remove old criteria
            $template->criteria()->delete();

            // Recreate criteria
            foreach ($validated['criteria'] as $index => $criterion) {
                PrescreeningCriterion::create([
                    'prescreening_template_id' => $template->id,
                    'name'            => $criterion['name'],
                    'field_key'       => $criterion['field_key'],
                    'evaluation_type' => $criterion['evaluation_type'],
                    'min_value'       => $criterion['min_value'] ?? null,
                    'is_mandatory'    => $criterion['is_mandatory'] ?? false,
                    'sort_order'      => $index + 1,
                ]);
            }
        });

        return redirect()
            ->route('prescreening.templates.show', $template)
            ->with('success', 'Prescreening template updated successfully.');
    }

    private function validateTemplatePayload(Request $request): array
    {
        $criteria = $this->resolveCriteriaPayload($request);

        $validator = Validator::make(
            [
                'name'        => $request->input('name'),
                'description' => $request->input('description'),
                'is_active'   => $request->boolean('is_active'),
                'criteria'    => $criteria,
            ],
            [
                'name'                        => 'required|string|max:255',
                'description'                 => 'nullable|string',
                'is_active'                   => 'nullable|boolean',
                'criteria'                    => 'required|array|min:1',
                'criteria.*.name'             => 'required|string|max:255',
                'criteria.*.field_key'        => 'required|string|max:191',
                'criteria.*.evaluation_type'  => 'required|in:yes_no,exists,numeric',
                'criteria.*.min_value'        => 'nullable|numeric',
                'criteria.*.is_mandatory'     => 'nullable|boolean',
            ],
            [
                'criteria.required' => 'Add at least one criterion before saving the template.',
                'criteria.array'    => 'Criteria payload is invalid. Please refresh and try again.',
                'criteria.min'      => 'Add at least one criterion before saving the template.',
            ]
        );

        $validator->after(function ($validator) use ($criteria) {
            $fieldKeys = collect($criteria)
                ->pluck('field_key')
                ->map(fn ($key) => trim((string) $key))
                ->filter();

            if ($fieldKeys->duplicates()->isNotEmpty()) {
                $validator->errors()->add(
                    'criteria',
                    'Duplicate field keys detected. Each criterion must have a unique field key.'
                );
            }
        });

        $validated = $validator->validate();

        $validated['criteria'] = collect($validated['criteria'])
            ->values()
            ->map(function (array $criterion): array {
                $evaluationType = (string) $criterion['evaluation_type'];

                return [
                    'name'            => trim((string) $criterion['name']),
                    'field_key'       => trim((string) $criterion['field_key']),
                    'evaluation_type' => $evaluationType,
                    'min_value'       => $evaluationType === 'numeric'
                        ? ($criterion['min_value'] !== null && $criterion['min_value'] !== '' ? $criterion['min_value'] : null)
                        : null,
                    'is_mandatory'    => filter_var($criterion['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->all();

        return $validated;
    }

    private function resolveCriteriaPayload(Request $request): array
    {
        $payload = $request->input('criteria_payload');
        if (!is_string($payload) || trim($payload) === '') {
            return (array) $request->input('criteria', []);
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                'criteria' => 'Unable to process criteria rows. Please reload the page and try saving again.',
            ]);
        }

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'criteria' => 'Unable to process criteria rows. Please reload the page and try saving again.',
            ]);
        }

        return $decoded;
    }
}

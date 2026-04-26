<?php

namespace App\Http\Controllers;

use App\Models\PrescreeningTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrescreeningTemplateController extends Controller
{
    /**
     * List templates.
     */
    public function index()
    {
        $templates = PrescreeningTemplate::withCount(['criteria', 'sections'])
            ->orderByDesc('created_at')
            ->get();

        return view('prescreening.templates.index', compact('templates'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('prescreening.templates.create');
    }

    /**
     * Store template with sectioned criteria.
     */
    public function store(Request $request)
    {
        $validated = $this->validateTemplatePayload($request);

        DB::transaction(function () use ($validated) {
            $template = PrescreeningTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? false,
                'created_by' => auth()->id(),
            ]);

            $this->syncSections($template, $validated['sections']);
        });

        return redirect()
            ->route('prescreening.templates.index')
            ->with('success', 'Prescreening template created successfully.');
    }

    /**
     * Show template.
     */
    public function show(PrescreeningTemplate $template)
    {
        $template->load('sections.criteria');

        return view('prescreening.templates.show', compact('template'));
    }

    /**
     * Show edit form.
     */
    public function edit(PrescreeningTemplate $template)
    {
        $template->load('sections.criteria');

        return view('prescreening.templates.edit', compact('template'));
    }

    /**
     * Update template with sectioned criteria.
     */
    public function update(Request $request, PrescreeningTemplate $template)
    {
        $validated = $this->validateTemplatePayload($request);

        DB::transaction(function () use ($validated, $template) {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? false,
            ]);

            $template->criteria()->delete();
            $template->sections()->delete();

            $this->syncSections($template, $validated['sections']);
        });

        return redirect()
            ->route('prescreening.templates.show', $template)
            ->with('success', 'Prescreening template updated successfully.');
    }

    private function syncSections(PrescreeningTemplate $template, array $sections): void
    {
        foreach ($sections as $sectionIndex => $sectionData) {
            $section = $template->sections()->create([
                'name' => $sectionData['name'],
                'description' => $sectionData['description'] ?? null,
                'sort_order' => $sectionIndex + 1,
            ]);

            foreach ($sectionData['items'] as $itemIndex => $item) {
                $section->criteria()->create([
                    'prescreening_template_id' => $template->id,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'field_key' => $item['field_key'],
                    'evaluation_type' => 'yes_no',
                    'min_value' => null,
                    'is_mandatory' => $item['is_mandatory'] ?? false,
                    'sort_order' => $itemIndex + 1,
                ]);
            }
        }
    }

    private function validateTemplatePayload(Request $request): array
    {
        $sections = $this->resolveSectionsPayload($request);

        $validated = Validator::make(
            [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'is_active' => $request->boolean('is_active'),
                'sections' => $sections,
            ],
            [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'sections' => 'required|array|min:1',
                'sections.*.name' => 'required|string|max:255',
                'sections.*.description' => 'nullable|string',
                'sections.*.items' => 'required|array|min:1',
                'sections.*.items.*.name' => 'required|string|max:255',
                'sections.*.items.*.description' => 'nullable|string',
                'sections.*.items.*.is_mandatory' => 'nullable|boolean',
            ],
            [
                'sections.required' => 'Add at least one section before saving the template.',
                'sections.min' => 'Add at least one section before saving the template.',
                'sections.*.items.required' => 'Each section must have at least one item.',
                'sections.*.items.min' => 'Each section must have at least one item.',
            ]
        )->validate();

        $usedFieldKeys = [];
        $itemIndex = 1;

        $validated['sections'] = collect($validated['sections'])
            ->values()
            ->map(function (array $section) use (&$usedFieldKeys, &$itemIndex): array {
                return [
                    'name' => trim((string) $section['name']),
                    'description' => filled($section['description'] ?? null)
                        ? trim((string) $section['description'])
                        : null,
                    'items' => collect($section['items'])
                        ->values()
                        ->map(function (array $item) use (&$usedFieldKeys, &$itemIndex): array {
                            $name = trim((string) $item['name']);

                            return [
                                'name' => $name,
                                'description' => filled($item['description'] ?? null)
                                    ? trim((string) $item['description'])
                                    : null,
                                'field_key' => $this->makeUniqueFieldKey($name, $usedFieldKeys, $itemIndex++),
                                'is_mandatory' => filter_var($item['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();

        return $validated;
    }

    private function resolveSectionsPayload(Request $request): array
    {
        $sectionsPayload = $request->input('sections_payload');
        if (is_string($sectionsPayload) && trim($sectionsPayload) !== '') {
            return $this->decodeJsonPayload($sectionsPayload, 'sections');
        }

        $sections = $request->input('sections');
        if (is_array($sections) && !empty($sections)) {
            return $sections;
        }

        $criteriaPayload = $request->input('criteria_payload');
        if (is_string($criteriaPayload) && trim($criteriaPayload) !== '') {
            return $this->wrapCriteriaAsSection(
                $this->decodeJsonPayload($criteriaPayload, 'criteria')
            );
        }

        $criteria = $request->input('criteria', []);
        if (is_array($criteria) && !empty($criteria)) {
            return $this->wrapCriteriaAsSection($criteria);
        }

        return [];
    }

    private function wrapCriteriaAsSection(array $criteria): array
    {
        return [[
            'name' => 'General Requirements',
            'description' => null,
            'items' => $criteria,
        ]];
    }

    private function decodeJsonPayload(string $payload, string $type): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                $type => 'Unable to process the submitted template. Please refresh and try again.',
            ]);
        }

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $type => 'Unable to process the submitted template. Please refresh and try again.',
            ]);
        }

        return $decoded;
    }

    private function makeUniqueFieldKey(string $name, array &$usedFieldKeys, int $index): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'criterion_' . $index;
        }

        $candidate = $base;
        $suffix = 2;

        while (in_array($candidate, $usedFieldKeys, true)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        $usedFieldKeys[] = $candidate;

        return $candidate;
    }
}

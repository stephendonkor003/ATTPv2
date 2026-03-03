<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EvaluationCriteriaController extends Controller
{
    /**
     * Store a new criteria under a section
     */
    public function store(Request $request, EvaluationSection $section)
    {
        if ($section->evaluation->status !== 'draft') {
            return back()->with('error', 'Cannot modify criteria once evaluation is active.');
        }

        $isServices = $section->evaluation->type === 'services';
        $bulkRows = $this->resolveBulkRows($request);

        if (!empty($bulkRows)) {
            $validated = Validator::make(
                ['criteria' => $bulkRows],
                [
                    'criteria'                => 'required|array|min:1',
                    'criteria.*.name'         => 'required|string|max:255',
                    'criteria.*.description'  => 'nullable|string',
                    'criteria.*.max_score'    => $isServices
                        ? 'required|numeric|min:1'
                        : 'nullable|numeric|min:0',
                ],
                [
                    'criteria.required'             => 'Add at least one criteria row before saving.',
                    'criteria.min'                  => 'Add at least one criteria row before saving.',
                    'criteria.*.name.required'      => 'Each criteria row must have a name.',
                    'criteria.*.max_score.required' => 'Max score is required for services evaluation criteria.',
                ]
            )->validate();

            $created = [];
            foreach ($validated['criteria'] as $row) {
                $criteria = $section->criteria()->create([
                    'name'        => trim((string) $row['name']),
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'max_score'   => $isServices ? $row['max_score'] : null,
                ]);

                $created[] = [
                    'id'          => $criteria->id,
                    'name'        => $criteria->name,
                    'description' => $criteria->description,
                    'max_score'   => $criteria->max_score,
                    'update_url'  => route('evals.cfg.crt.upd', $criteria),
                    'delete_url'  => route('evals.cfg.crt.del', $criteria),
                ];
            }

            $message = count($created) . ' criteria added successfully.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success'  => true,
                    'message'  => $message,
                    'criteria' => $created,
                ]);
            }

            return back()->with('success', $message);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_score'   => $isServices ? 'required|numeric|min:1' : 'nullable|numeric|min:0',
        ]);

        $criteria = $section->criteria()->create([
            'name'        => trim((string) $validated['name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'max_score'   => $isServices ? ($validated['max_score'] ?? null) : null,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Evaluation criteria added successfully.',
                'criteria' => [[
                    'id'          => $criteria->id,
                    'name'        => $criteria->name,
                    'description' => $criteria->description,
                    'max_score'   => $criteria->max_score,
                    'update_url'  => route('evals.cfg.crt.upd', $criteria),
                    'delete_url'  => route('evals.cfg.crt.del', $criteria),
                ]],
            ]);
        }

        return back()->with('success', 'Evaluation criteria added successfully.');
    }

    /**
     * Update a criteria
     */
    public function update(Request $request, EvaluationCriteria $criteria)
    {
        if ($criteria->section->evaluation->status !== 'draft') {
            return back()->with('error', 'Cannot modify criteria once evaluation is active.');
        }

        $isServices = $criteria->section->evaluation->type === 'services';

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_score'   => $isServices ? 'required|numeric|min:1' : 'nullable|numeric|min:0',
        ]);

        $criteria->update(
            [
                'name'        => trim((string) $request->name),
                'description' => trim((string) $request->description) ?: null,
                'max_score'   => $isServices ? $request->max_score : null,
            ]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Criteria updated successfully.',
                'criteria' => [
                    'id'          => $criteria->id,
                    'name'        => $criteria->name,
                    'description' => $criteria->description,
                    'max_score'   => $criteria->max_score,
                ],
            ]);
        }

        return back()->with('success', 'Criteria updated successfully.');
    }

    /**
     * Delete a criteria
     */
    public function destroy(EvaluationCriteria $criteria)
    {
        if ($criteria->section->evaluation->status !== 'draft') {
            return back()->with('error', 'Cannot delete criteria once evaluation is active.');
        }

        $criteria->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Criteria removed successfully.',
            ]);
        }

        return back()->with('success', 'Criteria removed successfully.');
    }

    private function resolveBulkRows(Request $request): array
    {
        $payload = $request->input('criteria_payload');
        if (is_string($payload) && trim($payload) !== '') {
            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ValidationException::withMessages([
                    'criteria' => 'Unable to process criteria payload. Please refresh and try again.',
                ]);
            }

            if (!is_array($decoded)) {
                throw ValidationException::withMessages([
                    'criteria' => 'Unable to process criteria payload. Please refresh and try again.',
                ]);
            }

            return collect($decoded)
                ->filter(fn ($row) => is_array($row))
                ->map(function (array $row): array {
                    return [
                        'name'        => trim((string) ($row['name'] ?? '')),
                        'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                        'max_score'   => $row['max_score'] ?? null,
                    ];
                })
                ->filter(fn (array $row) => $row['name'] !== '' || ($row['description'] ?? '') !== '' || $row['max_score'] !== null)
                ->values()
                ->all();
        }

        $criteria = $request->input('criteria', []);
        if (!is_array($criteria)) {
            return [];
        }

        return collect($criteria)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                return [
                    'name'        => trim((string) ($row['name'] ?? '')),
                    'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                    'max_score'   => $row['max_score'] ?? null,
                ];
            })
            ->filter(fn (array $row) => $row['name'] !== '' || ($row['description'] ?? '') !== '' || $row['max_score'] !== null)
            ->values()
            ->all();
    }
}

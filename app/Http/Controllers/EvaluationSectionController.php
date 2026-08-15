<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\EvaluationSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationSectionController extends Controller
{
    use ScopesAssignedPortfolios;

    /**
     * Store a new evaluation section
     */
    public function store(Request $request, Evaluation $evaluation)
    {
        $this->assertSectionEvaluationManageable($evaluation);

        if ($evaluation->status !== 'draft') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Cannot modify sections once evaluation is active.',
                ], 422);
            }

            return back()->with('error', 'Cannot modify sections once evaluation is active.');
        }

        $validated = $this->validateSection($request, $evaluation);
        $parent = $this->resolveParent($validated['parent_section_id'] ?? null, $evaluation);

        if ($parent && $parent->depth >= EvaluationSection::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_section_id' => 'The evaluation form supports a maximum of four section levels.',
            ]);
        }

        $section = DB::transaction(function () use ($evaluation, $parent, $validated): EvaluationSection {
            $section = $evaluation->sections()->create([
                'parent_section_id' => $parent?->id,
                'name' => trim((string) $validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'show_subtotal' => array_key_exists('show_subtotal', $validated)
                    ? (bool) $validated['show_subtotal']
                    : true,
                'sort_order' => $this->nextSiblingPosition($evaluation, $parent?->id),
            ]);

            $this->normalizeSiblingOrder(
                $evaluation,
                $parent?->id,
                $section,
                $validated['sort_order'] ?? null
            );

            return $section;
        });

        $message = $section->level_label.' added successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'section' => $this->sectionPayload($section->fresh()),
            ], 201);
        }

        return back()->with('success', $message);
    }

    /**
     * Update a section
     */
    public function update(Request $request, EvaluationSection $section)
    {
        $this->assertSectionEvaluationManageable($section->evaluation);

        if ($section->evaluation->status !== 'draft') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Cannot modify sections once evaluation is active.',
                ], 422);
            }

            return back()->with('error', 'Cannot modify sections once evaluation is active.');
        }

        $evaluation = $section->evaluation;
        $validated = $this->validateSection($request, $evaluation);
        $parentId = array_key_exists('parent_section_id', $validated)
            ? ($validated['parent_section_id'] ?: null)
            : $section->parent_section_id;
        $parent = $this->resolveParent($parentId, $evaluation);

        $this->assertValidMove($section, $parent);

        DB::transaction(function () use ($section, $evaluation, $parent, $validated): void {
            $oldParentId = $section->parent_section_id;
            $parentChanged = (string) $oldParentId !== (string) $parent?->id;
            $requestedPosition = array_key_exists('sort_order', $validated)
                ? (int) $validated['sort_order']
                : ($parentChanged ? null : (int) $section->sort_order);

            $section->update([
                'parent_section_id' => $parent?->id,
                'name' => trim((string) $validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'show_subtotal' => array_key_exists('show_subtotal', $validated)
                    ? (bool) $validated['show_subtotal']
                    : $section->show_subtotal,
                'sort_order' => $parentChanged
                    ? $this->nextSiblingPosition($evaluation, $parent?->id)
                    : $section->sort_order,
            ]);

            if ($parentChanged) {
                $this->normalizeSiblingOrder($evaluation, $oldParentId);
            }

            $this->normalizeSiblingOrder(
                $evaluation,
                $parent?->id,
                $section,
                $requestedPosition
            );
        });

        $section = $section->fresh();
        $message = $section->level_label.' updated successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'section' => $this->sectionPayload($section),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Delete a section
     */
    public function destroy(EvaluationSection $section)
    {
        $this->assertSectionEvaluationManageable($section->evaluation);

        if ($section->evaluation->status !== 'draft') {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'message' => 'Cannot delete sections once evaluation is active.',
                ], 422);
            }

            return back()->with('error', 'Cannot delete sections once evaluation is active.');
        }

        $branchSize = $section->subtreeSections()->count();
        $parentId = $section->parent_section_id;
        $evaluation = $section->evaluation;

        DB::transaction(function () use ($section, $evaluation, $parentId): void {
            $section->delete();
            $this->normalizeSiblingOrder($evaluation, $parentId);
        });

        $descendantCount = $branchSize - 1;
        $message = $descendantCount > 0
            ? 'Section and its '.$descendantCount.' child '.str('section')->plural($descendantCount).' were removed successfully.'
            : 'Section removed successfully.';

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_section_id' => (string) $section->getKey(),
            ]);
        }

        return back()->with('success', $message);
    }

    private function sectionPayload(EvaluationSection $section): array
    {
        return [
            'id' => (string) $section->getKey(),
            'parent_section_id' => $section->parent_section_id
                ? (string) $section->parent_section_id
                : null,
            'name' => $section->name,
            'description' => $section->description,
            'show_subtotal' => (bool) $section->show_subtotal,
            'sort_order' => (int) $section->sort_order,
            'level' => $section->depth,
            'level_label' => $section->level_label,
        ];
    }

    private function assertSectionEvaluationManageable(Evaluation $evaluation): void
    {
        abort_unless(
            in_array($evaluation->type, Evaluation::MANAGED_TYPES, true)
            && in_array($evaluation->status, ['draft', 'active', 'close'], true)
            && filled($evaluation->portfolio_id),
            404
        );

        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        abort_unless(
            $this->evaluationIsInAssignedPortfolio($evaluation),
            403,
            'This evaluation configuration is not assigned to your portfolio.'
        );
    }

    private function validateSection(Request $request, Evaluation $evaluation): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_section_id' => [
                'nullable',
                'uuid',
                Rule::exists('evaluation_sections', 'id')
                    ->where(fn ($query) => $query->where('evaluation_id', $evaluation->id)),
            ],
            'show_subtotal' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'parent_section_id.exists' => 'Choose a parent section from this evaluation.',
            'parent_section_id.uuid' => 'Choose a valid parent section.',
        ]);
    }

    private function resolveParent(?string $parentId, Evaluation $evaluation): ?EvaluationSection
    {
        if (! filled($parentId)) {
            return null;
        }

        return EvaluationSection::query()
            ->where('evaluation_id', $evaluation->id)
            ->findOrFail($parentId);
    }

    private function assertValidMove(EvaluationSection $section, ?EvaluationSection $parent): void
    {
        if (! $parent) {
            $newDepth = 1;
        } else {
            if ($parent->is($section)) {
                throw ValidationException::withMessages([
                    'parent_section_id' => 'A section cannot be its own parent.',
                ]);
            }

            if ($section->containsDescendant($parent)) {
                throw ValidationException::withMessages([
                    'parent_section_id' => 'A section cannot be moved inside one of its child sections.',
                ]);
            }

            $newDepth = $parent->depth + 1;
        }

        if ($newDepth + $section->subtreeHeight() - 1 > EvaluationSection::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_section_id' => 'This move would exceed the four-level section limit.',
            ]);
        }
    }

    private function nextSiblingPosition(Evaluation $evaluation, ?string $parentId): int
    {
        $highestPosition = EvaluationSection::query()
            ->where('evaluation_id', $evaluation->id)
            ->when(
                filled($parentId),
                fn ($query) => $query->where('parent_section_id', $parentId),
                fn ($query) => $query->whereNull('parent_section_id')
            )
            ->ordered()
            ->lockForUpdate()
            ->pluck('sort_order')
            ->max();

        return (int) $highestPosition + 1;
    }

    private function normalizeSiblingOrder(
        Evaluation $evaluation,
        ?string $parentId,
        ?EvaluationSection $focus = null,
        ?int $requestedPosition = null
    ): void {
        $siblings = EvaluationSection::query()
            ->where('evaluation_id', $evaluation->id)
            ->when(
                filled($parentId),
                fn ($query) => $query->where('parent_section_id', $parentId),
                fn ($query) => $query->whereNull('parent_section_id')
            )
            ->ordered()
            ->lockForUpdate()
            ->get();

        if ($focus) {
            $siblings = $siblings->reject(fn (EvaluationSection $sibling): bool => $sibling->is($focus))->values();
            $position = max(1, min($requestedPosition ?? ($siblings->count() + 1), $siblings->count() + 1));
            $siblings->splice($position - 1, 0, [$focus]);
        }

        $siblings->values()->each(function (EvaluationSection $sibling, int $index): void {
            $position = $index + 1;

            if ((int) $sibling->sort_order !== $position) {
                $sibling->updateQuietly(['sort_order' => $position]);
            }
        });
    }
}

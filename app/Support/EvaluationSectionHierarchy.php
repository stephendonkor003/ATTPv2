<?php

namespace App\Support;

use App\Models\Evaluation;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use Illuminate\Support\Collection;

/**
 * Presentation helpers for the four-tier evaluation section tree.
 *
 * Criteria remain attached to exactly one section. This class only flattens
 * the ordered tree for screens and documents, so a criterion is never
 * rendered or included in the overall evaluation total more than once.
 */
final class EvaluationSectionHierarchy
{
    /**
     * Return the ordered tree as a depth-first list with stable outline
     * numbering (1, 1.1, 1.1.1 and 1.1.1.1).
     *
     * @return Collection<int, array{
     *     section: EvaluationSection,
     *     depth: int,
     *     number: string,
     *     label: string,
     *     root_index: int,
     *     root_section_id: string
     * }>
     */
    public static function flattened(Evaluation $evaluation): Collection
    {
        $rootIndex = -1;
        $rootSectionId = '';

        return $evaluation->flattenedSections()
            ->map(function (EvaluationSection $section) use (&$rootIndex, &$rootSectionId): array {
                if ($section->depth === 1) {
                    $rootIndex++;
                    $rootSectionId = (string) $section->getKey();
                }

                return [
                    'section' => $section,
                    'depth' => $section->depth - 1,
                    'number' => $section->outline_number,
                    'label' => $section->level_label,
                    'root_index' => max(0, $rootIndex),
                    'root_section_id' => $rootSectionId,
                ];
            })
            ->values();
    }

    /**
     * Sum submitted numeric criterion scores across this section's full
     * subtree. The evaluation overall total still sums each criterion once.
     */
    public static function numericSubtotal(
        EvaluationSubmission $submission,
        EvaluationSection $section
    ): float {
        $criteriaIds = $section->subtreeCriteria()->pluck('id');

        return round((float) $submission->criteriaScores
            ->whereIn('evaluation_criteria_id', $criteriaIds)
            ->sum('score'), 2);
    }

    /**
     * Count categorical responses across a section's full subtree without
     * converting ordinal categories into a synthetic score or ranking.
     *
     * @return array<string, int>
     */
    public static function decisionDistribution(
        EvaluationSubmission $submission,
        EvaluationSection $section
    ): array {
        $criteriaIds = $section->subtreeCriteria()->pluck('id');
        $scores = $submission->criteriaScores
            ->whereIn('evaluation_criteria_id', $criteriaIds);

        return collect($submission->evaluation->decisionOptions())
            ->mapWithKeys(fn (string $label, int $decision): array => [
                $label => $scores->filter(
                    fn ($score): bool => $score->decision !== null
                        && $score->decision !== ''
                        && (int) $score->decision === $decision
                )->count(),
            ])
            ->all();
    }
}

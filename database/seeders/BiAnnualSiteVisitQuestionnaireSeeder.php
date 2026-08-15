<?php

namespace Database\Seeders;

use App\Models\BiAnnualSiteVisitTemplate;
use App\Services\BiAnnualSiteVisitTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BiAnnualSiteVisitQuestionnaireSeeder extends Seeder
{
    public function run(BiAnnualSiteVisitTemplateService $templates): void
    {
        $code = 'ATTP-BASV-MONITORING';

        /** @var array<string, mixed> $definition */
        $definition = require database_path('data/biannual_monitoring_questionnaire.php');
        $sourceHash = (string) data_get($definition, 'source.content_sha256');
        $expectedQuestionCount = (int) data_get($definition, 'counts.questions', 0);

        if ($sourceHash === '' || $expectedQuestionCount < 1) {
            throw new \RuntimeException(
                'The bundled Bi-Annual questionnaire must include source provenance and question counts.'
            );
        }

        // Published versions are immutable. If this exact workbook release is
        // already present, leave its lifecycle state and any administrator
        // choices untouched. Otherwise create a new version instead of
        // rewriting a version already used by scheduled visits.
        $existingRelease = BiAnnualSiteVisitTemplate::query()
            ->forCode($code)
            ->withCount('questions')
            ->get()
            ->first(fn (BiAnnualSiteVisitTemplate $template): bool => hash_equals(
                $sourceHash,
                (string) data_get($template->settings, 'source.content_sha256', '')
            )
                && (int) $template->questions_count === $expectedQuestionCount
            );

        if ($existingRelease) {
            return;
        }

        foreach ($definition['sections'] as &$section) {
            foreach ($section['topics'] as &$topic) {
                foreach ($topic['questions'] as &$question) {
                    $question['required'] = true;
                    $question['weight'] = 1;
                    $question['scoring_direction'] = 'positive';
                }
                unset($question);
            }
            unset($topic);
        }
        unset($section);

        DB::transaction(function () use (
            $definition,
            $code,
            $templates,
            $sourceHash,
            $expectedQuestionCount
        ): void {
            // Serialize releases for this template code. The fingerprint must
            // then be re-read in a new statement: under READ COMMITTED, a
            // SELECT that waited for another seeder can otherwise retain the
            // older statement snapshot and miss the release it just created.
            BiAnnualSiteVisitTemplate::query()
                ->forCode($code)
                ->lockForUpdate()
                ->get(['id']);

            $releaseExists = BiAnnualSiteVisitTemplate::query()
                ->forCode($code)
                ->withCount('questions')
                ->get()
                ->contains(fn (BiAnnualSiteVisitTemplate $template): bool => hash_equals(
                    $sourceHash,
                    (string) data_get($template->settings, 'source.content_sha256', '')
                )
                    && (int) $template->questions_count === $expectedQuestionCount
                );

            if ($releaseExists) {
                return;
            }

            $version = BiAnnualSiteVisitTemplate::nextVersionForCode($code);
            $sameCodeWasDefault = BiAnnualSiteVisitTemplate::query()
                ->forCode($code)
                ->where('is_default', true)
                ->exists();
            $publishedDefaultExists = BiAnnualSiteVisitTemplate::query()
                ->published()
                ->where('is_default', true)
                ->exists();
            $shouldBeDefault = $sameCodeWasDefault || ! $publishedDefaultExists;

            $template = BiAnnualSiteVisitTemplate::create([
                'code' => $code,
                'version' => $version,
                'name' => $definition['title'],
                'description' => 'The official ATTP monitoring and evaluation tool for each participating Think Tank.',
                'instructions' => 'For every verification question, record a 0–3 rating, the observed strength, the weakness or gap, and supporting evidence. Rating 0 (Not Applicable) requires a justification.',
                'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
                'is_default' => false,
                'settings' => [
                    'cadence' => 'biannual',
                    'team_size' => 5,
                    'rating_scale' => $definition['rating_scale'],
                    'source' => [
                        ...$definition['source'],
                        'question_count' => $expectedQuestionCount,
                        'seeded_by' => self::class,
                    ],
                    'aggregation' => [
                        'exclude_not_applicable' => true,
                        'question_to_topic' => 'weighted_average',
                        'topic_to_section' => 'weighted_average',
                        'section_to_overall' => 'weighted_average',
                    ],
                ],
            ]);

            $templates->replaceStructure($template, $definition);

            BiAnnualSiteVisitTemplate::query()
                ->forCode($code)
                ->whereKeyNot($template->id)
                ->published()
                ->update([
                    'status' => BiAnnualSiteVisitTemplate::STATUS_ARCHIVED,
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            if ($shouldBeDefault) {
                BiAnnualSiteVisitTemplate::query()
                    ->whereKeyNot($template->id)
                    ->update(['is_default' => false]);
            }

            $template->update([
                'status' => BiAnnualSiteVisitTemplate::STATUS_PUBLISHED,
                'is_default' => $shouldBeDefault,
                'published_at' => now(),
            ]);
        });
    }
}

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

        // Never rewrite an existing version: administrators may already have
        // customized it. New changes should be introduced as a new version.
        if (BiAnnualSiteVisitTemplate::query()->where('code', $code)->where('version', 1)->exists()) {
            return;
        }

        /** @var array<string, mixed> $definition */
        $definition = require database_path('data/biannual_monitoring_questionnaire.php');

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

        DB::transaction(function () use ($definition, $code, $templates): void {
            $template = BiAnnualSiteVisitTemplate::create([
                'code' => $code,
                'version' => 1,
                'name' => $definition['title'],
                'description' => 'The official ATTP monitoring and evaluation tool for each participating Think Tank.',
                'instructions' => 'For every verification question, record a 0–3 rating, the observed strength, the weakness or gap, and supporting evidence. Rating 0 (Not Applicable) requires a justification.',
                'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
                'is_default' => ! BiAnnualSiteVisitTemplate::query()->where('is_default', true)->exists(),
                'settings' => [
                    'cadence' => 'biannual',
                    'team_size' => 5,
                    'rating_scale' => $definition['rating_scale'],
                    'source' => $definition['source'],
                    'aggregation' => [
                        'exclude_not_applicable' => true,
                        'question_to_topic' => 'weighted_average',
                        'topic_to_section' => 'weighted_average',
                        'section_to_overall' => 'weighted_average',
                    ],
                ],
            ]);

            $templates->replaceStructure($template, $definition);
            $template->update([
                'status' => BiAnnualSiteVisitTemplate::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        });
    }
}

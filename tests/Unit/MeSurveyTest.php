<?php

namespace Tests\Unit;

use App\Support\MeSurvey;
use PHPUnit\Framework\TestCase;

class MeSurveyTest extends TestCase
{
    public function test_it_wraps_legacy_questions_into_a_default_section(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'title' => 'Legacy Survey',
                'questions' => [
                    [
                        'label' => 'Name of participant',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'label' => 'Participation Type',
                        'type' => 'radio',
                        'required' => true,
                        'options' => ['In-person', 'Virtual'],
                    ],
                ],
            ],
        ], 'Fallback Survey');

        $this->assertTrue($survey['enabled']);
        $this->assertSame('Legacy Survey', $survey['title']);
        $this->assertCount(1, $survey['sections']);
        $this->assertSame('Section 1', $survey['sections'][0]['title']);
        $this->assertCount(2, $survey['questions']);
        $this->assertSame('section_1', $survey['sections'][0]['key']);
        $this->assertNotEmpty($survey['questions'][0]['key']);
        $this->assertSame('section_1', $survey['questions'][0]['section_key']);
    }

    public function test_it_resolves_section_visibility_from_previous_answers(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'sections' => [
                    [
                        'key' => 'participant_information',
                        'title' => 'Participant Information',
                        'questions' => [
                            [
                                'key' => 'participation_type',
                                'label' => 'Participation Type',
                                'type' => 'radio',
                                'required' => true,
                                'options' => ['In-person', 'Virtual'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'virtual_experience',
                        'title' => 'Virtual Participation Experience',
                        'visibility' => [
                            'question_key' => 'participation_type',
                            'values' => ['Virtual'],
                        ],
                        'questions' => [
                            [
                                'key' => 'internet_quality',
                                'label' => 'Please rate the quality of internet connection',
                                'type' => 'radio',
                                'required' => true,
                                'options' => ['Poor', 'Average', 'Good'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $visibleForVirtual = MeSurvey::visibleSections($survey, [
            'participation_type' => 'Virtual',
        ]);
        $visibleForInPerson = MeSurvey::visibleSections($survey, [
            'participation_type' => 'In-person',
        ]);

        $this->assertCount(2, $visibleForVirtual);
        $this->assertCount(1, $visibleForInPerson);
        $this->assertSame('virtual_experience', $visibleForVirtual[1]['key']);
    }

    public function test_it_validates_checkbox_selection_limits(): void
    {
        $question = [
            'key' => 'capacity_areas',
            'label' => 'Which capacity areas improved the most?',
            'type' => 'checkbox',
            'required' => true,
            'options' => ['Planning', 'Budgeting', 'QA', 'Communications'],
            'max_selections' => 3,
        ];

        $valid = MeSurvey::validateAnswer($question, ['Planning', 'Budgeting', 'QA'], true);
        $invalid = MeSurvey::validateAnswer($question, ['Planning', 'Budgeting', 'QA', 'Communications'], true);

        $this->assertSame([], $valid['errors']);
        $this->assertNotEmpty($invalid['errors']);
        $this->assertStringContainsString('no more than 3', strtolower($invalid['errors'][0]));
    }
}

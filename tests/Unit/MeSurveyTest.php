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

    public function test_it_humanizes_fully_uppercase_public_survey_titles_for_display(): void
    {
        $this->assertSame(
            'Post Workshop Survey: ATTP Think Tank Consortium Kickoff, Coordination And Capacity Strengthening Workshop',
            MeSurvey::displayTitle('POST WORKSHOP SURVEY: ATTP THINK TANK CONSORTIUM KICKOFF, COORDINATION AND CAPACITY STRENGTHENING WORKSHOP')
        );

        $this->assertSame(
            'Post workshop survey: attp kickoff workshop',
            MeSurvey::displayTitle('Post workshop survey: attp kickoff workshop')
        );
    }

    public function test_it_preserves_estimated_time_labels_and_extended_presentation_settings(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'estimated_time_label' => '10-15 minutes',
                'presentation' => [
                    'show_public_qr' => true,
                    'unified_typography' => true,
                    'compact_title' => true,
                ],
            ],
        ]);

        $this->assertSame('10-15 minutes', $survey['estimated_time_label']);
        $this->assertTrue(data_get($survey, 'presentation.show_public_qr'));
        $this->assertTrue(data_get($survey, 'presentation.unified_typography'));
        $this->assertTrue(data_get($survey, 'presentation.compact_title'));
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

    public function test_it_supports_extended_questionnaire_response_types(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'sections' => [
                    [
                        'title' => 'Response Types',
                        'color' => '#8c4b2f',
                        'questions' => [
                            [
                                'label' => 'Reference link',
                                'type' => 'url',
                                'required' => true,
                            ],
                            [
                                'label' => 'Meeting date and time',
                                'type' => 'datetime',
                                'required' => true,
                            ],
                            [
                                'label' => 'Priority areas',
                                'type' => 'multiselect',
                                'options' => ['Policy', 'Coordination', 'Capacity'],
                            ],
                            [
                                'label' => 'Satisfaction slider',
                                'type' => 'slider',
                                'scale' => [
                                    'min' => 1,
                                    'max' => 10,
                                    'step' => 1,
                                    'labels' => [
                                        '1' => 'Very low',
                                        '5' => 'Moderate',
                                        '10' => 'Very high',
                                    ],
                                ],
                            ],
                            [
                                'label' => 'Supporting document',
                                'type' => 'file',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $types = array_column($survey['questions'], 'type');

        $this->assertContains('url', $types);
        $this->assertContains('datetime', $types);
        $this->assertContains('multiselect', $types);
        $this->assertContains('slider', $types);
        $this->assertContains('file', $types);
        $this->assertSame(1, data_get($survey['questions'][3], 'scale.step'));
        $this->assertSame('Very low', data_get($survey['questions'][3], 'scale.labels.1'));
        $this->assertSame('Very high', data_get($survey['questions'][3], 'scale.labels.10'));
        $this->assertSame('#8C4B2F', data_get($survey['sections'][0], 'color'));
        $this->assertSame('#8C4B2F', data_get($survey['questions'][0], 'section_color'));
    }

    public function test_it_validates_link_slider_and_multi_select_answers(): void
    {
        $linkQuestion = [
            'key' => 'reference_link',
            'label' => 'Reference link',
            'type' => 'url',
            'required' => true,
        ];

        $sliderQuestion = [
            'key' => 'satisfaction_slider',
            'label' => 'Satisfaction slider',
            'type' => 'slider',
            'required' => true,
            'scale' => [
                'min' => 1,
                'max' => 10,
                'step' => 1,
            ],
        ];

        $multiSelectQuestion = [
            'key' => 'priority_areas',
            'label' => 'Priority areas',
            'type' => 'multiselect',
            'required' => true,
            'options' => ['Policy', 'Coordination', 'Capacity'],
            'max_selections' => 2,
        ];

        $validLink = MeSurvey::validateAnswer($linkQuestion, 'https://example.org/report', true);
        $invalidLink = MeSurvey::validateAnswer($linkQuestion, 'not-a-link', true);
        $validSlider = MeSurvey::validateAnswer($sliderQuestion, '6', true);
        $invalidSlider = MeSurvey::validateAnswer($sliderQuestion, '12', true);
        $validMultiSelect = MeSurvey::validateAnswer($multiSelectQuestion, ['Policy', 'Capacity'], true);
        $invalidMultiSelect = MeSurvey::validateAnswer($multiSelectQuestion, ['Policy', 'Coordination', 'Capacity'], true);

        $this->assertSame([], $validLink['errors']);
        $this->assertNotEmpty($invalidLink['errors']);
        $this->assertSame([], $validSlider['errors']);
        $this->assertNotEmpty($invalidSlider['errors']);
        $this->assertSame([], $validMultiSelect['errors']);
        $this->assertNotEmpty($invalidMultiSelect['errors']);
    }

    public function test_it_supports_follow_up_skip_logic_on_questions(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'sections' => [
                    [
                        'title' => 'Overall Assessment',
                        'questions' => [
                            [
                                'key' => 'goal_met',
                                'label' => 'To what extent do you think the workshop met its goal?',
                                'type' => 'radio',
                                'options' => ['Fully achieved', 'Mostly achieved', 'Partially achieved', 'Not achieved'],
                            ],
                            [
                                'key' => 'goal_met_follow_up',
                                'label' => 'Please briefly explain why it did not fully meet its goal',
                                'type' => 'textarea',
                                'visibility' => [
                                    'question_key' => 'goal_met',
                                    'values' => ['Partially achieved', 'Not achieved'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $followUpQuestion = $survey['questions'][1];

        $this->assertTrue(MeSurvey::isQuestionVisible($followUpQuestion, [
            'goal_met' => 'Partially achieved',
        ]));
        $this->assertTrue(MeSurvey::isQuestionVisible($followUpQuestion, [
            'goal_met' => 'Not achieved',
        ]));
        $this->assertFalse(MeSurvey::isQuestionVisible($followUpQuestion, [
            'goal_met' => 'Fully achieved',
        ]));
    }

    public function test_it_reaches_special_follow_up_questions_and_sections_outside_normal_flow(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'sections' => [
                    [
                        'key' => 'overall_assessment',
                        'title' => 'Overall Assessment',
                        'questions' => [
                            [
                                'key' => 'goal_met',
                                'label' => 'To what extent did the workshop meet its goal?',
                                'type' => 'radio',
                                'options' => ['Fully achieved', 'Partially achieved', 'Not achieved'],
                                'route' => [
                                    'target_type' => 'question',
                                    'target_key' => 'goal_reason',
                                    'values' => ['Partially achieved', 'Not achieved'],
                                ],
                            ],
                            [
                                'key' => 'participation_type',
                                'label' => 'Participation type',
                                'type' => 'radio',
                                'options' => ['In-person', 'Virtual'],
                                'route' => [
                                    'target_type' => 'section',
                                    'target_key' => 'virtual_experience',
                                    'values' => ['Virtual'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'follow_up_pool',
                        'title' => 'Follow-up pool',
                        'questions' => [
                            [
                                'key' => 'goal_reason',
                                'label' => 'Why was the goal not fully met?',
                                'type' => 'textarea',
                            ],
                        ],
                    ],
                    [
                        'key' => 'virtual_experience',
                        'title' => 'Virtual Experience',
                        'questions' => [
                            [
                                'key' => 'internet_quality',
                                'label' => 'Rate the internet quality',
                                'type' => 'radio',
                                'options' => ['Poor', 'Average', 'Good'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $normalReachable = collect(MeSurvey::reachableQuestions($survey, [
            'goal_met' => 'Fully achieved',
            'participation_type' => 'In-person',
        ]))->pluck('key')->all();

        $branchedReachable = collect(MeSurvey::reachableQuestions($survey, [
            'goal_met' => 'Partially achieved',
            'participation_type' => 'Virtual',
        ]))->pluck('key')->all();

        $goalReasonQuestion = collect($survey['questions'])->firstWhere('key', 'goal_reason');
        $virtualSection = collect($survey['sections'])->firstWhere('key', 'virtual_experience');

        $this->assertTrue(MeSurvey::isSpecialQuestion($goalReasonQuestion));
        $this->assertTrue(MeSurvey::isSpecialSection($virtualSection));
        $this->assertSame(['goal_met', 'participation_type'], $normalReachable);
        $this->assertContains('goal_reason', $branchedReachable);
        $this->assertContains('internet_quality', $branchedReachable);
    }

    public function test_it_maps_legacy_min_and_max_labels_into_scale_point_labels(): void
    {
        $survey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'sections' => [
                    [
                        'title' => 'Legacy scale labels',
                        'questions' => [
                            [
                                'label' => 'Overall workshop rating',
                                'type' => 'scale',
                                'scale' => [
                                    'min' => 1,
                                    'max' => 5,
                                    'min_label' => 'Poor',
                                    'max_label' => 'Excellent',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $question = $survey['questions'][0];

        $this->assertSame('Poor', data_get($question, 'scale.labels.1'));
        $this->assertSame('Excellent', data_get($question, 'scale.labels.5'));
    }
}

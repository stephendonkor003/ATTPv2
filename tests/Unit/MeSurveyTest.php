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
                                    'min_label' => 'Low',
                                    'max_label' => 'High',
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
}

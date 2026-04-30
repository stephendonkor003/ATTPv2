<?php

namespace Database\Seeders;

use App\Models\IndicatorMethodology;
use App\Models\User;
use App\Support\MeSurvey;
use Illuminate\Database\Seeder;

class AttpWorkshopPostSurveySeeder extends Seeder
{
    protected const METHODOLOGY_NAME = 'ATTP Post Workshop Survey';

    public function run(): void
    {
        $creator = User::where('email', 'amodonlimited@gmail.com')->value('id')
            ?? User::query()->value('id');

        $title = 'Post Workshop Survey: ATTP Think Tank Consortium Kickoff, Coordination and Capacity Strengthening Workshop';
        $intro = 'Thank you for participating in the ATTP Workshop. Your feedback is essential in helping us improve future coordination, strengthen capacity, and enhance the overall implementation of ATTP activities. This survey aims to gather your insights on the workshop\'s content, delivery, and relevance. It will take approximately 10-15 minutes to complete, and your responses will be treated in confidence.';

        $sections = [
            $this->section('participant_information', 'Section 1: Participant Information', [
                $this->textQuestion('participant_affiliation', 'Think tank / Directorate / Consortium', true),
                $this->textQuestion('participant_role', 'Role / position', true),
                $this->radioQuestion('participation_type', 'Participation Type', [
                    'In-person',
                    'Virtual',
                ], true),
            ]),
            $this->section('overall_workshop_assessment', 'Section 2: Overall Workshop Assessment', [
                $this->radioQuestion(
                    'overall_workshop_rating',
                    'Overall, how would you rate the workshop?',
                    [
                        'Poor',
                        'Fair',
                        'Good',
                        'Very Good',
                        'Excellent',
                    ],
                    true
                ),
                $this->radioQuestion('workshop_goal_achievement', 'To what extent do you think the workshop met its overall goal?', [
                    'Fully achieved',
                    'Mostly achieved',
                    'Partially achieved',
                    'Not achieved',
                ], true, $this->routeToQuestion('workshop_goal_follow_up', [
                    'Partially achieved',
                    'Not achieved',
                ])),
                $this->textareaQuestion(
                    'workshop_goal_follow_up',
                    'Please briefly explain why you think the workshop did not fully meet its goal',
                    true,
                    [
                        'flow_type' => 'special',
                    ]
                ),
                $this->radioQuestion('workshop_role_relevance', 'How relevant was the workshop to your role?', [
                    'Highly relevant',
                    'Moderately relevant',
                    'Slightly relevant',
                    'Not relevant',
                ], true, $this->routeToQuestion('workshop_role_relevance_follow_up', [
                    'Not relevant',
                ])),
                $this->textareaQuestion(
                    'workshop_role_relevance_follow_up',
                    'Please explain why the workshop was not relevant to your role',
                    true,
                    [
                        'flow_type' => 'special',
                    ]
                ),
            ]),
            $this->section('specific_objectives', 'Section 3: Achievement of Specific Objectives', [
                $this->matrixQuestion(
                    'specific_objectives_achievement',
                    'Please rate the extent to which the workshop achieved the following:',
                    [
                        'Harmonized research among Consortia',
                        'Harmonized research between Consortia and the AUC',
                        'Strengthened technical capacity (modelling, M&E/MEDAL, budgeting, legal, etc.)',
                        'Clarified the 2026/27 ATTP operating model (governance, QA, reporting)',
                        'Improved understanding of available support (AUC, ACBF, etc.)',
                        'Improved understanding of REC / Member State domestication pathways',
                        'Improved clarity of consortium work plans (2026-2027)',
                        'Strengthened understanding of PPA compliance',
                        'Improved understanding of ATTP sustainability mechanisms (Endowment fund discussion)',
                    ],
                    ['Fully', 'Mostly', 'Partially', 'Not at all'],
                    true
                ),
            ]),
            $this->section('workshop_outcomes_validation', 'Section 4: Workshop Outcomes Validation', [
                $this->matrixQuestion(
                    'workshop_outcomes_validation',
                    'Indicate your level of agreement with the following statements:',
                    [
                        'A shared understanding of goals and priorities was established',
                        'ATTP implementation requirements and risks are clear',
                        'Collaboration opportunities are clearer',
                        'A practical roadmap to December 2027 was developed',
                        'Key research thematic gaps were identified',
                    ],
                    [
                        '1 - Strongly Disagree',
                        '2 - Disagree',
                        '3 - Neutral',
                        '4 - Agree',
                        '5 - Strongly Agree',
                    ],
                    true
                ),
            ]),
            $this->section('capacity_strengthening_and_learning', 'Section 5: Capacity Strengthening and Learning', [
                $this->checkboxQuestion(
                    'capacity_areas_improved',
                    'Which capacity areas improved the most?',
                    [
                        'Modelling and policy analysis',
                        'Budgeting and financial alignment',
                        'PPA compliance and fiduciary readiness',
                        'Legal frameworks and compliance',
                        'Strategic communication',
                        'Planning and sequencing',
                        'Partnership engagement',
                        'QA and knowledge management',
                    ],
                    true,
                    3,
                    'Select up to 3 options.'
                ),
                $this->textareaQuestion('capacity_areas_support_needed', 'Which areas require further support?'),
                $this->radioQuestion('cross_consortia_exchange_usefulness', 'How useful were the cross-consortia exchanges?', [
                    'Very useful',
                    'Useful',
                    'Somewhat useful',
                    'Not very useful',
                    'Not useful at all',
                ], true, $this->routeToQuestion('cross_consortia_exchange_follow_up', [
                    'Not very useful',
                    'Not useful at all',
                ])),
                $this->textareaQuestion(
                    'cross_consortia_exchange_follow_up',
                    'Please explain why the peer learning or cross-consortia exchange was not useful',
                    true,
                    [
                        'flow_type' => 'special',
                    ]
                ),
            ]),
            $this->section('workshop_design_and_facilitation', 'Section 6: Workshop Design and Facilitation', [
                $this->matrixQuestion(
                    'workshop_design_rating',
                    'Rate the following aspects of the workshop:',
                    [
                        'Clarity of presentations',
                        'Facilitation quality',
                        'Balance of presentations and discussions',
                        'Opportunities for participation',
                        'Structure and flow',
                    ],
                    ['Excellent', 'Good', 'Fair', 'Poor'],
                    true
                ),
                $this->radioQuestion('research_harmonization_effectiveness', 'Was the research harmonization process effective?', [
                    'Very effective',
                    'Effective',
                    'Somewhat effective',
                    'Not effective',
                    'Not applicable',
                ], true, $this->routeToQuestion('research_harmonization_challenges', [
                    'Not effective',
                ])),
                $this->textareaQuestion(
                    'research_harmonization_challenges',
                    'What challenges did you experience with the harmonization process?',
                    true,
                    [
                        'flow_type' => 'special',
                    ]
                ),
                $this->textareaQuestion('workshop_what_worked_well', 'What worked particularly well?'),
                $this->textareaQuestion('workshop_improvements', 'What could be improved?'),
            ]),
            $this->section(
                'virtual_participation_experience',
                'Section 7: Virtual Participation Experience',
                [
                    $this->radioQuestion('virtual_internet_quality', 'Please rate the quality of internet connection during sessions:', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->radioQuestion('virtual_audio_video_quality', 'Please rate the audio and video quality of the sessions:', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->radioQuestion('virtual_participation_ease', 'Please rate the ease of participation (asking questions, engagement):', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->textareaQuestion('virtual_technical_challenges', 'What technical challenges did you face?'),
                ],
                [
                    'description' => 'Visible to virtual attendees only.',
                    'visibility' => [
                        'question_key' => 'participation_type',
                        'values' => ['Virtual'],
                    ],
                ]
            ),
            $this->section(
                'in_person_experience',
                'Section 8: In-Person Experience',
                [
                    $this->radioQuestion('venue_comfort', 'Venue comfort (space, seating, environment):', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->radioQuestion('audio_visual_setup_quality', 'Quality of audio / visual setup:', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->radioQuestion('logistics_organization', 'Organization of logistics (registration, coordination):', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                    $this->radioQuestion('catering_refreshments', 'Catering and refreshments:', [
                        'Very poor',
                        'Poor',
                        'Average',
                        'Good',
                        'Excellent',
                    ], true),
                ],
                [
                    'description' => 'Visible to in-person attendees only.',
                    'visibility' => [
                        'question_key' => 'participation_type',
                        'values' => ['In-person'],
                    ],
                ]
            ),
            $this->section('engagement_and_experience', 'Section 9: Engagement and Experience', [
                $this->radioQuestion('active_engagement_rating', 'How would you rate your level of active engagement during the workshop?', [
                    'Very poor',
                    'Poor',
                    'Average',
                    'Good',
                    'Excellent',
                ], true),
                $this->textareaQuestion('most_positive_aspect', 'What was the most positive aspect of the workshop?'),
                $this->textareaQuestion('future_workshop_improvement', 'What should be improved in future workshops?'),
            ]),
            $this->section('final_reflections', 'Section 10: Final Reflections', [
                $this->textareaQuestion('secretariat_priority_action', 'What is the single most important action the Secretariat should prioritise before the next consortium reporting milestone?'),
                $this->textareaQuestion('most_valuable_takeaway', 'What was your most valuable takeaway?'),
                $this->textareaQuestion('additional_comments', 'Any additional comments or recommendations?'),
            ]),
        ];

        $normalizedSurvey = MeSurvey::surveyConfigFromMetadata([
            'survey' => [
                'enabled' => true,
                'title' => $title,
                'intro' => $intro,
                'estimated_minutes' => 15,
                'estimated_time_label' => '10-15 minutes',
                'respondent' => [
                    'show_notes' => false,
                    'fields' => [
                        'name' => [
                            'label' => 'Name',
                            'placeholder' => 'Enter your full name',
                            'required' => true,
                        ],
                        'email' => [
                            'label' => 'Email',
                            'placeholder' => 'name@example.org',
                            'required' => true,
                        ],
                        'phone' => [
                            'label' => 'Phone number',
                            'placeholder' => 'Enter a phone contact',
                            'required' => true,
                        ],
                        'organization' => [
                            'label' => 'Organization',
                            'placeholder' => 'Enter your institution or team',
                            'required' => true,
                        ],
                    ],
                ],
                'presentation' => [
                    'show_header_meta' => false,
                    'show_briefing_panel' => false,
                    'show_sidebar_guide' => false,
                    'show_side_navigation' => false,
                    'show_step_navigation' => false,
                    'show_intro_guidance' => false,
                    'show_progress_tracker' => false,
                    'show_intro_step_summary' => false,
                    'compact_title' => true,
                    'show_public_qr' => true,
                    'unified_typography' => true,
                ],
                'sections' => $sections,
                'updated_at' => now()->toDateTimeString(),
            ],
        ], $title);

        IndicatorMethodology::updateOrCreate(
            ['name' => self::METHODOLOGY_NAME],
            [
                'description' => 'Post-workshop feedback questionnaire for the ATTP Think Tank Consortium Kickoff, Coordination and Capacity Strengthening Workshop.',
                'steps' => null,
                'metadata' => [
                    'survey' => [
                        'enabled' => $normalizedSurvey['enabled'],
                        'title' => $normalizedSurvey['title'],
                        'intro' => $normalizedSurvey['intro'],
                        'estimated_minutes' => $normalizedSurvey['estimated_minutes'],
                        'estimated_time_label' => $normalizedSurvey['estimated_time_label'],
                        'respondent' => $normalizedSurvey['respondent'],
                        'presentation' => $normalizedSurvey['presentation'],
                        'sections' => $normalizedSurvey['sections'],
                        'questions' => $normalizedSurvey['questions'],
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
                'is_active' => true,
                'created_by' => $creator,
                'updated_by' => $creator,
            ]
        );
    }

    protected function section(string $key, string $title, array $questions, array $attributes = []): array
    {
        return array_merge([
            'key' => $key,
            'title' => $title,
            'description' => '',
            'questions' => $questions,
        ], $attributes);
    }

    protected function textQuestion(string $key, string $label, bool $required = false, array $attributes = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'text',
            'required' => $required,
        ], $attributes);
    }

    protected function textareaQuestion(string $key, string $label, bool $required = false, array $attributes = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'textarea',
            'required' => $required,
        ], $attributes);
    }

    protected function radioQuestion(string $key, string $label, array $options, bool $required = false, array $attributes = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'radio',
            'required' => $required,
            'options' => $options,
        ], $attributes);
    }

    protected function routeToQuestion(string $targetKey, array $values): array
    {
        return [
            'route' => [
                'target_type' => 'question',
                'target_key' => $targetKey,
                'values' => $values,
            ],
        ];
    }

    protected function checkboxQuestion(
        string $key,
        string $label,
        array $options,
        bool $required = false,
        ?int $maxSelections = null,
        string $hint = '',
        array $attributes = []
    ): array {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'checkbox',
            'required' => $required,
            'options' => $options,
            'max_selections' => $maxSelections,
            'hint' => $hint,
        ], $attributes);
    }

    protected function scaleQuestion(
        string $key,
        string $label,
        int $min = 1,
        int $max = 5,
        bool $required = false,
        array $labels = [],
        array $attributes = []
    ): array {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'scale',
            'required' => $required,
            'scale' => [
                'min' => $min,
                'max' => $max,
                'step' => 1,
                'labels' => $labels,
            ],
        ], $attributes);
    }

    protected function matrixQuestion(
        string $key,
        string $label,
        array $rows,
        array $columns,
        bool $required = false,
        array $attributes = []
    ): array {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'matrix',
            'required' => $required,
            'rows' => $rows,
            'columns' => $columns,
        ], $attributes);
    }
}

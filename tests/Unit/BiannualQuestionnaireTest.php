<?php

use App\Support\BiannualQuestionnaire;

function biannualSupportQuestionnaireFixture(): array
{
    return [
        'key' => 'ATTP Monitoring Questionnaire',
        'title' => 'ATTP Bi-Annual Monitoring Questionnaire',
        'version' => 3,
        'sections' => [
            [
                'key' => 'governance',
                'title' => 'Governance and institutional capacity',
                'weight' => 2,
                'topics' => [
                    [
                        'key' => 'leadership',
                        'title' => 'Leadership',
                        'weight' => 1,
                        'questions' => [
                            [
                                'key' => 'leadership_note',
                                'type' => 'information',
                                'content' => 'Review the evidence before scoring.',
                            ],
                            [
                                'key' => 'leadership_applicable',
                                'label' => 'Is this assessment applicable?',
                                'type' => 'yes_no_na',
                                'required' => true,
                            ],
                            [
                                'key' => 'leadership_score',
                                'label' => 'Leadership performance',
                                'type' => 'scored_assessment',
                                'weight' => 2,
                                'max_score' => 4,
                                'options' => [
                                    ['value' => 'not_applicable', 'label' => 'N/A', 'score' => 0],
                                    ['value' => 'partly_met', 'label' => 'Partly met', 'score' => 2],
                                    ['value' => 'fully_met', 'label' => 'Fully met', 'score' => 4],
                                ],
                                'visibility' => [
                                    'question_key' => 'leadership_applicable',
                                    'operator' => 'equals',
                                    'value' => 'yes',
                                ],
                            ],
                            [
                                'key' => 'leadership_comment',
                                'label' => 'Explain the leadership score',
                                'type' => 'long_text',
                                'required' => true,
                                'depends_on' => 'leadership_applicable',
                                'show_if' => 'yes',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'delivery',
                'title' => 'Programme delivery',
                'weight' => 1,
                'questions' => [
                    [
                        'label' => 'Delivery quality',
                        'type' => 'score',
                        'max_score' => 5,
                    ],
                    [
                        'label' => 'Delivery quality',
                        'type' => 'score',
                        'max_score' => 5,
                    ],
                ],
            ],
        ],
    ];
}

it('normalizes a flexible hierarchy into stable uniquely keyed snapshot data', function () {
    $questionnaire = new BiannualQuestionnaire;
    $template = biannualSupportQuestionnaireFixture();
    $capturedAt = new DateTimeImmutable('2026-07-26 09:30:00+03:00');

    $snapshot = $questionnaire->buildSnapshot($template, $capturedAt);
    $questions = $questionnaire->flattenQuestions($snapshot);

    expect($snapshot)
        ->toHaveKeys(['snapshot_version', 'captured_at', 'fingerprint', 'counts', 'template'])
        ->and($snapshot['snapshot_version'])->toBe(1)
        ->and($snapshot['captured_at'])->toBe('2026-07-26T06:30:00+00:00')
        ->and($snapshot['counts'])->toBe([
            'sections' => 2,
            'topics' => 2,
            'questions' => 6,
            'scored_questions' => 3,
        ])
        ->and($snapshot['template']['key'])->toBe('attp_monitoring_questionnaire')
        ->and($snapshot['template']['version'])->toBe(3)
        ->and($snapshot['template']['sections'][1]['topics'][0]['is_implicit'])->toBeTrue()
        ->and(array_column($questions, 'key'))->toContain(
            'leadership_score',
            'delivery_quality',
            'delivery_quality_2'
        )
        ->and($questions[4]['type'])->toBe('scored_assessment');

    $notApplicableScore = $questionnaire->validateAnswer($questions[2], 'N/A');
    expect($notApplicableScore)->toBe([
        'value' => BiannualQuestionnaire::NOT_APPLICABLE,
        'valid' => true,
        'errors' => [],
    ]);

    $fingerprint = $snapshot['fingerprint'];
    $template['title'] = 'Changed after the visit started';
    $template['sections'][0]['topics'][0]['questions'][1]['label'] = 'Changed question';

    expect($snapshot['template']['title'])->toBe('ATTP Bi-Annual Monitoring Questionnaire')
        ->and($snapshot['template']['sections'][0]['topics'][0]['questions'][1]['label'])
        ->toBe('Is this assessment applicable?')
        ->and($questionnaire->fingerprint($snapshot))->toBe($fingerprint)
        ->and($questionnaire->buildSnapshot(
            biannualSupportQuestionnaireFixture(),
            new DateTimeImmutable('2027-01-01T00:00:00Z')
        )['fingerprint'])->toBe($fingerprint);
});

it('accepts the imported workbook schema and model snapshot field names', function () {
    $questionnaire = new BiannualQuestionnaire;
    $importedTemplate = require dirname(__DIR__, 2).'/database/data/biannual_monitoring_questionnaire.php';
    $snapshot = $questionnaire->buildSnapshot($importedTemplate);
    $questions = $questionnaire->flattenQuestions($snapshot);
    $expectedQuestionCount = (int) $importedTemplate['counts']['questions'];

    expect($expectedQuestionCount)->toBe(146);

    expect($snapshot['counts'])->toBe([
        'sections' => 7,
        'topics' => 30,
        'questions' => $expectedQuestionCount,
        'scored_questions' => $expectedQuestionCount,
    ])
        ->and($questions[0]['key'])->toBe('question-001')
        ->and($questions[0]['label'])->toBe('Is the consortium structure consistent with the PPA?')
        ->and($questions[0]['type'])->toBe('scored_assessment')
        ->and($questions[0]['score'])->toBe(['min' => 0.0, 'max' => 3.0])
        ->and($questions[0]['options'])->toHaveCount(4)
        ->and($questions[0]['response_schema']['fields'])->toHaveKeys([
            'strength',
            'weakness',
            'rating_code',
            'ranking_label',
        ]);

    expect($questionnaire->validateAnswer($questions[0], 'Not Applicable'))->toBe([
        'value' => BiannualQuestionnaire::NOT_APPLICABLE,
        'valid' => true,
        'errors' => [],
    ])
        ->and($questionnaire->validateAnswer($questions[0], 'Strong')['value'])->toBe(3);

    $modelSnapshot = [
        'template' => [
            'code' => 'persisted-template',
            'name' => 'Persisted template',
            'version' => 2,
        ],
        'sections' => [[
            'section_key' => 'section-01',
            'title' => 'Persisted section',
            'topics' => [[
                'topic_key' => 'topic-01',
                'title' => 'Persisted topic',
                'questions' => [[
                    'question_key' => 'question-001',
                    'prompt' => 'Persisted question',
                    'question_type' => 'scored_finding',
                    'is_required' => true,
                    'maximum_score' => 3,
                    'score_weight' => 2,
                ]],
            ]],
        ]],
    ];
    $normalizedModelSnapshot = $questionnaire->buildSnapshot($modelSnapshot);

    expect($normalizedModelSnapshot['counts']['questions'])->toBe(1)
        ->and($normalizedModelSnapshot['template']['sections'][0]['key'])->toBe('section-01')
        ->and($normalizedModelSnapshot['template']['sections'][0]['topics'][0]['key'])->toBe('topic-01')
        ->and($normalizedModelSnapshot['template']['sections'][0]['topics'][0]['questions'][0])
        ->toMatchArray([
            'key' => 'question-001',
            'label' => 'Persisted question',
            'type' => 'scored_assessment',
            'required' => true,
            'weight' => 2.0,
            'score' => ['min' => 0.0, 'max' => 3.0],
        ]);
});

it('normalizes and validates all supported response types without an HTTP request', function () {
    $questionnaire = new BiannualQuestionnaire;
    $snapshot = $questionnaire->buildSnapshot([
        'title' => 'Typed questionnaire',
        'questions' => [
            ['key' => 'summary', 'label' => 'Summary', 'type' => 'short_text', 'required' => true, 'rules' => ['min_length' => 3]],
            ['key' => 'detail', 'label' => 'Detail', 'type' => 'long_text', 'rules' => ['max_length' => 30]],
            ['key' => 'status', 'label' => 'Status', 'type' => 'single_choice', 'required' => true, 'options' => [
                ['value' => 'on_track', 'label' => 'On track'],
                ['value' => 'delayed', 'label' => 'Delayed'],
            ]],
            ['key' => 'risks', 'label' => 'Risks', 'type' => 'multiple_choice', 'options' => ['Finance', 'Staffing', 'Schedule'], 'rules' => ['min_selections' => 1, 'max_selections' => 2]],
            ['key' => 'verified', 'label' => 'Verified?', 'type' => 'yes_no_na', 'required' => true],
            ['key' => 'beneficiaries', 'label' => 'Beneficiaries', 'type' => 'number', 'rules' => ['min' => 0]],
            ['key' => 'progress', 'label' => 'Progress', 'type' => 'percentage'],
            ['key' => 'visit_date', 'label' => 'Visit date', 'type' => 'date', 'required' => true],
            ['key' => 'evidence', 'label' => 'Evidence', 'type' => 'evidence', 'required' => true, 'rules' => [
                'min_files' => 1,
                'allowed_extensions' => ['pdf'],
                'max_file_size_mb' => 2,
            ]],
            ['key' => 'guidance', 'type' => 'information', 'content' => 'Internal instructions only.'],
            [
                'key' => 'delay_reason',
                'label' => 'Reason for delay',
                'type' => 'long_text',
                'required' => true,
                'visibility' => [
                    'question_key' => 'status',
                    'operator' => 'equals',
                    'value' => 'delayed',
                ],
            ],
        ],
    ]);

    $valid = $questionnaire->validateAnswers($snapshot, [
        'summary' => '  Good progress  ',
        'detail' => 'Implementation remains stable.',
        'status' => 'On track',
        'risks' => ['Finance', 'Finance', 'Staffing'],
        'verified' => true,
        'beneficiaries' => '24',
        'progress' => '72.5',
        'visit_date' => '2026-07-26',
        'evidence' => [
            'path' => 'biannual/evidence/report.pdf',
            'original_name' => 'report.pdf',
            'size' => 1024,
        ],
        'delay_reason' => 'This stale hidden answer must not be persisted.',
    ]);

    expect($valid['valid'])->toBeTrue()
        ->and($valid['answers']['summary'])->toBe('Good progress')
        ->and($valid['answers']['status'])->toBe('on_track')
        ->and($valid['answers']['risks'])->toBe(['Finance', 'Staffing'])
        ->and($valid['answers']['verified'])->toBe('yes')
        ->and($valid['answers']['beneficiaries'])->toBe(24)
        ->and($valid['answers']['progress'])->toBe(72.5)
        ->and($valid['answers']['evidence'][0]['original_name'])->toBe('report.pdf')
        ->and($valid['answers'])->not->toHaveKey('delay_reason')
        ->and($valid['hidden_question_keys'])->toContain('delay_reason');

    $invalid = $questionnaire->validateAnswers($snapshot, [
        'summary' => 'No',
        'status' => 'Delayed',
        'risks' => ['Unknown risk', 'Finance', 'Staffing'],
        'verified' => 'perhaps',
        'beneficiaries' => '-2',
        'progress' => '125',
        'visit_date' => '2026-02-31',
        'evidence' => [
            'original_name' => 'unsafe.exe',
            'size' => 3 * 1024 * 1024,
        ],
    ]);

    expect($invalid['valid'])->toBeFalse()
        ->and($invalid['errors'])->toHaveKeys([
            'summary',
            'risks',
            'verified',
            'beneficiaries',
            'progress',
            'visit_date',
            'evidence',
            'delay_reason',
        ])
        ->and($invalid['errors']['delay_reason'])->toContain('This question is required.');

    $draft = $questionnaire->validateAnswers($snapshot, [], false);
    expect($draft['errors'])->not->toHaveKey('summary')
        ->and($draft['errors'])->not->toHaveKey('visit_date');
});

it('calculates visibility-aware required completion statistics', function () {
    $questionnaire = new BiannualQuestionnaire;
    $snapshot = $questionnaire->buildSnapshot([
        'title' => 'Completion questionnaire',
        'questions' => [
            ['key' => 'applicable', 'label' => 'Applicable?', 'type' => 'yes_no_na', 'required' => true],
            [
                'key' => 'explanation',
                'label' => 'Explanation',
                'type' => 'long_text',
                'required' => true,
                'depends_on' => 'applicable',
                'show_if' => 'yes',
            ],
            ['key' => 'optional_note', 'label' => 'Optional note', 'type' => 'short_text'],
            ['key' => 'information', 'type' => 'information', 'content' => 'Read before submitting.'],
        ],
    ]);

    $notApplicable = $questionnaire->completionStats($snapshot, [
        'applicable' => 'no',
    ]);

    expect($notApplicable)
        ->toMatchArray([
            'question_count' => 4,
            'visible_question_count' => 3,
            'answerable_question_count' => 2,
            'answered_question_count' => 1,
            'required_question_count' => 1,
            'required_answered_count' => 1,
            'completion_percentage' => 50.0,
            'required_completion_percentage' => 100.0,
            'is_complete' => true,
        ])
        ->and($notApplicable['required_missing_keys'])->toBe([]);

    $missingExplanation = $questionnaire->completionStats($snapshot, [
        'applicable' => 'yes',
    ]);

    expect($missingExplanation)
        ->toMatchArray([
            'visible_question_count' => 4,
            'answerable_question_count' => 3,
            'answered_question_count' => 1,
            'required_question_count' => 2,
            'required_answered_count' => 1,
            'completion_percentage' => 33.33,
            'required_completion_percentage' => 50.0,
            'is_complete' => false,
        ])
        ->and($missingExplanation['required_missing_keys'])->toBe(['explanation']);
});

it('aggregates weighted scores by topic section and overall while excluding zero and N/A', function () {
    $questionnaire = new BiannualQuestionnaire;
    $snapshot = $questionnaire->buildSnapshot([
        'title' => 'Weighted monitoring assessment',
        'sections' => [
            [
                'key' => 'institution',
                'title' => 'Institution',
                'weight' => 2,
                'topics' => [
                    [
                        'key' => 'governance',
                        'title' => 'Governance',
                        'weight' => 1,
                        'questions' => [
                            ['key' => 'governance_score', 'label' => 'Governance', 'type' => 'scored_assessment', 'max_score' => 4, 'weight' => 2],
                            ['key' => 'policy_score', 'label' => 'Policy', 'type' => 'scored_assessment', 'max_score' => 4, 'weight' => 1],
                        ],
                    ],
                    [
                        'key' => 'systems',
                        'title' => 'Systems',
                        'weight' => 3,
                        'questions' => [
                            ['key' => 'systems_score', 'label' => 'Systems', 'type' => 'scored_assessment', 'max_score' => 5],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'delivery',
                'title' => 'Delivery',
                'weight' => 1,
                'topics' => [
                    [
                        'key' => 'outputs',
                        'title' => 'Outputs',
                        'questions' => [
                            ['key' => 'outputs_score', 'label' => 'Outputs', 'type' => 'scored_assessment', 'max_score' => 4],
                            ['key' => 'outcomes_score', 'label' => 'Outcomes', 'type' => 'scored_assessment', 'max_score' => 4],
                            [
                                'key' => 'hidden_score',
                                'label' => 'Hidden follow-up',
                                'type' => 'scored_assessment',
                                'max_score' => 4,
                                'visibility' => [
                                    'question_key' => 'outputs_score',
                                    'operator' => 'greater_than',
                                    'value' => 4,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $aggregation = $questionnaire->aggregateScores($snapshot, [
        'governance_score' => 4,
        'policy_score' => 0,
        'systems_score' => 2.5,
        'outputs_score' => 1,
        'outcomes_score' => 'N/A',
        'hidden_score' => 4,
    ]);

    expect($aggregation['sections'][0])
        ->toMatchArray([
            'key' => 'institution',
            'percentage' => 62.5,
            'applicable_question_count' => 2,
            'excluded_question_count' => 1,
        ])
        ->and($aggregation['sections'][0]['topics'][0]['percentage'])->toBe(100.0)
        ->and($aggregation['sections'][0]['topics'][0]['excluded_questions'][0])
        ->toBe(['key' => 'policy_score', 'reason' => 'not_applicable'])
        ->and($aggregation['sections'][0]['topics'][1]['percentage'])->toBe(50.0)
        ->and($aggregation['sections'][1]['percentage'])->toBe(25.0)
        ->and($aggregation['overall'])->toMatchArray([
            'percentage' => 50.0,
            'applicable_section_count' => 2,
            'applicable_question_count' => 3,
            'excluded_question_count' => 2,
            'weight' => 3.0,
        ]);
});

it('honours configurable score ranges, risk direction, and non-scored ratings', function () {
    $questionnaire = new BiannualQuestionnaire;
    $snapshot = $questionnaire->buildSnapshot([
        'title' => 'Configurable scoring',
        'questions' => [
            [
                'key' => 'performance',
                'label' => 'Performance',
                'type' => 'scored_assessment',
                'max_score' => 4,
                'allows_na' => false,
                'scoring_direction' => 'positive',
            ],
            [
                'key' => 'risk',
                'label' => 'Risk',
                'type' => 'scored_assessment',
                'min_score' => 1,
                'max_score' => 4,
                'allows_na' => false,
                'scoring_direction' => 'negative',
            ],
            [
                'key' => 'context_only',
                'label' => 'Context rating',
                'type' => 'scored_assessment',
                'max_score' => 4,
                'scoring_direction' => 'none',
            ],
        ],
    ]);

    $questions = $questionnaire->flattenQuestions($snapshot);
    $aggregation = $questionnaire->aggregateScores($snapshot, [
        'performance' => 4,
        'risk' => 1,
        'context_only' => 4,
    ]);

    expect($questionnaire->validateAnswer($questions[0], 0)['valid'])->toBeTrue()
        ->and($questions[1]['scoring_direction'])->toBe('negative')
        ->and($questions[1]['score'])->toBe(['min' => 1.0, 'max' => 4.0])
        ->and($questions[2]['weight'])->toBe(0.0)
        ->and($aggregation['overall']['percentage'])->toBe(100.0)
        ->and($aggregation['overall']['applicable_question_count'])->toBe(2)
        ->and($aggregation['overall']['excluded_question_count'])->toBe(1)
        ->and($aggregation['sections'][0]['topics'][0]['questions'][1]['percentage'])->toBe(100.0)
        ->and($aggregation['sections'][0]['topics'][0]['excluded_questions'][0])
        ->toBe(['key' => 'context_only', 'reason' => 'zero_weight']);
});

it('distinguishes an applicable zero score from an explicit N/A option', function () {
    $questionnaire = new BiannualQuestionnaire;
    $snapshot = $questionnaire->buildSnapshot([
        'title' => 'Zero score semantics',
        'questions' => [[
            'key' => 'risk_score',
            'label' => 'Risk score',
            'type' => 'scored_assessment',
            'allows_na' => true,
            'min_score' => 0,
            'max_score' => 5,
            'options' => [
                [
                    'value' => '001',
                    'score' => 0,
                    'label' => 'No risk',
                    'description' => 'No material risk was identified.',
                ],
                ['value' => 'critical', 'score' => 5, 'label' => 'Critical'],
                ['value' => 'na', 'score' => 0, 'label' => 'Not Applicable', 'is_not_applicable' => true],
            ],
        ]],
    ]);
    $question = $questionnaire->flattenQuestions($snapshot)[0];

    expect($question['options'][0])->toMatchArray([
        'value' => '001',
        'description' => 'No material risk was identified.',
    ])
        ->and($questionnaire->validateAnswer($question, '001'))->toMatchArray([
            'value' => 0,
            'valid' => true,
        ])
        ->and($questionnaire->validateAnswer($question, 'na'))->toMatchArray([
            'value' => BiannualQuestionnaire::NOT_APPLICABLE,
            'valid' => true,
        ])
        ->and($questionnaire->aggregateScores($snapshot, ['risk_score' => '001'])['overall'])
        ->toMatchArray([
            'percentage' => 0.0,
            'applicable_question_count' => 1,
            'excluded_question_count' => 0,
        ])
        ->and($questionnaire->aggregateScores($snapshot, ['risk_score' => 'na'])['overall'])
        ->toMatchArray([
            'percentage' => null,
            'applicable_question_count' => 0,
            'excluded_question_count' => 1,
        ]);
});

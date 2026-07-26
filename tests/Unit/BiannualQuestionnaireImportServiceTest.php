<?php

use App\Services\BiannualQuestionnaireImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function biannualQuestionnaireFixture(): array
{
    return require dirname(__DIR__, 2).'/database/data/biannual_monitoring_questionnaire.php';
}

function biannualQuestionnaireContentProjection(array $template): array
{
    return [
        'key' => $template['key'],
        'title' => $template['title'],
        'rating_scale' => $template['rating_scale'],
        'sections' => array_map(
            static fn (array $section): array => [
                'part_number' => $section['part_number'],
                'key' => $section['key'],
                'order' => $section['order'],
                'title' => $section['title'],
                'topics' => array_map(
                    static fn (array $topic): array => [
                        'key' => $topic['key'],
                        'order' => $topic['order'],
                        'global_order' => $topic['global_order'],
                        'title' => $topic['title'],
                        'description' => $topic['description'],
                        'guidance' => $topic['guidance'],
                        'questions' => array_map(
                            static fn (array $question): array => [
                                'key' => $question['key'],
                                'order' => $question['order'],
                                'global_order' => $question['global_order'],
                                'prompt' => $question['prompt'],
                                'response_type' => $question['response_type'],
                                'required' => $question['required'],
                            ],
                            $topic['questions']
                        ),
                    ],
                    $section['topics']
                ),
            ],
            $template['sections']
        ),
    ];
}

it('bundles the complete default biannual monitoring questionnaire', function () {
    $fixture = biannualQuestionnaireFixture();
    $topics = [];
    $questions = [];

    foreach ($fixture['sections'] as $section) {
        foreach ($section['topics'] as $topic) {
            $topics[] = $topic;

            foreach ($topic['questions'] as $question) {
                $questions[] = $question;
            }
        }
    }

    expect($fixture['counts'])->toBe([
        'sections' => 7,
        'topics' => 30,
        'questions' => 142,
    ])
        ->and($fixture['title'])->toBe('Monitoring Evaluation Tool (Per Think Tank)')
        ->and($fixture['code'])->toBe('monitoring-evaluation-tool-per-think-tank')
        ->and($fixture['sections'])->toHaveCount(7)
        ->and($topics)->toHaveCount(30)
        ->and($questions)->toHaveCount(142)
        ->and(array_column($fixture['sections'], 'key'))->toHaveCount(7)
        ->and(array_unique(array_column($fixture['sections'], 'key')))->toHaveCount(7)
        ->and(array_unique(array_column($topics, 'key')))->toHaveCount(30)
        ->and(array_unique(array_column($questions, 'key')))->toHaveCount(142)
        ->and($questions[0])->toMatchArray([
            'key' => 'question-001',
            'question_key' => 'question-001',
            'sort_order' => 1,
            'question_type' => 'scored_finding',
            'prompt' => 'Is the consortium structure consistent with the PPA?',
        ])
        ->and($questions[141])->toMatchArray([
            'key' => 'question-142',
            'prompt' => 'What are the priority recommendations for the next monitoring period?',
        ])
        ->and($fixture['sections'][0]['topics'][1]['guidance'])
        ->toBe('NB: TTPSC engagement to be assessed during subsequent monitoring visits following Year 1 review.');
});

it('discovers shifted headers and imports a compatible merged questionnaire generically', function () {
    $temporaryBase = tempnam(sys_get_temp_dir(), 'attp-questionnaire-');

    if ($temporaryBase === false) {
        throw new RuntimeException('Unable to create a temporary workbook path.');
    }

    $temporaryPath = $temporaryBase.'.xlsx';
    unlink($temporaryBase);

    $spreadsheet = new Spreadsheet;
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->setTitle('Custom monitoring');
    $worksheet->setCellValue('L1', '0 = Not Applicable');
    $worksheet->setCellValue('L2', '1 = Strong (requirement met)');
    $worksheet->setCellValue('B6', 'Custom Monitoring Questionnaire');
    foreach ([
        'E8' => 'Sn.',
        'F8' => 'Topic',
        'G8' => 'Description of Topic',
        'H8' => 'Key Verification Questions',
        'I8' => 'Strength',
        'J8' => 'Weakness',
        'K8' => 'Score (0-1)',
        'L8' => 'Ranking',
    ] as $coordinate => $label) {
        $worksheet->setCellValue($coordinate, $label);
    }
    $worksheet->setCellValue('F9', 'Part 1: Custom Assessment');
    $worksheet->setCellValue('E10', 1);
    $worksheet->setCellValue('F10', "Custom Topic\n\nNOTE: Apply after setup.");
    $worksheet->setCellValue('G10', 'A reusable topic description');
    $worksheet->setCellValue('H10', 'Is the first requirement met?');
    $worksheet->setCellValue('H11', 'Is the second requirement met?');
    $worksheet->mergeCells('E10:E11');
    $worksheet->mergeCells('F10:F11');
    $worksheet->mergeCells('G10:G11');

    try {
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $imported = (new BiannualQuestionnaireImportService)->import(
            $temporaryPath,
            'Custom monitoring'
        );

        expect($imported['title'])->toBe('Custom Monitoring Questionnaire')
            ->and($imported['counts'])->toBe([
                'sections' => 1,
                'topics' => 1,
                'questions' => 2,
            ])
            ->and($imported['source']['header_row'])->toBe(8)
            ->and($imported['source']['headers']['question']['column'])->toBe('H')
            ->and($imported['sections'][0]['title'])->toBe('Custom Assessment')
            ->and($imported['sections'][0]['topics'][0]['title'])->toBe('Custom Topic')
            ->and($imported['sections'][0]['topics'][0]['guidance'])
            ->toBe('NOTE: Apply after setup.')
            ->and($imported['sections'][0]['topics'][0]['source_merge_ranges']['topic'])
            ->toBe('F10:F11')
            ->and($imported['sections'][0]['topics'][0]['questions'][1])
            ->toMatchArray([
                'key' => 'question-002',
                'prompt' => 'Is the second requirement met?',
            ]);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

it('imports the supplied ATTP workbook into the same bundled definition when available', function () {
    $path = getenv('ATTP_MONITORING_QUESTIONNAIRE_PATH')
        ?: 'C:\Users\user\Downloads\2026-07-26_ATTP Monitoring Questionnaire.xlsx';

    if (! is_file($path)) {
        $this->markTestSkipped(
            'Set ATTP_MONITORING_QUESTIONNAIRE_PATH to compare the source workbook with the fixture.'
        );
    }

    $fixture = biannualQuestionnaireFixture();
    $imported = (new BiannualQuestionnaireImportService)->import($path);

    expect($imported['counts'])->toBe([
        'sections' => 7,
        'topics' => 30,
        'questions' => 142,
    ])
        ->and($imported['source']['sheet'])->toBe('Sheet1')
        ->and($imported['source']['header_row'])->toBe(8)
        ->and($imported['sections'][0]['topics'][0]['source_merge_ranges'])
        ->toBe([
            'topic' => 'B10:B15',
            'description' => 'C10:C15',
        ])
        ->and(biannualQuestionnaireContentProjection($imported))
        ->toBe(biannualQuestionnaireContentProjection($fixture));
});

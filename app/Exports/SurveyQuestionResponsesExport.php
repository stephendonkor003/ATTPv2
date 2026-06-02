<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyQuestionResponsesExport implements WithMultipleSheets
{
    public function __construct(private array $payload) {}

    public function sheets(): array
    {
        return [
            new SurveyQuestionResponsesSummarySheet($this->payload),
            new SurveyQuestionResponsesDataSheet($this->payload),
        ];
    }
}

class SurveyQuestionResponsesSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const PATTERN_HEADING_ROW = 13;

    public function __construct(private array $payload) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $surveyLink = $this->payload['surveyLink'] ?? null;
        $methodology = $this->payload['methodology'] ?? null;
        $selectedQuestion = (array) ($this->payload['selectedQuestion'] ?? []);
        $stats = (array) ($this->payload['stats'] ?? []);
        $questionStats = (array) ($this->payload['selectedQuestionStats'] ?? []);
        $answerBreakdown = (array) ($this->payload['answerBreakdown'] ?? []);
        $generatedAt = $this->payload['generatedAt'] ?? now();

        $rows = [
            ['Survey Response Details'],
            ['Generated At', $this->formatDateTime($generatedAt)],
            ['Questionnaire', (string) ($methodology?->name ?? $surveyLink?->methodology?->name ?? 'Questionnaire')],
            ['Indicator', (string) ($surveyLink?->indicator?->name ?? 'Unassigned indicator')],
            ['Survey Token', (string) ($surveyLink?->public_token ?? '')],
            ['Selected Question', (string) ($selectedQuestion['label'] ?? 'Question')],
            ['Question Section', trim((string) ($selectedQuestion['section_title'] ?? '')) ?: 'General section'],
            ['Question Type', Str::headline((string) ($selectedQuestion['type'] ?? 'question'))],
            ['Total Submissions', $stats['responses'] ?? 0],
            ['Answered', $stats['answered'] ?? 0],
            ['Missing', $stats['missing'] ?? 0],
            ['Completion Rate', number_format((float) ($questionStats['completion_rate'] ?? 0), 1) . '%'],
            ['Answer / Pattern', 'Count', 'Share'],
        ];

        foreach ((array) ($answerBreakdown['rows'] ?? []) as $row) {
            $rows[] = [
                $row['label'] ?? '',
                $row['count'] ?? 0,
                number_format((float) ($row['percent'] ?? 0), 1) . '%',
            ];
        }

        if (empty($answerBreakdown['rows'])) {
            $rows[] = ['No answer pattern is available for this question yet.', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E0F2FE']],
            ],
            self::PATTERN_HEADING_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '0F766E']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 42,
            'B' => 26,
            'C' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:C1');
                $sheet->getStyle('A1:C' . $highestRow)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:C1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2:A12')->getFont()->setBold(true);
                $sheet->getStyle('A' . self::PATTERN_HEADING_ROW . ':C' . self::PATTERN_HEADING_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }

    private function formatDateTime(mixed $value): string
    {
        return Carbon::parse($value ?: now())->format('Y-m-d H:i:s');
    }
}

class SurveyQuestionResponsesDataSheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const HEADING_ROW = 4;

    public function __construct(private array $payload) {}

    public function title(): string
    {
        return 'Responses';
    }

    public function array(): array
    {
        $selectedQuestion = (array) ($this->payload['selectedQuestion'] ?? []);
        $answerRows = $this->payload['answerRows'] ?? collect();
        $isAllQuestions = !empty($this->payload['isAllQuestions']);

        if ($isAllQuestions) {
            $dataRows = [];

            foreach ($answerRows as $row) {
                $answers = (array) ($row['answers'] ?? []);

                if (empty($answers)) {
                    $dataRows[] = [
                        $row['response_number'] ?? '',
                        $row['respondent_name'] ?? '',
                        $row['respondent_email'] ?? '',
                        $row['respondent_phone'] ?? '',
                        $row['respondent_organization'] ?? '',
                        '',
                        'No question answers were captured in this submission.',
                        '',
                        'Missing',
                        '',
                        $row['submitted_at'] ?? '',
                        $row['indicator_name'] ?? '',
                        $row['methodology_name'] ?? '',
                        $row['survey_token'] ?? '',
                        $row['response_id'] ?? '',
                    ];

                    continue;
                }

                foreach ($answers as $answerItem) {
                    $dataRows[] = [
                        $row['response_number'] ?? '',
                        $row['respondent_name'] ?? '',
                        $row['respondent_email'] ?? '',
                        $row['respondent_phone'] ?? '',
                        $row['respondent_organization'] ?? '',
                        $answerItem['section_title'] ?? '',
                        $answerItem['question'] ?? '',
                        $answerItem['type'] ?? '',
                        !empty($answerItem['has_answer']) ? 'Answered' : 'Missing',
                        $answerItem['answer_value'] ?? '',
                        $row['submitted_at'] ?? '',
                        $row['indicator_name'] ?? '',
                        $row['methodology_name'] ?? '',
                        $row['survey_token'] ?? '',
                        $row['response_id'] ?? '',
                    ];
                }
            }

            $rows = [
                ['Response Data'],
                ['Selected Question', (string) ($selectedQuestion['label'] ?? 'All questions')],
                ['Rows', count($dataRows)],
                [
                    '#',
                    'Respondent Name',
                    'Email',
                    'Phone',
                    'Organization',
                    'Question Section',
                    'Question',
                    'Question Type',
                    'Answer Status',
                    'Answer',
                    'Submitted At',
                    'Indicator',
                    'Questionnaire',
                    'Survey Token',
                    'Response ID',
                ],
            ];

            if (empty($dataRows)) {
                $dataRows[] = ['', 'No responses were submitted for this survey link.', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            }

            return array_merge($rows, $dataRows);
        }

        $rows = [
            ['Response Data'],
            ['Selected Question', (string) ($selectedQuestion['label'] ?? 'Question')],
            ['Rows', is_countable($answerRows) ? count($answerRows) : 0],
            [
                '#',
                'Respondent Name',
                'Email',
                'Phone',
                'Organization',
                'Answer Status',
                'Selected Question Answer',
                'Submitted At',
                'Indicator',
                'Questionnaire',
                'Survey Token',
                'Response ID',
            ],
        ];

        foreach ($answerRows as $row) {
            $rows[] = [
                $row['response_number'] ?? '',
                $row['respondent_name'] ?? '',
                $row['respondent_email'] ?? '',
                $row['respondent_phone'] ?? '',
                $row['respondent_organization'] ?? '',
                !empty($row['has_answer']) ? 'Answered' : 'Missing',
                $row['answer_value'] ?? '',
                $row['submitted_at'] ?? '',
                $row['indicator_name'] ?? '',
                $row['methodology_name'] ?? '',
                $row['survey_token'] ?? '',
                $row['response_id'] ?? '',
            ];
        }

        if ((is_countable($answerRows) ? count($answerRows) : 0) === 0) {
            $rows[] = ['', 'No responses were submitted for this survey link.', '', '', '', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E0F2FE']],
            ],
            self::HEADING_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '0F766E']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        if (!empty($this->payload['isAllQuestions'])) {
            return [
                'A' => 7,
                'B' => 24,
                'C' => 28,
                'D' => 18,
                'E' => 30,
                'F' => 24,
                'G' => 56,
                'H' => 18,
                'I' => 16,
                'J' => 70,
                'K' => 20,
                'L' => 34,
                'M' => 30,
                'N' => 28,
                'O' => 38,
            ];
        }

        return [
            'A' => 7,
            'B' => 24,
            'C' => 28,
            'D' => 18,
            'E' => 30,
            'F' => 15,
            'G' => 70,
            'H' => 20,
            'I' => 34,
            'J' => 30,
            'K' => 28,
            'L' => 38,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->freezePane('A' . (self::HEADING_ROW + 1));
                $sheet->setAutoFilter('A' . self::HEADING_ROW . ':' . $highestColumn . self::HEADING_ROW);

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);

                $sheet->getStyle('A1:' . $highestColumn . '1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A2:A3')->getFont()->setBold(true);
                $sheet->getStyle('A' . self::HEADING_ROW . ':' . $highestColumn . self::HEADING_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(self::HEADING_ROW)->setRowHeight(24);
            },
        ];
    }
}

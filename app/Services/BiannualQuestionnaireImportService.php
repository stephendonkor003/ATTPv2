<?php

namespace App\Services;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class BiannualQuestionnaireImportService
{
    /**
     * Import the ATTP monitoring-questionnaire workbook shape into a normalized,
     * persistence-agnostic template definition.
     *
     * @return array<string, mixed>
     */
    public function import(string $path, ?string $sheetName = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Questionnaire workbook is not readable: {$path}");
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            // Merge definitions are part of the questionnaire's structure and
            // are not retained by every PhpSpreadsheet reader in data-only mode.
            $reader->setReadDataOnly(false);
            try {
                // PhpSpreadsheet 1.x performs string increments internally.
                // PHP 8.4 deprecates that implementation detail even though the
                // workbook remains valid and readable.
                $previousErrorHandler = null;
                $previousErrorHandler = set_error_handler(
                    static function (
                        int $severity,
                        string $message,
                        string $file,
                        int $line
                    ) use (&$previousErrorHandler): bool {
                        if ($severity === E_DEPRECATED) {
                            return true;
                        }

                        return is_callable($previousErrorHandler)
                            ? (bool) $previousErrorHandler($severity, $message, $file, $line)
                            : false;
                    }
                );
                $spreadsheet = $reader->load($path);
            } finally {
                restore_error_handler();
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Unable to read questionnaire workbook: {$exception->getMessage()}",
                previous: $exception
            );
        }

        try {
            $worksheet = $sheetName === null
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->getSheetByName($sheetName);

            if (! $worksheet instanceof Worksheet) {
                throw new InvalidArgumentException("Questionnaire worksheet was not found: {$sheetName}");
            }

            return $this->importWorksheet($worksheet, basename($path));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function importWorksheet(Worksheet $worksheet, string $sourceFile): array
    {
        $header = $this->locateHeader($worksheet);
        $title = $this->locateTitle($worksheet, $header['row']);
        $ratingOptions = $this->extractRatingOptions($worksheet, $header['row']);

        if ($title === '') {
            throw new UnexpectedValueException('The questionnaire title could not be found above the header row.');
        }

        if (count($ratingOptions) < 2) {
            throw new UnexpectedValueException(
                'At least two rating options in the form "number = label" are required above the header row.'
            );
        }

        $sections = [];
        $currentSectionIndex = null;
        $currentTopicIndex = null;
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = $header['row'] + 1; $row <= $highestRow; $row++) {
            $part = $this->partAtRow($worksheet, $row);

            if ($part !== null) {
                $sections[] = [
                    'part_number' => $part['number'],
                    'title' => $part['title'],
                    'source_row' => $row,
                    'topics' => [],
                ];
                $currentSectionIndex = array_key_last($sections);
                $currentTopicIndex = null;

                continue;
            }

            $topicValue = $this->cellText(
                $worksheet,
                $header['columns']['topic']['index'],
                $row
            );
            $question = $this->cellText(
                $worksheet,
                $header['columns']['question']['index'],
                $row
            );

            if ($topicValue !== '') {
                if ($currentSectionIndex === null) {
                    throw new UnexpectedValueException(
                        "Topic '{$topicValue}' at row {$row} appears before the first Part section."
                    );
                }

                [$topicTitle, $guidance] = $this->splitTopicAndGuidance($topicValue);
                $description = $this->cellText(
                    $worksheet,
                    $header['columns']['description']['index'],
                    $row
                );

                if ($topicTitle === '') {
                    throw new UnexpectedValueException("The topic title at row {$row} is empty.");
                }

                if ($description === '') {
                    throw new UnexpectedValueException(
                        "Topic '{$topicTitle}' at row {$row} has no description."
                    );
                }

                $topicCoordinate = $header['columns']['topic']['column'].$row;
                $descriptionCoordinate = $header['columns']['description']['column'].$row;

                $sections[$currentSectionIndex]['topics'][] = [
                    'title' => $topicTitle,
                    'description' => $description,
                    'guidance' => $guidance,
                    'source_rows' => [
                        'start' => $row,
                        'end' => $row,
                    ],
                    'source_merge_ranges' => [
                        'topic' => $this->mergedRangeForCell($worksheet, $topicCoordinate),
                        'description' => $this->mergedRangeForCell($worksheet, $descriptionCoordinate),
                    ],
                    'questions' => [],
                ];
                $currentTopicIndex = array_key_last($sections[$currentSectionIndex]['topics']);
            }

            if ($question === '') {
                continue;
            }

            if ($currentSectionIndex === null || $currentTopicIndex === null) {
                throw new UnexpectedValueException(
                    "Question at row {$row} does not belong to a Part section and topic."
                );
            }

            $sections[$currentSectionIndex]['topics'][$currentTopicIndex]['questions'][] = [
                'prompt' => $question,
                'response_type' => 'scored_finding',
                'required' => false,
                'source_row' => $row,
            ];
            $sections[$currentSectionIndex]['topics'][$currentTopicIndex]['source_rows']['end'] = $row;
        }

        $structureWarnings = $this->validateStructure($sections);

        $sectionOrder = 0;
        $topicOrder = 0;
        $questionOrder = 0;

        foreach ($sections as &$section) {
            $sectionOrder++;
            $section['key'] = sprintf('section-%02d', $sectionOrder);
            $section['section_key'] = $section['key'];
            $section['order'] = $sectionOrder;
            $section['sort_order'] = $sectionOrder;
            $topicOrderWithinSection = 0;

            foreach ($section['topics'] as &$topic) {
                $topicOrder++;
                $topicOrderWithinSection++;
                $topic['key'] = sprintf('topic-%02d', $topicOrder);
                $topic['topic_key'] = $topic['key'];
                $topic['order'] = $topicOrderWithinSection;
                $topic['sort_order'] = $topicOrderWithinSection;
                $topic['global_order'] = $topicOrder;
                $questionOrderWithinTopic = 0;

                foreach ($topic['questions'] as &$question) {
                    $questionOrder++;
                    $questionOrderWithinTopic++;
                    $question['key'] = sprintf('question-%03d', $questionOrder);
                    $question['question_key'] = $question['key'];
                    $question['order'] = $questionOrderWithinTopic;
                    $question['sort_order'] = $questionOrderWithinTopic;
                    $question['global_order'] = $questionOrder;
                    $question['question_type'] = $question['response_type'];
                }
                unset($question);
            }
            unset($topic);
        }
        unset($section);

        $ratingValues = array_column($ratingOptions, 'value');

        $templateKey = $this->slug($title);

        return [
            'key' => $templateKey,
            'code' => $templateKey,
            'title' => $title,
            'rating_scale' => [
                'key' => sprintf('rating-scale-%d-%d', min($ratingValues), max($ratingValues)),
                'minimum' => min($ratingValues),
                'maximum' => max($ratingValues),
                'options' => $ratingOptions,
            ],
            'response_schema' => [
                'type' => 'scored_finding',
                'fields' => [
                    'strength' => [
                        'type' => 'long_text',
                        'required' => false,
                    ],
                    'weakness' => [
                        'type' => 'long_text',
                        'required' => false,
                    ],
                    'rating_code' => [
                        'type' => 'single_choice',
                        'required' => false,
                        'rating_scale' => sprintf(
                            'rating-scale-%d-%d',
                            min($ratingValues),
                            max($ratingValues)
                        ),
                    ],
                    'ranking_label' => [
                        'type' => 'derived',
                        'source' => 'rating_code',
                    ],
                ],
            ],
            'sections' => $sections,
            'counts' => [
                'sections' => $sectionOrder,
                'topics' => $topicOrder,
                'questions' => $questionOrder,
            ],
            'source' => [
                'file' => $sourceFile,
                'sheet' => $worksheet->getTitle(),
                'header_row' => $header['row'],
                'warnings' => $structureWarnings,
                'headers' => array_map(
                    static fn (array $column): array => [
                        'column' => $column['column'],
                        'label' => $column['label'],
                    ],
                    $header['columns']
                ),
            ],
        ];
    }

    /**
     * @return array{row: int, columns: array<string, array{index: int, column: string, label: string}>}
     */
    private function locateHeader(Worksheet $worksheet): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $required = [
            'serial',
            'topic',
            'description',
            'question',
            'strength',
            'weakness',
            'score',
            'ranking',
        ];

        for ($row = 1; $row <= $highestRow; $row++) {
            $columns = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $label = $this->cellText($worksheet, $column, $row);

                if ($label === '') {
                    continue;
                }

                $type = $this->headerType($label);

                if ($type !== null && ! isset($columns[$type])) {
                    $columns[$type] = [
                        'index' => $column,
                        'column' => Coordinate::stringFromColumnIndex($column),
                        'label' => $label,
                    ];
                }
            }

            if (count(array_intersect($required, array_keys($columns))) === count($required)) {
                return [
                    'row' => $row,
                    'columns' => $columns,
                ];
            }
        }

        throw new UnexpectedValueException(
            'A valid questionnaire header row was not found. Expected serial, topic, description, '
            .'verification question, strength, weakness, score, and ranking columns.'
        );
    }

    private function headerType(string $label): ?string
    {
        $normalized = $this->normalizeForMatching($label);

        if (preg_match('/^(sn|s n|s no|serial|serial no|number|no)$/', $normalized) === 1) {
            return 'serial';
        }

        if ($normalized === 'topic') {
            return 'topic';
        }

        if (str_contains($normalized, 'description') && str_contains($normalized, 'topic')) {
            return 'description';
        }

        if (str_contains($normalized, 'question')) {
            return 'question';
        }

        if (str_contains($normalized, 'strength')) {
            return 'strength';
        }

        if (str_contains($normalized, 'weakness')) {
            return 'weakness';
        }

        if (str_starts_with($normalized, 'score')) {
            return 'score';
        }

        if (str_contains($normalized, 'ranking') || str_contains($normalized, 'rating')) {
            return 'ranking';
        }

        return null;
    }

    private function locateTitle(Worksheet $worksheet, int $headerRow): string
    {
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        // Prefer an explicit document heading over the administrative fields
        // that commonly sit immediately above the questionnaire header (for
        // example Think Tank name, consortium name, and reporting date).
        for ($row = 1; $row < $headerRow; $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->cellText($worksheet, $column, $row);
                $normalized = $this->normalizeForMatching($value);

                if (
                    $value !== ''
                    && (
                        str_contains($normalized, 'questionnaire')
                        || (
                            str_contains($normalized, 'monitoring')
                            && str_contains($normalized, 'tool')
                        )
                    )
                ) {
                    return $value;
                }
            }
        }

        $administrativeLabels = [
            'name of think tank',
            'corresponding name of consortia',
            'corresponding name of consortium',
            'reporting date',
            'reporing date',
        ];

        for ($row = $headerRow - 1; $row >= 1; $row--) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->cellText($worksheet, $column, $row);
                $normalized = $this->normalizeForMatching($value);

                if (
                    $value !== ''
                    && preg_match('/^-?\d+\s*=\s*.+$/u', $value) !== 1
                    && $normalized !== 'ranking'
                    && ! in_array($normalized, $administrativeLabels, true)
                ) {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array{value: int, label: string, description: string|null, order: int}>
     */
    private function extractRatingOptions(Worksheet $worksheet, int $headerRow): array
    {
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $options = [];

        for ($row = 1; $row < $headerRow; $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->cellText($worksheet, $column, $row);

                if (preg_match('/^(-?\d+)\s*=\s*(.+)$/u', $value, $matches) !== 1) {
                    continue;
                }

                $ratingValue = (int) $matches[1];
                $labelAndDescription = trim($matches[2]);
                $label = $labelAndDescription;
                $description = null;

                if (
                    preg_match('/^(.+?)\s*\((.+)\)$/u', $labelAndDescription, $labelMatches) === 1
                ) {
                    $label = trim($labelMatches[1]);
                    $description = trim($labelMatches[2]);
                }

                $options[$ratingValue] = [
                    'value' => $ratingValue,
                    'label' => $label,
                    'description' => $description,
                    'order' => 0,
                ];
            }
        }

        ksort($options, SORT_NUMERIC);
        $order = 0;

        foreach ($options as &$option) {
            $option['order'] = ++$order;
        }
        unset($option);

        return array_values($options);
    }

    /**
     * @return array{number: int, title: string}|null
     */
    private function partAtRow(Worksheet $worksheet, int $row): ?array
    {
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        for ($column = 1; $column <= $highestColumn; $column++) {
            $value = $this->cellText($worksheet, $column, $row);

            if (preg_match('/^Part\s+(\d+)\s*:\s*(.+)$/iu', $value, $matches) === 1) {
                return [
                    'number' => (int) $matches[1],
                    'title' => trim($matches[2]),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitTopicAndGuidance(string $value): array
    {
        $parts = preg_split(
            '/\n\s*\n(?=(?:NB|NOTE)\s*:)/iu',
            $value,
            2
        );

        if ($parts === false || count($parts) === 1) {
            return [trim($value), null];
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function validateStructure(array $sections): array
    {
        $warnings = [];

        if ($sections === []) {
            throw new UnexpectedValueException('The workbook contains no "Part n:" sections.');
        }

        $partNumbers = [];

        foreach ($sections as $section) {
            if (in_array($section['part_number'], $partNumbers, true)) {
                throw new UnexpectedValueException(
                    "Part number {$section['part_number']} is used more than once."
                );
            }

            $partNumbers[] = $section['part_number'];

            if ($section['topics'] === []) {
                throw new UnexpectedValueException(
                    "Section '{$section['title']}' contains no topics."
                );
            }

            foreach ($section['topics'] as $topic) {
                if ($topic['questions'] === []) {
                    throw new UnexpectedValueException(
                        "Topic '{$topic['title']}' contains no verification questions."
                    );
                }

                $expectedEndRow = $topic['source_rows']['end'];
                $expectedStartRow = $topic['source_rows']['start'];
                $questionCount = count($topic['questions']);

                if ($questionCount !== ($expectedEndRow - $expectedStartRow + 1)) {
                    throw new UnexpectedValueException(
                        "Topic '{$topic['title']}' must contain one verification question on every "
                        ."row from {$expectedStartRow} through {$expectedEndRow}."
                    );
                }

                foreach (['topic', 'description'] as $mergeType) {
                    $mergeRange = $topic['source_merge_ranges'][$mergeType];

                    if ($questionCount > 1 && $mergeRange === null) {
                        throw new UnexpectedValueException(
                            "Topic '{$topic['title']}' has multiple questions but its {$mergeType} "
                            .'cell is not merged across the question rows.'
                        );
                    }

                    if ($mergeRange === null) {
                        continue;
                    }

                    [$start, $end] = Coordinate::rangeBoundaries($mergeRange);

                    $mergeStartRow = (int) $start[1];
                    $mergeEndRow = (int) $end[1];

                    if (
                        $mergeStartRow !== $expectedStartRow
                        || $mergeEndRow > $expectedEndRow
                        || $mergeEndRow < ($expectedEndRow - 1)
                    ) {
                        throw new UnexpectedValueException(
                            "Merged {$mergeType} range '{$mergeRange}' for topic "
                            ."'{$topic['title']}' does not span all question rows "
                            ."{$expectedStartRow}-{$expectedEndRow}."
                        );
                    }

                    if ($mergeEndRow < $expectedEndRow) {
                        $warnings[] = "Merged {$mergeType} range '{$mergeRange}' for topic "
                            ."'{$topic['title']}' stops at row {$mergeEndRow}; the question "
                            ."on row {$expectedEndRow} remains attached to this topic.";
                    }
                }
            }
        }

        return $warnings;
    }

    private function mergedRangeForCell(Worksheet $worksheet, string $coordinate): ?string
    {
        [$column, $row] = Coordinate::indexesFromString($coordinate);

        foreach ($worksheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);

            if (
                $column >= $start[0]
                && $column <= $end[0]
                && $row >= $start[1]
                && $row <= $end[1]
            ) {
                return $range;
            }
        }

        return null;
    }

    private function cellText(Worksheet $worksheet, int $column, int $row): string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column).$row;
        $value = $worksheet->getCell($coordinate)->getValue();

        if (is_object($value) && method_exists($value, 'getPlainText')) {
            $value = $value->getPlainText();
        }

        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], (string) $value);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeForMatching(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['–', '—', '_'], [' ', ' ', ' '], $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(str_replace('&', ' and ', $value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'questionnaire-template';
    }
}

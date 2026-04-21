<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MeSurvey
{
    protected const QUESTION_TYPES = [
        'text',
        'textarea',
        'number',
        'email',
        'date',
        'datetime',
        'url',
        'select',
        'multiselect',
        'radio',
        'checkbox',
        'scale',
        'slider',
        'file',
        'matrix',
    ];

    public static function surveyConfigFromMetadata(array $metadata, string $fallbackTitle = 'Public Survey'): array
    {
        $survey = (array) data_get($metadata, 'survey', []);
        $title = trim((string) ($survey['title'] ?? $fallbackTitle));
        $intro = trim((string) ($survey['intro'] ?? ''));

        $rawSections = is_array($survey['sections'] ?? null)
            ? $survey['sections']
            : [];

        if (empty($rawSections)) {
            $legacyQuestions = self::normalizeLegacyQuestions((array) ($survey['questions'] ?? []));
            if (!empty($legacyQuestions)) {
                $rawSections = [[
                    'key' => 'section_1',
                    'title' => 'Section 1',
                    'description' => '',
                    'questions' => $legacyQuestions,
                ]];
            }
        }

        $usedSectionKeys = [];
        $usedQuestionKeys = [];

        $sections = collect($rawSections)
            ->map(function ($section, int $sectionIndex) use (&$usedSectionKeys, &$usedQuestionKeys) {
                return self::normalizeSection($section, $sectionIndex, $usedSectionKeys, $usedQuestionKeys);
            })
            ->filter()
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($survey['enabled'] ?? false),
            'title' => $title !== '' ? $title : $fallbackTitle,
            'intro' => $intro,
            'estimated_minutes' => self::normalizePositiveInteger($survey['estimated_minutes'] ?? null),
            'sections' => $sections,
            'questions' => self::flattenQuestionsFromSections($sections),
            'updated_at' => (string) ($survey['updated_at'] ?? ''),
        ];
    }

    public static function hasEnabledQuestions(array $metadata, string $fallbackTitle = 'Public Survey'): bool
    {
        $survey = self::surveyConfigFromMetadata($metadata, $fallbackTitle);

        return (bool) $survey['enabled'] && !empty($survey['questions']);
    }

    public static function flattenQuestions(array $surveyConfig): array
    {
        return self::flattenQuestionsFromSections((array) ($surveyConfig['sections'] ?? []));
    }

    public static function isSectionVisible(array $section, array $answers): bool
    {
        return self::matchesVisibility((array) ($section['visibility'] ?? []), $answers);
    }

    public static function isQuestionVisible(array $question, array $answers): bool
    {
        return self::matchesVisibility((array) ($question['visibility'] ?? []), $answers);
    }

    public static function visibleSections(array $surveyConfig, array $answers): array
    {
        return collect((array) ($surveyConfig['sections'] ?? []))
            ->filter(fn (array $section) => self::isSectionVisible($section, $answers))
            ->values()
            ->all();
    }

    public static function normalizeAnswer(array $question, mixed $rawValue): mixed
    {
        $type = strtolower((string) ($question['type'] ?? 'text'));

        return match ($type) {
            'checkbox', 'multiselect' => collect(is_array($rawValue) ? $rawValue : [])
                ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
                ->map(fn ($item) => trim((string) $item))
                ->unique()
                ->values()
                ->all(),
            'matrix' => self::normalizeMatrixAnswer($question, $rawValue),
            'number' => self::normalizeScalar($rawValue, true),
            'scale', 'slider' => self::normalizeScaleValue($rawValue),
            'file' => self::normalizeFileValue($rawValue),
            default => self::normalizeScalar($rawValue),
        };
    }

    public static function validateAnswer(array $question, mixed $rawValue, bool $isVisible = true): array
    {
        $type = strtolower((string) ($question['type'] ?? 'text'));
        $required = (bool) ($question['required'] ?? false);
        $normalized = self::normalizeAnswer($question, $rawValue);
        $errors = [];

        if (!$isVisible) {
            return ['value' => $normalized, 'errors' => []];
        }

        $options = collect((array) ($question['options'] ?? []))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        if (in_array($type, ['text', 'textarea'], true)) {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'number') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && !is_numeric($normalized)) {
                $errors[] = 'Please enter a valid number.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'email') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && !filter_var((string) $normalized, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'date') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && strtotime((string) $normalized) === false) {
                $errors[] = 'Please enter a valid date.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'datetime') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && strtotime((string) $normalized) === false) {
                $errors[] = 'Please enter a valid date and time.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'url') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && !filter_var((string) $normalized, FILTER_VALIDATE_URL)) {
                $errors[] = 'Please enter a valid link.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if (in_array($type, ['select', 'radio'], true)) {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized) && $options->isNotEmpty() && !$options->contains((string) $normalized)) {
                $errors[] = 'Please select a valid option.';
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if (in_array($type, ['checkbox', 'multiselect'], true)) {
            $selected = collect(is_array($normalized) ? $normalized : [])->values();

            if ($required && $selected->isEmpty()) {
                $errors[] = 'Select at least one option.';
            }

            if ($options->isNotEmpty() && $selected->diff($options)->isNotEmpty()) {
                $errors[] = 'One or more selected options are invalid.';
            }

            $minSelections = self::normalizePositiveInteger($question['min_selections'] ?? null);
            $maxSelections = self::normalizePositiveInteger($question['max_selections'] ?? null);

            if ($minSelections !== null && $selected->count() < $minSelections) {
                $errors[] = 'Select at least ' . $minSelections . ' option' . ($minSelections === 1 ? '' : 's') . '.';
            }

            if ($maxSelections !== null && $selected->count() > $maxSelections) {
                $errors[] = 'Select no more than ' . $maxSelections . ' option' . ($maxSelections === 1 ? '' : 's') . '.';
            }

            return ['value' => $selected->all(), 'errors' => array_values(array_unique($errors))];
        }

        if (in_array($type, ['scale', 'slider'], true)) {
            $min = (int) ($question['scale']['min'] ?? 1);
            $max = (int) ($question['scale']['max'] ?? 5);
            $step = $type === 'slider'
                ? (self::normalizePositiveInteger($question['scale']['step'] ?? 1) ?? 1)
                : 1;

            if ($required && self::isBlank($normalized)) {
                $errors[] = 'This question is required.';
            } elseif (!self::isBlank($normalized)) {
                if (!is_numeric($normalized) || (int) $normalized != $normalized) {
                    $errors[] = 'Please choose a valid scale value.';
                } else {
                    $value = (int) $normalized;
                    if ($value < $min || $value > $max) {
                        $errors[] = 'Please choose a value between ' . $min . ' and ' . $max . '.';
                    }

                    if ($step > 1 && (($value - $min) % $step) !== 0) {
                        $errors[] = 'Please choose a valid slider value.';
                    }

                    $normalized = $value;
                }
            }

            return ['value' => $normalized, 'errors' => $errors];
        }

        if ($type === 'file') {
            if ($required && self::isBlank($normalized)) {
                $errors[] = 'Please upload a file.';
            }

            if ($rawValue instanceof UploadedFile) {
                if (!$rawValue->isValid()) {
                    $errors[] = 'The uploaded file could not be processed.';
                }

                if ($rawValue->getSize() > (20 * 1024 * 1024)) {
                    $errors[] = 'The uploaded file must not exceed 20 MB.';
                }
            } elseif (!self::isBlank($normalized) && !is_array($normalized)) {
                $errors[] = 'Please upload a valid file.';
            }

            return ['value' => $normalized, 'errors' => array_values(array_unique($errors))];
        }

        if ($type === 'matrix') {
            $rows = collect((array) ($question['rows'] ?? []));
            $allowedColumns = collect((array) ($question['columns'] ?? []))
                ->flatMap(function ($column) {
                    return [
                        trim((string) data_get($column, 'key')),
                        trim((string) data_get($column, 'label')),
                    ];
                })
                ->filter()
                ->unique()
                ->values();

            $selectedRows = collect(is_array($normalized) ? $normalized : []);

            if ($required) {
                $missingRows = $rows
                    ->filter(function ($row) use ($selectedRows) {
                        $rowKey = (string) data_get($row, 'key');
                        return $rowKey !== '' && self::isBlank($selectedRows->get($rowKey));
                    })
                    ->pluck('label')
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($missingRows)) {
                    $errors[] = 'Please answer every row in this grid.';
                }
            }

            foreach ($selectedRows as $rowKey => $columnKey) {
                if ($allowedColumns->isNotEmpty() && !$allowedColumns->contains((string) $columnKey)) {
                    $errors[] = 'One or more grid selections are invalid.';
                    break;
                }
            }

            return ['value' => $selectedRows->all(), 'errors' => array_values(array_unique($errors))];
        }

        if ($required && self::isBlank($normalized)) {
            $errors[] = 'This question is required.';
        }

        return ['value' => $normalized, 'errors' => $errors];
    }

    public static function displayAnswer(array $question, mixed $value): mixed
    {
        $type = strtolower((string) ($question['type'] ?? 'text'));

        if ($type === 'matrix') {
            $rows = collect((array) ($question['rows'] ?? []))->keyBy('key');
            $columns = collect((array) ($question['columns'] ?? []))->keyBy('key');

            return collect(is_array($value) ? $value : [])
                ->mapWithKeys(function ($columnKey, $rowKey) use ($rows, $columns) {
                    $rowLabel = (string) ($rows->get((string) $rowKey)['label'] ?? $rowKey);
                    $columnLabel = (string) (
                        $columns->get((string) $columnKey)['label']
                        ?? collect($columns->all())->firstWhere('label', (string) $columnKey)['label']
                        ?? $columnKey
                    );

                    return [$rowLabel => $columnLabel];
                })
                ->all();
        }

        if ($type === 'file' && is_array($value)) {
            $filename = trim((string) ($value['original_name'] ?? $value['name'] ?? ''));
            $url = trim((string) ($value['url'] ?? ''));

            return array_filter([
                'File' => $filename,
                'Link' => $url,
            ]);
        }

        return $value;
    }

    public static function qrCodeUrl(string $url, int $size = 280): string
    {
        $size = max(160, min($size, 800));

        return 'https://quickchart.io/qr?size=' . $size . '&margin=2&text=' . rawurlencode($url);
    }

    protected static function normalizeSection(
        mixed $section,
        int $sectionIndex,
        array &$usedSectionKeys,
        array &$usedQuestionKeys
    ): ?array {
        if (!is_array($section)) {
            return null;
        }

        $title = trim((string) ($section['title'] ?? $section['name'] ?? ''));
        $baseSectionKey = (string) ($section['key'] ?? $section['id'] ?? '');
        $sectionKey = self::uniqueKey(
            self::normalizeKey($baseSectionKey !== '' ? $baseSectionKey : $title, 'section_' . ($sectionIndex + 1)),
            $usedSectionKeys
        );

        $questions = collect((array) ($section['questions'] ?? []))
            ->map(function ($question, int $questionIndex) use ($sectionKey, &$usedQuestionKeys) {
                return self::normalizeQuestion($question, $sectionKey, $questionIndex, $usedQuestionKeys);
            })
            ->filter()
            ->values()
            ->all();

        if (empty($questions)) {
            return null;
        }

        return [
            'key' => $sectionKey,
            'title' => $title !== '' ? $title : 'Section ' . ($sectionIndex + 1),
            'description' => trim((string) ($section['description'] ?? $section['intro'] ?? '')),
            'visibility' => self::normalizeVisibility($section['visibility'] ?? [
                'question_key' => $section['depends_on'] ?? null,
                'values' => $section['show_if'] ?? null,
            ]),
            'questions' => $questions,
        ];
    }

    protected static function normalizeQuestion(
        mixed $question,
        string $sectionKey,
        int $questionIndex,
        array &$usedQuestionKeys
    ): ?array {
        if (!is_array($question)) {
            return null;
        }

        $label = trim((string) ($question['label'] ?? ''));
        $type = strtolower(trim((string) ($question['type'] ?? 'text')));

        if ($label === '' || !in_array($type, self::QUESTION_TYPES, true)) {
            return null;
        }

        $questionKey = self::uniqueKey(
            self::normalizeKey(
                (string) ($question['key'] ?? $question['id'] ?? ''),
                $sectionKey . '_q_' . ($questionIndex + 1)
            ),
            $usedQuestionKeys
        );

        $options = self::normalizeStringList($question['options'] ?? []);
        $rows = self::normalizeLabelList($question['rows'] ?? []);
        $columns = self::normalizeLabelList($question['columns'] ?? []);
        $scaleMin = self::normalizePositiveInteger($question['scale']['min'] ?? $question['scale_min'] ?? $question['min'] ?? null) ?? 1;
        $scaleMax = self::normalizePositiveInteger($question['scale']['max'] ?? $question['scale_max'] ?? $question['max'] ?? null) ?? 5;
        $scaleStep = self::normalizePositiveInteger($question['scale']['step'] ?? $question['scale_step'] ?? $question['step'] ?? null) ?? 1;

        if (in_array($type, ['checkbox', 'multiselect'], true) && !empty($options)) {
            $maxSelections = self::normalizePositiveInteger($question['max_selections'] ?? $question['maxSelections'] ?? null);
            $minSelections = self::normalizePositiveInteger($question['min_selections'] ?? $question['minSelections'] ?? null);
        } else {
            $maxSelections = null;
            $minSelections = null;
        }

        if ($type === 'matrix' && (empty($rows) || empty($columns))) {
            return null;
        }

        if (in_array($type, ['select', 'multiselect', 'radio', 'checkbox'], true) && empty($options)) {
            return null;
        }

        if ($type === 'scale' && $scaleMax < $scaleMin) {
            [$scaleMin, $scaleMax] = [$scaleMax, $scaleMin];
        }

        if ($type === 'slider' && $scaleMax < $scaleMin) {
            [$scaleMin, $scaleMax] = [$scaleMax, $scaleMin];
        }

        return [
            'key' => $questionKey,
            'label' => $label,
            'type' => $type,
            'required' => (bool) ($question['required'] ?? false),
            'hint' => trim((string) ($question['hint'] ?? '')),
            'options' => in_array($type, ['select', 'multiselect', 'radio', 'checkbox'], true) ? $options : [],
            'rows' => $type === 'matrix' ? $rows : [],
            'columns' => $type === 'matrix' ? $columns : [],
            'scale' => in_array($type, ['scale', 'slider'], true) ? [
                'min' => $scaleMin,
                'max' => $scaleMax,
                'step' => $scaleStep,
                'min_label' => trim((string) ($question['scale']['min_label'] ?? $question['scale_min_label'] ?? '')),
                'max_label' => trim((string) ($question['scale']['max_label'] ?? $question['scale_max_label'] ?? '')),
            ] : null,
            'min_selections' => $minSelections,
            'max_selections' => $maxSelections,
            'visibility' => self::normalizeVisibility($question['visibility'] ?? [
                'question_key' => $question['depends_on'] ?? null,
                'values' => $question['show_if'] ?? null,
            ]),
            'section_key' => $sectionKey,
        ];
    }

    protected static function normalizeLegacyQuestions(array $questions): array
    {
        return collect($questions)
            ->filter(fn ($question) => is_array($question))
            ->values()
            ->all();
    }

    protected static function flattenQuestionsFromSections(array $sections): array
    {
        return collect($sections)
            ->flatMap(function (array $section, int $sectionIndex) {
                return collect((array) ($section['questions'] ?? []))
                    ->map(function (array $question, int $questionIndex) use ($section, $sectionIndex) {
                        $question['section_key'] = (string) ($section['key'] ?? '');
                        $question['section_title'] = (string) ($section['title'] ?? ('Section ' . ($sectionIndex + 1)));
                        $question['section_index'] = $sectionIndex;
                        $question['question_index'] = $questionIndex;

                        return $question;
                    });
            })
            ->values()
            ->all();
    }

    protected static function normalizeVisibility(mixed $visibility): array
    {
        if (!is_array($visibility)) {
            return [];
        }

        $questionKey = trim((string) ($visibility['question_key'] ?? $visibility['question'] ?? $visibility['depends_on'] ?? ''));
        $values = self::normalizeStringList($visibility['values'] ?? $visibility['show_if'] ?? []);

        if ($questionKey === '' || empty($values)) {
            return [];
        }

        return [
            'question_key' => $questionKey,
            'values' => $values,
        ];
    }

    protected static function matchesVisibility(array $visibility, array $answers): bool
    {
        $questionKey = trim((string) ($visibility['question_key'] ?? ''));
        $values = collect((array) ($visibility['values'] ?? []))
            ->map(fn ($item) => Str::lower(trim((string) $item)))
            ->filter()
            ->values();

        if ($questionKey === '' || $values->isEmpty()) {
            return true;
        }

        $candidate = $answers[$questionKey] ?? null;
        $candidateValues = self::flattenComparableValues($candidate);

        if ($candidateValues->isEmpty()) {
            return false;
        }

        return $candidateValues->intersect($values)->isNotEmpty();
    }

    protected static function normalizeMatrixAnswer(array $question, mixed $rawValue): array
    {
        if (!is_array($rawValue)) {
            return [];
        }

        $allowedRowKeys = collect((array) ($question['rows'] ?? []))
            ->map(fn ($row) => (string) data_get($row, 'key'))
            ->filter()
            ->values();

        return collect($rawValue)
            ->filter(function ($value, $rowKey) use ($allowedRowKeys) {
                return $allowedRowKeys->contains((string) $rowKey) && is_scalar($value) && trim((string) $value) !== '';
            })
            ->mapWithKeys(fn ($value, $rowKey) => [(string) $rowKey => trim((string) $value)])
            ->all();
    }

    protected static function normalizeScaleValue(mixed $rawValue): mixed
    {
        if (is_scalar($rawValue) && trim((string) $rawValue) !== '') {
            return trim((string) $rawValue);
        }

        return null;
    }

    protected static function normalizeFileValue(mixed $rawValue): ?array
    {
        if ($rawValue instanceof UploadedFile) {
            return array_filter([
                'original_name' => trim((string) $rawValue->getClientOriginalName()),
                'mime_type' => trim((string) $rawValue->getClientMimeType()),
                'size' => $rawValue->getSize(),
            ], fn ($value) => $value !== null && $value !== '');
        }

        if (!is_array($rawValue)) {
            return null;
        }

        $normalized = [
            'original_name' => trim((string) ($rawValue['original_name'] ?? $rawValue['name'] ?? '')),
            'stored_path' => trim((string) ($rawValue['stored_path'] ?? $rawValue['path'] ?? '')),
            'url' => trim((string) ($rawValue['url'] ?? '')),
            'mime_type' => trim((string) ($rawValue['mime_type'] ?? $rawValue['mime'] ?? '')),
            'size' => is_numeric($rawValue['size'] ?? null) ? (int) $rawValue['size'] : null,
        ];

        return array_filter($normalized, fn ($value) => $value !== null && $value !== '');
    }

    protected static function normalizeScalar(mixed $value, bool $allowNumericString = false): mixed
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($allowNumericString && is_numeric($normalized)) {
            return $normalized;
        }

        return $normalized;
    }

    protected static function normalizeStringList(mixed $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\r\n|\r|\n|,/', $values) ?: [];
        }

        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item) => trim((string) $item))
            ->unique()
            ->values()
            ->all();
    }

    protected static function normalizeLabelList(mixed $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\r\n|\r|\n/', $values) ?: [];
        }

        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(function ($item, int $index) {
                if (is_array($item)) {
                    $label = trim((string) ($item['label'] ?? ''));
                    $key = trim((string) ($item['key'] ?? ''));
                } else {
                    $label = trim((string) $item);
                    $key = '';
                }

                if ($label === '') {
                    return null;
                }

                return [
                    'key' => self::normalizeKey($key !== '' ? $key : $label, 'item_' . ($index + 1)),
                    'label' => $label,
                ];
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();
    }

    protected static function normalizeKey(string $value, string $fallback): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        if ($normalized === '') {
            $normalized = Str::of($fallback)
                ->lower()
                ->ascii()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();
        }

        return $normalized !== '' ? $normalized : 'item';
    }

    protected static function uniqueKey(string $key, array &$usedKeys): string
    {
        $candidate = $key;
        $suffix = 2;

        while (in_array($candidate, $usedKeys, true)) {
            $candidate = $key . '_' . $suffix;
            $suffix++;
        }

        $usedKeys[] = $candidate;

        return $candidate;
    }

    protected static function flattenComparableValues(mixed $value): Collection
    {
        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
                ->map(fn ($item) => Str::lower(trim((string) $item)))
                ->values();
        }

        if (is_scalar($value) && trim((string) $value) !== '') {
            return collect([Str::lower(trim((string) $value))]);
        }

        return collect();
    }

    protected static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }

        return $value === null || trim((string) $value) === '';
    }

    protected static function normalizePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}

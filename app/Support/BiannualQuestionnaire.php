<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Normalizes and evaluates configurable bi-annual site-visit questionnaires.
 *
 * The class deliberately has no model, database, authentication, request, or
 * filesystem dependencies. Controllers can persist the returned snapshot and
 * normalized answers in whichever aggregate owns the questionnaire.
 */
final class BiannualQuestionnaire
{
    public const SNAPSHOT_VERSION = 1;

    public const NOT_APPLICABLE = 'na';

    public const QUESTION_TYPES = [
        'scored_assessment',
        'short_text',
        'long_text',
        'single_choice',
        'multiple_choice',
        'yes_no_na',
        'number',
        'percentage',
        'date',
        'evidence',
        'information',
    ];

    private const TYPE_ALIASES = [
        'assessment' => 'scored_assessment',
        'score' => 'scored_assessment',
        'scored' => 'scored_assessment',
        'scored_finding' => 'scored_assessment',
        'rating' => 'scored_assessment',
        'text' => 'short_text',
        'string' => 'short_text',
        'textarea' => 'long_text',
        'narrative' => 'long_text',
        'select' => 'single_choice',
        'radio' => 'single_choice',
        'multiselect' => 'multiple_choice',
        'checkbox' => 'multiple_choice',
        'checkboxes' => 'multiple_choice',
        'yes_no' => 'yes_no_na',
        'boolean' => 'yes_no_na',
        'numeric' => 'number',
        'decimal' => 'number',
        'percent' => 'percentage',
        'file' => 'evidence',
        'image' => 'evidence',
        'attachment' => 'evidence',
        'note' => 'information',
        'heading' => 'information',
        'instructions' => 'information',
    ];

    private const VISIBILITY_OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'contains',
        'not_contains',
        'answered',
        'not_answered',
        'greater_than',
        'greater_than_or_equal',
        'less_than',
        'less_than_or_equal',
    ];

    /**
     * @return list<string>
     */
    public function supportedQuestionTypes(): array
    {
        return self::QUESTION_TYPES;
    }

    /**
     * Convert a loosely structured template into one canonical hierarchy:
     * template -> sections -> topics -> questions.
     *
     * Direct questions are retained inside an implicit "General" topic. Keys
     * supplied by the form builder are preferred; generated keys are
     * deterministic and unique within the template.
     */
    public function normalizeTemplate(array $template): array
    {
        if (isset($template['template']) && is_array($template['template'])) {
            if (
                isset($template['snapshot_version'])
                || ! isset($template['sections'])
                || ! is_array($template['sections'])
            ) {
                $template = $template['template'];
            } else {
                $template = array_merge($template['template'], [
                    'sections' => $template['sections'],
                ]);
            }
        }

        $usedSectionKeys = [];
        $usedTopicKeys = [];
        $usedQuestionKeys = [];
        $usedTemplateKeys = [];

        $templateTitle = $this->stringValue(
            $template['title'] ?? $template['name'] ?? 'Bi-Annual Site Visit Questionnaire'
        );
        $ratingScale = $this->normalizeRatingScale($template['rating_scale'] ?? []);
        $responseSchema = is_array($template['response_schema'] ?? null)
            ? $this->serializableArray($template['response_schema'])
            : [];
        $templateKey = $this->uniqueKey(
            $template['key'] ?? $template['code'] ?? $template['id'] ?? $templateTitle,
            'biannual_site_visit_questionnaire',
            $usedTemplateKeys
        );

        $rawSections = $this->listValue($template['sections'] ?? []);

        if ($rawSections === []) {
            $rootTopics = $this->listValue($template['topics'] ?? []);
            $rootQuestions = $this->listValue($template['questions'] ?? []);

            if ($rootTopics !== [] || $rootQuestions !== []) {
                $rawSections = [[
                    'key' => 'general',
                    'title' => 'General',
                    'topics' => $rootTopics,
                    'questions' => $rootQuestions,
                ]];
            }
        }

        $sections = [];
        foreach ($rawSections as $sectionIndex => $rawSection) {
            if (! is_array($rawSection)) {
                continue;
            }

            $sectionTitle = $this->stringValue(
                $rawSection['title'] ?? $rawSection['name'] ?? ('Section '.($sectionIndex + 1))
            );
            $sectionKey = $this->uniqueKey(
                $rawSection['key']
                    ?? $rawSection['section_key']
                    ?? $rawSection['code']
                    ?? $rawSection['id']
                    ?? $sectionTitle,
                'section_'.($sectionIndex + 1),
                $usedSectionKeys
            );

            $rawTopics = $this->listValue($rawSection['topics'] ?? []);
            $directQuestions = $this->listValue($rawSection['questions'] ?? []);

            if ($directQuestions !== []) {
                array_unshift($rawTopics, [
                    'key' => $sectionKey.'_general',
                    'title' => 'General',
                    'questions' => $directQuestions,
                    'is_implicit' => true,
                ]);
            }

            $topics = [];
            foreach ($rawTopics as $topicIndex => $rawTopic) {
                if (! is_array($rawTopic)) {
                    continue;
                }

                $topicTitle = $this->stringValue(
                    $rawTopic['title'] ?? $rawTopic['name'] ?? ('Topic '.($topicIndex + 1))
                );
                $topicKey = $this->uniqueKey(
                    $rawTopic['key']
                        ?? $rawTopic['topic_key']
                        ?? $rawTopic['code']
                        ?? $rawTopic['id']
                        ?? $topicTitle,
                    $sectionKey.'_topic_'.($topicIndex + 1),
                    $usedTopicKeys
                );

                $questions = [];
                foreach ($this->listValue($rawTopic['questions'] ?? []) as $questionIndex => $rawQuestion) {
                    if (! is_array($rawQuestion)) {
                        continue;
                    }

                    $questions[] = $this->normalizeQuestion(
                        $rawQuestion,
                        $sectionKey,
                        $topicKey,
                        $questionIndex,
                        $usedQuestionKeys,
                        $ratingScale,
                        $responseSchema
                    );
                }

                $topics[] = [
                    'key' => $topicKey,
                    'title' => $topicTitle !== '' ? $topicTitle : 'Topic '.($topicIndex + 1),
                    'description' => $this->stringValue(
                        $rawTopic['description'] ?? $rawTopic['guidance'] ?? $rawTopic['intro'] ?? ''
                    ),
                    'weight' => $this->weightValue(
                        $rawTopic['weight'] ?? ($rawTopic['settings']['weight'] ?? 1)
                    ),
                    'visibility' => $this->normalizeLogic(
                        $rawTopic['visibility'] ?? $this->legacyVisibility($rawTopic)
                    ),
                    'is_implicit' => (bool) ($rawTopic['is_implicit'] ?? false),
                    'questions' => $questions,
                ];
            }

            $sections[] = [
                'key' => $sectionKey,
                'title' => $sectionTitle !== '' ? $sectionTitle : 'Section '.($sectionIndex + 1),
                'description' => $this->stringValue(
                    $rawSection['description'] ?? $rawSection['guidance'] ?? $rawSection['intro'] ?? ''
                ),
                'weight' => $this->weightValue(
                    $rawSection['weight'] ?? ($rawSection['settings']['weight'] ?? 1)
                ),
                'visibility' => $this->normalizeLogic(
                    $rawSection['visibility'] ?? $this->legacyVisibility($rawSection)
                ),
                'topics' => $topics,
            ];
        }

        return [
            'schema_version' => self::SNAPSHOT_VERSION,
            'key' => $templateKey,
            'title' => $templateTitle !== '' ? $templateTitle : 'Bi-Annual Site Visit Questionnaire',
            'description' => $this->stringValue(
                $template['description'] ?? $template['intro'] ?? ''
            ),
            'instructions' => $this->stringValue($template['instructions'] ?? ''),
            'version' => max(1, (int) ($template['version'] ?? 1)),
            'rating_scale' => $ratingScale,
            'response_schema' => $responseSchema,
            'sections' => $sections,
        ];
    }

    /**
     * Build a data-only, versioned snapshot. PHP arrays use copy-on-write, so
     * later mutations of the source template cannot change this result.
     */
    public function buildSnapshot(
        array $template,
        ?DateTimeInterface $capturedAt = null
    ): array {
        $normalized = $this->normalizeTemplate($template);
        $questions = $this->flattenQuestions($normalized);
        $capturedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $capturedAt = DateTimeImmutable::createFromInterface($capturedAt)
            ->setTimezone(new DateTimeZone('UTC'));

        return [
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'captured_at' => $capturedAt->format(DATE_ATOM),
            'fingerprint' => $this->fingerprint($normalized),
            'counts' => [
                'sections' => count($normalized['sections']),
                'topics' => array_sum(array_map(
                    static fn (array $section): int => count($section['topics']),
                    $normalized['sections']
                )),
                'questions' => count($questions),
                'scored_questions' => count(array_filter(
                    $questions,
                    static fn (array $question): bool => $question['type'] === 'scored_assessment'
                )),
            ],
            'template' => $normalized,
        ];
    }

    /**
     * A deterministic digest of the normalized schema, excluding capture time.
     */
    public function fingerprint(array $templateOrSnapshot): string
    {
        $template = $this->schema($templateOrSnapshot);

        return hash('sha256', (string) json_encode(
            $template,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function flattenQuestions(array $templateOrSnapshot): array
    {
        $template = $this->schema($templateOrSnapshot);
        $questions = [];

        foreach ($template['sections'] as $sectionIndex => $section) {
            foreach ($section['topics'] as $topicIndex => $topic) {
                foreach ($topic['questions'] as $questionIndex => $question) {
                    $questions[] = array_merge($question, [
                        'section_key' => $section['key'],
                        'section_title' => $section['title'],
                        'section_weight' => $section['weight'],
                        'section_index' => $sectionIndex,
                        'topic_key' => $topic['key'],
                        'topic_title' => $topic['title'],
                        'topic_weight' => $topic['weight'],
                        'topic_index' => $topicIndex,
                        'question_index' => $questionIndex,
                    ]);
                }
            }
        }

        return $questions;
    }

    /**
     * Normalize one raw response according to a canonical or template-like
     * question definition.
     */
    public function normalizeAnswer(array $question, mixed $rawValue): mixed
    {
        $type = $this->questionType($question['type'] ?? 'short_text');

        return match ($type) {
            'information' => null,
            'short_text', 'long_text' => $this->normalizeText($rawValue),
            'single_choice' => $this->normalizeSingleChoice($question, $rawValue),
            'multiple_choice' => $this->normalizeMultipleChoice($question, $rawValue),
            'yes_no_na' => $this->normalizeYesNoNa($rawValue),
            'number', 'percentage' => $this->normalizeNumber($rawValue),
            'date' => $this->normalizeDate($rawValue),
            'evidence' => $this->normalizeEvidence($rawValue),
            'scored_assessment' => $this->normalizeScore($question, $rawValue),
            default => $this->normalizeText($rawValue),
        };
    }

    /**
     * Normalize a flat answer map. By default stale answers to hidden questions
     * and presentation-only information blocks are omitted.
     *
     * @return array<string, mixed>
     */
    public function normalizeAnswers(
        array $templateOrSnapshot,
        array $answers,
        bool $onlyVisible = true
    ): array {
        $prepared = $this->prepareAnswers($templateOrSnapshot, $answers);
        $result = [];

        foreach ($prepared['questions'] as $question) {
            $key = $question['key'];

            if ($question['type'] === 'information') {
                continue;
            }

            if ($onlyVisible && ! ($prepared['visibility'][$key] ?? false)) {
                continue;
            }

            $result[$key] = $prepared['all_answers'][$key] ?? null;
        }

        return $result;
    }

    /**
     * Validate one answer without requiring an HTTP request or Laravel
     * validator. Pass normalized prior answers for conditional required rules.
     *
     * @return array{value: mixed, valid: bool, errors: list<string>}
     */
    public function validateAnswer(
        array $question,
        mixed $rawValue,
        bool $enforceRequired = true,
        array $contextAnswers = []
    ): array {
        $type = $this->questionType($question['type'] ?? 'short_text');
        $question['type'] = $type;
        $value = $this->normalizeAnswer($question, $rawValue);
        $errors = [];
        $required = $this->isQuestionRequired($question, $contextAnswers);

        if ($type === 'information') {
            return ['value' => null, 'valid' => true, 'errors' => []];
        }

        if ($this->isBlank($value)) {
            if ($enforceRequired && $required) {
                $errors[] = 'This question is required.';
            }

            return ['value' => $value, 'valid' => $errors === [], 'errors' => $errors];
        }

        $rules = is_array($question['rules'] ?? null) ? $question['rules'] : [];

        if (in_array($type, ['short_text', 'long_text'], true)) {
            $length = $this->textLength((string) $value);
            $minimum = $this->nullableInteger($rules['min_length'] ?? null);
            $maximum = $this->nullableInteger($rules['max_length'] ?? null);

            if ($minimum !== null && $length < $minimum) {
                $errors[] = "Enter at least {$minimum} characters.";
            }
            if ($maximum !== null && $length > $maximum) {
                $errors[] = "Enter no more than {$maximum} characters.";
            }
        }

        if ($type === 'single_choice') {
            $allowed = $this->optionValues($question);
            if ($allowed !== [] && ! $this->containsComparable($allowed, $value)) {
                $errors[] = 'Select a configured option.';
            }
        }

        if ($type === 'multiple_choice') {
            $selected = is_array($value) ? $value : [];
            $allowed = $this->optionValues($question);
            $minimum = $this->nullableInteger($rules['min_selections'] ?? null);
            $maximum = $this->nullableInteger($rules['max_selections'] ?? null);

            foreach ($selected as $selection) {
                if ($allowed !== [] && ! $this->containsComparable($allowed, $selection)) {
                    $errors[] = 'One or more selected options are not configured.';
                    break;
                }
            }

            if ($minimum !== null && count($selected) < $minimum) {
                $errors[] = "Select at least {$minimum} option(s).";
            }
            if ($maximum !== null && count($selected) > $maximum) {
                $errors[] = "Select no more than {$maximum} option(s).";
            }
        }

        if ($type === 'yes_no_na' && ! in_array($value, ['yes', 'no', self::NOT_APPLICABLE], true)) {
            $errors[] = 'Select Yes, No, or N/A.';
        }

        if (in_array($type, ['number', 'percentage'], true)) {
            if (! is_int($value) && ! is_float($value)) {
                $errors[] = 'Enter a valid number.';
            } else {
                $minimum = $this->nullableFloat(
                    $rules['min'] ?? ($type === 'percentage' ? 0 : null)
                );
                $maximum = $this->nullableFloat(
                    $rules['max'] ?? ($type === 'percentage' ? 100 : null)
                );

                if ($minimum !== null && $value < $minimum) {
                    $errors[] = "Enter a value of at least {$this->formatNumber($minimum)}.";
                }
                if ($maximum !== null && $value > $maximum) {
                    $errors[] = "Enter a value no greater than {$this->formatNumber($maximum)}.";
                }
            }
        }

        if ($type === 'date' && ! $this->isIsoDate((string) $value)) {
            $errors[] = 'Enter a valid date.';
        }

        if ($type === 'evidence') {
            $items = is_array($value) ? $value : [];
            $minimum = $this->nullableInteger($rules['min_files'] ?? null);
            $maximum = $this->nullableInteger($rules['max_files'] ?? null);

            if ($minimum !== null && count($items) < $minimum) {
                $errors[] = "Attach at least {$minimum} evidence file(s).";
            }
            if ($maximum !== null && count($items) > $maximum) {
                $errors[] = "Attach no more than {$maximum} evidence file(s).";
            }

            $allowedExtensions = $this->stringList($rules['allowed_extensions'] ?? []);
            $maxMegabytes = $this->nullableFloat($rules['max_file_size_mb'] ?? null);

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $filename = $this->stringValue(
                    $item['original_name'] ?? $item['name'] ?? $item['path'] ?? ''
                );
                $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
                if (
                    $allowedExtensions !== []
                    && $extension !== ''
                    && ! in_array($extension, array_map('strtolower', $allowedExtensions), true)
                ) {
                    $errors[] = 'One or more evidence files use a disallowed extension.';
                    break;
                }

                if (
                    $maxMegabytes !== null
                    && is_numeric($item['size'] ?? null)
                    && (float) $item['size'] > ($maxMegabytes * 1024 * 1024)
                ) {
                    $errors[] = "Evidence files must not exceed {$this->formatNumber($maxMegabytes)} MB.";
                    break;
                }
            }
        }

        if (
            $type === 'scored_assessment'
            && $value === self::NOT_APPLICABLE
            && ! ($question['allows_na'] ?? true)
        ) {
            $errors[] = 'Not applicable is not allowed for this assessment.';
        }

        if ($type === 'scored_assessment' && $value !== self::NOT_APPLICABLE) {
            if (! is_int($value) && ! is_float($value)) {
                $errors[] = 'Select or enter a valid assessment score.';
            } else {
                $score = is_array($question['score'] ?? null) ? $question['score'] : [];
                $minimum = (float) ($score['min'] ?? 0);
                $maximum = (float) ($score['max'] ?? 5);

                if ($value < $minimum || $value > $maximum) {
                    $errors[] = 'Enter a score between '
                        .$this->formatNumber($minimum).' and '.$this->formatNumber($maximum).'.';
                }

                $configuredScores = $this->configuredOptionScores($question);
                if (
                    $configuredScores !== []
                    && ! $this->containsComparable($configuredScores, $value)
                ) {
                    $errors[] = 'Select a configured assessment score.';
                }
            }
        }

        return [
            'value' => $value,
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Validate every currently visible answer. Set $enforceRequired to false
     * when saving an incomplete draft; type and range errors are still returned.
     *
     * @return array{
     *   valid: bool,
     *   answers: array<string, mixed>,
     *   all_answers: array<string, mixed>,
     *   errors: array<string, list<string>>,
     *   visible_question_keys: list<string>,
     *   hidden_question_keys: list<string>
     * }
     */
    public function validateAnswers(
        array $templateOrSnapshot,
        array $answers,
        bool $enforceRequired = true
    ): array {
        $prepared = $this->prepareAnswers($templateOrSnapshot, $answers);
        $errors = [];
        $visibleAnswers = [];
        $visibleKeys = [];
        $hiddenKeys = [];

        foreach ($prepared['questions'] as $question) {
            $key = $question['key'];
            $visible = (bool) ($prepared['visibility'][$key] ?? false);

            if (! $visible) {
                $hiddenKeys[] = $key;

                continue;
            }

            $visibleKeys[] = $key;
            if ($question['type'] === 'information') {
                continue;
            }

            $validation = $this->validateAnswer(
                $question,
                $prepared['all_answers'][$key] ?? null,
                $enforceRequired,
                $prepared['visible_answers']
            );
            $visibleAnswers[$key] = $validation['value'];

            if (! $validation['valid']) {
                $errors[$key] = $validation['errors'];
            }
        }

        return [
            'valid' => $errors === [],
            'answers' => $visibleAnswers,
            'all_answers' => $prepared['all_answers'],
            'errors' => $errors,
            'visible_question_keys' => $visibleKeys,
            'hidden_question_keys' => $hiddenKeys,
        ];
    }

    /**
     * Evaluate a normalized visibility/required condition for one question.
     */
    public function isQuestionVisible(array $question, array $answers): bool
    {
        return $this->matchesLogic(
            $this->normalizeLogic(
                $question['visibility'] ?? $this->legacyVisibility($question)
            ),
            $answers
        );
    }

    /**
     * Determine whether the question's unconditional or conditional required
     * rule applies to the supplied answer context.
     */
    public function isQuestionRequired(array $question, array $answers): bool
    {
        if ((bool) ($question['required'] ?? false)) {
            return true;
        }

        $requiredWhen = $this->normalizeLogic($question['required_when'] ?? []);

        return $requiredWhen['conditions'] !== []
            && $this->matchesLogic($requiredWhen, $answers);
    }

    /**
     * @return array{
     *   question_count: int,
     *   visible_question_count: int,
     *   answerable_question_count: int,
     *   answered_question_count: int,
     *   unanswered_question_count: int,
     *   required_question_count: int,
     *   required_answered_count: int,
     *   required_missing_keys: list<string>,
     *   invalid_question_keys: list<string>,
     *   completion_percentage: float,
     *   required_completion_percentage: float,
     *   is_complete: bool
     * }
     */
    public function completionStats(array $templateOrSnapshot, array $answers): array
    {
        $prepared = $this->prepareAnswers($templateOrSnapshot, $answers);
        $validation = $this->validateAnswers($templateOrSnapshot, $answers, true);
        $answerable = 0;
        $answered = 0;
        $required = 0;
        $requiredAnswered = 0;
        $requiredMissing = [];
        $visibleCount = 0;

        foreach ($prepared['questions'] as $question) {
            $key = $question['key'];
            if (! ($prepared['visibility'][$key] ?? false)) {
                continue;
            }

            $visibleCount++;
            if ($question['type'] === 'information') {
                continue;
            }

            $answerable++;
            $value = $prepared['all_answers'][$key] ?? null;
            $hasAnswer = ! $this->isBlank($value);
            $valid = ! isset($validation['errors'][$key]);
            $isRequired = $this->isQuestionRequired($question, $prepared['visible_answers']);

            if ($hasAnswer) {
                $answered++;
            }

            if ($isRequired) {
                $required++;
                if ($hasAnswer && $valid) {
                    $requiredAnswered++;
                } else {
                    $requiredMissing[] = $key;
                }
            }
        }

        return [
            'question_count' => count($prepared['questions']),
            'visible_question_count' => $visibleCount,
            'answerable_question_count' => $answerable,
            'answered_question_count' => $answered,
            'unanswered_question_count' => max(0, $answerable - $answered),
            'required_question_count' => $required,
            'required_answered_count' => $requiredAnswered,
            'required_missing_keys' => array_values(array_unique($requiredMissing)),
            'invalid_question_keys' => array_keys($validation['errors']),
            'completion_percentage' => $this->percentage($answered, $answerable),
            'required_completion_percentage' => $this->percentage($requiredAnswered, $required),
            'is_complete' => $validation['valid'],
        ];
    }

    /**
     * Calculate weighted topic, section, and overall percentages.
     *
     * Only visible, weighted scored_assessment questions with a valid applicable
     * score participate. Missing and N/A values are removed from both the
     * numerator and denominator.
     */
    public function aggregateScores(array $templateOrSnapshot, array $answers): array
    {
        $template = $this->schema($templateOrSnapshot);
        $prepared = $this->prepareAnswers($template, $answers);
        $sections = [];
        $overallWeightedPoints = 0.0;
        $overallWeight = 0.0;
        $applicableQuestions = 0;
        $excludedQuestions = 0;

        foreach ($template['sections'] as $section) {
            $topicResults = [];
            $sectionWeightedPoints = 0.0;
            $sectionWeightTotal = 0.0;
            $sectionApplicable = 0;
            $sectionExcluded = 0;

            foreach ($section['topics'] as $topic) {
                $questionResults = [];
                $topicWeightedPoints = 0.0;
                $topicQuestionWeight = 0.0;
                $topicExcluded = [];

                foreach ($topic['questions'] as $question) {
                    if ($question['type'] !== 'scored_assessment') {
                        continue;
                    }

                    $key = $question['key'];
                    if (! ($prepared['visibility'][$key] ?? false)) {
                        continue;
                    }

                    $value = $prepared['all_answers'][$key] ?? null;
                    $questionWeight = (float) ($question['weight'] ?? 1);
                    $scoreConfig = is_array($question['score'] ?? null) ? $question['score'] : [];
                    $minimum = (float) ($scoreConfig['min'] ?? 0);
                    $maximum = (float) ($scoreConfig['max'] ?? 5);
                    $direction = $question['scoring_direction'] ?? 'positive';

                    if ($direction === 'none' || $questionWeight <= 0) {
                        $topicExcluded[] = [
                            'key' => $key,
                            'reason' => 'zero_weight',
                        ];

                        continue;
                    }

                    if ($this->isBlank($value)) {
                        $topicExcluded[] = [
                            'key' => $key,
                            'reason' => 'not_answered',
                        ];

                        continue;
                    }

                    if ($value === self::NOT_APPLICABLE) {
                        $topicExcluded[] = [
                            'key' => $key,
                            'reason' => 'not_applicable',
                        ];

                        continue;
                    }

                    $validation = $this->validateAnswer(
                        $question,
                        $value,
                        false,
                        $prepared['visible_answers']
                    );

                    if (
                        ! $validation['valid']
                        || ! is_numeric($validation['value'])
                        || $maximum <= $minimum
                    ) {
                        $topicExcluded[] = [
                            'key' => $key,
                            'reason' => 'invalid_score',
                        ];

                        continue;
                    }

                    $score = (float) $validation['value'];
                    $questionPercentage = $direction === 'negative'
                        ? (($maximum - $score) / ($maximum - $minimum)) * 100
                        : (($score - $minimum) / ($maximum - $minimum)) * 100;
                    $questionPercentage = max(0, min(100, $questionPercentage));
                    $topicWeightedPoints += $questionPercentage * $questionWeight;
                    $topicQuestionWeight += $questionWeight;
                    $questionResults[] = [
                        'key' => $key,
                        'label' => $question['label'],
                        'score' => $this->rounded($score),
                        'maximum_score' => $this->rounded($maximum),
                        'weight' => $this->rounded($questionWeight),
                        'scoring_direction' => $direction,
                        'percentage' => $this->rounded($questionPercentage),
                    ];
                }

                $topicPercentage = $topicQuestionWeight > 0
                    ? $topicWeightedPoints / $topicQuestionWeight
                    : null;
                $topicWeight = (float) ($topic['weight'] ?? 1);

                if ($topicPercentage !== null && $topicWeight > 0) {
                    $sectionWeightedPoints += $topicPercentage * $topicWeight;
                    $sectionWeightTotal += $topicWeight;
                }

                $sectionApplicable += count($questionResults);
                $sectionExcluded += count($topicExcluded);
                $topicResults[] = [
                    'key' => $topic['key'],
                    'title' => $topic['title'],
                    'weight' => $this->rounded($topicWeight),
                    'percentage' => $topicPercentage === null ? null : $this->rounded($topicPercentage),
                    'applicable_question_count' => count($questionResults),
                    'excluded_question_count' => count($topicExcluded),
                    'questions' => $questionResults,
                    'excluded_questions' => $topicExcluded,
                ];
            }

            $sectionPercentage = $sectionWeightTotal > 0
                ? $sectionWeightedPoints / $sectionWeightTotal
                : null;
            $sectionWeight = (float) ($section['weight'] ?? 1);

            if ($sectionPercentage !== null && $sectionWeight > 0) {
                $overallWeightedPoints += $sectionPercentage * $sectionWeight;
                $overallWeight += $sectionWeight;
            }

            $applicableQuestions += $sectionApplicable;
            $excludedQuestions += $sectionExcluded;
            $sections[] = [
                'key' => $section['key'],
                'title' => $section['title'],
                'weight' => $this->rounded($sectionWeight),
                'percentage' => $sectionPercentage === null ? null : $this->rounded($sectionPercentage),
                'applicable_question_count' => $sectionApplicable,
                'excluded_question_count' => $sectionExcluded,
                'topics' => $topicResults,
            ];
        }

        $overallPercentage = $overallWeight > 0
            ? $overallWeightedPoints / $overallWeight
            : null;

        return [
            'sections' => $sections,
            'overall' => [
                'percentage' => $overallPercentage === null ? null : $this->rounded($overallPercentage),
                'applicable_section_count' => count(array_filter(
                    $sections,
                    static fn (array $section): bool => $section['percentage'] !== null
                )),
                'applicable_question_count' => $applicableQuestions,
                'excluded_question_count' => $excludedQuestions,
                'weight' => $this->rounded($overallWeight),
            ],
        ];
    }

    private function normalizeQuestion(
        array $rawQuestion,
        string $sectionKey,
        string $topicKey,
        int $questionIndex,
        array &$usedQuestionKeys,
        array $defaultRatingScale = [],
        array $defaultResponseSchema = []
    ): array {
        $type = $this->questionType(
            $rawQuestion['type']
                ?? $rawQuestion['question_type']
                ?? $rawQuestion['response_type']
                ?? 'short_text'
        );
        $label = $this->stringValue(
            $rawQuestion['label']
                ?? $rawQuestion['question']
                ?? $rawQuestion['prompt']
                ?? $rawQuestion['title']
                ?? ($type === 'information' ? ($rawQuestion['content'] ?? '') : '')
        );
        $questionKey = $this->uniqueKey(
            $rawQuestion['key']
                ?? $rawQuestion['question_key']
                ?? $rawQuestion['code']
                ?? $rawQuestion['id']
                ?? $label,
            $sectionKey.'_'.$topicKey.'_question_'.($questionIndex + 1),
            $usedQuestionKeys
        );

        $options = $this->normalizeOptions($rawQuestion['options'] ?? $rawQuestion['choices'] ?? []);
        if ($type === 'scored_assessment' && $options === []) {
            $options = $this->normalizeOptions($defaultRatingScale['options'] ?? []);
        }
        if ($type === 'scored_assessment') {
            $options = array_map(function (array $option): array {
                if (! array_key_exists('score', $option) && is_numeric($option['value'] ?? null)) {
                    $option['score'] = $this->numericValue($option['value']);
                }

                return $option;
            }, $options);
        }
        if ($type === 'yes_no_na' && $options === []) {
            $options = [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
                ['value' => self::NOT_APPLICABLE, 'label' => 'N/A'],
            ];
        }

        $rawRules = $rawQuestion['rules'] ?? $rawQuestion['validation'] ?? [];
        $rules = $this->normalizeRules(is_array($rawRules) ? $rawRules : [], $type);
        $optionScores = array_values(array_filter(
            array_map(
                static fn (array $option): mixed => $option['score'] ?? null,
                $options
            ),
            static fn (mixed $score): bool => is_int($score) || is_float($score)
        ));
        $scoreConfig = is_array($rawQuestion['score'] ?? null)
            ? $rawQuestion['score']
            : [];
        $scoreMinimum = $this->nullableFloat(
            $scoreConfig['min']
                ?? $rawQuestion['min_score']
                ?? $defaultRatingScale['min']
                ?? 0
        ) ?? 0.0;
        $scoreMaximum = $this->nullableFloat(
            $scoreConfig['max']
                ?? $rawQuestion['max_score']
                ?? $rawQuestion['maximum_score']
                ?? ($optionScores !== [] ? max($optionScores) : null)
                ?? $defaultRatingScale['max']
                ?? $rules['max']
                ?? 5
        ) ?? 5.0;

        if ($scoreMaximum < $scoreMinimum) {
            [$scoreMinimum, $scoreMaximum] = [$scoreMaximum, $scoreMinimum];
        }
        $scoringDirection = strtolower(trim((string) (
            $rawQuestion['scoring_direction']
                ?? data_get($rawQuestion, 'settings.scoring_direction', 'positive')
        )));
        if (! in_array($scoringDirection, ['positive', 'negative', 'none'], true)) {
            $scoringDirection = 'positive';
        }
        $allowsNa = $type === 'scored_assessment'
            ? $this->booleanValue(
                $rawQuestion['allows_na']
                    ?? $rawQuestion['is_na_allowed']
                    ?? true
            )
            : $type === 'yes_no_na';

        return [
            'key' => $questionKey,
            'label' => $label !== '' ? $label : 'Question '.($questionIndex + 1),
            'type' => $type,
            'description' => $this->stringValue(
                $rawQuestion['description'] ?? $rawQuestion['hint'] ?? $rawQuestion['guidance'] ?? ''
            ),
            'content' => $type === 'information'
                ? $this->stringValue(
                    $rawQuestion['content'] ?? $rawQuestion['text'] ?? $rawQuestion['description'] ?? $label
                )
                : '',
            'required' => $type !== 'information'
                && $this->booleanValue(
                    $rawQuestion['required']
                        ?? $rawQuestion['is_required']
                        ?? $rules['required']
                        ?? false
                ),
            'required_when' => $type !== 'information'
                ? $this->normalizeLogic(
                    $rawQuestion['required_when']
                        ?? data_get($rawQuestion, 'settings.required_when', [])
                )
                : $this->emptyLogic(),
            'allows_na' => $allowsNa,
            'options' => $options,
            'rules' => $rules,
            'visibility' => $this->normalizeLogic(
                $rawQuestion['visibility'] ?? $this->legacyVisibility($rawQuestion)
            ),
            'weight' => $type === 'scored_assessment' && $scoringDirection !== 'none'
                ? $this->weightValue(
                    $rawQuestion['weight']
                        ?? $rawQuestion['score_weight']
                        ?? $scoreConfig['weight']
                        ?? 1
                )
                : 0.0,
            'scoring_direction' => $scoringDirection,
            'score' => $type === 'scored_assessment' ? [
                'min' => $this->rounded($scoreMinimum),
                'max' => $this->rounded($scoreMaximum),
            ] : null,
            'response_schema' => $type === 'scored_assessment'
                ? $this->serializableArray(
                    is_array($rawQuestion['response_schema'] ?? null)
                        ? $rawQuestion['response_schema']
                        : $defaultResponseSchema
                )
                : [],
            'section_key' => $sectionKey,
            'topic_key' => $topicKey,
        ];
    }

    private function normalizeOptions(mixed $rawOptions): array
    {
        if (is_string($rawOptions)) {
            $rawOptions = preg_split('/\r\n|\r|\n/', $rawOptions) ?: [];
        }

        if (! is_array($rawOptions)) {
            return [];
        }

        if (! $this->isList($rawOptions) && $this->looksLikeOption($rawOptions)) {
            $rawOptions = [$rawOptions];
        }

        $options = [];
        $seen = [];

        foreach ($rawOptions as $rawOption) {
            if (is_array($rawOption)) {
                $label = $this->stringValue(
                    $rawOption['label'] ?? $rawOption['name'] ?? $rawOption['title'] ?? $rawOption['value'] ?? ''
                );
                $value = $rawOption['value'] ?? $rawOption['key'] ?? $rawOption['id'] ?? $label;
                $score = $this->nullableFloat($rawOption['score'] ?? null);
            } elseif (is_scalar($rawOption)) {
                $label = trim((string) $rawOption);
                $value = $label;
                $score = null;
            } else {
                continue;
            }

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $value = trim((string) $value);
            $label = $label !== '' ? $label : $value;
            $identity = $this->comparable($value);
            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $option = [
                'value' => $value,
                'label' => $label,
            ];
            $description = is_array($rawOption)
                ? $this->stringValue($rawOption['description'] ?? $rawOption['help_text'] ?? '')
                : '';
            if ($description !== '') {
                $option['description'] = $description;
            }
            if ($score !== null) {
                $option['score'] = $this->rounded($score);
            }
            $isNotApplicable = is_array($rawOption)
                ? $this->booleanValue(
                    $rawOption['is_not_applicable']
                        ?? $rawOption['is_na']
                        ?? false
                )
                : false;
            if (
                $isNotApplicable
                || in_array($this->comparable($value), ['na', 'n/a', 'not applicable', 'not_applicable'], true)
                || in_array($this->comparable($label), ['na', 'n/a', 'not applicable', 'not_applicable'], true)
            ) {
                $option['is_not_applicable'] = true;
            }
            $options[] = $option;
        }

        return $options;
    }

    private function normalizeRatingScale(mixed $rawScale): array
    {
        if (! is_array($rawScale) || $rawScale === []) {
            return [];
        }

        $options = $this->normalizeOptions($rawScale['options'] ?? []);
        $numericValues = array_values(array_filter(array_map(
            static fn (array $option): mixed => is_numeric($option['value'] ?? null)
                ? (float) $option['value']
                : null,
            $options
        ), static fn (mixed $value): bool => is_float($value)));
        $minimum = $this->nullableFloat(
            $rawScale['min']
                ?? $rawScale['minimum']
                ?? ($numericValues !== [] ? min($numericValues) : null)
        );
        $maximum = $this->nullableFloat(
            $rawScale['max']
                ?? $rawScale['maximum']
                ?? ($numericValues !== [] ? max($numericValues) : null)
        );

        if ($minimum !== null && $maximum !== null && $maximum < $minimum) {
            [$minimum, $maximum] = [$maximum, $minimum];
        }

        return array_filter([
            'key' => $this->stringValue($rawScale['key'] ?? ''),
            'min' => $minimum === null ? null : $this->rounded($minimum),
            'max' => $maximum === null ? null : $this->rounded($maximum),
            'options' => $options,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function serializableArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $normalized[$key] = $this->serializableArray($item);

                continue;
            }

            if (is_scalar($item) || $item === null) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function normalizeRules(array $rules, string $type): array
    {
        $normalized = [
            'required' => $this->booleanValue($rules['required'] ?? false),
        ];

        foreach (['min', 'max', 'step', 'max_file_size_mb'] as $key) {
            $value = $this->nullableFloat($rules[$key] ?? null);
            if ($value !== null) {
                $normalized[$key] = $this->rounded($value);
            }
        }

        foreach ([
            'min_length',
            'max_length',
            'min_selections',
            'max_selections',
            'min_files',
            'max_files',
        ] as $key) {
            $value = $this->nullableInteger($rules[$key] ?? null);
            if ($value !== null) {
                $normalized[$key] = max(0, $value);
            }
        }

        $allowedExtensions = $this->stringList($rules['allowed_extensions'] ?? []);
        if ($allowedExtensions !== []) {
            $normalized['allowed_extensions'] = array_values(array_unique(array_map(
                static fn (string $extension): string => strtolower(ltrim($extension, '.')),
                $allowedExtensions
            )));
        }

        if ($type === 'percentage') {
            $normalized['min'] = $normalized['min'] ?? 0.0;
            $normalized['max'] = $normalized['max'] ?? 100.0;
        }

        return $normalized;
    }

    private function prepareAnswers(array $templateOrSnapshot, array $answers): array
    {
        $template = $this->schema($templateOrSnapshot);
        $questions = $this->flattenQuestions($template);
        $inputByKey = [];

        foreach ($answers as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $inputByKey[(string) $key] = $value;
            $inputByKey[$this->keyBase((string) $key, (string) $key)] = $value;
        }

        $allAnswers = [];
        foreach ($questions as $question) {
            $key = $question['key'];
            $rawValue = array_key_exists($key, $inputByKey)
                ? $inputByKey[$key]
                : null;
            $allAnswers[$key] = $this->normalizeAnswer($question, $rawValue);
        }

        $visibility = [];
        $visibleAnswers = [];

        foreach ($template['sections'] as $section) {
            $sectionVisible = $this->matchesLogic($section['visibility'], $visibleAnswers);

            foreach ($section['topics'] as $topic) {
                $topicVisible = $sectionVisible
                    && $this->matchesLogic($topic['visibility'], $visibleAnswers);

                foreach ($topic['questions'] as $question) {
                    $key = $question['key'];
                    $questionVisible = $topicVisible
                        && $this->matchesLogic($question['visibility'], $visibleAnswers);
                    $visibility[$key] = $questionVisible;

                    if ($questionVisible) {
                        $visibleAnswers[$key] = $allAnswers[$key] ?? null;
                    }
                }
            }
        }

        return [
            'template' => $template,
            'questions' => $questions,
            'all_answers' => $allAnswers,
            'visible_answers' => $visibleAnswers,
            'visibility' => $visibility,
        ];
    }

    private function normalizeLogic(mixed $rawLogic): array
    {
        if (! is_array($rawLogic) || $rawLogic === []) {
            return $this->emptyLogic();
        }

        $mode = strtolower(trim((string) ($rawLogic['mode'] ?? $rawLogic['match'] ?? 'all')));
        $mode = $mode === 'any' ? 'any' : 'all';
        $rawConditions = $rawLogic['conditions'] ?? null;

        if (! is_array($rawConditions)) {
            $rawConditions = [$rawLogic];
        } elseif (! $this->isList($rawConditions)) {
            $rawConditions = [$rawConditions];
        }

        $conditions = [];
        foreach ($rawConditions as $rawCondition) {
            if (! is_array($rawCondition)) {
                continue;
            }

            $questionKey = $this->stringValue(
                $rawCondition['question_key']
                    ?? $rawCondition['question']
                    ?? $rawCondition['depends_on']
                    ?? ''
            );
            if ($questionKey === '') {
                continue;
            }

            $operator = $this->normalizeOperator(
                $rawCondition['operator']
                    ?? (array_key_exists('values', $rawCondition) ? 'in' : 'equals')
            );
            $values = $rawCondition['values']
                ?? $rawCondition['value']
                ?? $rawCondition['show_if']
                ?? [];
            if (! is_array($values)) {
                $values = [$values];
            }

            $normalizedValues = [];
            foreach ($values as $value) {
                if (is_scalar($value) || $value === null) {
                    $normalizedValues[] = $value;
                }
            }

            if (
                ! in_array($operator, ['answered', 'not_answered'], true)
                && $normalizedValues === []
            ) {
                continue;
            }

            $conditions[] = [
                'question_key' => $this->keyBase($questionKey, $questionKey),
                'operator' => $operator,
                'values' => $normalizedValues,
            ];
        }

        return [
            'mode' => $mode,
            'conditions' => $conditions,
        ];
    }

    private function matchesLogic(array $logic, array $answers): bool
    {
        $conditions = $logic['conditions'] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        $results = array_map(
            fn (array $condition): bool => $this->matchesCondition($condition, $answers),
            $conditions
        );

        return ($logic['mode'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    private function matchesCondition(array $condition, array $answers): bool
    {
        $key = (string) ($condition['question_key'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equals');
        $candidate = $answers[$key] ?? null;
        $expected = is_array($condition['values'] ?? null) ? $condition['values'] : [];

        if ($operator === 'answered') {
            return ! $this->isBlank($candidate);
        }
        if ($operator === 'not_answered') {
            return $this->isBlank($candidate);
        }

        $candidateValues = $this->comparableValues($candidate);
        $expectedValues = array_map(fn (mixed $value): string => $this->comparable($value), $expected);
        $intersects = array_intersect($candidateValues, $expectedValues) !== [];

        if (in_array($operator, ['equals', 'in', 'contains'], true)) {
            return $intersects;
        }
        if (in_array($operator, ['not_equals', 'not_in', 'not_contains'], true)) {
            return ! $intersects;
        }

        $candidateNumber = $this->firstNumericValue($candidate);
        $expectedNumber = $this->firstNumericValue($expected);
        if ($candidateNumber === null || $expectedNumber === null) {
            return false;
        }

        return match ($operator) {
            'greater_than' => $candidateNumber > $expectedNumber,
            'greater_than_or_equal' => $candidateNumber >= $expectedNumber,
            'less_than' => $candidateNumber < $expectedNumber,
            'less_than_or_equal' => $candidateNumber <= $expectedNumber,
            default => false,
        };
    }

    private function legacyVisibility(array $item): array
    {
        $dependsOn = $item['depends_on'] ?? null;
        $showIf = $item['show_if'] ?? null;

        if ($dependsOn === null || $showIf === null) {
            return [];
        }

        return [
            'question_key' => $dependsOn,
            'values' => is_array($showIf) ? $showIf : [$showIf],
            'operator' => 'in',
        ];
    }

    private function emptyLogic(): array
    {
        return [
            'mode' => 'all',
            'conditions' => [],
        ];
    }

    private function normalizeOperator(mixed $operator): string
    {
        $operator = strtolower(trim((string) $operator));
        $aliases = [
            '=' => 'equals',
            '==' => 'equals',
            'is' => 'equals',
            'one_of' => 'in',
            '!=' => 'not_equals',
            '<>' => 'not_equals',
            'is_not' => 'not_equals',
            'not_one_of' => 'not_in',
            'includes' => 'contains',
            'excludes' => 'not_contains',
            'present' => 'answered',
            'empty' => 'not_answered',
            '>' => 'greater_than',
            '>=' => 'greater_than_or_equal',
            '<' => 'less_than',
            '<=' => 'less_than_or_equal',
        ];
        $operator = $aliases[$operator] ?? $operator;

        return in_array($operator, self::VISIBILITY_OPERATORS, true)
            ? $operator
            : 'equals';
    }

    private function normalizeSingleChoice(array $question, mixed $rawValue): mixed
    {
        if (! is_scalar($rawValue)) {
            return null;
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            return null;
        }

        $configuredOptions = $this->normalizedQuestionOptions($question);
        foreach ($configuredOptions as $option) {
            if (
                $this->comparable($option['value']) === $this->comparable($value)
                || $this->comparable($option['label']) === $this->comparable($value)
            ) {
                return $option['value'];
            }
        }

        return $value;
    }

    private function normalizeMultipleChoice(array $question, mixed $rawValue): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        $values = is_array($rawValue) ? $rawValue : [$rawValue];
        $normalized = [];

        foreach ($values as $value) {
            $choice = $this->normalizeSingleChoice($question, $value);
            if ($choice === null || $choice === '') {
                continue;
            }

            $identity = $this->comparable($choice);
            $normalized[$identity] = $choice;
        }

        return array_values($normalized);
    }

    private function normalizeYesNoNa(mixed $rawValue): ?string
    {
        if (is_bool($rawValue)) {
            return $rawValue ? 'yes' : 'no';
        }

        if (! is_scalar($rawValue)) {
            return null;
        }

        $value = $this->comparable($rawValue);

        return match ($value) {
            'yes', 'y', 'true', '1' => 'yes',
            'no', 'n', 'false' => 'no',
            'na', 'n/a', 'not applicable', 'not_applicable', '0' => self::NOT_APPLICABLE,
            default => trim((string) $rawValue) !== '' ? trim((string) $rawValue) : null,
        };
    }

    private function normalizeNumber(mixed $rawValue): int|float|string|null
    {
        if (! is_scalar($rawValue) || trim((string) $rawValue) === '') {
            return null;
        }

        $value = trim((string) $rawValue);
        if (! is_numeric($value)) {
            return $value;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private function normalizeDate(mixed $rawValue): ?string
    {
        if ($rawValue instanceof DateTimeInterface) {
            return $rawValue->format('Y-m-d');
        }

        if (! is_scalar($rawValue)) {
            return null;
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value
            ? $date->format('Y-m-d')
            : $value;
    }

    private function normalizeEvidence(mixed $rawValue): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        $items = is_array($rawValue) && $this->isList($rawValue)
            ? $rawValue
            : [$rawValue];
        $normalized = [];

        foreach ($items as $item) {
            if (is_scalar($item)) {
                $value = trim((string) $item);
                if ($value !== '') {
                    $normalized[] = ['reference' => $value];
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $evidence = [];
            foreach ([
                'id',
                'disk',
                'path',
                'stored_path',
                'url',
                'original_name',
                'name',
                'mime_type',
                'extension',
                'size',
                'sha256',
                'uploaded_at',
                'reference',
            ] as $key) {
                $value = $item[$key] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $evidence[$key] = $key === 'size' && is_numeric($value)
                        ? (int) $value
                        : trim((string) $value);
                }
            }

            if ($evidence !== []) {
                $normalized[] = $evidence;
            }
        }

        return $normalized;
    }

    private function normalizeScore(array $question, mixed $rawValue): int|float|string|null
    {
        if (is_array($rawValue)) {
            $rawValue = $rawValue['score'] ?? $rawValue['value'] ?? null;
        }

        if (! is_scalar($rawValue) || trim((string) $rawValue) === '') {
            return null;
        }

        $comparable = $this->comparable($rawValue);
        $allowsNa = (bool) ($question['allows_na'] ?? true);
        if (
            $allowsNa
            && in_array($comparable, ['na', 'n/a', 'not applicable', 'not_applicable'], true)
        ) {
            return self::NOT_APPLICABLE;
        }

        $configuredOptions = $this->normalizedQuestionOptions($question);
        foreach ($configuredOptions as $option) {
            if (
                $this->comparable($option['value']) === $comparable
                || $this->comparable($option['label']) === $comparable
            ) {
                if (isset($option['score']) && is_numeric($option['score'])) {
                    $optionScore = $this->numericValue($option['score']);

                    return $allowsNa && ($option['is_not_applicable'] ?? false)
                        ? self::NOT_APPLICABLE
                        : $optionScore;
                }

                $rawValue = $option['value'];
                break;
            }
        }

        if (! is_numeric($rawValue)) {
            return trim((string) $rawValue);
        }

        $score = (float) $rawValue;
        if (
            $allowsNa
            && $score <= 0
            && (
                $configuredOptions === []
                || (
                    collect($configuredOptions)->contains(
                        static fn (array $option): bool => (bool) ($option['is_not_applicable'] ?? false)
                    )
                    && ! collect($configuredOptions)->contains(
                        static fn (array $option): bool => ! ($option['is_not_applicable'] ?? false)
                            && is_numeric($option['score'] ?? null)
                            && (float) $option['score'] === 0.0
                    )
                )
            )
        ) {
            return self::NOT_APPLICABLE;
        }

        return floor($score) === $score ? (int) $score : $score;
    }

    private function normalizeText(mixed $rawValue): ?string
    {
        if (! is_scalar($rawValue)) {
            return null;
        }

        $value = trim((string) $rawValue);

        return $value !== '' ? $value : null;
    }

    private function schema(array $templateOrSnapshot): array
    {
        return $this->normalizeTemplate(
            isset($templateOrSnapshot['template']) && is_array($templateOrSnapshot['template'])
                ? $templateOrSnapshot['template']
                : $templateOrSnapshot
        );
    }

    private function questionType(mixed $type): string
    {
        $type = strtolower(trim((string) $type));
        $type = self::TYPE_ALIASES[$type] ?? $type;

        return in_array($type, self::QUESTION_TYPES, true)
            ? $type
            : 'short_text';
    }

    private function normalizedQuestionOptions(array $question): array
    {
        return $this->normalizeOptions($question['options'] ?? []);
    }

    private function optionValues(array $question): array
    {
        return array_values(array_map(
            static fn (array $option): string => (string) $option['value'],
            $this->normalizedQuestionOptions($question)
        ));
    }

    private function configuredOptionScores(array $question): array
    {
        return array_values(array_filter(array_map(
            static fn (array $option): mixed => $option['score'] ?? null,
            $this->normalizedQuestionOptions($question)
        ), static fn (mixed $score): bool => is_numeric($score)));
    }

    private function uniqueKey(mixed $candidate, string $fallback, array &$used): string
    {
        $base = $this->keyBase($candidate, $fallback);
        $key = $base;
        $suffix = 2;

        while (isset($used[$key])) {
            $key = $base.'_'.$suffix;
            $suffix++;
        }

        $used[$key] = true;

        return $key;
    }

    private function keyBase(mixed $candidate, string $fallback): string
    {
        $value = is_scalar($candidate) ? trim((string) $candidate) : '';
        if ($value === '') {
            $value = $fallback;
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9._-]+/', '_', $value) ?? '';
        $value = trim($value, '_.-');

        if ($value === '') {
            $value = preg_replace('/[^a-z0-9._-]+/', '_', strtolower($fallback)) ?? 'item';
            $value = trim($value, '_.-') ?: 'item';
        }

        if (ctype_digit(substr($value, 0, 1))) {
            $value = 'item_'.$value;
        }

        return $value;
    }

    private function weightValue(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 1.0;
        }

        return $this->rounded(max(0.0, (float) $value));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function numericValue(mixed $value): int|float
    {
        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function stringList(mixed $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\r\n|\r|\n|,/', $values) ?: [];
        }

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): string => $this->stringValue($value),
            $values
        ), static fn (string $value): bool => $value !== ''));
    }

    private function listValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return $this->isList($value) ? $value : array_values($value);
    }

    private function isList(array $value): bool
    {
        return array_is_list($value);
    }

    private function looksLikeOption(array $value): bool
    {
        return array_intersect(['value', 'label', 'name', 'title', 'score'], array_keys($value)) !== [];
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return $value === []
                || array_filter(
                    $value,
                    fn (mixed $item): bool => ! $this->isBlank($item)
                ) === [];
        }

        return false;
    }

    private function comparable(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    private function comparableValues(mixed $value): array
    {
        $values = is_array($value) ? $this->flattenScalars($value) : [$value];

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): string => $this->comparable($item),
            $values
        ), static fn (string $item): bool => $item !== '')));
    }

    private function flattenScalars(array $values): array
    {
        $result = [];
        array_walk_recursive($values, static function (mixed $value) use (&$result): void {
            if (is_scalar($value) || $value === null) {
                $result[] = $value;
            }
        });

        return $result;
    }

    private function containsComparable(array $haystack, mixed $needle): bool
    {
        $needle = $this->comparable($needle);

        foreach ($haystack as $value) {
            if ($this->comparable($value) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function firstNumericValue(mixed $value): ?float
    {
        $values = is_array($value) ? $this->flattenScalars($value) : [$value];

        foreach ($values as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return null;
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 100.0;
        }

        return $this->rounded(($numerator / $denominator) * 100);
    }

    private function rounded(float $value): float
    {
        return round($value, 2);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

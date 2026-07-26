<?php

namespace App\Services;

use App\Models\BiAnnualSiteVisitQuestion;
use App\Models\BiAnnualSiteVisitSection;
use App\Models\BiAnnualSiteVisitTemplate;
use App\Models\BiAnnualSiteVisitTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BiAnnualSiteVisitTemplateService
{
    public const RESPONSE_TYPES = [
        'scored_assessment' => 'Scored assessment (configurable scale)',
        'short_text' => 'Short text',
        'long_text' => 'Long narrative',
        'single_choice' => 'Single choice',
        'multiple_choice' => 'Multiple choice',
        'yes_no_na' => 'Yes / No / N/A',
        'number' => 'Number',
        'percentage' => 'Percentage',
        'date' => 'Date',
        'evidence' => 'Evidence reference',
        'information' => 'Information / guidance',
    ];

    /**
     * Replace a draft template structure with a normalized definition.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $definition
     */
    public function replaceStructure(
        BiAnnualSiteVisitTemplate $template,
        array $definition,
        ?string $actorId = null
    ): void {
        if (! $template->isDraft()) {
            throw ValidationException::withMessages([
                'template' => 'Published questionnaire versions cannot be changed. Duplicate this version first.',
            ]);
        }

        $sections = array_is_list($definition)
            ? $definition
            : ($definition['sections'] ?? []);

        if (! is_array($sections)) {
            throw ValidationException::withMessages([
                'structure' => 'The questionnaire structure must contain a sections array.',
            ]);
        }

        $storedRatingScale = $this->arrayValue(data_get($template->settings, 'rating_scale', []));
        $ratingScale = ! array_is_list($definition)
            ? $this->arrayValue($definition['rating_scale'] ?? $storedRatingScale)
            : $storedRatingScale;
        $ratingOptions = array_is_list($ratingScale)
            ? $ratingScale
            : $this->arrayValue(
                $ratingScale['options'] ?? $this->defaultRatingOptions()
            );
        $ratingOptionScores = collect($ratingOptions)
            ->map(fn (mixed $option): mixed => is_array($option)
                ? ($option['score'] ?? $option['value'] ?? null)
                : null)
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): float => (float) $score);
        $ratingMinimum = is_numeric($ratingScale['minimum'] ?? $ratingScale['min'] ?? null)
            ? (float) ($ratingScale['minimum'] ?? $ratingScale['min'])
            : $ratingOptionScores->min();
        $ratingMaximum = is_numeric($ratingScale['maximum'] ?? $ratingScale['max'] ?? null)
            ? (float) ($ratingScale['maximum'] ?? $ratingScale['max'])
            : $ratingOptionScores->max();
        $sections = $this->prepareStructure($sections);
        $usedQuestionKeys = [];

        DB::transaction(function () use (
            $template,
            $sections,
            $ratingOptions,
            $ratingMinimum,
            $ratingMaximum,
            $actorId,
            &$usedQuestionKeys
        ): void {
            $template->sections()->delete();

            foreach (array_values($sections) as $sectionIndex => $sectionData) {
                if (! is_array($sectionData)) {
                    continue;
                }

                $section = BiAnnualSiteVisitSection::create([
                    'template_id' => $template->id,
                    'section_key' => $sectionData['_resolved_key'],
                    'title' => $this->requiredText(
                        $sectionData['title'] ?? $sectionData['name'] ?? null,
                        'Section '.($sectionIndex + 1).' needs a title.'
                    ),
                    'description' => $this->nullableText($sectionData['description'] ?? null),
                    'guidance' => $this->nullableText($sectionData['guidance'] ?? null),
                    'settings' => [
                        ...$this->arrayValue($sectionData['settings'] ?? []),
                        'weight' => $this->weight($sectionData['weight'] ?? 1),
                    ],
                    'visibility' => $this->arrayValue(
                        $sectionData['visibility_rules'] ?? $sectionData['visibility'] ?? []
                    ),
                    'sort_order' => $sectionData['sort_order'] ?? $sectionData['order'] ?? $sectionIndex + 1,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                foreach (array_values($sectionData['topics'] ?? []) as $topicIndex => $topicData) {
                    if (! is_array($topicData)) {
                        continue;
                    }

                    $topic = BiAnnualSiteVisitTopic::create([
                        'section_id' => $section->id,
                        'topic_key' => $topicData['_resolved_key'],
                        'title' => $this->requiredText(
                            $topicData['title'] ?? $topicData['name'] ?? null,
                            'Topic '.($topicIndex + 1)." in {$section->title} needs a title."
                        ),
                        'description' => $this->nullableText($topicData['description'] ?? null),
                        'guidance' => $this->nullableText($topicData['guidance'] ?? null),
                        'settings' => [
                            ...$this->arrayValue($topicData['settings'] ?? []),
                            'weight' => $this->weight($topicData['weight'] ?? 1),
                        ],
                        'visibility' => $this->arrayValue(
                            $topicData['visibility_rules'] ?? $topicData['visibility'] ?? []
                        ),
                        'sort_order' => $topicData['sort_order'] ?? $topicData['order'] ?? $topicIndex + 1,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    foreach (array_values($topicData['questions'] ?? []) as $questionIndex => $questionData) {
                        if (! is_array($questionData)) {
                            continue;
                        }

                        $questionKey = $questionData['_resolved_key'];

                        if (isset($usedQuestionKeys[$questionKey])) {
                            throw ValidationException::withMessages([
                                'structure' => "Question key '{$questionKey}' is duplicated. Stable keys must be unique.",
                            ]);
                        }
                        $usedQuestionKeys[$questionKey] = true;

                        $responseType = $this->responseType(
                            $questionData['response_type']
                                ?? $questionData['question_type']
                                ?? $questionData['type']
                                ?? 'long_text'
                        );
                        $isScored = $responseType === 'scored_assessment';
                        $allowsNa = (bool) (
                            $questionData['allows_na']
                                ?? $questionData['is_na_allowed']
                                ?? ($isScored || $responseType === 'yes_no_na')
                        );
                        $options = $this->arrayValue($questionData['options'] ?? []);
                        $hasQuestionOptions = $options !== [];

                        if ($isScored && $options === []) {
                            $options = $ratingOptions;
                        }

                        if ($isScored) {
                            $options = $this->normalizeScoredOptions($options);
                            if (! $allowsNa) {
                                $options = array_values(array_filter(
                                    $options,
                                    fn (array $option): bool => ! ($option['is_not_applicable'] ?? false)
                                ));
                            }
                        }

                        $validation = $this->arrayValue(
                            $questionData['validation_rules']
                                ?? $questionData['validation']
                                ?? $questionData['rules']
                                ?? []
                        );
                        $requiredWhen = $this->arrayValue(
                            $questionData['required_when']
                                ?? data_get($questionData, 'settings.required_when')
                                ?? data_get($validation, 'required_when')
                                ?? []
                        );
                        $scoringDirection = $this->scoringDirection(
                            $questionData['scoring_direction']
                                ?? data_get($questionData, 'settings.scoring_direction', 'positive')
                        );
                        $ratingLabels = [];
                        if ($isScored) {
                            foreach ($options as $option) {
                                if (! is_array($option)) {
                                    continue;
                                }

                                $value = $option['value'] ?? $option['score'] ?? null;
                                if ($value === null) {
                                    continue;
                                }

                                $ratingLabels[(string) $value] = (string) ($option['label'] ?? $value);
                            }
                        }
                        $optionScores = collect($options)
                            ->reject(fn (mixed $option): bool => is_array($option)
                                && (bool) ($option['is_not_applicable'] ?? false))
                            ->map(fn (mixed $option): mixed => is_array($option)
                                ? ($option['score'] ?? $option['value'] ?? null)
                                : null)
                            ->filter(fn (mixed $score): bool => is_numeric($score))
                            ->map(fn (mixed $score): float => (float) $score)
                            ->values();
                        $optionMinimum = $optionScores->min();
                        $optionMaximum = $optionScores->max();
                        $minimumScore = $isScored
                            ? (float) (
                                $questionData['minimum_score']
                                    ?? $questionData['min_score']
                                    ?? data_get($questionData, 'score.min')
                                    ?? ($hasQuestionOptions ? $optionMinimum : null)
                                    ?? $ratingMinimum
                                    ?? $optionMinimum
                                    ?? 0
                            )
                            : null;
                        $maximumScore = $isScored
                            ? (float) (
                                $questionData['maximum_score']
                                    ?? $questionData['max_score']
                                    ?? data_get($questionData, 'score.max')
                                    ?? ($hasQuestionOptions ? $optionMaximum : null)
                                    ?? $ratingMaximum
                                    ?? $optionMaximum
                                    ?? 3
                            )
                            : null;

                        if ($isScored && $maximumScore <= $minimumScore) {
                            throw ValidationException::withMessages([
                                'structure' => "Question '{$questionKey}' must have a maximum score above its minimum score.",
                            ]);
                        }

                        foreach ($optionScores as $optionScore) {
                            if ($optionScore < $minimumScore || $optionScore > $maximumScore) {
                                throw ValidationException::withMessages([
                                    'structure' => "Question '{$questionKey}' has an option score outside its configured range.",
                                ]);
                            }
                        }

                        BiAnnualSiteVisitQuestion::create([
                            'template_id' => $template->id,
                            'topic_id' => $topic->id,
                            'question_key' => $questionKey,
                            'question_type' => $this->persistenceType($responseType),
                            'prompt' => $this->requiredText(
                                $questionData['prompt']
                                    ?? $questionData['label']
                                    ?? $questionData['question']
                                    ?? null,
                                'Question '.($questionIndex + 1)." in {$topic->title} needs a prompt."
                            ),
                            'help_text' => $this->nullableText(
                                $questionData['help_text']
                                    ?? $questionData['description']
                                    ?? null
                            ),
                            'options' => $options,
                            'validation' => $validation,
                            'visibility' => $this->arrayValue(
                                $questionData['visibility_rules']
                                    ?? $questionData['visibility']
                                    ?? []
                            ),
                            'settings' => [
                                ...$this->arrayValue($questionData['settings'] ?? []),
                                'response_type' => $responseType,
                                'scoring_direction' => $scoringDirection,
                                'minimum_score' => $minimumScore,
                                'required_when' => $requiredWhen,
                            ],
                            'rating_labels' => $isScored ? $ratingLabels : [],
                            'is_required' => (bool) (
                                $questionData['required']
                                    ?? $questionData['is_required']
                                    ?? data_get($validation, 'required')
                                    ?? false
                            ),
                            'is_scored' => $isScored,
                            'allows_na' => $allowsNa,
                            'maximum_score' => $maximumScore,
                            'score_weight' => $isScored && $scoringDirection !== 'none'
                                ? $this->weight(
                                    $questionData['weight']
                                        ?? $questionData['score_weight']
                                        ?? 1
                                )
                                : 0,
                            'sort_order' => $questionData['sort_order']
                                ?? $questionData['order']
                                ?? $questionIndex + 1,
                            'created_by' => $actorId,
                            'updated_by' => $actorId,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function builderStructure(BiAnnualSiteVisitTemplate $template): array
    {
        $template->loadMissing('sections.topics.questions');

        return $template->sections->map(fn (BiAnnualSiteVisitSection $section): array => [
            'key' => $section->section_key,
            'title' => $section->title,
            'description' => $section->description,
            'guidance' => $section->guidance,
            'settings' => $section->settings ?? [],
            'visibility_rules' => $section->visibility ?? [],
            'weight' => (float) data_get($section->settings, 'weight', 1),
            'topics' => $section->topics->map(fn (BiAnnualSiteVisitTopic $topic): array => [
                'key' => $topic->topic_key,
                'title' => $topic->title,
                'description' => $topic->description,
                'guidance' => $topic->guidance,
                'settings' => $topic->settings ?? [],
                'visibility_rules' => $topic->visibility ?? [],
                'weight' => (float) data_get($topic->settings, 'weight', 1),
                'questions' => $topic->questions->map(fn (BiAnnualSiteVisitQuestion $question): array => [
                    'key' => $question->question_key,
                    'original_key' => $question->question_key,
                    'prompt' => $question->prompt,
                    'response_type' => $this->responseType(
                        data_get($question->settings, 'response_type', $question->question_type)
                    ),
                    'required' => (bool) $question->is_required,
                    'weight' => (float) ($question->score_weight ?? 1),
                    'scoring_direction' => data_get($question->settings, 'scoring_direction', 'positive'),
                    'minimum_score' => (float) data_get($question->settings, 'minimum_score', 0),
                    'maximum_score' => $question->maximum_score === null
                        ? null
                        : (float) $question->maximum_score,
                    'allows_na' => (bool) $question->allows_na,
                    'required_when' => data_get(
                        $question->settings,
                        'required_when',
                        data_get($question->validation, 'required_when', [])
                    ),
                    'rating_labels' => $question->rating_labels ?? [],
                    'settings' => $question->settings ?? [],
                    'help_text' => $question->help_text,
                    'options' => $question->options ?? [],
                    'validation_rules' => $question->validation ?? [],
                    'visibility_rules' => $question->visibility ?? [],
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Convert a stored questionnaire snapshot into the canonical support shape.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function canonicalDefinition(array $snapshot): array
    {
        $template = $snapshot['template'] ?? [];

        return [
            'code' => $template['code'] ?? 'biannual-monitoring-questionnaire',
            'name' => $template['name'] ?? 'Bi-Annual Monitoring Questionnaire',
            'description' => $template['description'] ?? '',
            'instructions' => $template['instructions'] ?? '',
            'version' => $template['version'] ?? 1,
            'rating_scale' => data_get($template, 'settings.rating_scale', []),
            'sections' => collect($snapshot['sections'] ?? [])->map(function (array $section): array {
                return [
                    'key' => $section['section_key'] ?? $section['key'] ?? null,
                    'title' => $section['title'] ?? 'Section',
                    'description' => $section['description'] ?? '',
                    'guidance' => $section['guidance'] ?? '',
                    'settings' => $section['settings'] ?? [],
                    'weight' => data_get($section, 'settings.weight', 1),
                    'visibility' => $section['visibility'] ?? [],
                    'topics' => collect($section['topics'] ?? [])->map(function (array $topic): array {
                        return [
                            'key' => $topic['topic_key'] ?? $topic['key'] ?? null,
                            'title' => $topic['title'] ?? 'Topic',
                            'description' => $topic['description'] ?? '',
                            'guidance' => $topic['guidance'] ?? '',
                            'settings' => $topic['settings'] ?? [],
                            'weight' => data_get($topic, 'settings.weight', 1),
                            'visibility' => $topic['visibility'] ?? [],
                            'questions' => collect($topic['questions'] ?? [])->map(function (array $question): array {
                                $type = $this->responseType(
                                    data_get($question, 'settings.response_type', $question['question_type'] ?? 'textarea')
                                );

                                return [
                                    'id' => $question['id'] ?? null,
                                    'key' => $question['question_key'] ?? $question['key'] ?? null,
                                    'label' => $question['prompt'] ?? $question['label'] ?? 'Question',
                                    'prompt' => $question['prompt'] ?? $question['label'] ?? 'Question',
                                    'type' => $type,
                                    'response_type' => $type,
                                    'description' => $question['help_text'] ?? '',
                                    'help_text' => $question['help_text'] ?? '',
                                    'required' => (bool) ($question['is_required'] ?? false),
                                    'options' => $question['options'] ?? [],
                                    'rules' => $question['validation'] ?? [],
                                    'validation_rules' => $question['validation'] ?? [],
                                    'visibility' => $question['visibility'] ?? [],
                                    'weight' => (float) ($question['score_weight'] ?? 1),
                                    'minimum_score' => (float) data_get(
                                        $question,
                                        'settings.minimum_score',
                                        0
                                    ),
                                    'maximum_score' => $question['maximum_score'] ?? null,
                                    'allows_na' => (bool) ($question['allows_na'] ?? false),
                                    'required_when' => data_get(
                                        $question,
                                        'settings.required_when',
                                        data_get($question, 'validation.required_when', [])
                                    ),
                                    'rating_labels' => $question['rating_labels'] ?? [],
                                    'settings' => $question['settings'] ?? [],
                                    'score' => $type === 'scored_assessment'
                                        ? [
                                            'min' => (float) data_get(
                                                $question,
                                                'settings.minimum_score',
                                                0
                                            ),
                                            'max' => (float) ($question['maximum_score'] ?? 3),
                                        ]
                                        : null,
                                    'scoring_direction' => data_get($question, 'settings.scoring_direction', 'positive'),
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return list<array{value:int,label:string,score:int}>
     */
    public function defaultRatingOptions(): array
    {
        return [
            [
                'value' => 0,
                'label' => 'Not Applicable',
                'score' => 0,
                'is_not_applicable' => true,
            ],
            ['value' => 1, 'label' => 'Weak', 'score' => 1],
            ['value' => 2, 'label' => 'Average', 'score' => 2],
            ['value' => 3, 'label' => 'Strong', 'score' => 3],
        ];
    }

    /**
     * @param  array<mixed>  $options
     * @return list<array<string, mixed>>
     */
    private function normalizeScoredOptions(array $options): array
    {
        return collect(array_values($options))
            ->map(function (mixed $option, int $index): array {
                if (! is_array($option)) {
                    $label = trim((string) $option);

                    return [
                        'value' => $index,
                        'label' => $label,
                        'score' => $index,
                        'is_not_applicable' => $this->looksNotApplicable($index, $label),
                    ];
                }

                $value = $option['value'] ?? $option['score'] ?? $index;
                $label = (string) ($option['label'] ?? $value);
                $isNotApplicable = (bool) (
                    $option['is_not_applicable']
                        ?? $option['is_na']
                        ?? $this->looksNotApplicable($option['value'] ?? null, $label)
                );

                return [
                    ...$option,
                    'value' => $value,
                    'label' => $label,
                    'score' => is_numeric($option['score'] ?? $value)
                        ? (float) ($option['score'] ?? $value)
                        : $index,
                    'is_not_applicable' => $isNotApplicable,
                ];
            })
            ->values()
            ->all();
    }

    private function looksNotApplicable(mixed $value, string $label): bool
    {
        $aliases = ['na', 'n/a', 'not applicable', 'not_applicable'];

        return in_array(Str::lower(trim((string) $value)), $aliases, true)
            || in_array(Str::lower(trim($label)), $aliases, true);
    }

    public function responseType(string $type): string
    {
        return match (Str::lower(trim($type))) {
            'scored_finding', 'scored_assessment', 'rating', 'score' => 'scored_assessment',
            'text', 'short_text', 'string' => 'short_text',
            'textarea', 'long_text', 'narrative' => 'long_text',
            'select', 'single_choice', 'radio' => 'single_choice',
            'multiselect', 'multiple_choice', 'checkbox', 'checkboxes' => 'multiple_choice',
            'yes_no', 'yes_no_na', 'boolean' => 'yes_no_na',
            'numeric', 'number' => 'number',
            'percent', 'percentage' => 'percentage',
            'date' => 'date',
            'file', 'evidence', 'attachment' => 'evidence',
            'information', 'heading', 'note' => 'information',
            default => 'long_text',
        };
    }

    private function persistenceType(string $responseType): string
    {
        return match ($responseType) {
            'scored_assessment' => 'scored_finding',
            'short_text' => 'text',
            'long_text' => 'textarea',
            'single_choice' => 'select',
            'multiple_choice' => 'multiselect',
            'yes_no_na' => 'yes_no',
            'evidence' => 'file',
            default => $responseType,
        };
    }

    /**
     * Resolve stable keys before persistence, rewrite conditional references
     * after key edits, and ensure every condition points to an earlier question.
     *
     * @param  array<mixed>  $sections
     * @return list<array<string, mixed>>
     */
    private function prepareStructure(array $sections): array
    {
        $prepared = [];
        $aliases = [];
        $usedQuestionKeys = [];

        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            if (! is_array($sectionData)) {
                continue;
            }

            $sectionData['_resolved_key'] = $this->safeKey(
                $sectionData['section_key'] ?? $sectionData['key'] ?? null,
                'section-'.str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT)
            );
            $preparedTopics = [];
            $topics = is_array($sectionData['topics'] ?? null)
                ? $sectionData['topics']
                : [];

            foreach (array_values($topics) as $topicIndex => $topicData) {
                if (! is_array($topicData)) {
                    continue;
                }

                $topicData['_resolved_key'] = $this->safeKey(
                    $topicData['topic_key'] ?? $topicData['key'] ?? null,
                    $sectionData['_resolved_key'].'-topic-'.str_pad(
                        (string) ($topicIndex + 1),
                        2,
                        '0',
                        STR_PAD_LEFT
                    )
                );
                $preparedQuestions = [];
                $questions = is_array($topicData['questions'] ?? null)
                    ? $topicData['questions']
                    : [];

                foreach (array_values($questions) as $questionIndex => $questionData) {
                    if (! is_array($questionData)) {
                        continue;
                    }

                    $rawKey = $questionData['question_key']
                        ?? $questionData['key']
                        ?? null;
                    $questionKey = $this->safeKey(
                        $rawKey,
                        $topicData['_resolved_key'].'-question-'.str_pad(
                            (string) ($questionIndex + 1),
                            2,
                            '0',
                            STR_PAD_LEFT
                        ),
                        160
                    );

                    if (isset($usedQuestionKeys[$questionKey])) {
                        throw ValidationException::withMessages([
                            'structure' => "Question key '{$questionKey}' is duplicated. Stable keys must be unique.",
                        ]);
                    }
                    $usedQuestionKeys[$questionKey] = true;
                    $questionData['_resolved_key'] = $questionKey;

                    foreach ([
                        $rawKey,
                        $questionData['original_key'] ?? null,
                        $questionKey,
                    ] as $alias) {
                        $this->registerQuestionAlias($aliases, $alias, $questionKey);
                    }

                    $preparedQuestions[] = $questionData;
                }

                $topicData['questions'] = $preparedQuestions;
                $preparedTopics[] = $topicData;
            }

            $sectionData['topics'] = $preparedTopics;
            $prepared[] = $sectionData;
        }

        $availableQuestionKeys = [];
        foreach ($prepared as $sectionIndex => &$sectionData) {
            $sectionData['visibility_rules'] = $this->rewriteAndValidateLogic(
                $sectionData['visibility_rules']
                    ?? $sectionData['visibility']
                    ?? $this->legacyLogic($sectionData),
                $aliases,
                $availableQuestionKeys,
                'Section '.($sectionIndex + 1).' visibility'
            );
            $sectionData['visibility'] = $sectionData['visibility_rules'];

            foreach ($sectionData['topics'] as $topicIndex => &$topicData) {
                $topicData['visibility_rules'] = $this->rewriteAndValidateLogic(
                    $topicData['visibility_rules']
                        ?? $topicData['visibility']
                        ?? $this->legacyLogic($topicData),
                    $aliases,
                    $availableQuestionKeys,
                    'Topic '.($sectionIndex + 1).'.'.($topicIndex + 1).' visibility'
                );
                $topicData['visibility'] = $topicData['visibility_rules'];

                foreach ($topicData['questions'] as $questionIndex => &$questionData) {
                    $context = "Question '{$questionData['_resolved_key']}'";
                    $questionData['visibility_rules'] = $this->rewriteAndValidateLogic(
                        $questionData['visibility_rules']
                            ?? $questionData['visibility']
                            ?? $this->legacyLogic($questionData),
                        $aliases,
                        $availableQuestionKeys,
                        $context.' visibility'
                    );
                    $questionData['visibility'] = $questionData['visibility_rules'];

                    $validation = $this->arrayValue(
                        $questionData['validation_rules']
                            ?? $questionData['validation']
                            ?? $questionData['rules']
                            ?? []
                    );
                    $questionData['required_when'] = $this->rewriteAndValidateLogic(
                        $questionData['required_when']
                            ?? data_get($questionData, 'settings.required_when')
                            ?? data_get($validation, 'required_when')
                            ?? [],
                        $aliases,
                        $availableQuestionKeys,
                        $context.' required rule'
                    );
                    $questionData['settings'] = [
                        ...$this->arrayValue($questionData['settings'] ?? []),
                        'required_when' => $questionData['required_when'],
                    ];

                    $availableQuestionKeys[$questionData['_resolved_key']] = true;
                }
                unset($questionData);
            }
            unset($topicData);
        }
        unset($sectionData);

        return $prepared;
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function registerQuestionAlias(
        array &$aliases,
        mixed $alias,
        string $questionKey
    ): void {
        foreach ($this->questionAliasCandidates($alias) as $candidate) {
            if (isset($aliases[$candidate]) && $aliases[$candidate] !== $questionKey) {
                throw ValidationException::withMessages([
                    'structure' => "Question keys '{$aliases[$candidate]}' and '{$questionKey}' are too similar for reliable conditional logic.",
                ]);
            }

            $aliases[$candidate] = $questionKey;
        }
    }

    /**
     * @return list<string>
     */
    private function questionAliasCandidates(mixed $value): array
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return [];
        }

        $raw = Str::lower(trim((string) $value));
        $logicKey = preg_replace('/[^a-z0-9._-]+/', '_', $raw) ?: $raw;

        return array_values(array_unique([
            $raw,
            trim($logicKey, '_.-'),
            $this->safeKey($raw, $raw, 160),
        ]));
    }

    /**
     * @param  array<string, string>  $aliases
     * @param  array<string, bool>  $availableQuestionKeys
     * @return array<string, mixed>
     */
    private function rewriteAndValidateLogic(
        mixed $logic,
        array $aliases,
        array $availableQuestionKeys,
        string $context
    ): array {
        if (! is_array($logic) || $logic === []) {
            return [];
        }

        $rawConditions = $logic['conditions'] ?? null;
        if (! is_array($rawConditions)) {
            $rawConditions = [$logic];
        } elseif (! array_is_list($rawConditions)) {
            $rawConditions = [$rawConditions];
        }

        $conditions = [];
        foreach ($rawConditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $reference = $condition['question_key']
                ?? $condition['question']
                ?? $condition['depends_on']
                ?? null;
            $resolved = null;
            foreach ($this->questionAliasCandidates($reference) as $candidate) {
                if (isset($aliases[$candidate])) {
                    $resolved = $aliases[$candidate];
                    break;
                }
            }

            if (! $resolved) {
                throw ValidationException::withMessages([
                    'structure' => "{$context} references an unknown question key.",
                ]);
            }

            if (! isset($availableQuestionKeys[$resolved])) {
                throw ValidationException::withMessages([
                    'structure' => "{$context} must reference an earlier question; self, forward, and cyclic references are not allowed.",
                ]);
            }

            $values = $condition['values']
                ?? $condition['value']
                ?? $condition['show_if']
                ?? [];
            $conditions[] = [
                'question_key' => $resolved,
                'operator' => $condition['operator']
                    ?? (array_key_exists('values', $condition) ? 'in' : 'equals'),
                'values' => is_array($values) ? array_values($values) : [$values],
            ];
        }

        return $conditions === []
            ? []
            : [
                'mode' => ($logic['mode'] ?? $logic['match'] ?? 'all') === 'any'
                    ? 'any'
                    : 'all',
                'conditions' => $conditions,
            ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function legacyLogic(array $item): array
    {
        if (! isset($item['depends_on']) || ! array_key_exists('show_if', $item)) {
            return [];
        }

        return [
            'question_key' => $item['depends_on'],
            'operator' => 'in',
            'values' => is_array($item['show_if'])
                ? $item['show_if']
                : [$item['show_if']],
        ];
    }

    private function safeKey(mixed $value, string $fallback, int $maximumLength = 120): string
    {
        $key = Str::lower(trim((string) ($value ?: $fallback)));
        $key = preg_replace('/[^A-Za-z0-9._-]+/', '-', $key) ?: $fallback;
        $key = trim($key, '-');

        return Str::limit($key !== '' ? $key : $fallback, $maximumLength, '');
    }

    private function scoringDirection(mixed $value): string
    {
        $direction = Str::lower(trim((string) $value));

        return in_array($direction, ['positive', 'negative', 'none'], true)
            ? $direction
            : 'positive';
    }

    private function requiredText(mixed $value, string $message): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw ValidationException::withMessages(['structure' => $message]);
        }

        return $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function weight(mixed $value): float
    {
        return is_numeric($value) ? max(0, (float) $value) : 1;
    }

    /**
     * @return array<mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}

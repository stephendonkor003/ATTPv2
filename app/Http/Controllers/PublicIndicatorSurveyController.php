<?php

namespace App\Http\Controllers;

use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Models\User;
use App\Support\MeSurvey;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicIndicatorSurveyController extends Controller
{
    public function show(string $token): View
    {
        [$link, $methodology, $surveyConfig, $sections, $questions] = $this->resolveSurveyContext($token);

        abort_if(!$link || !$methodology || empty($questions), 404);

        return view('me.surveys.public', [
            'link' => $link,
            'methodology' => $methodology,
            'surveyConfig' => $surveyConfig,
            'sections' => $sections,
            'questions' => $questions,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        [$link, $methodology, $surveyConfig, $sections, $questions] = $this->resolveSurveyContext($token);

        abort_if(!$link || !$methodology || empty($questions), 404);

        $validator = Validator::make($request->all(), array_merge($this->respondentValidationRules($surveyConfig), [
            'answers' => 'nullable|array',
        ]), [
            'answers.array' => 'Please complete the survey form before submitting.',
        ]);

        $validator->after(function ($validator) use ($request, $surveyConfig, $questions) {
            $normalizedAnswers = [];

            foreach ($questions as $question) {
                $normalizedAnswers[$question['key']] = MeSurvey::normalizeAnswer(
                    $question,
                    $this->rawAnswerForQuestion($request, $question)
                );
            }

            $reachableQuestionKeys = collect(MeSurvey::reachableQuestions($surveyConfig, $normalizedAnswers))
                ->pluck('key')
                ->values()
                ->all();

            if (empty($reachableQuestionKeys)) {
                $validator->errors()->add('answers', 'This survey does not have any reachable questions to submit.');
                return;
            }

            foreach ($questions as $question) {
                $questionVisible = in_array((string) ($question['key'] ?? ''), $reachableQuestionKeys, true);
                $result = MeSurvey::validateAnswer(
                    $question,
                    $this->rawAnswerForQuestion($request, $question),
                    $questionVisible
                );

                $normalizedAnswers[$question['key']] = $result['value'];

                foreach ($result['errors'] as $error) {
                    $validator->errors()->add(
                        'answers.' . $question['key'],
                        (string) ($question['label'] ?? 'Question') . ': ' . $error
                    );
                }
            }
        });

        $validated = $validator->validate();

        $normalizedAnswers = [];
        foreach ($questions as $question) {
            $rawValue = $this->rawAnswerForQuestion($request, $question);

            $normalizedAnswers[$question['key']] = MeSurvey::validateAnswer(
                $question,
                $rawValue instanceof UploadedFile
                    ? $this->storeSurveyUpload($rawValue, $token, (string) ($question['key'] ?? 'file'))
                    : $rawValue,
                true
            )['value'];
        }

        $reachableQuestionKeys = collect(MeSurvey::reachableQuestions($surveyConfig, $normalizedAnswers))
            ->pluck('key')
            ->values()
            ->all();

        $answers = collect($questions)
            ->filter(function (array $question) use ($reachableQuestionKeys) {
                return in_array((string) ($question['key'] ?? ''), $reachableQuestionKeys, true);
            })
            ->map(function (array $question) use ($normalizedAnswers) {
                $value = $normalizedAnswers[$question['key']] ?? null;

                return [
                    'section' => (string) ($question['section_title'] ?? ''),
                    'section_key' => (string) ($question['section_key'] ?? ''),
                    'question_key' => (string) ($question['key'] ?? ''),
                    'question' => (string) ($question['label'] ?? 'Question'),
                    'type' => (string) ($question['type'] ?? 'text'),
                    'answer' => MeSurvey::displayAnswer($question, $value),
                ];
            })
            ->values()
            ->all();

        [$responsibleUserIds, $responsibleSnapshot] = $this->buildResponsibleSnapshot(
            (string) ($link->indicator->responsible_party ?? '')
        );

        IndicatorSurveyResponse::create([
            'indicator_id' => $link->indicator_id,
            'methodology_id' => $methodology->id,
            'survey_link_id' => $link->id,
            'respondent_name' => $validated['respondent_name'] ?? null,
            'respondent_email' => $validated['respondent_email'] ?? null,
            'respondent_phone' => $validated['respondent_phone'] ?? null,
            'respondent_organization' => $validated['respondent_organization'] ?? null,
            'answers' => $answers,
            'responsible_user_ids' => $responsibleUserIds,
            'responsible_snapshot' => $responsibleSnapshot,
            'submitted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()
            ->route('public.me.indicators.surveys.show', ['token' => $token])
            ->with('success', 'Thank you. Your survey response has been submitted successfully.');
    }

    protected function resolveSurveyContext(string $token): array
    {
        $link = IndicatorSurveyLink::query()
            ->with(['indicator', 'methodology'])
            ->where('public_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$link || !$link->indicator) {
            return [null, null, [], [], []];
        }

        $methodology = $link->methodology;
        if (!$methodology && $link->indicator->methodology) {
            $methodologyName = strtolower(trim((string) $link->indicator->methodology));
            $methodology = IndicatorMethodology::query()
                ->where('is_active', true)
                ->get()
                ->first(function (IndicatorMethodology $item) use ($methodologyName) {
                    return strtolower(trim((string) $item->name)) === $methodologyName;
                });
        }

        if (!$methodology) {
            return [null, null, [], [], []];
        }

        $surveyConfig = MeSurvey::surveyConfigFromMetadata(
            (array) ($methodology->metadata ?? []),
            trim((string) $methodology->name) !== '' ? ($methodology->name . ' Public Survey') : 'Public Survey'
        );

        if (!(bool) ($surveyConfig['enabled'] ?? false) || empty($surveyConfig['questions'])) {
            return [null, null, [], [], []];
        }

        $sections = (array) ($surveyConfig['sections'] ?? []);
        $questions = (array) ($surveyConfig['questions'] ?? []);

        return [$link, $methodology, $surveyConfig, $sections, $questions];
    }

    protected function buildResponsibleSnapshot(string $responsiblePartyJson): array
    {
        $responsibleUserIds = collect(json_decode($responsiblePartyJson, true))
            ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item) => (string) $item)
            ->unique()
            ->values();

        if ($responsibleUserIds->isEmpty()) {
            return [[], []];
        }

        $users = User::query()
            ->whereIn('id', $responsibleUserIds->all())
            ->with('governanceNode:id,name')
            ->get(['id', 'name', 'email', 'governance_node_id']);

        $orderedUsers = $users->sortBy(function (User $user) use ($responsibleUserIds) {
            return $responsibleUserIds->search((string) $user->id);
        })->values();

        $snapshot = $orderedUsers->map(function (User $user) {
            return [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
                'agency' => (string) ($user->governanceNode->name ?? ''),
            ];
        })->all();

        return [$responsibleUserIds->all(), $snapshot];
    }

    protected function respondentValidationRules(array $surveyConfig): array
    {
        return [
            'respondent_name' => [$this->respondentFieldIsRequired($surveyConfig, 'name') ? 'required' : 'nullable', 'string', 'max:255'],
            'respondent_email' => [$this->respondentFieldIsRequired($surveyConfig, 'email') ? 'required' : 'nullable', 'email', 'max:255'],
            'respondent_phone' => [$this->respondentFieldIsRequired($surveyConfig, 'phone') ? 'required' : 'nullable', 'string', 'max:60'],
            'respondent_organization' => [$this->respondentFieldIsRequired($surveyConfig, 'organization') ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    protected function respondentFieldIsRequired(array $surveyConfig, string $field): bool
    {
        return (bool) data_get($surveyConfig, 'respondent.fields.' . $field . '.required', false);
    }

    protected function rawAnswerForQuestion(Request $request, array $question): mixed
    {
        $questionKey = (string) ($question['key'] ?? '');
        $type = strtolower((string) ($question['type'] ?? 'text'));

        if ($questionKey === '') {
            return null;
        }

        if ($type === 'file') {
            return $request->file('answers.' . $questionKey);
        }

        return $request->input('answers.' . $questionKey);
    }

    protected function storeSurveyUpload(UploadedFile $file, string $token, string $questionKey): array
    {
        $path = $file->store(
            'me-survey-uploads/' . Str::slug($token) . '/' . Str::slug($questionKey),
            'public'
        );

        return array_filter([
            'original_name' => trim((string) $file->getClientOriginalName()),
            'stored_path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => trim((string) $file->getClientMimeType()),
            'size' => $file->getSize(),
        ], fn ($value) => $value !== null && $value !== '');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicIndicatorSurveyController extends Controller
{
    public function show(string $token): View
    {
        [$link, $methodology, $surveyConfig, $questions] = $this->resolveSurveyContext($token);

        abort_if(!$link || !$methodology || empty($questions), 404);

        return view('me.surveys.public', [
            'link' => $link,
            'methodology' => $methodology,
            'surveyConfig' => $surveyConfig,
            'questions' => $questions,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        [$link, $methodology, $surveyConfig, $questions] = $this->resolveSurveyContext($token);

        abort_if(!$link || !$methodology || empty($questions), 404);

        $rules = [
            'respondent_name' => 'nullable|string|max:255',
            'respondent_email' => 'nullable|email|max:255',
            'respondent_phone' => 'nullable|string|max:60',
            'respondent_organization' => 'nullable|string|max:255',
            'answers' => 'required|array',
        ];

        foreach ($questions as $index => $question) {
            $type = strtolower((string) ($question['type'] ?? 'text'));
            $isRequired = (bool) ($question['required'] ?? false);
            $field = 'answers.' . $index;

            $fieldRules = $isRequired ? ['required'] : ['nullable'];

            if ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'email') {
                $fieldRules[] = 'email';
            } elseif ($type === 'date') {
                $fieldRules[] = 'date';
            } elseif ($type === 'checkbox') {
                $fieldRules[] = 'array';
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:2000';
            }

            if (in_array($type, ['select', 'radio'], true)) {
                $options = collect($question['options'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();
                if (!empty($options)) {
                    $fieldRules[] = Rule::in($options);
                }
            }

            $rules[$field] = $fieldRules;

            if ($type === 'checkbox') {
                $options = collect($question['options'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();
                if (!empty($options)) {
                    $rules[$field . '.*'] = [Rule::in($options)];
                }
            }
        }

        $validated = $request->validate($rules, [
            'answers.required' => 'Please complete the survey form before submitting.',
        ]);

        $answers = collect($questions)
            ->map(function (array $question, int $index) use ($validated) {
                $rawValue = data_get($validated, 'answers.' . $index);
                if (is_array($rawValue)) {
                    $rawValue = collect($rawValue)
                        ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
                        ->map(fn ($item) => trim((string) $item))
                        ->values()
                        ->all();
                } elseif (is_scalar($rawValue)) {
                    $rawValue = trim((string) $rawValue);
                }

                return [
                    'question' => (string) ($question['label'] ?? ('Question ' . ($index + 1))),
                    'type' => (string) ($question['type'] ?? 'text'),
                    'answer' => $rawValue,
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
            return [null, null, [], []];
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
            return [null, null, [], []];
        }

        $surveyConfig = (array) data_get($methodology->metadata, 'survey', []);
        $enabled = (bool) ($surveyConfig['enabled'] ?? false);
        if (!$enabled) {
            return [null, null, [], []];
        }

        $questions = collect($surveyConfig['questions'] ?? [])
            ->filter(fn ($question) => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')
            ->values()
            ->all();

        return [$link, $methodology, $surveyConfig, $questions];
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Support\MeSurveyCleanup;
use App\Support\MeSurvey;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

class MeSurveyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:me.configuration.view|me.configuration.manage')->only([
            'index',
            'responses',
            'questionnaires',
            'qrCodes',
            'reports',
            'exportReportPdf',
        ]);
        $this->middleware('permission:me.configuration.manage')->only([
            'create',
            'edit',
            'destroySurvey',
            'destroyResponse',
        ]);
    }

    public function index()
    {
        $questionnaires = $this->surveyMethodologiesCollection()
            ->sortByDesc(fn (IndicatorMethodology $methodology) => optional($methodology->updated_at)->timestamp ?? 0)
            ->values();

        $recentLinks = IndicatorSurveyLink::query()
            ->with(['indicator:id,name', 'methodology:id,name'])
            ->withCount('responses')
            ->withMax('responses', 'submitted_at')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (IndicatorSurveyLink $surveyLink) => $this->decorateSurveyLink($surveyLink));

        $recentResponses = IndicatorSurveyResponse::query()
            ->with(['indicator:id,name', 'methodology:id,name', 'surveyLink:id,public_token'])
            ->latest('submitted_at')
            ->take(6)
            ->get();

        return view('me.survey-hub.index', [
            'stats' => $this->globalSurveyStats($questionnaires),
            'recentQuestionnaires' => $questionnaires->take(5),
            'recentLinks' => $recentLinks,
            'recentResponses' => $recentResponses,
        ]);
    }

    public function responses(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $surveyLinks = IndicatorSurveyLink::query()
            ->with(['indicator:id,name', 'methodology:id,name'])
            ->withCount('responses')
            ->withMax('responses', 'submitted_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('public_token', 'like', '%' . $search . '%')
                        ->orWhereHas('indicator', fn ($indicatorQuery) => $indicatorQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('methodology', fn ($methodologyQuery) => $methodologyQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $surveyLinks->setCollection(
            $surveyLinks->getCollection()->map(fn (IndicatorSurveyLink $surveyLink) => $this->decorateSurveyLink($surveyLink))
        );

        return view('me.survey-hub.responses', [
            'search' => $search,
            'stats' => [
                'responses' => IndicatorSurveyResponse::query()->count(),
                'active_links' => IndicatorSurveyLink::query()->where('is_active', true)->count(),
                'surveys_with_responses' => IndicatorSurveyLink::query()->has('responses')->count(),
                'last_response' => optional(IndicatorSurveyResponse::query()->latest('submitted_at')->first())->submitted_at,
            ],
            'surveyLinks' => $surveyLinks,
        ]);
    }

    public function questionnaires(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $questionnaires = $this->surveyMethodologiesCollection()
            ->filter(function (IndicatorMethodology $methodology) use ($search) {
                if ($search === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', [
                    (string) $methodology->name,
                    (string) $methodology->description,
                    (string) data_get($methodology, 'survey_summary.title', ''),
                    (string) data_get($methodology, 'survey_summary.intro', ''),
                ]));

                return Str::contains($haystack, Str::lower($search));
            })
            ->sortBy(fn (IndicatorMethodology $methodology) => Str::lower((string) $methodology->name))
            ->values();

        return view('me.survey-hub.questionnaires', [
            'search' => $search,
            'stats' => [
                'questionnaires' => $questionnaires->count(),
                'published' => $questionnaires->filter(fn (IndicatorMethodology $methodology) => (bool) data_get($methodology, 'survey_summary.enabled', false))->count(),
                'questions' => $questionnaires->sum(fn (IndicatorMethodology $methodology) => (int) data_get($methodology, 'survey_summary.question_count', 0)),
                'linked_indicators' => $questionnaires->sum(fn (IndicatorMethodology $methodology) => (int) ($methodology->linked_indicators_count ?? 0)),
            ],
            'questionnaires' => $this->paginateCollection($questionnaires, $request, 10),
        ]);
    }

    public function create()
    {
        return view('me.survey-hub.create');
    }

    public function edit(IndicatorMethodology $methodology)
    {
        if (!$this->isSurveyMethodology($methodology)) {
            return redirect()
                ->route('budget.me.surveys.questionnaires')
                ->with('error', 'The selected methodology is not configured as a survey questionnaire.');
        }

        return view('me.survey-hub.edit', [
            'methodology' => $methodology,
        ]);
    }

    public function destroySurvey(IndicatorSurveyLink $surveyLink): RedirectResponse
    {
        $attachmentPaths = DB::transaction(function () use ($surveyLink) {
            $responses = IndicatorSurveyResponse::query()
                ->where('survey_link_id', $surveyLink->id)
                ->get(['id', 'answers']);

            $attachmentPaths = MeSurveyCleanup::attachmentPathsFromResponses($responses);

            IndicatorSurveyResponse::query()
                ->where('survey_link_id', $surveyLink->id)
                ->delete();

            $surveyLink->delete();

            return $attachmentPaths;
        });

        if (!empty($attachmentPaths)) {
            Storage::disk('public')->delete($attachmentPaths);
        }

        return redirect()
            ->route('budget.me.surveys.responses')
            ->with('success', 'Survey deleted successfully.');
    }

    public function destroyResponse(IndicatorSurveyResponse $response): RedirectResponse
    {
        $attachmentPaths = MeSurveyCleanup::attachmentPathsFromResponse($response);

        $response->delete();

        if (!empty($attachmentPaths)) {
            Storage::disk('public')->delete($attachmentPaths);
        }

        return back()->with('success', 'Survey response deleted successfully.');
    }

    public function qrCodes(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $surveyLinks = IndicatorSurveyLink::query()
            ->with(['indicator:id,name', 'methodology:id,name'])
            ->withCount('responses')
            ->withMax('responses', 'submitted_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('public_token', 'like', '%' . $search . '%')
                        ->orWhereHas('indicator', fn ($indicatorQuery) => $indicatorQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('methodology', fn ($methodologyQuery) => $methodologyQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $surveyLinks->setCollection(
            $surveyLinks->getCollection()->map(fn (IndicatorSurveyLink $surveyLink) => $this->decorateSurveyLink($surveyLink))
        );

        return view('me.survey-hub.qr-codes', [
            'search' => $search,
            'stats' => [
                'active_links' => IndicatorSurveyLink::query()->where('is_active', true)->count(),
                'responses' => IndicatorSurveyResponse::query()->count(),
                'questionnaires' => $this->surveyMethodologiesCollection()->filter(fn (IndicatorMethodology $methodology) => (bool) data_get($methodology, 'survey_summary.enabled', false))->count(),
                'last_response' => optional(IndicatorSurveyResponse::query()->latest('submitted_at')->first())->submitted_at,
            ],
            'surveyLinks' => $surveyLinks,
        ]);
    }

    public function reports(Request $request)
    {
        [$filters, $report] = $this->buildSurveyReportData($request);

        return view('me.survey-hub.reports', [
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function exportReportPdf(Request $request)
    {
        [$filters, $report] = $this->buildSurveyReportData($request);

        $chartImages = [
            'trend' => (string) $request->input('chart_trend', ''),
            'pie' => (string) $request->input('chart_pie', ''),
            'bar' => (string) $request->input('chart_bar', ''),
            'heatmap' => (string) $request->input('chart_heatmap', ''),
        ];

        $pdf = PDF::loadView('me.survey-hub.report-pdf', [
            'filters' => $filters,
            'report' => $report,
            'chartImages' => $chartImages,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('survey-report-' . now()->format('Ymd_His') . '.pdf');
    }

    protected function buildSurveyReportData(Request $request): array
    {
        $methodologies = $this->surveyMethodologiesCollection()
            ->sortBy(fn (IndicatorMethodology $methodology) => Str::lower((string) $methodology->name))
            ->values();

        $selectedMethodologyId = trim((string) $request->input('methodology_id', $request->query('methodology_id', '')));
        $selectedSurveyLinkId = trim((string) $request->input('survey_link_id', $request->query('survey_link_id', '')));
        $selectedIndicatorId = trim((string) $request->input('indicator_id', $request->query('indicator_id', '')));
        $selectedQuestionKey = trim((string) $request->input('question_key', $request->query('question_key', '')));
        $dateFrom = trim((string) $request->input('date_from', $request->query('date_from', '')));
        $dateTo = trim((string) $request->input('date_to', $request->query('date_to', '')));

        $surveyLinks = IndicatorSurveyLink::query()
            ->with(['indicator:id,name', 'methodology:id,name'])
            ->withCount('responses')
            ->when($selectedMethodologyId !== '', fn ($query) => $query->where('methodology_id', $selectedMethodologyId))
            ->when($selectedIndicatorId !== '', fn ($query) => $query->where('indicator_id', $selectedIndicatorId))
            ->latest()
            ->get()
            ->map(fn (IndicatorSurveyLink $surveyLink) => $this->decorateSurveyLink($surveyLink))
            ->values();

        $indicators = Indicator::query()
            ->whereIn('id', IndicatorSurveyLink::query()->pluck('indicator_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();

        $responses = IndicatorSurveyResponse::query()
            ->with([
                'indicator:id,name',
                'methodology:id,name',
                'surveyLink:id,public_token',
            ])
            ->when($selectedMethodologyId !== '', fn ($query) => $query->where('methodology_id', $selectedMethodologyId))
            ->when($selectedSurveyLinkId !== '', fn ($query) => $query->where('survey_link_id', $selectedSurveyLinkId))
            ->when($selectedIndicatorId !== '', fn ($query) => $query->where('indicator_id', $selectedIndicatorId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('submitted_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('submitted_at', '<=', $dateTo))
            ->orderBy('submitted_at')
            ->get();

        $resolvedSurveyLink = $surveyLinks->firstWhere('id', $selectedSurveyLinkId);
        $resolvedMethodology = $methodologies->firstWhere('id', $selectedMethodologyId);

        if (!$resolvedMethodology && $resolvedSurveyLink?->methodology) {
            $resolvedMethodology = $methodologies->firstWhere('id', $resolvedSurveyLink->methodology->id);
        }

        if (!$resolvedMethodology) {
            $singleMethodologyId = $responses->pluck('methodology_id')->filter()->unique()->whenEmpty(fn ($collection) => $collection)->values();
            if ($singleMethodologyId->count() === 1) {
                $resolvedMethodology = $methodologies->firstWhere('id', $singleMethodologyId->first());
            }
        }

        $questionCatalog = $this->buildQuestionCatalog($resolvedMethodology, $responses);

        if ($selectedQuestionKey === '' || !array_key_exists($selectedQuestionKey, $questionCatalog)) {
            $selectedQuestionKey = $this->defaultReportQuestionKey($questionCatalog);
        }

        $selectedQuestion = $selectedQuestionKey !== '' ? ($questionCatalog[$selectedQuestionKey] ?? null) : null;

        $questionStats = $this->buildQuestionStats($responses, $questionCatalog);
        $timeline = $this->buildResponseTimeline($responses);
        $focusCharts = $this->buildFocusCharts($responses, $selectedQuestion, $timeline);
        $heatmap = $this->buildHeatmap($responses, $questionCatalog, $timeline, $selectedQuestion);
        $summary = $this->buildReportSummary(
            $responses,
            $timeline,
            $questionStats,
            $selectedQuestion,
            $focusCharts,
            $methodologies
        );
        $responseRegister = $this->buildResponseRegister($responses);

        $filters = [
            'selected_methodology_id' => $resolvedMethodology?->id ?? $selectedMethodologyId,
            'selected_survey_link_id' => $selectedSurveyLinkId,
            'selected_indicator_id' => $selectedIndicatorId,
            'selected_question_key' => $selectedQuestionKey,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'methodologies' => $methodologies,
            'survey_links' => $surveyLinks,
            'indicators' => $indicators,
            'question_options' => collect($questionCatalog)
                ->map(fn (array $question) => [
                    'key' => (string) ($question['key'] ?? ''),
                    'label' => (string) ($question['section_title'] ?? '') !== ''
                        ? ((string) ($question['section_title'] ?? '') . ' - ' . (string) ($question['label'] ?? 'Question'))
                        : (string) ($question['label'] ?? 'Question'),
                ])
                ->values(),
        ];

        $report = [
            'responses' => $responses,
            'resolved_methodology' => $resolvedMethodology,
            'resolved_survey_link' => $resolvedSurveyLink,
            'selected_question' => $selectedQuestion,
            'question_catalog' => $questionCatalog,
            'question_stats' => $questionStats,
            'timeline' => $timeline,
            'focus_charts' => $focusCharts,
            'heatmap' => $heatmap,
            'summary' => $summary,
            'response_register' => $responseRegister,
            'stats' => [
                'responses' => $responses->count(),
                'questionnaires' => $responses->pluck('methodology_id')->filter()->unique()->count(),
                'indicators' => $responses->pluck('indicator_id')->filter()->unique()->count(),
                'survey_links' => $responses->pluck('survey_link_id')->filter()->unique()->count(),
                'last_response' => optional($responses->last())->submitted_at,
                'average_per_day' => $timeline['days_count'] > 0
                    ? round($responses->count() / $timeline['days_count'], 1)
                    : 0,
            ],
        ];

        return [$filters, $report];
    }

    protected function surveyMethodologiesCollection(): Collection
    {
        $indicatorCounts = Indicator::query()
            ->selectRaw('LOWER(TRIM(methodology)) as methodology_key, COUNT(*) as aggregate')
            ->whereNotNull('methodology')
            ->whereRaw("TRIM(methodology) <> ''")
            ->groupByRaw('LOWER(TRIM(methodology))')
            ->pluck('aggregate', 'methodology_key');

        return IndicatorMethodology::query()
            ->withCount([
                'surveyLinks',
                'surveyResponses',
                'surveyLinks as active_survey_links_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (IndicatorMethodology $methodology) => $this->isSurveyMethodology($methodology))
            ->map(function (IndicatorMethodology $methodology) use ($indicatorCounts) {
                $methodology->survey_summary = $this->summarizeSurveyMethodology($methodology);
                $methodology->linked_indicators_count = (int) ($indicatorCounts[Str::lower(trim((string) $methodology->name))] ?? 0);

                return $methodology;
            })
            ->values();
    }

    protected function summarizeSurveyMethodology(IndicatorMethodology $methodology): array
    {
        $fallbackTitle = trim((string) $methodology->name) !== ''
            ? trim((string) $methodology->name) . ' Public Survey'
            : 'Public Survey';

        $surveyConfig = MeSurvey::surveyConfigFromMetadata((array) ($methodology->metadata ?? []), $fallbackTitle);
        $questions = collect((array) ($surveyConfig['questions'] ?? []))
            ->filter(fn ($question) => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')
            ->values();
        $sections = collect((array) ($surveyConfig['sections'] ?? []))
            ->filter(function ($section) {
                return is_array($section)
                    && (
                        trim((string) ($section['title'] ?? '')) !== ''
                        || !empty((array) ($section['questions'] ?? []))
                    );
            })
            ->values();

        $questionCount = $questions->count();
        $isEnabled = (bool) ($surveyConfig['enabled'] ?? false) && $questionCount > 0;

        $state = 'Draft';
        $stateClass = 'warning';

        if ($questionCount === 0) {
            $state = 'Incomplete';
            $stateClass = 'danger';
        } elseif ($isEnabled) {
            $state = 'Published';
            $stateClass = 'success';
        }

        return [
            'enabled' => $isEnabled,
            'title' => (string) ($surveyConfig['title'] ?? $fallbackTitle),
            'intro' => (string) ($surveyConfig['intro'] ?? ''),
            'estimated_minutes' => $surveyConfig['estimated_minutes'] ?? null,
            'section_count' => $sections->count(),
            'question_count' => $questionCount,
            'state' => $state,
            'state_class' => $stateClass,
        ];
    }

    protected function isSurveyMethodology(IndicatorMethodology $methodology): bool
    {
        return Str::contains(Str::lower(trim((string) $methodology->name)), 'survey')
            || !empty((array) data_get($methodology->metadata, 'survey', []));
    }

    protected function globalSurveyStats(?Collection $questionnaires = null): array
    {
        $questionnaires = $questionnaires ?? $this->surveyMethodologiesCollection();

        return [
            'questionnaires' => $questionnaires->count(),
            'published_questionnaires' => $questionnaires->filter(fn (IndicatorMethodology $methodology) => (bool) data_get($methodology, 'survey_summary.enabled', false))->count(),
            'active_links' => IndicatorSurveyLink::query()->where('is_active', true)->count(),
            'responses' => IndicatorSurveyResponse::query()->count(),
            'last_response' => optional(IndicatorSurveyResponse::query()->latest('submitted_at')->first())->submitted_at,
        ];
    }

    protected function decorateSurveyLink(IndicatorSurveyLink $surveyLink): IndicatorSurveyLink
    {
        $publicUrl = route('public.me.indicators.surveys.show', ['token' => $surveyLink->public_token]);

        $surveyLink->public_url = $publicUrl;
        $surveyLink->qr_url = MeSurvey::qrCodeUrl($publicUrl);
        $surveyLink->latest_response_at = $surveyLink->responses_max_submitted_at;

        return $surveyLink;
    }

    protected function buildQuestionCatalog(?IndicatorMethodology $methodology, Collection $responses): array
    {
        $catalog = [];

        if ($methodology) {
            $fallbackTitle = trim((string) $methodology->name) !== ''
                ? trim((string) $methodology->name) . ' Public Survey'
                : 'Public Survey';

            $surveyConfig = MeSurvey::surveyConfigFromMetadata((array) ($methodology->metadata ?? []), $fallbackTitle);

            $catalog = collect((array) ($surveyConfig['questions'] ?? []))
                ->mapWithKeys(function (array $question) {
                    $key = trim((string) ($question['key'] ?? ''));

                    if ($key === '') {
                        return [];
                    }

                    return [$key => $question];
                })
                ->all();
        }

        foreach ($responses as $response) {
            foreach ((array) ($response->answers ?? []) as $answerItem) {
                if (!is_array($answerItem)) {
                    continue;
                }

                $key = $this->reportQuestionKey($answerItem);
                if ($key === '' || array_key_exists($key, $catalog)) {
                    continue;
                }

                $catalog[$key] = [
                    'key' => $key,
                    'label' => (string) ($answerItem['question'] ?? 'Question'),
                    'type' => strtolower((string) ($answerItem['type'] ?? 'text')),
                    'section_title' => (string) ($answerItem['section'] ?? ''),
                    'options' => [],
                    'rows' => [],
                    'columns' => [],
                    'scale' => null,
                ];
            }
        }

        return $catalog;
    }

    protected function defaultReportQuestionKey(array $questionCatalog): string
    {
        $preferredTypes = ['radio', 'select', 'multiselect', 'checkbox', 'scale', 'slider', 'matrix', 'number'];

        foreach ($preferredTypes as $type) {
            $match = collect($questionCatalog)->first(fn (array $question) => strtolower((string) ($question['type'] ?? '')) === $type);
            if ($match) {
                return (string) ($match['key'] ?? '');
            }
        }

        return (string) array_key_first($questionCatalog);
    }

    protected function buildQuestionStats(Collection $responses, array $questionCatalog): array
    {
        $stats = collect($questionCatalog)
            ->mapWithKeys(function (array $question, string $key) {
                return [$key => [
                    'key' => $key,
                    'label' => (string) ($question['label'] ?? 'Question'),
                    'section_title' => (string) ($question['section_title'] ?? ''),
                    'type' => strtolower((string) ($question['type'] ?? 'text')),
                    'answered_count' => 0,
                    'headline' => 'No responses yet.',
                    '_values' => [],
                ]];
            })
            ->all();

        foreach ($responses as $response) {
            foreach ((array) ($response->answers ?? []) as $answerItem) {
                if (!is_array($answerItem)) {
                    continue;
                }

                $key = $this->reportQuestionKey($answerItem);
                if ($key === '') {
                    continue;
                }

                if (!array_key_exists($key, $stats)) {
                    $stats[$key] = [
                        'key' => $key,
                        'label' => (string) ($answerItem['question'] ?? 'Question'),
                        'section_title' => (string) ($answerItem['section'] ?? ''),
                        'type' => strtolower((string) ($answerItem['type'] ?? 'text')),
                        'answered_count' => 0,
                        'headline' => 'No responses yet.',
                        '_values' => [],
                    ];
                }

                $answer = $answerItem['answer'] ?? null;
                if ($this->hasUsableAnswer($answer)) {
                    $stats[$key]['answered_count']++;
                    $stats[$key]['_values'][] = $answer;
                }
            }
        }

        $totalResponses = $responses->count();

        return collect($stats)
            ->map(function (array $stat) use ($totalResponses) {
                $stat['completion_rate'] = $totalResponses > 0
                    ? round(($stat['answered_count'] / $totalResponses) * 100, 1)
                    : 0.0;
                $stat['headline'] = $this->questionHeadline($stat['type'], collect($stat['_values']));
                unset($stat['_values']);

                return $stat;
            })
            ->sortByDesc('answered_count')
            ->values()
            ->all();
    }

    protected function buildResponseTimeline(Collection $responses): array
    {
        if ($responses->isEmpty()) {
            return [
                'labels' => [],
                'keys' => [],
                'daily' => [],
                'cumulative' => [],
                'days_count' => 0,
                'is_monthly' => false,
            ];
        }

        $dates = $responses
            ->pluck('submitted_at')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->values();

        $groupMonthly = $dates->map(fn (Carbon $date) => $date->format('Y-m-d'))->unique()->count() > 14;

        $counts = [];
        $labels = [];
        foreach ($dates as $date) {
            $key = $groupMonthly ? $date->format('Y-m') : $date->format('Y-m-d');
            $labels[$key] = $groupMonthly ? $date->format('M Y') : $date->format('d M');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);
        $keys = array_keys($counts);
        $displayLabels = array_map(fn ($key) => $labels[$key] ?? $key, $keys);
        $daily = array_values($counts);

        $running = 0;
        $cumulative = array_map(function ($count) use (&$running) {
            $running += $count;
            return $running;
        }, $daily);

        return [
            'labels' => $displayLabels,
            'keys' => $keys,
            'daily' => $daily,
            'cumulative' => $cumulative,
            'days_count' => $dates->first()->diffInDays($dates->last()) + 1,
            'is_monthly' => $groupMonthly,
        ];
    }

    protected function buildFocusCharts(Collection $responses, ?array $selectedQuestion, array $timeline): array
    {
        if (!$selectedQuestion) {
            $distribution = $responses
                ->groupBy(fn (IndicatorSurveyResponse $response) => (string) ($response->indicator->name ?? 'Unassigned indicator'))
                ->map->count()
                ->sortDesc();

            return [
                'title' => 'Responses by Indicator',
                'pie' => [
                    'labels' => $distribution->keys()->values()->all(),
                    'data' => $distribution->values()->values()->all(),
                ],
                'bar' => [
                    'labels' => $distribution->keys()->values()->all(),
                    'data' => $distribution->values()->values()->all(),
                ],
                'insights' => [
                    'No question field is selected yet, so the report is showing the cumulative response distribution by indicator.',
                ],
            ];
        }

        $type = strtolower((string) ($selectedQuestion['type'] ?? 'text'));
        $questionKey = (string) ($selectedQuestion['key'] ?? '');
        $answers = $responses
            ->map(function (IndicatorSurveyResponse $response) use ($questionKey) {
                $answerItem = $this->findAnswerItem($response, $questionKey);

                return [
                    'submitted_at' => $response->submitted_at,
                    'answer' => $answerItem['answer'] ?? null,
                ];
            })
            ->filter(fn (array $item) => $this->hasUsableAnswer($item['answer']))
            ->values();

        $insights = [];
        $pie = ['labels' => [], 'data' => []];
        $bar = ['labels' => [], 'data' => []];

        if (in_array($type, ['select', 'radio', 'checkbox', 'multiselect'], true)) {
            $optionCounts = $this->categoricalDistribution($answers->pluck('answer'), (array) ($selectedQuestion['options'] ?? []));
            $pie = $bar = [
                'labels' => array_keys($optionCounts),
                'data' => array_values($optionCounts),
            ];

            if (!empty($optionCounts)) {
                arsort($optionCounts);
                $topLabel = array_key_first($optionCounts);
                $topValue = $optionCounts[$topLabel] ?? 0;
                $insights[] = $topLabel . ' is the most selected answer with ' . $topValue . ' responses.';
            }
        } elseif (in_array($type, ['scale', 'slider', 'number'], true)) {
            $valueCounts = $this->numericDistribution($answers->pluck('answer'));
            $pie = $bar = [
                'labels' => array_keys($valueCounts),
                'data' => array_values($valueCounts),
            ];

            if (!empty($valueCounts)) {
                $numbers = collect($answers->pluck('answer'))
                    ->flatten()
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value) => (float) $value)
                    ->values();

                if ($numbers->isNotEmpty()) {
                    $insights[] = 'Average score is ' . number_format($numbers->avg(), 1) . ' across ' . $numbers->count() . ' numeric responses.';
                }
            }
        } elseif ($type === 'matrix') {
            $matrixHeatmap = $this->buildMatrixHeatmap($answers->pluck('answer'), $selectedQuestion);
            $rowTotals = collect($matrixHeatmap['values'])
                ->map(fn (array $row) => array_sum($row))
                ->values();
            $columnTotals = collect(range(0, count($matrixHeatmap['columns']) - 1))
                ->map(function (int $columnIndex) use ($matrixHeatmap) {
                    return collect($matrixHeatmap['values'])->sum(fn (array $row) => $row[$columnIndex] ?? 0);
                })
                ->values();

            $bar = [
                'labels' => $matrixHeatmap['rows'],
                'data' => $rowTotals->all(),
            ];
            $pie = [
                'labels' => $matrixHeatmap['columns'],
                'data' => $columnTotals->all(),
            ];

            if ($columnTotals->isNotEmpty()) {
                $topColumnIndex = $columnTotals->search($columnTotals->max());
                if ($topColumnIndex !== false) {
                    $insights[] = ($matrixHeatmap['columns'][$topColumnIndex] ?? 'One column')
                        . ' is the most common grid outcome across the filtered responses.';
                }
            }
        } else {
            $answered = $answers->count();
            $missing = max($responses->count() - $answered, 0);

            $pie = [
                'labels' => ['Answered', 'Missing'],
                'data' => [$answered, $missing],
            ];

            $answeredByBucket = array_fill_keys($timeline['keys'], 0);
            foreach ($answers as $entry) {
                if (!$entry['submitted_at']) {
                    continue;
                }

                $date = Carbon::parse($entry['submitted_at']);
                $bucketKey = $timeline['is_monthly'] ? $date->format('Y-m') : $date->format('Y-m-d');
                if (array_key_exists($bucketKey, $answeredByBucket)) {
                    $answeredByBucket[$bucketKey]++;
                }
            }

            $bar = [
                'labels' => $timeline['labels'],
                'data' => array_values($answeredByBucket),
            ];

            $insights[] = 'This field has been answered in ' . $answered . ' of the ' . $responses->count() . ' filtered responses.';
        }

        if (empty($insights)) {
            $insights[] = 'The selected question is being summarized from the currently filtered response set.';
        }

        return [
            'title' => (string) ($selectedQuestion['label'] ?? 'Question analysis'),
            'pie' => $pie,
            'bar' => $bar,
            'insights' => $insights,
        ];
    }

    protected function buildHeatmap(
        Collection $responses,
        array $questionCatalog,
        array $timeline,
        ?array $selectedQuestion
    ): array {
        if ($selectedQuestion && strtolower((string) ($selectedQuestion['type'] ?? '')) === 'matrix') {
            $answers = $responses
                ->map(fn (IndicatorSurveyResponse $response) => $this->findAnswerItem($response, (string) ($selectedQuestion['key'] ?? '')))
                ->filter(fn ($item) => is_array($item) && $this->hasUsableAnswer($item['answer'] ?? null))
                ->pluck('answer');

            return $this->buildMatrixHeatmap($answers, $selectedQuestion);
        }

        $questionRows = collect($this->buildQuestionStats($responses, $questionCatalog))
            ->take(8)
            ->values();

        if ($questionRows->isEmpty() || empty($timeline['keys'])) {
            return [
                'title' => 'Question Completion Heatmap',
                'description' => 'No enough data is available yet to render the heatmap.',
                'rows' => [],
                'columns' => [],
                'values' => [],
                'max' => 0,
            ];
        }

        $rowKeys = $questionRows->pluck('key')->all();
        $matrix = [];
        foreach ($rowKeys as $rowKey) {
            $matrix[$rowKey] = array_fill_keys($timeline['keys'], 0);
        }

        foreach ($responses as $response) {
            if (!$response->submitted_at) {
                continue;
            }

            $date = Carbon::parse($response->submitted_at);
            $bucketKey = $timeline['is_monthly'] ? $date->format('Y-m') : $date->format('Y-m-d');

            foreach ((array) ($response->answers ?? []) as $answerItem) {
                if (!is_array($answerItem)) {
                    continue;
                }

                $questionKey = $this->reportQuestionKey($answerItem);
                if (!array_key_exists($questionKey, $matrix)) {
                    continue;
                }

                if ($this->hasUsableAnswer($answerItem['answer'] ?? null) && array_key_exists($bucketKey, $matrix[$questionKey])) {
                    $matrix[$questionKey][$bucketKey]++;
                }
            }
        }

        $values = [];
        $maxValue = 0;
        foreach ($questionRows as $questionRow) {
            $rowValues = array_values($matrix[$questionRow['key']] ?? []);
            $values[] = $rowValues;
            $maxValue = max(array_merge([$maxValue, 0], $rowValues));
        }

        return [
            'title' => 'Question Field Heatmap',
            'description' => 'Each cell shows how often a question field was answered in each reporting period after filters were applied.',
            'rows' => $questionRows->map(function (array $questionRow) {
                return Str::limit($questionRow['label'], 44);
            })->all(),
            'columns' => $timeline['labels'],
            'values' => $values,
            'max' => $maxValue,
        ];
    }

    protected function buildMatrixHeatmap(Collection $answers, array $selectedQuestion): array
    {
        $rows = collect((array) ($selectedQuestion['rows'] ?? []))
            ->map(fn ($row) => (string) data_get($row, 'label', $row))
            ->filter()
            ->values();
        $columns = collect((array) ($selectedQuestion['columns'] ?? []))
            ->map(fn ($column) => (string) data_get($column, 'label', $column))
            ->filter()
            ->values();

        if ($rows->isEmpty() || $columns->isEmpty()) {
            $rows = $answers
                ->flatMap(fn ($answer) => is_array($answer) ? array_keys($answer) : [])
                ->filter()
                ->unique()
                ->values();
            $columns = $answers
                ->flatMap(fn ($answer) => is_array($answer) ? array_values($answer) : [])
                ->filter()
                ->unique()
                ->values();
        }

        $values = [];
        $maxValue = 0;

        foreach ($rows as $rowLabel) {
            $rowValues = [];
            foreach ($columns as $columnLabel) {
                $count = $answers->filter(function ($answer) use ($rowLabel, $columnLabel) {
                    return is_array($answer)
                        && ((string) ($answer[$rowLabel] ?? '') === (string) $columnLabel);
                })->count();

                $rowValues[] = $count;
                $maxValue = max($maxValue, $count);
            }
            $values[] = $rowValues;
        }

        return [
            'title' => (string) ($selectedQuestion['label'] ?? 'Matrix Heatmap'),
            'description' => 'The heatmap shows which matrix row and column combinations appear most often in the filtered results.',
            'rows' => $rows->all(),
            'columns' => $columns->all(),
            'values' => $values,
            'max' => $maxValue,
        ];
    }

    protected function buildReportSummary(
        Collection $responses,
        array $timeline,
        array $questionStats,
        ?array $selectedQuestion,
        array $focusCharts,
        Collection $methodologies
    ): array {
        $summary = [];

        if ($responses->isEmpty()) {
            return ['No survey responses match the current report filters yet.'];
        }

        $summary[] = 'This report covers ' . $responses->count() . ' responses across '
            . $responses->pluck('methodology_id')->filter()->unique()->count() . ' questionnaire(s), '
            . $responses->pluck('indicator_id')->filter()->unique()->count() . ' indicator(s), and '
            . $responses->pluck('survey_link_id')->filter()->unique()->count() . ' public survey link(s).';

        if (!empty($timeline['daily'])) {
            $peakCount = max($timeline['daily']);
            $peakIndex = array_search($peakCount, $timeline['daily'], true);
            if ($peakIndex !== false) {
                $summary[] = 'The highest reporting volume was recorded in '
                    . ($timeline['labels'][$peakIndex] ?? 'the selected period')
                    . ' with ' . $peakCount . ' response(s).';
            }
        }

        $topQuestions = collect($questionStats)->take(3)->map(function (array $question) {
            return $question['label'] . ' (' . $question['completion_rate'] . '% completion)';
        })->implode(', ');

        if ($topQuestions !== '') {
            $summary[] = 'The most complete question fields in this filter set are: ' . $topQuestions . '.';
        }

        if ($selectedQuestion) {
            $summary[] = 'Question focus: ' . (string) ($selectedQuestion['label'] ?? 'Selected question') . '.';
        }

        foreach ((array) ($focusCharts['insights'] ?? []) as $insight) {
            $summary[] = (string) $insight;
        }

        return $summary;
    }

    protected function questionHeadline(string $type, Collection $values): string
    {
        if ($values->isEmpty()) {
            return 'No responses yet.';
        }

        if (in_array($type, ['select', 'radio', 'checkbox', 'multiselect'], true)) {
            $distribution = $this->categoricalDistribution($values);
            if (empty($distribution)) {
                return 'Responses captured.';
            }

            arsort($distribution);
            $topLabel = array_key_first($distribution);

            return 'Top answer: ' . $topLabel . ' (' . ($distribution[$topLabel] ?? 0) . ').';
        }

        if (in_array($type, ['scale', 'slider', 'number'], true)) {
            $numbers = $values
                ->flatten()
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (float) $value)
                ->values();

            return $numbers->isNotEmpty()
                ? 'Average: ' . number_format($numbers->avg(), 1) . '.'
                : 'Numeric responses captured.';
        }

        if ($type === 'matrix') {
            return 'Matrix responses recorded across the selected rows and columns.';
        }

        if ($type === 'file') {
            return $values->count() . ' upload(s) received.';
        }

        return $values->count() . ' response(s) captured.';
    }

    protected function buildResponseRegister(Collection $responses): array
    {
        return $responses
            ->sortByDesc(fn (IndicatorSurveyResponse $response) => optional($response->submitted_at)->timestamp ?? 0)
            ->values()
            ->map(function (IndicatorSurveyResponse $response, int $index) {
                $answerItems = collect((array) ($response->answers ?? []))
                    ->filter(fn ($answerItem) => is_array($answerItem))
                    ->map(function (array $answerItem) {
                        $value = $this->formatReportAnswerValue($answerItem);

                        return [
                            'section_title' => trim((string) ($answerItem['section'] ?? '')) ?: 'General section',
                            'question' => trim((string) ($answerItem['question'] ?? 'Question')),
                            'type' => Str::headline(strtolower((string) ($answerItem['type'] ?? 'text'))),
                            'value' => $value !== '' ? $value : 'No answer captured.',
                        ];
                    })
                    ->values();

                $answeredCount = $answerItems
                    ->filter(fn (array $answerItem) => ($answerItem['value'] ?? '') !== 'No answer captured.')
                    ->count();

                return [
                    'response_number' => $index + 1,
                    'response_id' => $response->id,
                    'submitted_at' => optional($response->submitted_at)->format('d M Y H:i') ?: 'Unknown submission time',
                    'respondent_name' => trim((string) ($response->respondent_name ?? '')) ?: 'Anonymous respondent',
                    'respondent_email' => trim((string) ($response->respondent_email ?? '')),
                    'respondent_phone' => trim((string) ($response->respondent_phone ?? '')),
                    'respondent_organization' => trim((string) ($response->respondent_organization ?? '')),
                    'indicator_name' => (string) ($response->indicator->name ?? 'Unassigned indicator'),
                    'methodology_name' => (string) ($response->methodology->name ?? 'Questionnaire'),
                    'survey_token' => (string) ($response->surveyLink->public_token ?? ''),
                    'answers_count' => $answeredCount,
                    'question_count' => $answerItems->count(),
                    'answers' => $answerItems->all(),
                ];
            })
            ->all();
    }

    protected function categoricalDistribution(Collection $values, array $preferredOrder = []): array
    {
        $counts = collect($preferredOrder)
            ->mapWithKeys(fn ($label) => [(string) $label => 0])
            ->all();

        foreach ($values as $value) {
            if (is_array($value)) {
                foreach ($value as $innerValue) {
                    if (is_scalar($innerValue) && trim((string) $innerValue) !== '') {
                        $label = trim((string) $innerValue);
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }
                }
                continue;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                $label = trim((string) $value);
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        return collect($counts)
            ->filter(fn ($count) => $count > 0)
            ->all();
    }

    protected function numericDistribution(Collection $values): array
    {
        return $values
            ->flatten()
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (string) $value)
            ->countBy()
            ->sortKeysUsing(function ($left, $right) {
                return ((float) $left) <=> ((float) $right);
            })
            ->all();
    }

    protected function findAnswerItem(IndicatorSurveyResponse $response, string $questionKey): ?array
    {
        return collect((array) ($response->answers ?? []))
            ->first(function ($answerItem) use ($questionKey) {
                return is_array($answerItem)
                    && $this->reportQuestionKey($answerItem) === $questionKey;
            });
    }

    protected function reportQuestionKey(array $answerItem): string
    {
        $key = trim((string) ($answerItem['question_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $fallback = trim((string) ($answerItem['question'] ?? ''));
        if ($fallback === '') {
            return '';
        }

        return Str::of($fallback)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    protected function formatReportAnswerValue(array $answerItem): string
    {
        $displayValue = $answerItem['answer'] ?? null;

        return $this->stringifyReportValue($displayValue);
    }

    protected function stringifyReportValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(function ($item, $key) {
                    $formatted = $this->stringifyReportValue($item);
                    if ($formatted === '') {
                        return null;
                    }

                    return is_string($key)
                        ? ($key . ': ' . $formatted)
                        : $formatted;
                })
                ->filter()
                ->implode(' | ');
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    protected function hasUsableAnswer(mixed $answer): bool
    {
        if (is_array($answer)) {
            return collect($answer)
                ->flatten()
                ->filter(function ($value) {
                    return is_scalar($value) && trim((string) $value) !== '';
                })
                ->isNotEmpty();
        }

        return is_scalar($answer) && trim((string) $answer) !== '';
    }

    protected function paginateCollection(
        Collection $items,
        Request $request,
        int $perPage = 10,
        string $pageName = 'page'
    ): LengthAwarePaginator {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ]
        );
    }
}

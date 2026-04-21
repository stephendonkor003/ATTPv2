<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Support\MeSurvey;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        ]);
        $this->middleware('permission:me.configuration.manage')->only([
            'create',
            'edit',
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

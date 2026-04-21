<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Support\MeSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IndicatorSurveyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.manage')->only(['generateLink']);
        $this->middleware('permission:me.configuration.view')->only(['responses']);
    }

    public function generateLink(Indicator $indicator): RedirectResponse
    {
        [$methodology, $surveyConfig] = $this->resolveSurveyMethodologyForIndicator($indicator);
        if (!$methodology || !$surveyConfig) {
            return redirect()
                ->route('budget.me.indicators.index', ['tab' => 'settings'])
                ->withErrors([
                    'survey_link' => 'This indicator does not use an active survey methodology with configured questions.',
                ]);
        }

        $link = IndicatorSurveyLink::query()->firstOrNew([
            'indicator_id' => $indicator->id,
        ]);

        $isNew = !$link->exists;
        $mustRefreshToken = $isNew
            || !$link->public_token
            || $link->methodology_id !== $methodology->id;

        $link->methodology_id = $methodology->id;
        $link->is_active = true;
        $link->updated_by = auth()->id();
        if ($isNew) {
            $link->created_by = auth()->id();
        }
        if ($mustRefreshToken) {
            $link->public_token = Str::random(64);
        }
        $link->save();

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'settings'])
            ->with('success', 'Public survey link is ready. You can now copy and share it.');
    }

    public function responses(Indicator $indicator): View
    {
        $indicator->load('surveyLink');

        $responses = IndicatorSurveyResponse::query()
            ->where('indicator_id', $indicator->id)
            ->orderByDesc('submitted_at')
            ->paginate(25);

        return view('me.surveys.responses', [
            'indicator' => $indicator,
            'responses' => $responses,
        ]);
    }

    protected function resolveSurveyMethodologyForIndicator(Indicator $indicator): array
    {
        $methodologyName = strtolower(trim((string) $indicator->methodology));
        if ($methodologyName === '') {
            return [null, null];
        }

        $methodology = IndicatorMethodology::query()
            ->where('is_active', true)
            ->get()
            ->first(function (IndicatorMethodology $item) use ($methodologyName) {
                return strtolower(trim((string) $item->name)) === $methodologyName;
            });

        if (!$methodology) {
            return [null, null];
        }

        $survey = MeSurvey::surveyConfigFromMetadata(
            (array) ($methodology->metadata ?? []),
            trim((string) $methodology->name) !== '' ? ($methodology->name . ' Public Survey') : 'Public Survey'
        );

        if (!(bool) ($survey['enabled'] ?? false) || empty($survey['questions'])) {
            return [null, null];
        }

        return [$methodology, $survey];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\IndicatorLevel;
use App\Models\ReportingFrequency;
use App\Models\IndicatorUnit;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorDefinitionVariable;
use App\Models\Indicator;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Support\MeSurveyCleanup;
use App\Support\MeSurvey;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MeConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:me.configuration.view')->only([
            'indicatorLevelsIndex',
            'frequenciesIndex',
            'unitsIndex',
            'definitionsIndex',
            'methodologiesIndex',
        ]);
        $this->middleware('permission:me.configuration.manage')->except([
            'indicatorLevelsIndex',
            'frequenciesIndex',
            'unitsIndex',
            'definitionsIndex',
            'methodologiesIndex',
        ]);
    }

    // ===== Indicator Levels =====

    public function indicatorLevelsIndex()
    {
        $levels = IndicatorLevel::active()->ordered()->paginate(20);
        return view('me.indicator-levels.index', compact('levels'));
    }

    public function indicatorLevelsCreate()
    {
        return view('me.indicator-levels.create');
    }

    public function indicatorLevelsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_indicator_levels',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        IndicatorLevel::create($validated);

        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level created successfully');
    }

    public function indicatorLevelsEdit(IndicatorLevel $level)
    {
        return view('me.indicator-levels.edit', compact('level'));
    }

    public function indicatorLevelsUpdate(Request $request, IndicatorLevel $level)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_indicator_levels,name,' . $level->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $level->update($validated);

        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level updated successfully');
    }

    public function indicatorLevelsDestroy(IndicatorLevel $level)
    {
        $level->delete();
        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level deleted successfully');
    }

    // ===== Reporting Frequencies =====

    public function frequenciesIndex()
    {
        $frequencies = ReportingFrequency::active()->ordered()->paginate(20);
        return view('me.frequencies.index', compact('frequencies'));
    }

    public function frequenciesCreate()
    {
        $intervalOptions = ReportingFrequency::intervalOptions();
        return view('me.frequencies.create', compact('intervalOptions'));
    }

    public function frequenciesStore(Request $request)
    {
        $allowedIntervalUnits = implode(',', array_keys(ReportingFrequency::intervalOptions()));
        $validated = $request->validate([
            'name' => 'required|string|unique:me_reporting_frequencies',
            'code' => 'required|string|unique:me_reporting_frequencies',
            'interval_unit' => 'required|in:' . $allowedIntervalUnits,
            'interval_value' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ((string) $request->input('interval_unit') !== 'once' && empty($value)) {
                        $fail('Interval value is required unless interval unit is Once.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        [$intervalUnit, $intervalValue, $frequencyInDays] = $this->normalizeFrequencyInterval(
            (string) $validated['interval_unit'],
            isset($validated['interval_value']) ? (int) $validated['interval_value'] : null
        );
        $validated['interval_unit'] = $intervalUnit;
        $validated['interval_value'] = $intervalValue;
        $validated['frequency_in_days'] = $frequencyInDays;

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ReportingFrequency::create($validated);

        return redirect()->route('budget.me-configuration.frequencies.index')
            ->with('success', 'Reporting Frequency created successfully');
    }

    public function frequenciesEdit(ReportingFrequency $frequency)
    {
        $intervalOptions = ReportingFrequency::intervalOptions();
        return view('me.frequencies.edit', compact('frequency', 'intervalOptions'));
    }

    public function frequenciesUpdate(Request $request, ReportingFrequency $frequency)
    {
        $allowedIntervalUnits = implode(',', array_keys(ReportingFrequency::intervalOptions()));
        $validated = $request->validate([
            'name' => 'required|string|unique:me_reporting_frequencies,name,' . $frequency->id,
            'code' => 'required|string|unique:me_reporting_frequencies,code,' . $frequency->id,
            'interval_unit' => 'required|in:' . $allowedIntervalUnits,
            'interval_value' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ((string) $request->input('interval_unit') !== 'once' && empty($value)) {
                        $fail('Interval value is required unless interval unit is Once.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        [$intervalUnit, $intervalValue, $frequencyInDays] = $this->normalizeFrequencyInterval(
            (string) $validated['interval_unit'],
            isset($validated['interval_value']) ? (int) $validated['interval_value'] : null
        );
        $validated['interval_unit'] = $intervalUnit;
        $validated['interval_value'] = $intervalValue;
        $validated['frequency_in_days'] = $frequencyInDays;

        $validated['is_active'] = $request->has('is_active');
        $frequency->update($validated);

        return redirect()->route('budget.me-configuration.frequencies.index')
            ->with('success', 'Reporting Frequency updated successfully');
    }

    public function frequenciesDestroy(ReportingFrequency $frequency)
    {
        $frequency->delete();
        return redirect()->route('budget.me-configuration.frequencies.index')
            ->with('success', 'Reporting Frequency deleted successfully');
    }

    protected function normalizeFrequencyInterval(string $intervalUnit, ?int $intervalValue): array
    {
        $allowedUnits = array_keys(ReportingFrequency::intervalOptions());
        if (!in_array($intervalUnit, $allowedUnits, true)) {
            $intervalUnit = 'day';
        }

        if ($intervalUnit === 'once') {
            return ['once', null, null];
        }

        $value = ($intervalValue && $intervalValue > 0) ? $intervalValue : 1;

        $frequencyInDays = match ($intervalUnit) {
            'second', 'minute', 'hour' => null,
            'day' => $value,
            'week' => $value * 7,
            'month' => $value * 30,
            'quarterly' => $value * 90,
            'year', 'annual' => $value * 365,
            'quinquennial' => $value * (365 * 5),
            default => null,
        };

        return [$intervalUnit, $value, $frequencyInDays];
    }

    // ===== Indicator Units =====

    public function unitsIndex()
    {
        $units = IndicatorUnit::active()->ordered()->paginate(20);
        return view('me.units.index', compact('units'));
    }

    public function unitsCreate()
    {
        return view('me.units.create');
    }

    public function unitsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_indicator_units',
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = ((int) IndicatorUnit::max('sort_order')) + 1;

        IndicatorUnit::create($validated);

        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit created successfully');
    }

    public function unitsEdit(IndicatorUnit $unit)
    {
        return view('me.units.edit', compact('unit'));
    }

    public function unitsUpdate(Request $request, IndicatorUnit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_indicator_units,name,' . $unit->id,
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $unit->update($validated);

        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit updated successfully');
    }

    public function unitsDestroy(IndicatorUnit $unit)
    {
        $unit->delete();
        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit deleted successfully');
    }

    // ===== Indicator Definitions (formulas) =====
    public function definitionsIndex()
    {
        $definitions = IndicatorDefinition::orderBy('name')->paginate(20);
        return view('me.definitions.index', compact('definitions'));
    }

    public function definitionsCreate()
    {
        $definition = null;
        $stats = $this->definitionStats();
        return view('me.definitions.create', compact('definition','stats'));
    }

    public function definitionsStore(Request $request)
    {
        $variables = json_decode($request->input('variables_json') ?? '[]', true);
        $formula = json_decode($request->input('formula_json') ?? '{}', true);
        $validated = $this->validateDefinition($request);
        $validated['variables'] = $variables; // keep json column for backward compatibility
        $validated['formula'] = $formula;
        $validated['created_by'] = auth()->id();

        DB::transaction(function () use ($validated, $variables) {
            $definition = IndicatorDefinition::create($validated);
            $this->syncDefinitionVariables($definition, $variables);
        });

        return redirect()->route('budget.me-configuration.definitions.index')
            ->with('success', 'Definition created successfully');
    }

    public function definitionsEdit(IndicatorDefinition $definition)
    {
        $definition->load('variableRows');
        $stats = $this->definitionStats();
        return view('me.definitions.edit', compact('definition','stats'));
    }

    public function definitionsUpdate(Request $request, IndicatorDefinition $definition)
    {
        $variables = json_decode($request->input('variables_json') ?? '[]', true);
        $formula = json_decode($request->input('formula_json') ?? '{}', true);
        $validated = $this->validateDefinition($request);
        $validated['variables'] = $variables;
        $validated['formula'] = $formula;

        DB::transaction(function () use ($definition, $validated, $variables) {
            $definition->update($validated);
            $this->syncDefinitionVariables($definition, $variables);
        });

        return redirect()->route('budget.me-configuration.definitions.index')
            ->with('success', 'Definition updated successfully');
    }

    public function definitionsDestroy(IndicatorDefinition $definition)
    {
        $definition->delete();
        return redirect()->route('budget.me-configuration.definitions.index')
            ->with('success', 'Definition deleted successfully');
    }

    protected function validateDefinition(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    }

    protected function definitionStats(): array
    {
        $defs = IndicatorDefinition::all();
        $totalFormulas = $defs->count();
        $totalVariables = IndicatorDefinitionVariable::count();
        $totalFunctions = $defs->sum(function ($d) {
            $expr = '';
            if (is_array($d->formula) && isset($d->formula['expression'])) {
                $expr = $d->formula['expression'];
            }
            return substr_count($expr, '('); // rough count
        });
        return [
            'formulas' => $totalFormulas,
            'variables' => $totalVariables,
            'functions' => $totalFunctions,
        ];
    }

    protected function syncDefinitionVariables(IndicatorDefinition $definition, array $variables): void
    {
        IndicatorDefinitionVariable::where('indicator_definition_id', $definition->id)->delete();
        foreach ($variables as $v) {
            $name = $v['label'] ?? $v['name'] ?? null;
            if (!$name) {
                continue;
            }
            IndicatorDefinitionVariable::create([
                'indicator_definition_id' => $definition->id,
                'name' => $name,
                'color' => $v['color'] ?? null,
                'created_by' => auth()->id(),
            ]);
        }
    }

    // ===== Methodologies =====
    public function methodologiesIndex()
    {
        $methodologies = IndicatorMethodology::orderBy('name')->paginate(20);
        return view('me.methodologies.index', compact('methodologies'));
    }

    public function methodologiesCreate()
    {
        return view('me.methodologies.create');
    }

    public function methodologiesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'survey_public_enabled' => 'nullable|boolean',
            'survey_title' => 'nullable|string|max:255',
            'survey_intro' => 'nullable|string|max:2000',
            'survey_estimated_minutes' => 'nullable|integer|min:1|max:240',
            'survey_sections_json' => 'nullable|string',
            'survey_questions_json' => 'nullable|string',
        ]);

        $surveySections = $this->parseSurveySections(
            (string) $request->input('survey_sections_json', ''),
            (string) $request->input('survey_questions_json', '')
        );
        if (
            $this->shouldTreatMethodologyAsSurvey((string) $validated['name'], $request, $surveySections)
            && empty(MeSurvey::flattenQuestions(['sections' => $surveySections]))
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'survey_sections_json' => 'Add at least one survey section with questions before saving this questionnaire.',
                ]);
        }

        $validated['metadata'] = $this->buildMethodologyMetadata(
            (string) $validated['name'],
            $request,
            [],
            $surveySections
        );
        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        IndicatorMethodology::create($validated);

        return $this->redirectAfterMethodologySave($request, 'Methodology created successfully');
    }

    public function methodologiesEdit(IndicatorMethodology $methodology)
    {
        return view('me.methodologies.edit', compact('methodology'));
    }

    public function methodologiesUpdate(Request $request, IndicatorMethodology $methodology)
    {
        $previousName = (string) $methodology->name;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'survey_public_enabled' => 'nullable|boolean',
            'survey_title' => 'nullable|string|max:255',
            'survey_intro' => 'nullable|string|max:2000',
            'survey_estimated_minutes' => 'nullable|integer|min:1|max:240',
            'survey_sections_json' => 'nullable|string',
            'survey_questions_json' => 'nullable|string',
        ]);

        $surveySections = $this->parseSurveySections(
            (string) $request->input('survey_sections_json', ''),
            (string) $request->input('survey_questions_json', '')
        );
        if (
            $this->shouldTreatMethodologyAsSurvey((string) $validated['name'], $request, $surveySections)
            && empty(MeSurvey::flattenQuestions(['sections' => $surveySections]))
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'survey_sections_json' => 'Add at least one survey section with questions before saving this questionnaire.',
                ]);
        }

        $validated['metadata'] = $this->buildMethodologyMetadata(
            (string) $validated['name'],
            $request,
            (array) ($methodology->metadata ?? []),
            $surveySections
        );
        $validated['is_active'] = $request->has('is_active');
        $validated['updated_by'] = auth()->id();
        $methodology->update($validated);

        $newName = (string) $validated['name'];
        if (strtolower(trim($previousName)) !== strtolower(trim($newName))) {
            Indicator::query()
                ->whereRaw('LOWER(TRIM(methodology)) = ?', [strtolower(trim($previousName))])
                ->update(['methodology' => $newName]);
        }

        $surveyMeta = (array) data_get($validated['metadata'], 'survey', []);
        $surveyEnabled = (bool) ($surveyMeta['enabled'] ?? false);
        $questionCount = count(MeSurvey::flattenQuestions($surveyMeta));

        if (!$surveyEnabled || $questionCount === 0) {
            IndicatorSurveyLink::query()
                ->where('methodology_id', $methodology->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => auth()->id(),
                ]);
        }

        return $this->redirectAfterMethodologySave($request, 'Methodology updated successfully');
    }

    public function methodologiesDestroy(Request $request, IndicatorMethodology $methodology)
    {
        $attachmentPaths = DB::transaction(function () use ($methodology) {
            $responses = IndicatorSurveyResponse::query()
                ->where('methodology_id', $methodology->id)
                ->get(['id', 'answers']);

            $attachmentPaths = MeSurveyCleanup::attachmentPathsFromResponses($responses);

            IndicatorSurveyResponse::query()
                ->where('methodology_id', $methodology->id)
                ->delete();

            IndicatorSurveyLink::query()
                ->where('methodology_id', $methodology->id)
                ->delete();

            $methodology->delete();

            return $attachmentPaths;
        });

        if (!empty($attachmentPaths)) {
            Storage::disk('public')->delete($attachmentPaths);
        }

        return $this->redirectAfterMethodologySave($request, 'Methodology deleted successfully');
    }

    protected function isSurveyMethodologyName(string $name): bool
    {
        return str_contains(strtolower(trim($name)), 'survey');
    }

    protected function buildMethodologyMetadata(
        string $name,
        Request $request,
        array $existingMetadata = [],
        array $surveySections = []
    ): array {
        $metadata = $existingMetadata;

        if (!$this->shouldTreatMethodologyAsSurvey($name, $request, $surveySections)) {
            unset($metadata['survey']);
            return $metadata;
        }

        $defaultTitle = trim($name) !== '' ? trim($name) . ' Public Survey' : 'Public Survey';
        $existingSurvey = is_array($existingMetadata['survey'] ?? null)
            ? $existingMetadata['survey']
            : [];
        $normalizedSurvey = MeSurvey::surveyConfigFromMetadata([
            'survey' => array_merge($existingSurvey, [
                'enabled' => $request->has('survey_public_enabled'),
                'title' => trim((string) $request->input('survey_title', (string) ($existingSurvey['title'] ?? $defaultTitle))),
                'intro' => trim((string) $request->input('survey_intro', (string) ($existingSurvey['intro'] ?? ''))),
                'estimated_minutes' => $request->input('survey_estimated_minutes', $existingSurvey['estimated_minutes'] ?? null),
                'sections' => $surveySections,
            ]),
        ], $defaultTitle);

        $metadata['survey'] = array_merge($existingSurvey, [
            'enabled' => $normalizedSurvey['enabled'],
            'title' => $normalizedSurvey['title'],
            'intro' => $normalizedSurvey['intro'],
            'estimated_minutes' => $normalizedSurvey['estimated_minutes'],
            'respondent' => $normalizedSurvey['respondent'],
            'presentation' => $normalizedSurvey['presentation'],
            'sections' => $normalizedSurvey['sections'],
            'questions' => $normalizedSurvey['questions'],
            'updated_at' => now()->toDateTimeString(),
        ]);

        return $metadata;
    }

    protected function shouldTreatMethodologyAsSurvey(
        string $name,
        Request $request,
        array $surveySections = []
    ): bool {
        return $request->boolean('is_survey_methodology')
            || $this->isSurveyMethodologyName($name)
            || !empty(MeSurvey::flattenQuestions(['sections' => $surveySections]));
    }

    protected function redirectAfterMethodologySave(Request $request, string $message)
    {
        $route = $request->boolean('from_survey_module')
            ? 'budget.me.surveys.questionnaires'
            : 'budget.me-configuration.methodologies.index';

        return redirect()->route($route)->with('success', $message);
    }

    protected function parseSurveySections(string $rawSectionsJson, string $rawQuestionsJson = ''): array
    {
        $decodedSections = json_decode($rawSectionsJson, true);
        if (is_array($decodedSections) && !empty($decodedSections)) {
            return MeSurvey::surveyConfigFromMetadata([
                'survey' => [
                    'enabled' => true,
                    'sections' => $decodedSections,
                ],
            ])['sections'];
        }

        $decodedQuestions = json_decode($rawQuestionsJson, true);
        if (is_array($decodedQuestions) && !empty($decodedQuestions)) {
            return MeSurvey::surveyConfigFromMetadata([
                'survey' => [
                    'enabled' => true,
                    'questions' => $decodedQuestions,
                ],
            ])['sections'];
        }

        return [];
    }
}

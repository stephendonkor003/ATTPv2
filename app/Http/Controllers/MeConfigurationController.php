<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\IndicatorLevel;
use App\Models\ReportingFrequency;
use App\Models\IndicatorUnit;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorDefinitionVariable;
use App\Models\Indicator;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Models\Sector;
use App\Support\MeSurveyCleanup;
use App\Support\MeSurvey;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeConfigurationController extends Controller
{
    use ScopesAssignedPortfolios;

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
        $levelsQuery = IndicatorLevel::query()
            ->with('portfolio:id,name')
            ->active()
            ->ordered();
        $this->scopeMeConfigurationQuery($levelsQuery);
        $levels = $levelsQuery->paginate(20);

        return view('me.indicator-levels.index', compact('levels'));
    }

    public function indicatorLevelsCreate()
    {
        return view('me.indicator-levels.create', $this->configurationFormData());
    }

    public function indicatorLevelsStore(Request $request)
    {
        $portfolioId = $this->resolveConfigurationPortfolioId($request);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('me_indicator_levels', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId)),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['portfolio_id'] = $portfolioId;
        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        IndicatorLevel::create($validated);

        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level created successfully');
    }

    public function indicatorLevelsEdit(IndicatorLevel $level)
    {
        $this->assertMeConfigurationRecordManageable($level);

        return view('me.indicator-levels.edit', array_merge(compact('level'), $this->configurationFormData($level)));
    }

    public function indicatorLevelsUpdate(Request $request, IndicatorLevel $level)
    {
        $this->assertMeConfigurationRecordManageable($level);
        $portfolioId = $this->resolveConfigurationPortfolioId($request, $level);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('me_indicator_levels', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($level->id),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['portfolio_id'] = $portfolioId;
        $validated['is_active'] = $request->has('is_active');
        $level->update($validated);

        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level updated successfully');
    }

    public function indicatorLevelsDestroy(IndicatorLevel $level)
    {
        $this->assertMeConfigurationRecordManageable($level);

        $level->delete();
        return redirect()->route('budget.me-configuration.indicator-levels.index')
            ->with('success', 'Indicator Level deleted successfully');
    }

    // ===== Reporting Frequencies =====

    public function frequenciesIndex()
    {
        $frequenciesQuery = ReportingFrequency::query()
            ->with('portfolio:id,name')
            ->active()
            ->ordered();
        $this->scopeMeConfigurationQuery($frequenciesQuery);
        $frequencies = $frequenciesQuery->paginate(20);

        return view('me.frequencies.index', compact('frequencies'));
    }

    public function frequenciesCreate()
    {
        $intervalOptions = ReportingFrequency::intervalOptions();
        return view('me.frequencies.create', array_merge(compact('intervalOptions'), $this->configurationFormData()));
    }

    public function frequenciesStore(Request $request)
    {
        $allowedIntervalUnits = implode(',', array_keys(ReportingFrequency::intervalOptions()));
        $portfolioId = $this->resolveConfigurationPortfolioId($request);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_reporting_frequencies', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId)),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_reporting_frequencies', 'code')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId)),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
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

        $validated['portfolio_id'] = $portfolioId;
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

        $frequency = ReportingFrequency::create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            $frequency->load('portfolio:id,name');

            return response()->json([
                'message' => 'Reporting frequency created successfully.',
                'data' => [
                    'id' => $frequency->id,
                    'name' => $frequency->name,
                    'code' => $frequency->code,
                    'label' => $frequency->name,
                    'portfolio_id' => $frequency->portfolio_id,
                    'portfolio_name' => $frequency->portfolio?->name,
                    'interval_unit' => $frequency->interval_unit,
                    'interval_value' => $frequency->interval_value,
                ],
            ], 201);
        }

        return redirect()->route('budget.me-configuration.frequencies.index')
            ->with('success', 'Reporting Frequency created successfully');
    }

    public function frequenciesEdit(ReportingFrequency $frequency)
    {
        $this->assertMeConfigurationRecordManageable($frequency);

        $intervalOptions = ReportingFrequency::intervalOptions();
        return view('me.frequencies.edit', array_merge(compact('frequency', 'intervalOptions'), $this->configurationFormData($frequency)));
    }

    public function frequenciesUpdate(Request $request, ReportingFrequency $frequency)
    {
        $allowedIntervalUnits = implode(',', array_keys(ReportingFrequency::intervalOptions()));
        $this->assertMeConfigurationRecordManageable($frequency);
        $portfolioId = $this->resolveConfigurationPortfolioId($request, $frequency);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_reporting_frequencies', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($frequency->id),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_reporting_frequencies', 'code')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($frequency->id),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
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

        $validated['portfolio_id'] = $portfolioId;
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
        $this->assertMeConfigurationRecordManageable($frequency);

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
        $unitsQuery = IndicatorUnit::query()
            ->with('portfolio:id,name')
            ->active()
            ->ordered();
        $this->scopeMeConfigurationQuery($unitsQuery);
        $units = $unitsQuery->paginate(20);

        return view('me.units.index', compact('units'));
    }

    public function unitsCreate()
    {
        return view('me.units.create', $this->configurationFormData());
    }

    public function unitsStore(Request $request)
    {
        $portfolioId = $this->resolveConfigurationPortfolioId($request);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_indicator_units', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId)),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['portfolio_id'] = $portfolioId;
        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = ((int) IndicatorUnit::max('sort_order')) + 1;

        $unit = IndicatorUnit::create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            $unit->load('portfolio:id,name');
            $label = $unit->name . ($unit->symbol ? ' (' . $unit->symbol . ')' : '');

            return response()->json([
                'message' => 'Indicator unit created successfully.',
                'data' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'label' => $label,
                    'portfolio_id' => $unit->portfolio_id,
                    'portfolio_name' => $unit->portfolio?->name,
                ],
            ], 201);
        }

        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit created successfully');
    }

    public function unitsEdit(IndicatorUnit $unit)
    {
        $this->assertMeConfigurationRecordManageable($unit);

        return view('me.units.edit', array_merge(compact('unit'), $this->configurationFormData($unit)));
    }

    public function unitsUpdate(Request $request, IndicatorUnit $unit)
    {
        $this->assertMeConfigurationRecordManageable($unit);
        $portfolioId = $this->resolveConfigurationPortfolioId($request, $unit);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('me_indicator_units', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($unit->id),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['portfolio_id'] = $portfolioId;
        $validated['is_active'] = $request->has('is_active');
        $unit->update($validated);

        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit updated successfully');
    }

    public function unitsDestroy(IndicatorUnit $unit)
    {
        $this->assertMeConfigurationRecordManageable($unit);

        $unit->delete();
        return redirect()->route('budget.me-configuration.units.index')
            ->with('success', 'Indicator Unit deleted successfully');
    }

    // ===== Indicator Definitions (formulas) =====
    public function definitionsIndex()
    {
        $definitionsQuery = IndicatorDefinition::query()
            ->with('portfolio:id,name')
            ->orderBy('name');
        $this->scopeMeConfigurationQuery($definitionsQuery);
        $definitions = $definitionsQuery->paginate(20);

        return view('me.definitions.index', compact('definitions'));
    }

    public function definitionsCreate()
    {
        $definition = null;
        $stats = $this->definitionStats();
        return view('me.definitions.create', array_merge(compact('definition','stats'), $this->configurationFormData()));
    }

    public function definitionsStore(Request $request)
    {
        $variables = json_decode($request->input('variables_json') ?? '[]', true);
        $formula = json_decode($request->input('formula_json') ?? '{}', true);
        $portfolioId = $this->resolveConfigurationPortfolioId($request);
        $validated = $this->validateDefinition($request, $portfolioId);
        $validated['portfolio_id'] = $portfolioId;
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
        $this->assertMeConfigurationRecordManageable($definition);

        $definition->load('variableRows');
        $stats = $this->definitionStats();
        return view('me.definitions.edit', array_merge(compact('definition','stats'), $this->configurationFormData($definition)));
    }

    public function definitionsUpdate(Request $request, IndicatorDefinition $definition)
    {
        $variables = json_decode($request->input('variables_json') ?? '[]', true);
        $formula = json_decode($request->input('formula_json') ?? '{}', true);
        $this->assertMeConfigurationRecordManageable($definition);
        $portfolioId = $this->resolveConfigurationPortfolioId($request, $definition);
        $validated = $this->validateDefinition($request, $portfolioId, $definition);
        $validated['portfolio_id'] = $portfolioId;
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
        $this->assertMeConfigurationRecordManageable($definition);

        $definition->delete();
        return redirect()->route('budget.me-configuration.definitions.index')
            ->with('success', 'Definition deleted successfully');
    }

    protected function validateDefinition(Request $request, string $portfolioId, ?IndicatorDefinition $definition = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('indicator_definitions', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($definition?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('indicator_definitions', 'code')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($definition?->id),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    }

    protected function definitionStats(): array
    {
        $defsQuery = IndicatorDefinition::query();
        $this->scopeMeConfigurationQuery($defsQuery);
        $defs = $defsQuery->get(['id', 'formula']);
        $totalFormulas = $defs->count();
        $totalVariables = IndicatorDefinitionVariable::query()
            ->whereIn('indicator_definition_id', $defs->pluck('id')->all())
            ->count();
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
        $methodologiesQuery = IndicatorMethodology::query()
            ->with('portfolio:id,name')
            ->orderBy('name');
        $this->scopeMeConfigurationQuery($methodologiesQuery);
        $methodologies = $methodologiesQuery->paginate(20);

        return view('me.methodologies.index', compact('methodologies'));
    }

    public function methodologiesCreate()
    {
        return view('me.methodologies.create', $this->configurationFormData());
    }

    public function methodologiesStore(Request $request)
    {
        $portfolioId = $this->resolveConfigurationPortfolioId($request);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('indicator_methodologies', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId)),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
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

        $validated['portfolio_id'] = $portfolioId;
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
        $this->assertMeConfigurationRecordManageable($methodology);

        return view('me.methodologies.edit', array_merge(compact('methodology'), $this->configurationFormData($methodology)));
    }

    public function methodologiesUpdate(Request $request, IndicatorMethodology $methodology)
    {
        $previousName = (string) $methodology->name;
        $this->assertMeConfigurationRecordManageable($methodology);
        $portfolioId = $this->resolveConfigurationPortfolioId($request, $methodology);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('indicator_methodologies', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $portfolioId))
                    ->ignore($methodology->id),
            ],
            'portfolio_id' => 'required|exists:myb_sectors,id',
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

        $validated['portfolio_id'] = $portfolioId;
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
            $indicatorQuery = Indicator::query()
                ->whereRaw('LOWER(TRIM(methodology)) = ?', [strtolower(trim($previousName))]);
            if ($this->userHasAssignedPortfolioScope()) {
                $this->applyAssignedPortfolioScopeToIndicators($indicatorQuery);
            }
            $indicatorQuery->update(['methodology' => $newName]);
        }

        $surveyMeta = (array) data_get($validated['metadata'], 'survey', []);
        $surveyEnabled = (bool) ($surveyMeta['enabled'] ?? false);
        $questionCount = count(MeSurvey::flattenQuestions($surveyMeta));

        if (!$surveyEnabled || $questionCount === 0) {
            $surveyLinkQuery = IndicatorSurveyLink::query()
                ->where('methodology_id', $methodology->id);
            if ($this->userHasAssignedPortfolioScope()) {
                $surveyLinkQuery->whereHas('indicator', function ($indicatorQuery) {
                    $this->applyAssignedPortfolioScopeToIndicators($indicatorQuery);
                });
            }
            $surveyLinkQuery->update([
                'is_active' => false,
                'updated_by' => auth()->id(),
            ]);
        }

        return $this->redirectAfterMethodologySave($request, 'Methodology updated successfully');
    }

    public function methodologiesDestroy(Request $request, IndicatorMethodology $methodology)
    {
        $this->assertMeConfigurationRecordManageable($methodology);

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

    protected function scopeMeConfigurationQuery($query): void
    {
        $query->whereNotNull('portfolio_id');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query);
        }
    }

    protected function assertMeConfigurationRecordManageable(object $record): void
    {
        if (! $record->portfolio_id) {
            abort(403, 'This M&E configuration record is not assigned to a portfolio.');
        }

        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        if (! $this->portfolioOwnedRecordIsInAssignedPortfolio($record)) {
            abort(403, 'This M&E configuration record is outside your assigned portfolio.');
        }
    }

    protected function resolveConfigurationPortfolioId(Request $request, ?object $record = null): ?string
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            $portfolioId = $request->input('portfolio_id') ?: ($record?->portfolio_id ?? null);

            if (! $portfolioId || ! Sector::query()->whereKey($portfolioId)->exists()) {
                throw ValidationException::withMessages([
                    'portfolio_id' => 'Select the portfolio this M&E record belongs to.',
                ]);
            }

            return (string) $portfolioId;
        }

        $portfolioIds = $this->assignedPortfolioIds();
        $portfolioId = $request->input('portfolio_id')
            ?: ($record?->portfolio_id ?: ($portfolioIds[0] ?? null));

        if (! $portfolioId || ! in_array((string) $portfolioId, $portfolioIds, true)) {
            throw ValidationException::withMessages([
                'portfolio_id' => 'Select a portfolio assigned to your account.',
            ]);
        }

        return (string) $portfolioId;
    }

    protected function configurationFormData(?object $record = null): array
    {
        $portfolioOptionsQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioOptionsQuery);
        }

        $portfolioOptions = $portfolioOptionsQuery->get(['id', 'name']);
        $selectedPortfolioId = old(
            'portfolio_id',
            $record?->portfolio_id ?: ($this->userHasAssignedPortfolioScope() ? ($portfolioOptions->first()?->id) : null)
        );

        return [
            'portfolioOptions' => $portfolioOptions,
            'selectedPortfolioId' => $selectedPortfolioId,
            'portfolioFieldLocked' => $this->userHasAssignedPortfolioScope() || $portfolioOptions->count() === 1,
        ];
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

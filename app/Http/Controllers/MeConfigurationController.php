<?php

namespace App\Http\Controllers;

use App\Models\IndicatorLevel;
use App\Models\ReportingFrequency;
use App\Models\IndicatorUnit;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorDefinitionVariable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MeConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Add proper M&E admin role check if needed
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
        return view('me.frequencies.create');
    }

    public function frequenciesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_reporting_frequencies',
            'code' => 'required|string|unique:me_reporting_frequencies',
            'frequency_in_days' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ReportingFrequency::create($validated);

        return redirect()->route('budget.me-configuration.frequencies.index')
            ->with('success', 'Reporting Frequency created successfully');
    }

    public function frequenciesEdit(ReportingFrequency $frequency)
    {
        return view('me.frequencies.edit', compact('frequency'));
    }

    public function frequenciesUpdate(Request $request, ReportingFrequency $frequency)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:me_reporting_frequencies,name,' . $frequency->id,
            'code' => 'required|string|unique:me_reporting_frequencies,code,' . $frequency->id,
            'frequency_in_days' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

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
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

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
            'sort_order' => 'nullable|integer|min:0',
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
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = auth()->id();

        IndicatorMethodology::create($validated);

        return redirect()->route('budget.me-configuration.methodologies.index')
            ->with('success', 'Methodology created successfully');
    }

    public function methodologiesEdit(IndicatorMethodology $methodology)
    {
        return view('me.methodologies.edit', compact('methodology'));
    }

    public function methodologiesUpdate(Request $request, IndicatorMethodology $methodology)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $methodology->update($validated);

        return redirect()->route('budget.me-configuration.methodologies.index')
            ->with('success', 'Methodology updated successfully');
    }

    public function methodologiesDestroy(IndicatorMethodology $methodology)
    {
        $methodology->delete();
        return redirect()->route('budget.me-configuration.methodologies.index')
            ->with('success', 'Methodology deleted successfully');
    }
}

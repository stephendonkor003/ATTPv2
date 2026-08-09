<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\MeFramework;
use App\Models\MeIndicatorCalculationRule;
use App\Models\MeIndicatorReferenceSheet;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeFrameworkController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.framework.manage|me.targets.manage|me.configuration.manage')
            ->only('index');
        $this->middleware('permission:me.framework.manage|me.configuration.manage')
            ->only(['updateFramework', 'updateIndicator', 'storeReferenceSheet', 'storeCalculationRule']);
        $this->middleware('permission:me.targets.manage|me.framework.manage|me.configuration.manage')
            ->only('storeTarget');
    }

    public function index(Request $request)
    {
        $framework = MeFramework::query()->current()->firstOrFail();
        $indicators = Indicator::query()->where('framework_id', $framework->id)
            ->with(['projectComponent:id,project_id,name', 'unit:id,name,symbol', 'approvedReferenceSheet', 'targets.thinkTank:id,name', 'calculationRules'])
            ->orderBy('display_order')->get();
        $selected = $request->filled('indicator')
            ? $indicators->firstWhere('id', (string) $request->query('indicator'))
            : $indicators->first();

        return view('me.framework.index', [
            'framework' => $framework,
            'indicators' => $indicators,
            'selected' => $selected,
            'components' => Project::query()->whereIn('project_id', ['PROG00001-01', 'PROG00001-02', 'PROG00001-03'])->orderBy('project_id')->get(['id', 'project_id', 'name']),
            'thinkTanks' => ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'valueTypes' => Indicator::VALUE_TYPES,
            'reportingSources' => Indicator::REPORTING_SOURCES,
            'aggregationMethods' => Indicator::AGGREGATION_METHODS,
            'organizationRollupMethods' => Indicator::ORGANIZATION_ROLLUP_METHODS,
        ]);
    }

    public function updateFramework(Request $request, MeFramework $framework)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_development_objective' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'active', 'retired'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_current' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($framework, $validated, $request): void {
            if ((bool) ($validated['is_current'] ?? false)) {
                MeFramework::query()->where('code', $framework->code)->whereKeyNot($framework->id)->update(['is_current' => false]);
            }
            $framework->update($validated + [
                'is_current' => (bool) ($validated['is_current'] ?? false),
                'updated_by' => $request->user()->id,
                'approved_by' => $validated['status'] === 'active' ? $request->user()->id : $framework->approved_by,
                'approved_at' => $validated['status'] === 'active' ? now() : $framework->approved_at,
            ]);
        });

        return back()->with('success', 'Framework version settings updated. Historical results were not changed.');
    }

    public function updateIndicator(Request $request, Indicator $indicator)
    {
        $this->assertOfficial($indicator);
        $validated = $request->validate([
            'result_area' => ['nullable', 'string', 'max:5000'],
            'value_type' => ['required', Rule::in(array_keys(Indicator::VALUE_TYPES))],
            'target_type' => ['required', Rule::in(['cumulative', 'period', 'end_target', 'milestone'])],
            'reporting_source' => ['required', Rule::in(array_keys(Indicator::REPORTING_SOURCES))],
            'aggregation_method' => ['required', Rule::in(array_keys(Indicator::AGGREGATION_METHODS))],
            'organization_rollup_method' => ['required', Rule::in(array_keys(Indicator::ORGANIZATION_ROLLUP_METHODS))],
            'is_cumulative' => ['nullable', 'boolean'],
            'requires_evidence' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $indicator->update($validated + [
            'is_cumulative' => (bool) ($validated['is_cumulative'] ?? false),
            'requires_evidence' => (bool) ($validated['requires_evidence'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Indicator ownership and calculation configuration updated.');
    }

    public function storeReferenceSheet(Request $request, Indicator $indicator)
    {
        $this->assertOfficial($indicator);
        $validated = $request->validate([
            'definition' => ['required', 'string', 'max:10000'],
            'rationale' => ['nullable', 'string', 'max:10000'],
            'inclusion_criteria' => ['required', 'string', 'max:10000'],
            'exclusion_criteria' => ['required', 'string', 'max:10000'],
            'unit_of_measurement' => ['required', 'string', 'max:120'],
            'data_collection_method' => ['required', 'string', 'max:10000'],
            'disaggregation' => ['nullable', 'string', 'max:2000'],
            'data_sources' => ['required', 'string', 'max:10000'],
            'calculation_method' => ['required', 'string', 'max:10000'],
            'collection_frequency' => ['nullable', 'string', 'max:80'],
            'reporting_frequency' => ['required', 'string', 'max:80'],
            'means_of_verification' => ['required', 'string', 'max:10000'],
            'data_generation_responsibility' => ['required', 'string', 'max:5000'],
            'verification_responsibility' => ['required', 'string', 'max:5000'],
            'additional_guidance' => ['nullable', 'string', 'max:10000'],
            'approval_status' => ['required', Rule::in(['draft', 'approved', 'retired'])],
            'effective_from' => ['nullable', 'date'],
        ]);
        $version = (int) $indicator->referenceSheets()->max('version') + 1;
        MeIndicatorReferenceSheet::query()->create($validated + [
            'indicator_id' => $indicator->id,
            'framework_id' => $indicator->framework_id,
            'version' => $version,
            'disaggregation' => collect(explode(',', (string) ($validated['disaggregation'] ?? '')))->map->trim()->filter()->values()->all(),
            'approved_by' => $validated['approval_status'] === 'approved' ? $request->user()->id : null,
            'approved_at' => $validated['approval_status'] === 'approved' ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "IRS version {$version} created. Earlier versions remain immutable.");
    }

    public function storeTarget(Request $request, Indicator $indicator)
    {
        $this->assertOfficial($indicator);
        $validated = $request->validate([
            'target_scope' => ['required', Rule::in(['project', 'think_tank'])],
            'think_tank_member_id' => ['nullable', 'uuid', 'required_if:target_scope,think_tank', 'exists:attp_consortium_think_tanks,id'],
            'project_year' => ['nullable', 'integer', 'min:1', 'max:99'],
            'reporting_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_label' => ['required', 'string', 'max:80'],
            'target_value' => ['nullable', 'numeric', 'required_without:target_text'],
            'target_text' => ['nullable', 'string', 'max:100', 'required_without:target_value'],
            'baseline_value' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'revision_reason' => ['required', 'string', 'max:5000'],
            'approval_status' => ['required', Rule::in(['draft', 'approved', 'retired'])],
        ]);
        $context = IndicatorTarget::query()
            ->where('indicator_id', $indicator->id)
            ->where('target_scope', $validated['target_scope'])
            ->where('think_tank_member_id', $validated['think_tank_member_id'] ?? null)
            ->where('period_label', $validated['period_label']);
        $revision = (int) (clone $context)->max('revision') + 1;
        IndicatorTarget::query()->create($validated + [
            'indicator_id' => $indicator->id,
            'framework_id' => $indicator->framework_id,
            'target_context' => 'managed_'.strtolower(preg_replace('/[^a-z0-9]+/i', '_', $validated['period_label'])),
            'period_type' => 'year',
            'revision' => $revision,
            'unit_id' => $indicator->unit_id,
            'approved_by' => $validated['approval_status'] === 'approved' ? $request->user()->id : null,
            'approved_at' => $validated['approval_status'] === 'approved' ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "Target revision {$revision} created without overwriting history.");
    }

    public function storeCalculationRule(Request $request, Indicator $indicator)
    {
        $this->assertOfficial($indicator);
        $validated = $request->validate([
            'calculation_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'source_type' => ['required', 'string', 'max:80'],
            'source_indicator_codes' => ['nullable', 'string', 'max:2000'],
            'qualification_filter' => ['nullable', 'json'],
            'deduplication_key' => ['nullable', 'string', 'max:120'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $version = (int) $indicator->calculationRules()->max('version') + 1;
        MeIndicatorCalculationRule::query()->create([
            'indicator_id' => $indicator->id,
            'framework_id' => $indicator->framework_id,
            'calculation_key' => $validated['calculation_key'],
            'source_type' => $validated['source_type'],
            'configuration' => [
                'source_indicator_codes' => collect(explode(',', (string) ($validated['source_indicator_codes'] ?? '')))->map->trim()->filter()->values()->all(),
                'achievement_filter' => filled($validated['qualification_filter'] ?? null) ? json_decode($validated['qualification_filter'], true) : null,
            ],
            'deduplication_key' => $validated['deduplication_key'] ?? null,
            'version' => $version,
            'is_active' => true,
            'effective_from' => $validated['effective_from'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $indicator->update(['calculation_key' => $validated['calculation_key']]);

        return back()->with('success', "Calculation rule version {$version} created.");
    }

    private function assertOfficial(Indicator $indicator): void
    {
        abort_unless($indicator->framework_id, 404, 'This indicator is not part of a controlled MEL framework.');
    }
}

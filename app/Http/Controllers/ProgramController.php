<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\Indicator;
use App\Models\IndicatorLevel;
use App\Models\ReportingFrequency;
use App\Models\IndicatorUnit;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Jobs\IndicatorReminderJob;

class ProgramController extends Controller
{
    /**
     * PROGRAM RBAC
     * Matches routes:
     * permission:budget.structure.manage
     */
     public function __construct()
    {
        $this->middleware(['auth', 'verified']);

        $this->middleware('permission:program.view')
            ->only(['index', 'show']);

        $this->middleware('permission:program.create')
            ->only(['create', 'store']);

        $this->middleware('permission:program.edit')
            ->only(['edit', 'update']);

        $this->middleware('permission:program.delete')
            ->only(['destroy']);
    }

    protected function methodologyLabel($id): ?string
    {
        if (!$id) {
            return null;
        }
        return IndicatorMethodology::find($id)?->name;
    }

    protected function definitionLabel($id, $fallback = null): ?string
    {
        if ($id) {
            return IndicatorDefinition::find($id)?->name;
        }
        return $fallback;
    }

    protected function packResponsible(array $userIds): ?string
    {
        $userIds = array_filter($userIds);
        return empty($userIds) ? null : json_encode(array_values($userIds));
    }

    protected function unpackResponsible(?string $json): array
    {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function packPrimarySource(array $data): ?string
    {
        $type = $data['primary_source_type'] ?? null;
        $detail = $data['primary_source_detail'] ?? null;
        if (!$type && !$detail) return null;
        return trim(($type ?: 'manual') . ':' . ($detail ?? ''));
    }


    /**
     * List all programs
     */
    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to programs.');
        }

        $programs = Program::with(['sector', 'governanceNode', 'projects'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->latest()
            ->get();

        return view('budget.programs.index', compact('programs'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $sectors = $this->availableSectors();
        $approvedPrograms = $this->approvedProgramNames();
        $approvedProgramFunding = $this->approvedProgramFundingMap();

        // M&E Configuration data
        $indicatorLevels = IndicatorLevel::active()->ordered()->get();
        $reportingFrequencies = ReportingFrequency::active()->ordered()->get();
        $indicatorUnits = IndicatorUnit::active()->ordered()->get();
        $indicatorMethodologies = IndicatorMethodology::orderBy('name')->get();
        $indicatorDefinitions = IndicatorDefinition::orderBy('name')->get();
        $responsibleUsers = User::where('governance_node_id', Auth::user()?->governance_node_id)
            ->orderBy('name')
            ->get();

        return view('budget.programs.create', compact(
            'sectors',
            'approvedPrograms',
            'approvedProgramFunding',
            'indicatorLevels',
            'reportingFrequencies',
            'indicatorUnits',
            'indicatorMethodologies',
            'indicatorDefinitions',
            'responsibleUsers'
        ));
    }

    /**
     * Store Program
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sector_id'   => 'required|exists:myb_sectors,id',
            'program_id'  => 'required|string|max:50|unique:myb_programs,program_id',
            'program_name' => 'required|string|max:255',
            'expected_outcome_type' => 'required|in:percentage,text',
            'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
            'expected_outcome_text' => 'nullable|string|max:2000',
            'currency'    => 'required|string|max:10',
            'start_year'  => 'required|integer|min:1900|max:2100',
            'end_year'    => 'required|integer|min:1900|max:2100|gte:start_year',
            'description' => 'nullable|string',
            'indicators' => 'nullable|array',
            'indicators.*.name' => 'required_with:indicators|string|max:255',
            'indicators.*.baseline_year' => 'nullable|string|max:50',
            'indicators.*.baseline_type' => 'nullable|in:year,month,quarter,week,day',
            'indicators.*.baseline_value' => 'nullable|numeric',
            'indicators.*.indicator_level_id' => 'nullable|exists:me_indicator_levels,id',
            'indicators.*.methodology_id' => 'nullable|exists:indicator_methodologies,id',
            'indicators.*.notes' => 'nullable|string',
            'indicators.*.responsible_user_ids' => 'nullable|array',
            'indicators.*.responsible_user_ids.*' => 'exists:users,id',
            'indicators.*.frequency_of_reporting_id' => 'nullable|exists:me_reporting_frequencies,id',
            'indicators.*.unit_id' => 'nullable|exists:me_indicator_units,id',
            'indicators.*.primary_source_type' => 'nullable|in:external,file,manual',
            'indicators.*.primary_source_detail' => 'nullable|string|max:255',
            'indicators.*.definition_id' => 'nullable|exists:indicator_definitions,id',
            'indicators.*.definitions' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $this->assertSectorInScope((int) $validated['sector_id']);
            $this->assertProgramNameAllowed($validated['program_name']);

            $fundingMap = $this->approvedProgramFundingMap();
            $funding = $fundingMap->get($validated['program_name']);
            if (!$funding || $funding['total_budget'] === null) {
                throw new \Exception('Approved funding amount not found for the selected program.');
            }

            $validated['currency'] = $funding['currency'];
            $validated['start_year'] = $funding['start_year'];
            $validated['end_year'] = $funding['end_year'];
            $validated['total_budget'] = $funding['total_budget'];

            $validated['total_years'] =
                ($validated['end_year'] - $validated['start_year']) + 1;

            $validated['created_by'] = auth()->id();
            $validated['governance_node_id'] = Auth::user()?->governance_node_id;

            $expectedOutcomeValue = $validated['expected_outcome_type'] === 'percentage'
                ? (string) ($validated['expected_outcome_percentage'] ?? '')
                : ($validated['expected_outcome_text'] ?? '');

            if ($validated['expected_outcome_type'] === 'percentage' && $expectedOutcomeValue === '') {
                throw new \Exception('Expected outcome percentage is required.');
            }

            if ($validated['expected_outcome_type'] === 'text' && $expectedOutcomeValue === '') {
                throw new \Exception('Expected outcome description is required.');
            }

            $program = Program::create([
                'program_id' => $validated['program_id'],
                'sector_id' => $validated['sector_id'],
                'name' => $validated['program_name'],
                'currency' => $validated['currency'],
                'start_year' => $validated['start_year'],
                'end_year' => $validated['end_year'],
                'total_budget' => $validated['total_budget'],
                'description' => $validated['description'] ?? null,
                'expected_outcome_type' => $validated['expected_outcome_type'],
                'expected_outcome_value' => $expectedOutcomeValue,
                'total_years' => $validated['total_years'],
                'created_by' => $validated['created_by'],
                'governance_node_id' => $validated['governance_node_id'],
            ]);

            // Persist indicators if provided
            if (!empty($validated['indicators'])) {
                foreach ($validated['indicators'] as $ind) {
                    try {
                        $indicator = Indicator::create([
                            'indicatorable_type' => Program::class,
                            'indicatorable_id' => $program->id,
                            'name' => $ind['name'] ?? null,
                            'baseline_year' => $ind['baseline_year'] ?? null,
                            'baseline_type' => $ind['baseline_type'] ?? 'year',
                            'baseline_value' => $ind['baseline_value'] ?? null,
                            'indicator_level_id' => $ind['indicator_level_id'] ?? null,
                            'methodology' => $this->methodologyLabel($ind['methodology_id'] ?? null),
                            'notes' => $ind['notes'] ?? null,
                            'responsible_party' => $this->packResponsible($ind['responsible_user_ids'] ?? []),
                            'frequency_of_reporting_id' => $ind['frequency_of_reporting_id'] ?? null,
                            'unit_id' => $ind['unit_id'] ?? null,
                            'primary_source' => $this->packPrimarySource($ind),
                            'definitions' => $this->definitionLabel($ind['definition_id'] ?? null, $ind['definitions'] ?? null),
                            'created_by' => auth()->id(),
                        ]);
                        // queue reminder email (initial dispatch)
                        IndicatorReminderJob::dispatch($indicator->id);
                    } catch (\Throwable $e) {
                        // swallow per-indicator errors to avoid blocking whole save
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('budget.programs.index')
                ->with('success', 'Program created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to create program: ' . $e->getMessage());
        }
    }

    /**
     * Show a single program
     */
    public function show(Program $program)
    {
        $this->assertProgramInScope($program);
        $program->load([
            'sector',
            'projects.activities.subActivities',
            'indicators.level',
            'indicators.frequency',
            'indicators.unit',
        ]);

        return view('budget.programs.show', compact('program'));
    }

    /**
     * Edit program
     */
    public function edit(Program $program)
    {
        $this->assertProgramInScope($program);
        $sectors = $this->availableSectors();
        $approvedPrograms = $this->approvedProgramNames($program->name);
        $approvedProgramFunding = $this->approvedProgramFundingMap($program->name, $program);
        $program->load('indicators');

        // M&E Configuration data
        $indicatorLevels = IndicatorLevel::active()->ordered()->get();
        $reportingFrequencies = ReportingFrequency::active()->ordered()->get();
        $indicatorUnits = IndicatorUnit::active()->ordered()->get();
        $indicatorMethodologies = IndicatorMethodology::orderBy('name')->get();
        $indicatorDefinitions = IndicatorDefinition::orderBy('name')->get();
        $responsibleUsers = User::where('governance_node_id', Auth::user()?->governance_node_id)
            ->orderBy('name')
            ->get();

        return view('budget.programs.edit', compact(
            'program',
            'sectors',
            'approvedPrograms',
            'approvedProgramFunding',
            'indicatorLevels',
            'reportingFrequencies',
            'indicatorUnits',
            'indicatorMethodologies',
            'indicatorDefinitions',
            'responsibleUsers'
        ));
    }

    /**
     * Update program
     */
    public function update(Request $request, Program $program)
    {
        $this->assertProgramInScope($program);
        $validated = $request->validate([
            'sector_id'   => 'required|exists:myb_sectors,id',
            'program_name' => 'required|string|max:255',
            'expected_outcome_type' => 'required|in:percentage,text',
            'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
            'expected_outcome_text' => 'nullable|string|max:2000',
            'currency'    => 'required|string|max:10',
            'start_year'  => 'required|integer|min:1900|max:2100',
            'end_year'    => 'required|integer|min:1900|max:2100|gte:start_year',
            'description' => 'nullable|string',
            'indicators' => 'nullable|array',
            'indicators.*.id' => 'nullable|exists:myb_indicators,id',
            'indicators.*.name' => 'required_with:indicators|string|max:255',
            'indicators.*.baseline_year' => 'nullable|string|max:50',
            'indicators.*.baseline_type' => 'nullable|in:year,month,quarter,week,day',
            'indicators.*.baseline_value' => 'nullable|numeric',
            'indicators.*.indicator_level_id' => 'nullable|exists:me_indicator_levels,id',
            'indicators.*.methodology_id' => 'nullable|exists:indicator_methodologies,id',
            'indicators.*.notes' => 'nullable|string',
            'indicators.*.responsible_user_ids' => 'nullable|array',
            'indicators.*.responsible_user_ids.*' => 'exists:users,id',
            'indicators.*.frequency_of_reporting_id' => 'nullable|exists:me_reporting_frequencies,id',
            'indicators.*.unit_id' => 'nullable|exists:me_indicator_units,id',
            'indicators.*.primary_source_type' => 'nullable|in:external,file,manual',
            'indicators.*.primary_source_detail' => 'nullable|string|max:255',
            'indicators.*.definition_id' => 'nullable|exists:indicator_definitions,id',
            'indicators.*.definitions' => 'nullable|string',
        ]);

        $this->assertSectorInScope((int) $validated['sector_id']);
        $this->assertProgramNameAllowedForUpdate($validated['program_name'], $program);

        $fundingMap = $this->approvedProgramFundingMap();
        $funding = $fundingMap->get($validated['program_name']);
        if (!$funding || $funding['total_budget'] === null) {
            abort(422, 'Approved funding amount not found for the selected program.');
        }

        $validated['currency'] = $funding['currency'];
        $validated['start_year'] = $funding['start_year'];
        $validated['end_year'] = $funding['end_year'];
        $validated['total_budget'] = $funding['total_budget'];

        $validated['total_years'] =
            ($validated['end_year'] - $validated['start_year']) + 1;

        $validated['updated_by'] = auth()->id();

        $expectedOutcomeValue = $validated['expected_outcome_type'] === 'percentage'
            ? (string) ($validated['expected_outcome_percentage'] ?? '')
            : ($validated['expected_outcome_text'] ?? '');

        if ($validated['expected_outcome_type'] === 'percentage' && $expectedOutcomeValue === '') {
            abort(422, 'Expected outcome percentage is required.');
        }

        if ($validated['expected_outcome_type'] === 'text' && $expectedOutcomeValue === '') {
            abort(422, 'Expected outcome description is required.');
        }

        $program->update([
            'sector_id' => $validated['sector_id'],
            'name' => $validated['program_name'],
            'currency' => $validated['currency'],
            'start_year' => $validated['start_year'],
            'end_year' => $validated['end_year'],
            'total_budget' => $validated['total_budget'],
            'description' => $validated['description'] ?? null,
            'expected_outcome_type' => $validated['expected_outcome_type'],
            'expected_outcome_value' => $expectedOutcomeValue,
            'total_years' => $validated['total_years'],
            'updated_by' => $validated['updated_by'],
        ]);

        // Handle indicators update: delete old ones and add new ones
        if (isset($validated['indicators'])) {
            $program->indicators()->delete();
            if (!empty($validated['indicators'])) {
                foreach ($validated['indicators'] as $ind) {
                    try {
                        $indicator = Indicator::create([
                            'indicatorable_type' => Program::class,
                            'indicatorable_id' => $program->id,
                            'name' => $ind['name'] ?? null,
                            'baseline_year' => $ind['baseline_year'] ?? null,
                            'baseline_type' => $ind['baseline_type'] ?? 'year',
                            'baseline_value' => $ind['baseline_value'] ?? null,
                            'indicator_level_id' => $ind['indicator_level_id'] ?? null,
                            'methodology' => $this->methodologyLabel($ind['methodology_id'] ?? null),
                            'notes' => $ind['notes'] ?? null,
                            'responsible_party' => $this->packResponsible($ind['responsible_user_ids'] ?? []),
                            'frequency_of_reporting_id' => $ind['frequency_of_reporting_id'] ?? null,
                            'unit_id' => $ind['unit_id'] ?? null,
                            'primary_source' => $this->packPrimarySource($ind),
                            'definitions' => $this->definitionLabel($ind['definition_id'] ?? null, $ind['definitions'] ?? null),
                            'created_by' => auth()->id(),
                        ]);
                        IndicatorReminderJob::dispatch($indicator->id);
                    } catch (\Throwable $e) {
                        // swallow per-indicator errors
                    }
                }
            }
        }

        return redirect()
            ->route('budget.programs.index')
            ->with('success', 'Program updated successfully.');
    }

    /**
     * Delete program (cascade-safe)
     */
    public function destroy(Program $program)
    {
        $this->assertProgramInScope($program);
        DB::beginTransaction();

        try {
            foreach ($program->projects as $project) {
                foreach ($project->activities as $activity) {
                    foreach ($activity->subActivities as $sub) {
                        $sub->allocations()?->delete();
                        $sub->delete();
                    }

                    $activity->allocations()?->delete();
                    $activity->delete();
                }

                $project->allocations()?->delete();
                $project->delete();
            }

            $program->delete();

            DB::commit();

            return back()->with('success', 'Program deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function availableSectors()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        return Sector::orderBy('name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();
    }

    private function approvedProgramNames(?string $includeName = null)
    {
        $names = $this->approvedProgramFundingMap()->keys();

        if ($includeName && !$names->contains($includeName)) {
            $names->push($includeName);
        }

        return $names;
    }

    private function approvedProgramFundingMap(?string $includeName = null, ?Program $program = null)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        $query = ProgramFunding::where('status', 'approved')
            ->whereNotNull('program_name')
            ->orderByDesc('id');

        if ($scopedNodeIds !== null) {
            $query->whereIn('governance_node_id', $scopedNodeIds);
        }

        $fundings = $query->get(['program_name', 'currency', 'start_year', 'end_year', 'approved_amount']);

        $map = $fundings->groupBy('program_name')->map(function ($rows) {
            $row = $rows->first();
            return [
                'currency' => $row->currency,
                'start_year' => $row->start_year,
                'end_year' => $row->end_year,
                'total_budget' => $row->approved_amount,
            ];
        });

        if ($includeName && !$map->has($includeName)) {
            $map->put($includeName, [
                'currency' => $program?->currency,
                'start_year' => $program?->start_year,
                'end_year' => $program?->end_year,
                'total_budget' => null,
            ]);
        }

        return $map;
    }

    private function assertProgramNameAllowed(string $name): void
    {
        $allowed = $this->approvedProgramNames();
        if (!$allowed->contains($name)) {
            abort(422, 'Program name must come from approved funding.');
        }

        $exists = Program::where('name', $name)->exists();
        if ($exists) {
            abort(422, 'That approved funding has already been used by another program. Please choose a different approved program.');
        }
    }

    private function assertProgramNameAllowedForUpdate(string $name, Program $program): void
    {
        $allowed = $this->approvedProgramNames($program->name);
        if (!$allowed->contains($name)) {
            abort(422, 'Program name must come from approved funding.');
        }

        $exists = Program::where('name', $name)
            ->where('id', '!=', $program->id)
            ->exists();
        if ($exists) {
            abort(422, 'That approved funding has already been used by another program. Please choose a different approved program.');
        }
    }

    private function assertProgramInScope(Program $program): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$program->governance_node_id || !in_array($program->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this program.');
        }
    }

    private function assertSectorInScope(int $sectorId): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $sector = Sector::find($sectorId);
        if (!$sector || !$sector->governance_node_id || !in_array($sector->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this sector.');
        }
    }
}

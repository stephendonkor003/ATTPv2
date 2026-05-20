<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Funder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttpBudgetStructureSeeder extends Seeder
{
    private const DATA_FILE = 'data/attp_budget_structure.json';

    public function run(): void
    {
        $data = $this->loadData();
        $createdBy = $this->createdBy();

        DB::transaction(function () use ($data, $createdBy) {
            $sector = $this->syncSector($data['sector'] ?? null);
            $program = $this->syncProgram($data['program'], $sector?->id, $createdBy);
            $this->syncProgramFunding($program, $data['program_funding'] ?? null, $createdBy);

            foreach ($data['projects'] ?? [] as $projectData) {
                $project = $this->syncProject($program, $projectData, $createdBy);
                $this->syncProjectAllocations($project, $projectData['allocations'] ?? []);

                foreach ($projectData['activities'] ?? [] as $activityData) {
                    $activity = $this->syncActivity($project, $activityData, $createdBy);
                    $this->syncActivityAllocations($activity, $activityData['allocations'] ?? []);

                    foreach ($activityData['sub_activities'] ?? [] as $subActivityData) {
                        $subActivity = $this->syncSubActivity($activity, $subActivityData, $createdBy);
                        $this->syncSubActivityAllocations($subActivity, $subActivityData['allocations'] ?? []);
                    }
                }
            }
        });
    }

    private function loadData(): array
    {
        $path = database_path('seeders/' . self::DATA_FILE);

        if (! is_file($path)) {
            throw new RuntimeException('ATTP budget structure data file is missing: ' . $path);
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['program']['program_id'])) {
            throw new RuntimeException('ATTP budget structure data file is invalid.');
        }

        return $data;
    }

    private function createdBy(): ?string
    {
        return User::where('email', 'stephendonkor03@outlook.com')->value('id')
            ?? User::where('email', 'stephendonkor03@outlok.com')->value('id')
            ?? User::query()->value('id');
    }

    private function syncSector(?array $sectorData): ?Sector
    {
        if (empty($sectorData['name'])) {
            return null;
        }

        return Sector::updateOrCreate(
            ['name' => $sectorData['name']],
            ['description' => $sectorData['description'] ?? null]
        );
    }

    private function syncProgram(array $programData, ?string $sectorId, ?string $createdBy): Program
    {
        return Program::updateOrCreate(
            ['program_id' => $programData['program_id']],
            [
                'sector_id' => $sectorId,
                'name' => $programData['name'],
                'description' => $programData['description'] ?? null,
                'expected_outcome_type' => $programData['expected_outcome_type'] ?? null,
                'expected_outcome_value' => $programData['expected_outcome_value'] ?? null,
                'currency' => $programData['currency'] ?? 'USD',
                'start_year' => $programData['start_year'] ?? null,
                'end_year' => $programData['end_year'] ?? null,
                'total_years' => $programData['total_years'] ?? null,
                'total_budget' => $programData['total_budget'] ?? null,
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncProgramFunding(Program $program, ?array $fundingData, ?string $createdBy): void
    {
        if (! $fundingData) {
            return;
        }

        $funder = Funder::updateOrCreate(
            ['name' => $fundingData['funder_name'] ?? 'World Bank'],
            [
                'type' => $fundingData['funder_type'] ?? 'donor',
                'currency' => $fundingData['currency'] ?? 'USD',
                'partnership_status' => 'active',
            ]
        );

        ProgramFunding::updateOrCreate(
            [
                'program_id' => $program->id,
                'funder_id' => $funder->id,
            ],
            [
                'program_name' => $fundingData['program_name'] ?? $program->name,
                'funding_type' => $fundingData['funding_type'] ?? 'grant',
                'approved_amount' => $fundingData['approved_amount'] ?? $program->total_budget,
                'currency' => $fundingData['currency'] ?? 'USD',
                'start_year' => $fundingData['start_year'] ?? $program->start_year,
                'end_year' => $fundingData['end_year'] ?? $program->end_year,
                'status' => $fundingData['status'] ?? 'approved',
                'is_continental_initiative' => (bool) ($fundingData['is_continental_initiative'] ?? true),
                'approved_by' => $createdBy,
                'approved_at' => now(),
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncProject(Program $program, array $projectData, ?string $createdBy): Project
    {
        return Project::updateOrCreate(
            ['project_id' => $projectData['project_id']],
            [
                'program_id' => $program->id,
                'name' => $projectData['name'],
                'description' => $projectData['description'] ?? null,
                'expected_outcome_type' => $projectData['expected_outcome_type'] ?? null,
                'expected_outcome_value' => $projectData['expected_outcome_value'] ?? null,
                'currency' => $projectData['currency'] ?? 'USD',
                'start_year' => $projectData['start_year'] ?? $program->start_year,
                'end_year' => $projectData['end_year'] ?? $program->end_year,
                'total_years' => $projectData['total_years'] ?? $program->total_years,
                'total_budget' => $projectData['total_budget'] ?? null,
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncProjectAllocations(Project $project, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            if (! isset($allocation['year'])) {
                continue;
            }

            ProjectAllocation::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'year' => (int) $allocation['year'],
                ],
                [
                    'year_number' => $allocation['year_number'] ?? null,
                    'actual_year' => $allocation['actual_year'] ?? $allocation['year'],
                    'amount' => $allocation['amount'] ?? 0,
                ]
            );
        }
    }

    private function syncActivity(Project $project, array $activityData, ?string $createdBy): Activity
    {
        return Activity::updateOrCreate(
            [
                'project_id' => $project->id,
                'name' => $activityData['name'],
            ],
            [
                'description' => $activityData['description'] ?? null,
                'expected_outcome_type' => $activityData['expected_outcome_type'] ?? null,
                'expected_outcome_value' => $activityData['expected_outcome_value'] ?? null,
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncActivityAllocations(Activity $activity, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            if (! isset($allocation['year'])) {
                continue;
            }

            ActivityAllocation::updateOrCreate(
                [
                    'activity_id' => $activity->id,
                    'year' => (int) $allocation['year'],
                ],
                ['amount' => $allocation['amount'] ?? 0]
            );
        }
    }

    private function syncSubActivity(Activity $activity, array $subActivityData, ?string $createdBy): SubActivity
    {
        return SubActivity::updateOrCreate(
            [
                'activity_id' => $activity->id,
                'name' => $subActivityData['name'],
            ],
            [
                'description' => $subActivityData['description'] ?? null,
                'expected_outcome_type' => $subActivityData['expected_outcome_type'] ?? null,
                'expected_outcome_value' => $subActivityData['expected_outcome_value'] ?? null,
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncSubActivityAllocations(SubActivity $subActivity, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            if (! isset($allocation['year'])) {
                continue;
            }

            SubActivityAllocation::updateOrCreate(
                [
                    'sub_activity_id' => $subActivity->id,
                    'year' => (int) $allocation['year'],
                ],
                ['amount' => $allocation['amount'] ?? 0]
            );
        }
    }
}

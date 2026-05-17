<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ApprovedWorkPlan;
use App\Models\ApprovedWorkPlanItemReview;
use App\Models\BudgetCommitment;
use App\Models\Funder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttpWorkPlan2025Seeder extends Seeder
{
    private const SOURCE = 'ATTP AWPB FY2025 Excel';
    private const FISCAL_YEAR = 2025;

    public function run(): void
    {
        $createdBy = User::where('email', 'stephendonkor03@outlok.com')->value('id')
            ?? User::where('email', 'stephendonkor03@outlook.com')->value('id')
            ?? User::query()->value('id');

        $program = $this->resolveProgram($createdBy);
        $funder = Funder::firstOrCreate(
            ['name' => 'World Bank'],
            ['type' => 'donor', 'currency' => 'USD']
        );

        $funding = $this->resolveProgramFunding($program, $funder, $createdBy);

        foreach ($this->workPlanRows() as $index => $row) {
            $row = $this->withWorkPlanPlanningMetadata($row);
            $project = $this->resolveProject($program, (int) $row['component_no'], $createdBy);
            $activity = $this->resolveActivity($project, $row, $createdBy);
            $subActivity = $this->resolveSubActivity($activity, $row, $createdBy);
            $category = $this->resolveResourceCategory((string) $row['object_type'], $createdBy);
            $resource = $this->resolveResource($row, $category, $createdBy);
            $amount = round((float) $row['estimated_amount'], 2);
            $reference = sprintf('AWP-%s-%03d', self::FISCAL_YEAR, $index + 1);

            $this->syncMissingSubActivityAllocation($subActivity, $amount);

            $purchaseRequest = PurchaseRequest::updateOrCreate(
                ['reference_no' => $reference],
                [
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $funding->governance_node_id,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'start_year' => self::FISCAL_YEAR,
                    'commitment_date' => self::FISCAL_YEAR . '-01-01',
                    'delivery_date' => self::FISCAL_YEAR . '-12-31',
                    'currency' => 'USD',
                    'total_amount' => $amount,
                    'description' => 'FY2025 ATTP work plan item: ' . $row['activity'],
                    'status' => 'approved',
                    'work_plan_source' => self::SOURCE,
                    'work_plan_component' => $row['component'],
                    'work_plan_sub_component' => $row['sub_component'],
                    'created_by' => $createdBy,
                ]
            );

            $item = PurchaseRequestItem::updateOrCreate(
                ['purchase_request_id' => $purchaseRequest->id],
                [
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'milestone' => $row['intermediate_indicator'] ?: $row['result_indicator'],
                    'amount' => $amount,
                    'work_plan_source' => self::SOURCE,
                    'work_plan_sort_order' => (int) $row['row'],
                    'work_plan_serial' => $row['sequence'] ? (string) $row['sequence'] : null,
                    'implemented_by' => $row['implemented_by'] ?: null,
                    'budget_code' => $row['budget_code'] ?: null,
                    'object_type' => $row['object_type'] ?: null,
                    'estimated_amount' => $amount,
                    'work_plan_months' => $row['work_plan_months'] ?: null,
                    'work_plan_audience' => $row['work_plan_audience'] ?: null,
                    'work_plan_units' => $row['work_plan_units'] ?: null,
                    'work_plan_payment_basis' => $row['work_plan_payment_basis'],
                    'work_plan_person_months' => $row['work_plan_person_months'],
                    'work_plan_monthly_amount' => $row['work_plan_monthly_amount'],
                    'intermediate_indicator' => $row['intermediate_indicator'] ?: null,
                    'result_indicator' => $row['result_indicator'] ?: null,
                    'observations' => $row['observations'] ?: null,
                    'world_bank_comments' => $row['world_bank_comments'] ?: null,
                    'attp_secretariat_comments' => $row['attp_secretariat_comments'] ?: null,
                    'world_bank_amount' => $row['world_bank_amount'],
                ]
            );

            $commitment = BudgetCommitment::updateOrCreate(
                ['purchase_request_id' => $purchaseRequest->id],
                [
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $funding->governance_node_id,
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'commitment_amount' => $amount,
                    'commitment_year' => self::FISCAL_YEAR,
                    'status' => BudgetCommitment::STATUS_APPROVED,
                    'description' => 'FY2025 ATTP work plan commitment: ' . $row['activity'],
                    'created_by' => $createdBy,
                    'approved_by' => $createdBy,
                    'approved_at' => now(),
                ]
            );

            ApprovedWorkPlan::updateOrCreate(
                ['budget_commitment_id' => $commitment->id],
                [
                    'awp_code' => sprintf('AWP-%s-ITEM-%03d', self::FISCAL_YEAR, $index + 1),
                    'title' => $row['activity'],
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $funding->governance_node_id,
                    'fiscal_year' => (string) self::FISCAL_YEAR,
                    'planned_amount' => $amount,
                    'currency' => 'USD',
                    'start_date' => self::FISCAL_YEAR . '-01-01',
                    'end_date' => self::FISCAL_YEAR . '-12-31',
                    'status' => 'approved',
                    'description' => $row['component'] . "\n" . $row['sub_component'],
                    'expected_outputs' => $row['result_indicator'] ?: $row['intermediate_indicator'],
                    'implementation_notes' => trim(($row['observations'] ?? '') . "\n" . ($row['world_bank_comments'] ?? '')),
                    'created_by' => $createdBy,
                    'approved_by' => $createdBy,
                    'approved_at' => now(),
                    'review_notes' => $row['world_bank_comments'] ?: null,
                ]
            );

            ApprovedWorkPlanItemReview::updateOrCreate(
                ['purchase_request_item_id' => $item->id],
                [
                    'program_funding_id' => $funding->id,
                    'funder_id' => $funder->id,
                    'status' => $this->worldBankStatus($row['world_bank_comments']),
                    'reviewed_by' => $row['world_bank_comments'] ? $createdBy : null,
                    'reviewed_at' => $row['world_bank_comments'] ? now() : null,
                    'review_notes' => $row['world_bank_comments'] ?: null,
                ]
            );
        }
    }

    private function resolveProgram(?string $createdBy): Program
    {
        return Program::firstOrCreate(
            ['name' => 'Africa Think Tank Platform (ATTP) Project'],
            [
                'program_id' => 'PROG00001',
                'description' => 'The Africa Think Tank Platform (ATTP) project is a strategic initiative designed to enhance the capacity and influence of think tanks across Africa.',
                'expected_outcome_type' => 'text',
                'expected_outcome_value' => 'To establish a sustainable platform that strengthens the capacity for effective policy research and policymaking on cross-border priorities in Africa',
                'currency' => 'USD',
                'start_year' => 2024,
                'end_year' => 2028,
                'total_years' => 5,
                'total_budget' => 50000000,
                'created_by' => $createdBy,
            ]
        );
    }

    private function resolveProgramFunding(Program $program, Funder $funder, ?string $createdBy): ProgramFunding
    {
        $funding = ProgramFunding::where('funder_id', $funder->id)
            ->where(function ($query) use ($program) {
                $query->where('program_id', $program->id)
                    ->orWhere('program_name', $program->name)
                    ->orWhereNull('program_id');
            })
            ->first();

        if (! $funding) {
            $funding = new ProgramFunding([
                'funder_id' => $funder->id,
                'created_by' => $createdBy,
            ]);
        }

        $funding->fill([
            'program_id' => $program->id,
            'program_name' => $program->name,
            'funding_type' => 'grant',
            'approved_amount' => $funding->approved_amount ?: 50000000,
            'currency' => 'USD',
            'start_year' => 2024,
            'end_year' => 2028,
            'status' => 'approved',
            'is_continental_initiative' => true,
            'approved_by' => $funding->approved_by ?: $createdBy,
            'approved_at' => $funding->approved_at ?: now(),
        ])->save();

        return $funding;
    }

    private function resolveProject(Program $program, int $componentNo, ?string $createdBy): Project
    {
        $project = $program->projects()
            ->get()
            ->first(fn (Project $project) => str_contains($this->normalize($project->name), 'component ' . $componentNo));

        if ($project) {
            return $project;
        }

        $names = [
            1 => 'COMPONENT 1: Establish capacity to operate a sustainable policy-making platform',
            2 => 'COMPONENT 2: Strengthen the quality, relevance, and uptake of policy research on priority issues',
            3 => 'COMPONENT 3: Support platform sustainability',
        ];

        return Project::create([
            'program_id' => $program->id,
            'project_id' => $program->program_id . '-' . str_pad((string) $componentNo, 2, '0', STR_PAD_LEFT),
            'name' => $names[$componentNo] ?? 'COMPONENT ' . $componentNo,
            'currency' => 'USD',
            'start_year' => 2024,
            'end_year' => 2028,
            'total_years' => 5,
            'created_by' => $createdBy,
        ]);
    }

    private function resolveActivity(Project $project, array $row, ?string $createdBy): Activity
    {
        $key = $row['component_no'] . ':' . $row['sub_component_code'];
        $aliases = $this->activityAliases()[$key] ?? [$row['sub_component']];
        $activities = $project->activities()->get();

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalize($alias);
            $match = $activities->first(fn (Activity $activity) => str_contains($this->normalize($activity->name), $normalizedAlias));

            if ($match) {
                return $match;
            }
        }

        return Activity::create([
            'project_id' => $project->id,
            'name' => $row['sub_component'],
            'description' => self::SOURCE,
            'expected_outcome_type' => 'text',
            'expected_outcome_value' => $row['result_indicator'] ?: $row['intermediate_indicator'],
            'created_by' => $createdBy,
        ]);
    }

    private function resolveSubActivity(Activity $activity, array $row, ?string $createdBy): SubActivity
    {
        $normalizedName = $this->normalize($row['activity']);
        $subActivity = $activity->subActivities()
            ->get()
            ->first(fn (SubActivity $subActivity) => $this->normalize($subActivity->name) === $normalizedName);

        if (! $subActivity) {
            $subActivity = new SubActivity([
                'activity_id' => $activity->id,
                'name' => $row['activity'],
                'created_by' => $createdBy,
            ]);
        }

        $subActivity->fill([
            'description' => $row['intermediate_indicator'] ?: self::SOURCE,
            'expected_outcome_type' => 'text',
            'expected_outcome_value' => $row['result_indicator'] ?: $row['intermediate_indicator'],
        ])->save();

        return $subActivity;
    }

    private function resolveResourceCategory(string $objectType, ?string $createdBy): ResourceCategory
    {
        $normalized = $this->normalize($objectType);
        $name = match (true) {
            str_contains($normalized, 'workshop') => 'Workshops & Events',
            str_contains($normalized, 'goods') => 'Goods',
            str_contains($normalized, 'application') || str_contains($normalized, 'pass through') => 'Grants / Transfers',
            str_contains($normalized, 'staff') || str_contains($normalized, 'translation') || str_contains($normalized, 'communication') => 'Implementation/Operational Costs (IOC)',
            default => 'Consulting Services',
        };

        return ResourceCategory::firstOrCreate(
            ['name' => $name],
            [
                'description' => $name . ' for ATTP work plan items',
                'status' => 'active',
                'created_by' => $createdBy,
            ]
        );
    }

    private function resolveResource(array $row, ResourceCategory $category, ?string $createdBy): Resource
    {
        return Resource::updateOrCreate(
            ['name' => $row['activity']],
            [
                'resource_category_id' => $category->id,
                'reference_code' => $row['budget_code'] ?: 'AWP-' . self::FISCAL_YEAR . '-' . $row['row'],
                'description' => $row['intermediate_indicator'] ?: $row['result_indicator'],
                'status' => 'active',
                'is_human_resource' => $this->normalize($row['object_type']) === 'consulting',
                'created_by' => $createdBy,
            ]
        );
    }

    private function worldBankStatus(?string $comment): string
    {
        $normalized = $this->normalize((string) $comment);

        if ($normalized === '') {
            return 'pending';
        }

        if (str_contains($normalized, 'conditional')) {
            return 'needs_revision';
        }

        if (str_starts_with($normalized, 'approved')) {
            return 'approved';
        }

        return 'pending';
    }

    private function activityAliases(): array
    {
        return [
            '1:a' => ['establishing a secretariat'],
            '1:b' => ['establishing and maintaining the ttpsc'],
            '1:c' => ['designing and implementing monitoring and evaluation'],
            '1:d' => ['draw and learn from lessons'],
            '1:e' => ['building and maintaining a network'],
            '1:f' => ['organising meetings of african governments'],
            '2:b' => ['knowledge exchange events'],
            '2:c' => ['fellowships or secondment'],
            '2:d' => ['training and capacity building'],
            '2:f' => ['finance high quality research', 'financing high quality research'],
            '3:b' => ['resource mobilisation strategy', 'resource mobilization strategy'],
        ];
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(Str::lower($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function withWorkPlanPlanningMetadata(array $row): array
    {
        $timeline = $this->workPlanTimelineOverrides()[(int) $row['row']] ?? [];
        $months = $timeline['months'] ?? [];
        $paymentBasis = $this->paymentBasisForWorkPlanRow($row, $months);
        $personMonths = $paymentBasis === 'monthly' && ! empty($months)
            ? count($months)
            : null;

        return array_merge($row, [
            'work_plan_months' => $months,
            'work_plan_audience' => $timeline['audience'] ?? null,
            'work_plan_units' => $timeline['units'] ?? null,
            'work_plan_payment_basis' => $paymentBasis,
            'work_plan_person_months' => $personMonths,
            'work_plan_monthly_amount' => $paymentBasis === 'monthly' && $personMonths
                ? round((float) $row['estimated_amount'] / $personMonths, 2)
                : null,
        ]);
    }

    private function paymentBasisForWorkPlanRow(array $row, array $months): string
    {
        if ($this->isMonthlyPersonnelRow($row)) {
            return 'monthly';
        }

        return count($months) > 1 ? 'scheduled' : 'one_off';
    }

    private function isMonthlyPersonnelRow(array $row): bool
    {
        $objectType = $this->normalize((string) ($row['object_type'] ?? ''));
        if ($objectType !== '' && $objectType !== 'consulting') {
            return false;
        }

        $text = $this->normalize(implode(' ', [
            $row['activity'] ?? '',
            $row['observations'] ?? '',
            $row['intermediate_indicator'] ?? '',
        ]));

        if (str_contains($text, 'critical position')) {
            return true;
        }

        foreach ([
            'technical project coordinator',
            'procurement specialist',
            'project admin assistant',
            'technical project advisor',
            'communication specialist',
            'project m e officer',
            'financial management specialist',
            'grm consultant',
        ] as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function syncMissingSubActivityAllocation(SubActivity $subActivity, float $amount): void
    {
        SubActivityAllocation::firstOrCreate(
            [
                'sub_activity_id' => $subActivity->id,
                'year' => self::FISCAL_YEAR,
            ],
            ['amount' => $amount]
        );
    }

    private function workPlanTimelineOverrides(): array
    {
        return [
            8 => ['months' => ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            9 => ['months' => ['mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            10 => ['months' => ['jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            11 => ['months' => ['nov', 'dec']],
            12 => ['months' => ['nov', 'dec']],
            13 => ['months' => ['nov', 'dec']],
            17 => ['months' => ['jul', 'aug', 'sep']],
            18 => ['months' => ['jul', 'aug', 'sep']],
            19 => ['months' => ['oct']],
            20 => ['months' => ['jun', 'jul', 'aug', 'sep', 'oct', 'nov']],
            21 => ['months' => ['dec']],
            22 => ['months' => ['sep']],
            23 => ['months' => ['oct', 'nov']],
            24 => ['months' => ['oct', 'nov']],
            25 => ['months' => ['dec']],
            48 => ['months' => ['oct', 'nov', 'dec']],
            51 => ['months' => ['oct']],
            52 => ['months' => ['oct', 'nov', 'dec']],
            56 => ['months' => ['jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            60 => ['months' => ['jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            61 => ['months' => ['jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            64 => ['months' => ['oct', 'nov', 'dec']],
            68 => ['months' => ['jun', 'jul', 'aug']],
            69 => ['months' => ['oct', 'nov', 'dec']],
            70 => ['months' => ['jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            71 => ['months' => ['jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']],
            81 => ['months' => ['oct', 'nov', 'dec']],
            85 => ['months' => ['oct', 'nov', 'dec']],
        ];
    }

    private function workPlanRows(): array
    {
        return json_decode(<<<'JSON'
[
  {"row":8,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":1,"activity":"ATTP Technical Project Coordinator","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":64000.0,"intermediate_indicator":"Project Coordinator hired by May 2025","object_type":"Consulting","result_indicator":"90% of ATTP Project milestones met for FY2025","observations":"Critical position (5) for the ATTP Secretariat FY2025","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":64000.0},
  {"row":9,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":2,"activity":"ATTP Procurement Specialist","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":72600.0,"intermediate_indicator":"Procurement Consultant hired by January 2025","object_type":"Consulting","result_indicator":"90% of ATTP Project procurement activities met for FY2025","observations":"Critical position (5) for the ATTP Secretariat FY2025","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":72600.0},
  {"row":10,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":3,"activity":"ATTP Project Admin Assistant","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":30000.0,"intermediate_indicator":"Project Admin Assistant hired by May 2025","object_type":"Consulting","result_indicator":"90% of ATTP Project operational milestones met for FY2025","observations":"Critical position (5) for the ATTP Secretariat FY2025","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":30000.0},
  {"row":11,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":4,"activity":"ATTP Technical Project Advisor","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":0,"intermediate_indicator":"Project Advisor hired by July 2025","object_type":"Consulting","result_indicator":"TBD","observations":"Critical position (5) for the ATTP Secretariat FY2025","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":12,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":5,"activity":"ATTP Communication Specialist","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":0,"intermediate_indicator":"Communication Specialist hired by July 2025","object_type":"Consulting","result_indicator":"90% Execution of Communication and Outreach Plan","observations":"Critical position (5) for the ATTP Secretariat FY2025","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":13,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":6,"activity":"ATTP Project M&E Officer","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":0,"intermediate_indicator":"M&E Specialist hired by October 2025","object_type":"Consulting","result_indicator":"","observations":"","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":14,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"a","sub_component":"Sub-Component : (a) establishing a secretariat to manage the platform","sequence":7,"activity":"ATTP Financial Management Specialist","implemented_by":"PMRM","budget_code":"PRM100208MB","estimated_amount":0,"intermediate_indicator":"Finance Management Specialist hired to ATTP by July 2025","object_type":"Consulting","result_indicator":"100% of ATTP Project financial requirements met for FY2025","observations":"New thinking is to use existing AUC Finance Officers (Joyeuse) and later have one seconded to the Secretariat","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":17,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":null,"activity":"GRM Consultant","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":0,"intermediate_indicator":"","object_type":"","result_indicator":"","observations":"","world_bank_comments":"","attp_secretariat_comments":"","world_bank_amount":null},
  {"row":18,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":1,"activity":"Selection process of think tanks","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":10342.0,"intermediate_indicator":"ATTP launch conducted in July 2025","object_type":"Workshop","result_indicator":"Launch workshop completed","observations":"Holding workshop, honorarium for experts","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":10342.0},
  {"row":19,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":2,"activity":"Advertisement and communication services for call for application","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":28686.86,"intermediate_indicator":"No. of communication materials printed at the AU printing unit No. of Translators hired","object_type":"Consulting","result_indicator":"ATTP communication materials distributed to stakeholders","observations":"Advertising call, translation","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":28686.86},
  {"row":20,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":3,"activity":"ICT and office Supplies & Equipment","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":0,"intermediate_indicator":"Procurement of ICT equipment launched","object_type":"Goods","result_indicator":"ICT equipment and accessories procured for PMRM and ATTP Secretariat","observations":"","world_bank_comments":"Conditional approval - requires resubmission of requirements only for secretariat staff","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":21,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":4,"activity":"Stakeholders engagement meetings","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":20000.0,"intermediate_indicator":"Conduct Sensitization Workshop","object_type":"Workshop","result_indicator":"1 No. Sensitization workshop conducted","observations":"","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":20000.0},
  {"row":22,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":5,"activity":"ATTP Steering Committee Meetings","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":15029.0,"intermediate_indicator":"1 physical and 1 virtual meeting each year","object_type":"Workshop","result_indicator":"2 No. TTPSC meeting held for FY2025","observations":"Holding workshop, honorarium for experts","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":15029.0},
  {"row":23,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":6,"activity":"Independent Committee of Experts Honoraria","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":112200.0,"intermediate_indicator":"6 No. ICE Engaged by ATTP Project","object_type":"Consulting","result_indicator":"100% Evaluation of Think Tank Call for Proposals completed","observations":"","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":112200.0},
  {"row":24,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":7,"activity":"Independent Evaluation Committee Selection Meetings - Logistics and Travel Costs","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":59700.0,"intermediate_indicator":"Think Tank site visits conducted in all 5 African regions, Call for Application evaluations conducted, Translations completed,","object_type":"Workshop","result_indicator":"At least 5 Think Tank Consortia engaged by end of FY2025","observations":"Holding workshop, honorarium for experts","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":59700.0},
  {"row":25,"component_no":1,"component":"COMPONENT #1Establish capacity to operate a sustainable policy making platform (US$3.5 million) equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) establishing and maintaining the TTPSC, including its meetings","sequence":8,"activity":"Hiring of External Audit Firm","implemented_by":"PMRM","budget_code":"PRM090203MB","estimated_amount":0,"intermediate_indicator":"External Hired to audit ATTP Project covering FY2025 (Jan - Dec)","object_type":"Consulting","result_indicator":"ATTP Project FY2025 audited by June 2026","observations":"","world_bank_comments":"","attp_secretariat_comments":"","world_bank_amount":null},
  {"row":48,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"a","sub_component":"Sub-Component : (a) Creating and maintaining a database of African and global think tanks (including information on number of female","sequence":null,"activity":"Establish the database","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"Engage AUC MIS to develop database and repository on ATTP website","object_type":"Consulting","result_indicator":"1 Think Tank database established","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":51,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"b","sub_component":"Sub-Component : (b) Organizing knowledge exchange events","sequence":null,"activity":"Organize the Africa Think Tank Summit (Launch)","implemented_by":"ACBF","budget_code":"ACB060501MB","estimated_amount":150000.0,"intermediate_indicator":"Launch of ATTP Project","object_type":"Workshop","result_indicator":"1 Summit held to launch the ATTP Project for Think Tanks and Policymakers","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity. We do not expect that ATTP will fully finance the Summit but contribute with other partners","attp_secretariat_comments":"","world_bank_amount":150000.0},
  {"row":52,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"b","sub_component":"Sub-Component : (b) Organizing knowledge exchange events","sequence":null,"activity":"Conduct follow-up meetings to support the implementation of the recommendations of the events","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"To cover travel costs to planned meetings","object_type":"Staff, communication, translation","result_indicator":"ACBF staff attending all required meetings","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":56,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"c","sub_component":"Sub-Component : (c) Facilitating fellowships or secondment programs between governments and think tanks to enhance the research and skill sets of public policy makers and policy researchers","sequence":null,"activity":"Coordinate fellowship and secondment program","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"Support given to 5 Think Tanks","object_type":"Staff, communication, translation","result_indicator":"5 think tanks supported (one per Africa region)","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":60,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"d","sub_component":"Sub-Component : (d) Training and capacity building of policy makers, civil society, and other key stakeholders to develop their research and analytical capabilities","sequence":null,"activity":"Conduct a scoping exercise - peer network for female researchers","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"XX female professionals sensitized about the Think Tank opportunity","object_type":"Staff, communication, translation","result_indicator":"More female professionals engaged in winning Think Tank Consortia","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":61,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"d","sub_component":"Sub-Component : (d) Training and capacity building of policy makers, civil society, and other key stakeholders to develop their research and analytical capabilities","sequence":null,"activity":"Study on good practices to improve female participation in policy research","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"Consultant hired to conduct study","object_type":"Consulting","result_indicator":"Study report approved","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":64,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"e","sub_component":"Sub-Component : (e) Creating and maintaining a database and online Africa Knowledge Repository of existing policy research conducted across the continent","sequence":null,"activity":"Establish a repository","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"Engage AUC MIS to develop database and repository on ATTP website","object_type":"Consulting","result_indicator":"1 repository created and operational","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":68,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"f","sub_component":"Sub-Component : (f) Financing high-quality research on priority issues for the continent and supporting capacity building for think tanks to provide the knowledge and evidence base for regional policy making and policy engagement.","sequence":null,"activity":"Support the call for research proposals - communication and outreach plan and post-events","implemented_by":"ACBF","budget_code":"ACB060301MB","estimated_amount":24800.0,"intermediate_indicator":"No. of communication materials printed at the AU printing unit No. of Translators hired","object_type":"Staff, communication, translation","result_indicator":"ATTP communication materials distributed to stakeholders","observations":"","world_bank_comments":"Conditional approval - Approval granted for the ACBF comms plan for $53,500. Any additional funding conditional on a plan and budget.","attp_secretariat_comments":"","world_bank_amount":24800.0},
  {"row":69,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"f","sub_component":"Sub-Component : (f) Financing high-quality research on priority issues for the continent and supporting capacity building for think tanks to provide the knowledge and evidence base for regional policy making and policy engagement.","sequence":null,"activity":"Policy community survey","implemented_by":"ACBF","budget_code":"","estimated_amount":0,"intermediate_indicator":"Consultant hired to conduct survey","object_type":"Consulting","result_indicator":"Survey report approved","observations":"","world_bank_comments":"Conditional approval - prior to approval we will need to see a full concept note and detailed budget for this activity","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":70,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"f","sub_component":"Sub-Component : (f) Financing high-quality research on priority issues for the continent and supporting capacity building for think tanks to provide the knowledge and evidence base for regional policy making and policy engagement.","sequence":null,"activity":"Funding to think tanks","implemented_by":"PMRM","budget_code":"Pass through","estimated_amount":0,"intermediate_indicator":"Call for Applications Launched","object_type":"Applications","result_indicator":"At least 5 Think Tank Consortia engaged by end of FY2025","observations":"Pass through funds for 5 Think tank consortia","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":71,"component_no":2,"component":"COMPONENT #2 Strengthen the quality, relevance, and uptake of policy research on priority issues (US$35.5 million equivalent)","sub_component_code":"f","sub_component":"Sub-Component : (f) Financing high-quality research on priority issues for the continent and supporting capacity building for think tanks to provide the knowledge and evidence base for regional policy making and policy engagement.","sequence":null,"activity":"Participation in Think Tank Call and project planning","implemented_by":"AUDA-NEPAD","budget_code":"","estimated_amount":0,"intermediate_indicator":"To cover travel costs to planned meetings","object_type":"Staff, communication, translation","result_indicator":"AUDA staff attending all required meetings","observations":"","world_bank_comments":"Approved","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":81,"component_no":3,"component":"COMPONENT #Component 3: Support platform sustainability (indicative amount: US$11 million equivalent).","sub_component_code":"b","sub_component":"Sub-Component : (b) design of a resource mobilization strategy to raise funds ","sequence":null,"activity":"Selection of Consultant for Design of Resource Mobilization Strategy","implemented_by":"PMRM","budget_code":"PRM090205MB","estimated_amount":0,"intermediate_indicator":"Consultant hired to design ATTP Resource Mobilization Strategy","object_type":"Consulting","result_indicator":"Resource Mobilization Strategy developed","observations":"","world_bank_comments":"Approved - need to approve/clear the ToR for this study and review final costing","attp_secretariat_comments":"","world_bank_amount":0.0},
  {"row":85,"component_no":3,"component":"COMPONENT #Component 3: Support platform sustainability (indicative amount: US$11 million equivalent).","sub_component_code":"c","sub_component":"Sub-Component : (c) studies necessary to establish and operate the endowment fund including feasibility studies","sequence":null,"activity":"Selection of Consultant to conduct Feasibility study for endowment fund","implemented_by":"PMRM","budget_code":"PRM090205MB","estimated_amount":0,"intermediate_indicator":"Consultant hired to conduct feasibility study for the ATTP endowment fund","object_type":"Consulting","result_indicator":"Feasibility study report submitted by the Consultant","observations":"","world_bank_comments":"Approved - need to approve/clear the ToR for this study and review final costing","attp_secretariat_comments":"","world_bank_amount":0.0}
]
JSON, true, 512, JSON_THROW_ON_ERROR);
    }
}

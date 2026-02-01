<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\Activity;
use App\Models\SubActivity;
use Illuminate\Database\Seeder;

class ResourceDataSeeder extends Seeder
{
    public function run(): void
    {
        $categoryNames = [
            'Hardware & Devices',
            'Software Licensing',
            'Consultancy Services',
            'Travel & Per Diem',
            'Security & Compliance',
            'Telecom Services',
            'Facility Management',
            'Communications & Outreach',
            'Research & Evaluation',
            'Training & Capacity Building',
            'Monitoring Equipment',
            'Laboratory Supplies',
            'Data Center Services',
            'Procurement Support',
            'Maintenance Contracts',
            'Transport & Logistics',
            'Audit & Assurance',
            'Legal & Advisory',
            'Digital Platforms',
            'Cloud Hosting'
        ];

        $categories = collect($categoryNames)->map(function ($name, $index) {
            return ResourceCategory::updateOrCreate(
                ['name' => $name],
                [
                    'description' => "{$name} for program support",
                    'status' => 'active',
                    'created_by' => 1,
                ]
            );
        });

        $resourceTemplates = [
            'Satellite Connectivity Package',
            'Data Analytics Platform',
            'Eco-safe Vehicle Fleet',
            'Field Research Kits',
            'Capacity Building Workshop',
            'Field Hospital Setup',
            'Digital Authentication Licenses',
            'Governance Reporting Suite',
            'Solar Energy Array',
            'Water Treatment Units',
            'Climate Monitoring Sensors',
            'Security Surveillance System',
            'Public Engagement Campaign',
            'Logistics Coordination Hub',
            'Legal Review Panel',
            'Cloud Storage Tier',
            'Renewable Energy Certificates',
            'AI Translation Engine',
            'Remote Training Studio',
            'Data Center Backup'
        ];

        $resources = collect($resourceTemplates)->map(function ($name, $index) use ($categories) {
            $category = $categories[$index % $categories->count()];
            return Resource::updateOrCreate(
                ['reference_code' => 'RC-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'resource_category_id' => $category->id,
                    'name' => $name,
                    'description' => "Provisioning {$name} across programs",
                    'status' => 'active',
                    'is_human_resource' => false,
                    'created_by' => 1,
                ]
            );
        });

        $programFundings = ProgramFunding::pluck('id');
        if ($programFundings->isEmpty()) {
            $this->command->warn('No program fundings present; run ProcurementStructureSeeder first.');
            return;
        }

        $projects = Project::limit(6)->get();
        $activities = Activity::limit(10)->get();
        $subActivities = SubActivity::limit(20)->get();

        $levels = collect([
            ['allocation_level' => 'project', 'collection' => $projects],
            ['allocation_level' => 'activity', 'collection' => $activities],
            ['allocation_level' => 'sub_activity', 'collection' => $subActivities],
        ]);

        foreach ($programFundings as $fundingId) {
            foreach ($levels as $levelEntry) {
                foreach ($levelEntry['collection'] as $index => $target) {
                    for ($round = 0; $round < 3; $round++) {
                        $category = $categories[($index + $round) % $categories->count()];
                        $resource = $resources[($index + $round) % $resources->count()];
                        $amount = mt_rand(15000, 200000);
                        $year = 2025 + ($round % 3);
                        BudgetCommitment::create([
                            'program_funding_id' => $fundingId,
                            'resource_category_id' => $category->id,
                            'resource_id' => $resource->id,
                            'allocation_level' => $levelEntry['allocation_level'],
                            'allocation_id' => $target->id,
                            'commitment_amount' => $amount,
                            'commitment_year' => $year,
                            'status' => ($index + $round) % 2 === 0 ? BudgetCommitment::STATUS_APPROVED : BudgetCommitment::STATUS_DRAFT,
                            'created_by' => 1,
                        ]);
                    }
                }
            }
        }
    }
}

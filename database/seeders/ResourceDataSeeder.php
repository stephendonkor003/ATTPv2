<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\Activity;
use App\Models\SubActivity;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ResourceDataSeeder extends Seeder
{
    public function run(): void
    {
        // UUID migration: use a real user id instead of hardcoding "1".
        $createdBy = User::where('email', 'amodonlimited@gmail.com')->value('id')
            ?? User::query()->value('id');

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

        $categories = collect($categoryNames)->map(function ($name, $index) use ($createdBy) {
            return ResourceCategory::updateOrCreate(
                ['name' => $name],
                [
                    'description' => "{$name} for program support",
                    'status' => 'active',
                    'created_by' => $createdBy,
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

        $resources = collect($resourceTemplates)->map(function ($name, $index) use ($categories, $createdBy) {
            $category = $categories[$index % $categories->count()];
            return Resource::updateOrCreate(
                ['reference_code' => 'RC-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'resource_category_id' => $category->id,
                    'name' => $name,
                    'description' => "Provisioning {$name} across programs",
                    'status' => 'active',
                    'is_human_resource' => false,
                    'created_by' => $createdBy,
                ]
            );
        });

        $programFundings = ProgramFunding::with('program')->get();
        if ($programFundings->isEmpty()) {
            $this->command->warn('No program fundings present; run ProcurementStructureSeeder first.');
            return;
        }

        $subActivities = SubActivity::limit(20)->get();

        if ($subActivities->isEmpty()) {
            $this->command->warn('No sub-activities found; run ProcurementStructureSeeder first.');
            return;
        }

        $generateReference = function () {
            $year = now()->year;
            do {
                $reference = 'PR-' . $year . '-' . strtoupper(Str::random(5));
            } while (PurchaseRequest::where('reference_no', $reference)->exists());
            return $reference;
        };

        foreach ($programFundings as $funding) {
            foreach ($subActivities as $index => $subActivity) {
                for ($round = 0; $round < 3; $round++) {
                    $category = $categories[($index + $round) % $categories->count()];
                    $resource = $resources[($index + $round) % $resources->count()];
                    $amount = mt_rand(15000, 200000);
                    $year = 2025 + ($round % 3);

                    $purchaseRequest = PurchaseRequest::create([
                        'reference_no' => $generateReference(),
                        'program_funding_id' => $funding->id,
                        'governance_node_id' => $funding->governance_node_id,
                        'allocation_level' => 'sub_activity',
                        'allocation_id' => $subActivity->id,
                        'start_year' => $year,
                        'commitment_date' => now()->toDateString(),
                        'delivery_date' => now()->toDateString(),
                        'currency' => $funding->currency ?? $funding->program?->currency,
                        'total_amount' => $amount,
                        'description' => "Seeded PR for {$subActivity->name}",
                        'status' => 'draft',
                        'created_by' => $createdBy,
                    ]);

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $purchaseRequest->id,
                        'resource_category_id' => $category->id,
                        'resource_id' => $resource->id,
                        'amount' => $amount,
                    ]);

                    BudgetCommitment::create([
                        'purchase_request_id' => $purchaseRequest->id,
                        'program_funding_id' => $funding->id,
                        'governance_node_id' => $funding->governance_node_id,
                        'resource_category_id' => null,
                        'resource_id' => null,
                        'allocation_level' => 'sub_activity',
                        'allocation_id' => $subActivity->id,
                        'commitment_amount' => $amount,
                        'commitment_year' => $year,
                        'status' => ($index + $round) % 2 === 0 ? BudgetCommitment::STATUS_APPROVED : BudgetCommitment::STATUS_DRAFT,
                        'description' => $purchaseRequest->description,
                        'created_by' => $createdBy,
                    ]);
                }
            }
        }
    }
}

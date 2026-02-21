<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    // public function run(): void
    // {
    //     // User::factory(10)->create();

    //     // User::factory()->create([
    //     //     'name' => 'Test User',
    //     //     'email' => 'test@example.com',
    //     // ]);

    //     $this->call(GeoRegionSeeder::class);

    // }

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            PartnerPortalPermissionsSeeder::class,
            GovernanceLevelSeeder::class,
            GovernanceNodeSeeder::class,
            GovernanceReportingLineSeeder::class,
            LegacyUserSeeder::class,
            GovernanceAssignmentSeeder::class,
            FundingPartnerSeeder::class,
            AssignPartnerPermissionsSeeder::class,
            ProcurementStructureSeeder::class,
            ProcurementPermissionsSeeder::class,
            MEConfigurationSeeder::class,
            ResourceDataSeeder::class,
            LegacyProcurementSeeder::class,
            LegacyEvaluationSeeder::class,
            ProgramPlanSheetSeeder::class,
        ]);
    }
}

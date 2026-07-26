<?php

namespace Database\Seeders;

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
            LegacySqlDumpSeeder::class,
            OldDataSqlImportSeeder::class,
            LegacyThinkTankProcurementLifecycleSeeder::class,
            LegacySiteVisitSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            GeoRegionSeeder::class,
            AuMasterDataPermissionsSeeder::class,
            AuAgenda2063Seeder::class,
            AuFlagshipProjectSeeder::class,
            AuMemberStateSeeder::class,
            AuRegionalBlockSeeder::class,
            TreatySeeder::class,
            WorldBankCatalogSeeder::class,
            PartnerPortalPermissionsSeeder::class,
            WorldBankPartnerAccessSeeder::class,
            ConsortiumOperationsPermissionsSeeder::class,
            HrGovernancePermissionsSeeder::class,
            AssignPartnerPermissionsSeeder::class,
            ProcurementPermissionsSeeder::class,
            AttpAiGuideSettingSeeder::class,
            DiscussionSeeder::class,
            AttpBudgetStructureSeeder::class,
            ApprovedWorkPlanSeeder::class,
            AttpWorkPlan2025Seeder::class,
            ConsortiumThinkTankMembershipSeeder::class,
            BiAnnualSiteVisitQuestionnaireSeeder::class,
            RolePermissionSeeder::class,
            MasterAdminSeeder::class,
            ChirwaSuperAdminSeeder::class,
            SystemAdminAccessSeeder::class,
        ]);
    }
}

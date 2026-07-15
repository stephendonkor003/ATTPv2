<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_sectors', 'portfolio_manager_user_id')) {
                $table->foreignUuid('portfolio_manager_user_id')
                    ->nullable()
                    ->after('governance_node_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('myb_sectors', 'portfolio_manager_name')) {
                $table->string('portfolio_manager_name')->nullable()->after('portfolio_manager_user_id');
            }

            if (!Schema::hasColumn('myb_sectors', 'portfolio_manager_email')) {
                $table->string('portfolio_manager_email')->nullable()->after('portfolio_manager_name');
            }

            if (!Schema::hasColumn('myb_sectors', 'portfolio_manager_role')) {
                $table->string('portfolio_manager_role', 80)->nullable()->after('portfolio_manager_email');
            }
        });

        $this->syncPortfolioLeadershipRoles();
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (Schema::hasColumn('myb_sectors', 'portfolio_manager_user_id')) {
                $table->dropForeign(['portfolio_manager_user_id']);
                $table->dropColumn('portfolio_manager_user_id');
            }

            foreach (['portfolio_manager_name', 'portfolio_manager_email', 'portfolio_manager_role'] as $column) {
                if (Schema::hasColumn('myb_sectors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function syncPortfolioLeadershipRoles(): void
    {
        $now = now();
        $roleNames = ['Portfolio Manager', 'Portfolio Coordinator'];

        foreach ($roleNames as $roleName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName],
                [
                    'id' => DB::table('roles')->where('name', $roleName)->value('id') ?: (string) Str::uuid(),
                    'description' => $roleName === 'Portfolio Manager'
                        ? 'Oversees portfolio delivery across budget, finance, procurement, M&E, evaluation, and site visits.'
                        : 'Coordinates portfolio delivery across budget, finance, procurement, M&E, evaluation, and site visits.',
                    'updated_at' => $now,
                    'created_at' => DB::table('roles')->where('name', $roleName)->value('created_at') ?: $now,
                ]
            );
        }

        $permissionNames = [
            'dashboard.access',
            'budget.access',
            'budget.structure.manage',
            'budget.activities.manage',
            'budget.allocations.manage',
            'budget.reports.view',
            'budget.project_financial_position.view',
            'budget.summary.view',
            'sector.view',
            'sector.edit',
            'program.view',
            'program.create',
            'program.edit',
            'project.view',
            'project.create',
            'project.edit',
            'activities.view',
            'activities.create',
            'activities.edit',
            'subactivities.view',
            'subactivities.create',
            'subactivities.edit',
            'finance.access',
            'finance.resources.view',
            'finance.resources.create',
            'finance.resources.edit',
            'finance.resources.delete',
            'finance.program_funding.view',
            'finance.commitments.view',
            'finance.commitments.create',
            'finance.commitments.edit',
            'finance.purchase_requests.view',
            'finance.purchase_requests.view_all',
            'finance.purchase_requests.send',
            'finance.purchase_requests.approve',
            'finance.purchase_orders.create',
            'finance.awp.view',
            'finance.awp.create',
            'finance.awp.edit',
            'finance.executions.view',
            'me.configuration.view',
            'me.configuration.manage',
            'forms.manage',
            'forms.submit',
            'forms.approve',
            'forms.reject',
            'evaluations.manage',
            'evaluations.evaluate',
            'evaluations.view_all',
            'site_visits.view',
            'site_visits.create',
            'site_visits.observe',
            'site_visits.submit',
            'site_visits.approve',
        ];

        foreach ([
            'finance.resources.edit' => 'Edit finance resources',
            'finance.resources.delete' => 'Delete finance resources',
        ] as $permissionName => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permissionName],
                [
                    'id' => DB::table('permissions')->where('name', $permissionName)->value('id') ?: (string) Str::uuid(),
                    'module' => 'Finance',
                    'description' => $description,
                    'updated_at' => $now,
                    'created_at' => DB::table('permissions')->where('name', $permissionName)->value('created_at') ?: $now,
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        foreach ($roleNames as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Permissions which describe observation, reporting, download, or export
     * access only. The HTTP read-only boundary remains authoritative even if
     * a future route accidentally reuses one of these permissions for a write.
     *
     * @var array<int, string>
     */
    private const READ_PERMISSION_NAMES = [
        'dashboard.access',
        'api_sync.view',
        'api_sync.audit.view',
        'api_sync.documents.view',
        'settings.au_master_data.view',
        'treaties.view',
        'budget.access',
        'budget.reports.view',
        'budget.project_financial_position.view',
        'budget.summary.view',
        'sector.view',
        'program.view',
        'program.report',
        'project.view',
        'project.report',
        'activities.view',
        'activity.report',
        'subactivities.view',
        'consortiums.view',
        'consortiums.analysis.view',
        'discussions.view',
        'evaluations.view_all',
        'finance.access',
        'finance.awp.view',
        'finance.commitments.view',
        'finance.commitments.view_all',
        'finance.departments.view',
        'finance.executions.view',
        'finance.funders.view',
        'finance.governance_structure.view',
        'finance.program_funding.view',
        'finance.purchase_requests.view',
        'finance.purchase_requests.view_all',
        'finance.resources.view',
        'grm.view',
        'grm.reports',
        'hr.access',
        'hr.positions.view',
        'hr.vacancies.view',
        'hr.analytics.view',
        'hr.applicants.view',
        'hr.employees.view',
        'hr.view_all_nodes',
        'hrm.positions.view',
        'hrm.vacancies.view',
        'biannual_site_visits.view',
        'biannual_site_visits.export',
        'me.configuration.view',
        'me.data_entry.view',
        'me.mission_reports.view',
        'me.performance_reports.view',
        'me.reporting_notifications.view',
        'me.reports.export',
        'me.results.view',
        'prescreening.access',
        'prescreening.reports.view_all',
        'prescreening.view_all',
        'procurement.audit',
        'procurement.plan.view',
        'procurement.view_all',
        'site_visits.view',
        'communications.view',
        'national_data.review',
        'questions.view',
        'system.audit.view',
        'think_tanks.directory.view',
        'think_tanks.funding.history.view',
        'think_tanks.funding.view',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'is_read_only_auditor')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->boolean('is_read_only_auditor')->default(false)->index();
            });
        }

        DB::transaction(function (): void {
            if (! DB::table('roles')->whereRaw('LOWER(TRIM(name)) = ?', ['auditor'])->exists()) {
                DB::table('roles')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => 'Auditor',
                    'description' => 'System-wide read-only audit access. Business data and workflow actions cannot be changed.',
                    'is_read_only_auditor' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('roles')
                ->whereRaw('LOWER(TRIM(name)) = ?', ['auditor'])
                ->update([
                    'name' => 'Auditor',
                    'description' => 'System-wide read-only audit access. Business data and workflow actions cannot be changed.',
                    'is_read_only_auditor' => true,
                    'updated_at' => now(),
                ]);

            $auditorRoleIds = DB::table('roles')
                ->where('is_read_only_auditor', true)
                ->pluck('id');

            if ($auditorRoleIds->isEmpty()) {
                return;
            }

            // Direct grants previously overrode the role. Remove every direct
            // grant for current auditors so stale write access cannot reappear.
            $auditorUserIds = DB::table('users')
                ->whereIn('role_id', $auditorRoleIds)
                ->pluck('id');

            if ($auditorUserIds->isNotEmpty()) {
                DB::table('user_permission')
                    ->whereIn('user_id', $auditorUserIds)
                    ->delete();

                DB::table('users')
                    ->whereIn('id', $auditorUserIds)
                    ->update([
                        'user_type' => 'staff',
                        'member_state_id' => null,
                        'vendor_category' => null,
                        'updated_at' => now(),
                    ]);
            }

            $readPermissionIds = DB::table('permissions')
                ->whereIn('name', self::READ_PERMISSION_NAMES)
                ->pluck('id');

            DB::table('role_permission')
                ->whereIn('role_id', $auditorRoleIds)
                ->delete();

            $rows = [];
            foreach ($auditorRoleIds as $roleId) {
                foreach ($readPermissionIds as $permissionId) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('role_permission')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        // Direct write grants are intentionally not restored on rollback. A
        // rollback must not silently reintroduce access which was unsafe.
        if (Schema::hasColumn('roles', 'is_read_only_auditor')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('is_read_only_auditor');
            });
        }
    }
};

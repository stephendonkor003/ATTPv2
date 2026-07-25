<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'me.performance_reports.view' => 'View performance reports across the assigned M&E portfolio',
        'me.performance_reports.review' => 'Review, approve, or return submitted performance reports',
        'me.performance_reports.archive' => 'Archive reviewed performance reports as historical records',
    ];

    public function up(): void
    {
        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->dropUnique('me_performance_report_form_period_unique');

            $table->foreignUuid('think_tank_member_id')
                ->nullable()
                ->after('responsible_directorate_id')
                ->constrained('attp_consortium_think_tanks')
                ->nullOnDelete();
            $table->foreignUuid('assignment_id')
                ->nullable()
                ->after('think_tank_member_id')
                ->constrained('me_data_collection_assignments')
                ->nullOnDelete();
            $table->foreignUuid('archived_by')
                ->nullable()
                ->after('review_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('archived_by');
            $table->text('archive_notes')->nullable()->after('archived_at');

            $table->unique(
                ['form_id', 'reporting_year', 'reporting_quarter', 'think_tank_member_id'],
                'me_performance_report_owner_period_unique'
            );
            $table->index(
                ['think_tank_member_id', 'status', 'reporting_year'],
                'me_performance_report_owner_status_idx'
            );
            $table->index('assignment_id', 'me_performance_report_assignment_idx');
        });

        Schema::create('me_performance_report_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('me_performance_reports')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->foreignUuid('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'created_at'], 'me_performance_report_transition_idx');
        });

        DB::table('me_performance_reports')
            ->whereIn('status', ['validated', 'approved'])
            ->update(['status' => 'reviewed']);
        DB::table('me_performance_reports')
            ->where('status', 'returned')
            ->update(['status' => 'draft']);

        DB::table('me_performance_reports')
            ->orderBy('created_at')
            ->get(['id', 'status', 'created_by', 'created_at'])
            ->each(function (object $report): void {
                DB::table('me_performance_report_transitions')->insert([
                    'id' => (string) Str::uuid(),
                    'report_id' => $report->id,
                    'from_status' => null,
                    'to_status' => $report->status,
                    'action' => 'lifecycle_initialized',
                    'notes' => 'Existing report migrated into the controlled reporting lifecycle.',
                    'acted_by' => $report->created_by,
                    'created_at' => $report->created_at ?? now(),
                ]);
            });

        $this->syncPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('me_performance_report_transitions');

        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->dropIndex('me_performance_report_assignment_idx');
            $table->dropIndex('me_performance_report_owner_status_idx');
            $table->dropUnique('me_performance_report_owner_period_unique');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'archive_notes']);
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropConstrainedForeignId('think_tank_member_id');
            $table->unique(
                ['form_id', 'reporting_year', 'reporting_quarter'],
                'me_performance_report_form_period_unique'
            );
        });
    }

    private function syncPermissions(): void
    {
        $permissionIds = collect(self::PERMISSIONS)->mapWithKeys(function (
            string $description,
            string $name
        ): array {
            $existingId = DB::table('permissions')->where('name', $name)->value('id');
            $permissionId = $existingId ?: (string) Str::uuid();

            if ($existingId) {
                DB::table('permissions')->where('id', $existingId)->update([
                    'id' => $permissionId,
                    'module' => 'M&E',
                    'description' => $description,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')->insert([
                    'id' => $permissionId,
                    'name' => $name,
                    'module' => 'M&E',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [$name => $permissionId];
        });

        $roles = [
            'System Admin' => array_keys(self::PERMISSIONS),
            'Monitoring and Evaluation Manager' => array_keys(self::PERMISSIONS),
            'Portfolio Manager' => ['me.performance_reports.view'],
            'Portfolio Coordinator' => ['me.performance_reports.view'],
        ];

        foreach ($roles as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds->get($permissionName),
                ]);
            }
        }
    }
};

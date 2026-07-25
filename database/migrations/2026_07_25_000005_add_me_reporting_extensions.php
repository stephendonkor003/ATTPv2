<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'me.mission_reports.view' => 'View standardized M&E mission reports',
        'me.mission_reports.manage' => 'Create and update M&E mission reports',
        'me.mission_reports.review' => 'Review, approve, or return M&E mission reports',
        'me.mission_reports.archive' => 'Archive approved M&E mission reports',
        'me.reporting_notifications.view' => 'View personal M&E reporting notifications and reminders',
    ];

    public function up(): void
    {
        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->string('aggregation_method', 32)->default('sum')->after('results_level');
            $table->decimal('annual_target', 20, 4)->nullable()->after('baseline_value');
            $table->decimal('life_of_programme_target', 20, 4)->nullable()->after('annual_target');
            $table->index('aggregation_method', 'me_indicators_aggregation_method_idx');
        });

        Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
            $table->string('aggregation_method', 32)->default('sum')->after('reporting_frequency');
            $table->decimal('annual_target', 20, 4)->nullable()->after('target_value');
            $table->decimal('life_of_programme_target', 20, 4)->nullable()->after('annual_target');
            $table->decimal('cumulative_year_result', 20, 4)->nullable()->after('actual_value');
            $table->decimal('cumulative_programme_result', 20, 4)->nullable()->after('cumulative_year_result');
            $table->decimal('target_achievement_percent', 10, 2)->nullable()->after('progress_percent');
        });

        Schema::table('me_performance_report_documents', function (Blueprint $table): void {
            $table->string('validation_status', 24)->default('pending')->after('file_size');
            $table->foreignUuid('validated_by')->nullable()->after('validation_status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->text('validation_notes')->nullable()->after('validated_at');
            $table->index(['validation_status', 'created_at'], 'me_report_documents_validation_idx');
        });

        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->string('validation_status', 24)->default('pending')->after('external_url');
            $table->foreignUuid('validated_by')->nullable()->after('validation_status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->text('validation_notes')->nullable()->after('validated_at');
            $table->index(['document_type', 'validation_status'], 'me_evidence_type_validation_idx');
        });

        Schema::create('me_mission_report_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('sections');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('me_mission_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('me_mission_report_templates')->restrictOnDelete();
            $table->foreignUuid('portfolio_id')->nullable()->constrained('myb_sectors')->nullOnDelete();
            $table->foreignUuid('project_component_id')->nullable()->constrained('myb_projects')->nullOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->string('report_number', 80)->unique();
            $table->string('title');
            $table->string('location')->nullable();
            $table->date('mission_start_date');
            $table->date('mission_end_date');
            $table->date('action_due_at')->nullable();
            $table->text('team_members')->nullable();
            $table->text('objectives')->nullable();
            $table->text('methodology')->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('key_findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('responsible_parties')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->text('conclusion')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignUuid('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_notes')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'status'], 'me_mission_reports_portfolio_status_idx');
            $table->index(['think_tank_member_id', 'status'], 'me_mission_reports_owner_status_idx');
            $table->index(['action_due_at', 'status'], 'me_mission_reports_action_due_idx');
        });

        Schema::create('me_mission_report_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mission_report_id')->constrained('me_mission_reports')->cascadeOnDelete();
            $table->string('document_name');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('me_reporting_notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 120);
            $table->string('subject_type', 120);
            $table->uuid('subject_id');
            $table->date('notification_date');
            $table->timestamps();

            $table->unique(
                ['user_id', 'event_key', 'subject_type', 'subject_id', 'notification_date'],
                'me_reporting_notification_dedupe'
            );
            $table->index(['event_key', 'notification_date'], 'me_reporting_notification_event_idx');
        });

        $this->backfillAggregationConfiguration();
        $this->seedMissionTemplates();
        $this->syncPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('me_reporting_notification_logs');
        Schema::dropIfExists('me_mission_report_documents');
        Schema::dropIfExists('me_mission_reports');
        Schema::dropIfExists('me_mission_report_templates');

        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->dropIndex('me_evidence_type_validation_idx');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['validation_status', 'validated_at', 'validation_notes']);
        });

        Schema::table('me_performance_report_documents', function (Blueprint $table): void {
            $table->dropIndex('me_report_documents_validation_idx');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['validation_status', 'validated_at', 'validation_notes']);
        });

        Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
            $table->dropColumn([
                'aggregation_method',
                'annual_target',
                'life_of_programme_target',
                'cumulative_year_result',
                'cumulative_programme_result',
                'target_achievement_percent',
            ]);
        });

        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->dropIndex('me_indicators_aggregation_method_idx');
            $table->dropColumn(['aggregation_method', 'annual_target', 'life_of_programme_target']);
        });
    }

    private function backfillAggregationConfiguration(): void
    {
        $units = DB::table('me_indicator_units')
            ->get(['id', 'name', 'symbol'])
            ->keyBy('id');
        $setupTargets = DB::table('me_indicator_targets')
            ->where('target_context', 'setup')
            ->pluck('target_value', 'indicator_id');

        DB::table('myb_indicators')
            ->get(['id', 'name', 'unit_id'])
            ->each(function (object $indicator) use ($units, $setupTargets): void {
                $unit = $units->get($indicator->unit_id);
                $classification = Str::lower(implode(' ', [
                    $indicator->name,
                    $unit?->name,
                    $unit?->symbol,
                ]));
                $nonAdditive = Str::contains($classification, [
                    '%',
                    'percent',
                    'percentage',
                    'ratio',
                    'rate',
                    'average',
                    'mean',
                    'index',
                    'score',
                ]);
                $target = $setupTargets->get($indicator->id);

                DB::table('myb_indicators')->where('id', $indicator->id)->update([
                    'aggregation_method' => $nonAdditive ? 'non_additive' : 'sum',
                    'annual_target' => $target,
                    'life_of_programme_target' => $target,
                ]);
            });

        DB::table('me_performance_report_indicator_results')
            ->orderBy('created_at')
            ->get(['id', 'indicator_id', 'target_value', 'actual_value'])
            ->each(function (object $result): void {
                $indicator = DB::table('myb_indicators')->where('id', $result->indicator_id)->first([
                    'aggregation_method',
                    'baseline_value',
                    'annual_target',
                    'life_of_programme_target',
                ]);
                if (! $indicator) {
                    return;
                }
                $actual = $result->actual_value;
                $annualTarget = $indicator->annual_target ?? $result->target_value;
                $achievement = $actual !== null && (float) $annualTarget !== 0.0
                    ? round(((float) $actual / (float) $annualTarget) * 100, 2)
                    : null;

                DB::table('me_performance_report_indicator_results')->where('id', $result->id)->update([
                    'aggregation_method' => $indicator->aggregation_method,
                    'annual_target' => $annualTarget,
                    'life_of_programme_target' => $indicator->life_of_programme_target,
                    'cumulative_year_result' => $actual,
                    'cumulative_programme_result' => $actual === null
                        ? $indicator->baseline_value
                        : ($indicator->aggregation_method === 'sum'
                            ? ((float) $indicator->baseline_value + (float) $actual)
                            : (float) $actual),
                    'target_achievement_percent' => $achievement,
                ]);
            });
    }

    private function seedMissionTemplates(): void
    {
        $templates = [
            [
                'code' => 'MONITORING-MISSION',
                'name' => 'Monitoring Mission Report',
                'description' => 'Standard field-monitoring template for implementation progress, findings and corrective actions.',
            ],
            [
                'code' => 'TECHNICAL-SUPERVISION',
                'name' => 'Technical Support and Supervision Mission',
                'description' => 'Standard template for technical assistance, supervision findings and agreed follow-up actions.',
            ],
            [
                'code' => 'VERIFICATION-MISSION',
                'name' => 'Results Verification Mission',
                'description' => 'Standard template for independent verification of reported results and supporting evidence.',
            ],
        ];
        $sections = [
            'Mission identification and team',
            'Objectives and scope',
            'Methodology',
            'Executive summary',
            'Key findings',
            'Recommendations',
            'Corrective actions and responsible parties',
            'Lessons learned',
            'Conclusion',
            'Supporting documents',
        ];

        foreach ($templates as $template) {
            DB::table('me_mission_report_templates')->insert([
                'id' => (string) Str::uuid(),
                ...$template,
                'sections' => json_encode($sections, JSON_THROW_ON_ERROR),
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncPermissions(): void
    {
        $permissionIds = collect(self::PERMISSIONS)->mapWithKeys(function (
            string $description,
            string $name
        ): array {
            $id = DB::table('permissions')->where('name', $name)->value('id') ?: (string) Str::uuid();
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $id,
                    'module' => 'M&E',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return [$name => $id];
        });

        $roles = [
            'System Admin' => array_keys(self::PERMISSIONS),
            'Monitoring and Evaluation Manager' => array_keys(self::PERMISSIONS),
            'Portfolio Manager' => ['me.mission_reports.view', 'me.reporting_notifications.view'],
            'Portfolio Coordinator' => ['me.mission_reports.view', 'me.reporting_notifications.view'],
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

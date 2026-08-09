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
        Schema::table('attp_think_tank_procurement_plans', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_resubmitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_reason')->nullable();
        });

        Schema::create('attp_think_tank_procurement_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')
                ->constrained('attp_think_tank_procurement_plans')
                ->cascadeOnDelete();
            $table->string('item_code')->unique();
            $table->string('source_reference')->nullable()->index();
            $table->string('loan_credit_no')->nullable();
            $table->text('component')->nullable();
            $table->string('source_in_process', 30)->nullable();
            $table->string('source_process_status')->nullable();
            $table->string('source_activity_status')->nullable();
            $table->string('source_document_type')->nullable();
            $table->string('source_sea_sh_risk', 30)->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('procurement_category', 80)->nullable();
            $table->string('procurement_method', 120)->nullable();
            $table->string('market_approach', 80)->nullable();
            $table->string('review_type', 80)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 60)->nullable();
            $table->decimal('estimated_unit_cost', 18, 2)->nullable();
            $table->decimal('estimated_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('planned_quarter', 30)->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->text('review_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('step_reference')->nullable()->index();
            $table->timestamp('step_exported_at')->nullable();
            $table->foreignUuid('step_exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('no_objection_reference')->nullable();
            $table->date('no_objection_date')->nullable();
            $table->text('no_objection_notes')->nullable();
            $table->foreignUuid('no_objection_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('no_objection_recorded_at')->nullable();
            $table->foreignUuid('procurement_id')->nullable()->constrained('procurements')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->string('source_sheet')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->json('source_payload')->nullable();
            $table->json('planned_milestones')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['plan_id', 'status'], 'attp_tt_proc_items_plan_status_idx');
            $table->unique(
                ['plan_id', 'source_file', 'source_sheet', 'source_row'],
                'attp_tt_proc_items_source_unique'
            );
        });

        Schema::create('attp_think_tank_procurement_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')
                ->constrained('attp_think_tank_procurement_items')
                ->cascadeOnDelete();
            $table->string('document_type', 40)->default('supporting');
            $table->string('document_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'document_type'], 'attp_tt_proc_docs_item_type_idx');
        });

        Schema::create('attp_think_tank_procurement_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')
                ->constrained('attp_think_tank_procurement_plans')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')
                ->nullable()
                ->constrained('attp_think_tank_procurement_items')
                ->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['plan_id', 'created_at'], 'attp_tt_proc_events_plan_created_idx');
        });

        Schema::create('attp_think_tank_procurement_import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_path');
            $table->string('original_name');
            $table->string('archive_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('source_checksum', 64)->unique();
            $table->string('status', 40)->default('processing')->index();
            $table->unsignedInteger('sheet_count')->default(0);
            $table->unsignedInteger('source_row_count')->default(0);
            $table->unsignedInteger('mapped_item_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('summary')->nullable();
            $table->foreignUuid('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attp_think_tank_procurement_import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')
                ->constrained('attp_think_tank_procurement_import_batches')
                ->cascadeOnDelete();
            $table->string('sheet_name');
            $table->unsignedInteger('row_number');
            $table->json('row_payload');
            $table->string('mapping_status', 40)->default('preserved')->index();
            $table->text('mapping_message')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('attp_think_tank_procurement_plans')->nullOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('attp_think_tank_procurement_items')->nullOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'sheet_name', 'row_number'], 'attp_tt_proc_import_rows_unique');
        });

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->foreignUuid('think_tank_member_id')
                ->nullable()
                ->constrained('attp_consortium_think_tanks')
                ->nullOnDelete();
            $table->string('evaluation_phase', 30)->nullable()->index();
            $table->foreignUuid('procurement_id')->nullable()->constrained('procurements')->cascadeOnDelete();
        });

        $this->registerAdministrativePermissions();
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('think_tank_member_id');
            $table->dropConstrainedForeignId('procurement_id');
            $table->dropColumn('evaluation_phase');
        });

        Schema::dropIfExists('attp_think_tank_procurement_import_rows');
        Schema::dropIfExists('attp_think_tank_procurement_import_batches');
        Schema::dropIfExists('attp_think_tank_procurement_events');
        Schema::dropIfExists('attp_think_tank_procurement_documents');
        Schema::dropIfExists('attp_think_tank_procurement_items');

        Schema::table('attp_think_tank_procurement_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'version',
                'submitted_at',
                'last_resubmitted_at',
                'approved_at',
                'rejected_at',
                'decision_reason',
            ]);
        });
    }

    private function registerAdministrativePermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $definitions = [
            'think_tank.procurement.review' => 'Review and decide Think Tank procurement plans',
            'think_tank.procurement.reports' => 'View consolidated Think Tank procurement reports',
            'think_tank.procurement.step' => 'Export STEP data and record World Bank no-objection decisions',
        ];

        foreach ($definitions as $name => $description) {
            $permission = DB::table('permissions')->where('name', $name)->first();
            $permissionId = $permission?->id ?: (string) Str::uuid();

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $permissionId,
                    'module' => 'Think Tank Procurement',
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => $permission?->created_at ?: now(),
                ]
            );

            if (! Schema::hasTable('role_permission') || ! Schema::hasTable('roles')) {
                continue;
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', ['Procurement Officer', 'System Admin'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
};

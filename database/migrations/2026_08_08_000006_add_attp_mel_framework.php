<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('me_frameworks')) {
            Schema::create('me_frameworks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 80);
                $table->string('version', 40);
                $table->string('title');
                $table->text('project_development_objective')->nullable();
                $table->string('status', 24)->default('draft');
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_current')->default(false);
                $table->text('notes')->nullable();
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['code', 'version'], 'me_frameworks_code_version_unique');
                $table->index(['is_current', 'status'], 'me_frameworks_current_status_idx');
            });
        }

        Schema::table('myb_indicators', function (Blueprint $table): void {
            if (! Schema::hasColumn('myb_indicators', 'framework_id')) {
                $table->foreignUuid('framework_id')->nullable()->constrained('me_frameworks')->nullOnDelete();
            }
            if (! Schema::hasColumn('myb_indicators', 'result_area')) {
                $table->text('result_area')->nullable();
            }
            if (! Schema::hasColumn('myb_indicators', 'value_type')) {
                $table->string('value_type', 24)->default('number');
            }
            if (! Schema::hasColumn('myb_indicators', 'target_type')) {
                $table->string('target_type', 24)->default('cumulative');
            }
            if (! Schema::hasColumn('myb_indicators', 'reporting_source')) {
                $table->string('reporting_source', 30)->default('both');
            }
            if (! Schema::hasColumn('myb_indicators', 'is_cumulative')) {
                $table->boolean('is_cumulative')->default(true);
            }
            if (! Schema::hasColumn('myb_indicators', 'calculation_key')) {
                $table->string('calculation_key', 100)->nullable();
            }
            if (! Schema::hasColumn('myb_indicators', 'requires_evidence')) {
                $table->boolean('requires_evidence')->default(false);
            }
            if (! Schema::hasColumn('myb_indicators', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('myb_indicators', 'display_order')) {
                $table->unsignedInteger('display_order')->default(0);
            }
            if (! Schema::hasColumn('myb_indicators', 'effective_from')) {
                $table->date('effective_from')->nullable();
            }
            if (! Schema::hasColumn('myb_indicators', 'effective_to')) {
                $table->date('effective_to')->nullable();
            }
        });

        if (! Schema::hasTable('me_indicator_reference_sheets')) {
            Schema::create('me_indicator_reference_sheets', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
                $table->foreignUuid('framework_id')->nullable()->constrained('me_frameworks')->nullOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->text('definition')->nullable();
                $table->text('rationale')->nullable();
                $table->text('inclusion_criteria')->nullable();
                $table->text('exclusion_criteria')->nullable();
                $table->string('unit_of_measurement', 120)->nullable();
                $table->text('data_collection_method')->nullable();
                $table->json('disaggregation')->nullable();
                $table->text('data_sources')->nullable();
                $table->text('calculation_method')->nullable();
                $table->string('collection_frequency', 80)->nullable();
                $table->string('reporting_frequency', 80)->nullable();
                $table->text('means_of_verification')->nullable();
                $table->text('data_generation_responsibility')->nullable();
                $table->text('verification_responsibility')->nullable();
                $table->text('additional_guidance')->nullable();
                $table->string('approval_status', 24)->default('draft');
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['indicator_id', 'version'], 'me_irs_indicator_version_unique');
                $table->index(['indicator_id', 'approval_status'], 'me_irs_indicator_status_idx');
            });
        }

        if (Schema::hasIndex('me_indicator_targets', 'me_indicator_targets_indicator_context_unique')) {
            Schema::table('me_indicator_targets', function (Blueprint $table): void {
                $table->dropUnique('me_indicator_targets_indicator_context_unique');
            });
        }

        Schema::table('me_indicator_targets', function (Blueprint $table): void {
            if (! Schema::hasColumn('me_indicator_targets', 'framework_id')) {
                $table->foreignUuid('framework_id')->nullable()->constrained('me_frameworks')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'reporting_year')) {
                $table->unsignedSmallInteger('reporting_year')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'project_year')) {
                $table->unsignedSmallInteger('project_year')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'target_scope')) {
                $table->string('target_scope', 24)->default('project');
            }
            if (! Schema::hasColumn('me_indicator_targets', 'think_tank_member_id')) {
                $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'baseline_value')) {
                $table->string('baseline_value', 100)->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'target_text')) {
                $table->string('target_text', 100)->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'revision')) {
                $table->unsignedInteger('revision')->default(1);
            }
            if (! Schema::hasColumn('me_indicator_targets', 'revision_reason')) {
                $table->text('revision_reason')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'approval_status')) {
                $table->string('approval_status', 24)->default('approved');
            }
            if (! Schema::hasColumn('me_indicator_targets', 'effective_from')) {
                $table->date('effective_from')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'approved_by')) {
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_indicator_targets', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        if (! Schema::hasTable('me_indicator_calculation_rules')) {
            Schema::create('me_indicator_calculation_rules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
                $table->foreignUuid('framework_id')->nullable()->constrained('me_frameworks')->nullOnDelete();
                $table->string('calculation_key', 100);
                $table->string('source_type', 80)->default('approved_indicator_results');
                $table->json('configuration')->nullable();
                $table->string('deduplication_key', 120)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['indicator_id', 'version'], 'me_calc_rule_indicator_version_unique');
                $table->index(['calculation_key', 'is_active'], 'me_calc_rule_key_active_idx');
            });
        }

        if (! Schema::hasTable('me_performance_thresholds')) {
            Schema::create('me_performance_thresholds', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('framework_id')->nullable()->constrained('me_frameworks')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('label', 100);
                $table->decimal('minimum_percent', 8, 2)->nullable();
                $table->decimal('maximum_percent', 8, 2)->nullable();
                $table->string('color', 20)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->unique(['framework_id', 'code'], 'me_threshold_framework_code_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('me_performance_thresholds');
        Schema::dropIfExists('me_indicator_calculation_rules');
        Schema::dropIfExists('me_indicator_reference_sheets');

        Schema::table('me_indicator_targets', function (Blueprint $table): void {
            foreach (['approved_by', 'think_tank_member_id', 'framework_id'] as $foreign) {
                if (Schema::hasColumn('me_indicator_targets', $foreign)) {
                    $table->dropForeign([$foreign]);
                }
            }
            $table->dropColumn([
                'framework_id', 'reporting_year', 'project_year', 'target_scope',
                'think_tank_member_id', 'baseline_value', 'target_text', 'revision',
                'revision_reason', 'approval_status', 'effective_from', 'approved_by', 'approved_at',
            ]);
            $table->unique(['indicator_id', 'target_context'], 'me_indicator_targets_indicator_context_unique');
        });

        Schema::table('myb_indicators', function (Blueprint $table): void {
            if (Schema::hasColumn('myb_indicators', 'framework_id')) {
                $table->dropForeign(['framework_id']);
            }
            $table->dropColumn([
                'framework_id', 'result_area', 'value_type', 'target_type', 'reporting_source',
                'is_cumulative', 'calculation_key', 'requires_evidence', 'is_active',
                'display_order', 'effective_from', 'effective_to',
            ]);
        });

        Schema::dropIfExists('me_frameworks');
    }
};

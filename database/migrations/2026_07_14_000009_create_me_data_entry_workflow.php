<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_data_entry_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->nullable()->constrained('myb_sectors')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['portfolio_id', 'status'], 'me_forms_portfolio_status_idx');
            $table->index(['responsible_user_id', 'status'], 'me_forms_responsible_status_idx');
        });

        Schema::create('me_data_entry_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('me_data_entry_forms')->cascadeOnDelete();
            $table->foreignUuid('indicator_id')->nullable()->constrained('myb_indicators')->nullOnDelete();
            $table->string('section')->nullable();
            $table->string('field_key', 120);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->enum('field_type', [
                'number',
                'percentage',
                'text',
                'textarea',
                'date',
                'select',
                'checkbox',
            ]);
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->string('unit_label')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'field_key'], 'me_form_fields_form_key_unique');
            $table->index(['form_id', 'sort_order'], 'me_form_fields_order_idx');
            $table->index('indicator_id', 'me_form_fields_indicator_idx');
        });

        Schema::create('me_reporting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->nullable()->constrained('myb_sectors')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('label');
            $table->enum('period_type', ['year', 'quarter', 'month', 'custom']);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['portfolio_id', 'status'], 'me_periods_portfolio_status_idx');
            $table->index(['period_start', 'period_end'], 'me_periods_date_range_idx');
        });

        Schema::create('me_data_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('me_data_entry_forms')->cascadeOnDelete();
            $table->foreignUuid('reporting_period_id')->constrained('me_reporting_periods')->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'reporting_period_id'], 'me_collections_form_period_unique');
            $table->index(['status', 'opens_at', 'closes_at'], 'me_collections_window_idx');
            $table->index('due_at', 'me_collections_due_idx');
        });

        Schema::create('me_data_collection_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained('me_data_collections')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->constrained('attp_consortium_think_tanks')->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(
                ['collection_id', 'think_tank_member_id'],
                'me_collection_tank_assignment_unique'
            );
            $table->index('think_tank_member_id', 'me_assignments_tank_idx');
        });

        Schema::create('me_data_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->unique()->constrained('me_data_collection_assignments')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->enum('status', ['draft', 'submitted', 'returned', 'validated', 'approved'])->default('draft');
            $table->json('schema_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at'], 'me_submissions_status_date_idx');
        });

        Schema::create('me_data_submission_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('me_data_submissions')->cascadeOnDelete();
            $table->foreignUuid('field_id')->nullable()->constrained('me_data_entry_form_fields')->nullOnDelete();
            $table->string('field_key', 120);
            $table->json('value')->nullable();
            $table->foreignUuid('indicator_result_id')->nullable()->constrained('me_indicator_results')->nullOnDelete();
            $table->timestamps();

            $table->unique(['submission_id', 'field_key'], 'me_answers_submission_field_unique');
            $table->index('field_id', 'me_answers_field_idx');
            $table->index('indicator_result_id', 'me_answers_result_idx');
        });

        Schema::table('me_indicator_results', function (Blueprint $table) {
            $table->foreignUuid('reporting_period_id')
                ->nullable()
                ->constrained('me_reporting_periods')
                ->nullOnDelete();
            $table->foreignUuid('think_tank_member_id')
                ->nullable()
                ->constrained('attp_consortium_think_tanks')
                ->nullOnDelete();
            $table->foreignUuid('data_submission_id')
                ->nullable()
                ->constrained('me_data_submissions')
                ->nullOnDelete();
            $table->string('source_field_key', 120)->nullable();

            $table->index('reporting_period_id', 'me_results_period_idx');
            $table->index('think_tank_member_id', 'me_results_tank_idx');
            $table->index('data_submission_id', 'me_results_submission_idx');
            $table->unique(
                ['data_submission_id', 'source_field_key'],
                'me_results_submission_field_idx'
            );
            $table->index(
                ['indicator_id', 'reporting_period_id', 'think_tank_member_id'],
                'me_results_indicator_period_tank_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('me_indicator_results', function (Blueprint $table) {
            $table->dropIndex('me_results_indicator_period_tank_idx');
            $table->dropUnique('me_results_submission_field_idx');
            $table->dropIndex('me_results_submission_idx');
            $table->dropIndex('me_results_tank_idx');
            $table->dropIndex('me_results_period_idx');
            $table->dropConstrainedForeignId('data_submission_id');
            $table->dropConstrainedForeignId('think_tank_member_id');
            $table->dropConstrainedForeignId('reporting_period_id');
            $table->dropColumn('source_field_key');
        });

        Schema::dropIfExists('me_data_submission_answers');
        Schema::dropIfExists('me_data_submissions');
        Schema::dropIfExists('me_data_collection_assignments');
        Schema::dropIfExists('me_data_collections');
        Schema::dropIfExists('me_reporting_periods');
        Schema::dropIfExists('me_data_entry_form_fields');
        Schema::dropIfExists('me_data_entry_forms');
    }
};

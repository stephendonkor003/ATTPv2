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
        Schema::table('me_data_entry_forms', function (Blueprint $table): void {
            $table->foreignUuid('project_component_id')
                ->nullable()
                ->after('portfolio_id')
                ->constrained('myb_projects')
                ->nullOnDelete();

            $table->index(
                ['project_component_id', 'status'],
                'me_forms_component_status_idx'
            );
        });

        Schema::create('me_data_entry_form_indicators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('me_data_entry_forms')->cascadeOnDelete();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'indicator_id'], 'me_form_indicator_unique');
            $table->index(['indicator_id', 'form_id'], 'me_indicator_form_index');
        });

        Schema::create('me_performance_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('me_data_entry_forms')->cascadeOnDelete();
            $table->foreignUuid('reporting_period_id')->constrained('me_reporting_periods')->restrictOnDelete();
            $table->foreignUuid('portfolio_id')->constrained('myb_sectors')->restrictOnDelete();
            $table->foreignUuid('project_component_id')->constrained('myb_projects')->restrictOnDelete();
            $table->foreignUuid('responsible_directorate_id')
                ->nullable()
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
            $table->unsignedSmallInteger('reporting_year');
            $table->string('reporting_quarter', 2);
            $table->string('status', 24)->default('draft');
            $table->text('key_achievements')->nullable();
            $table->text('variance_explanation')->nullable();
            $table->text('means_of_verification_notes')->nullable();
            $table->text('overall_assessment')->nullable();
            $table->string('performance_rating', 40)->nullable();
            $table->text('conclusion')->nullable();
            $table->text('challenges_faced')->nullable();
            $table->text('mitigation_strategies')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->text('adaptive_management_actions')->nullable();
            $table->text('next_period_priorities')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['form_id', 'reporting_year', 'reporting_quarter'],
                'me_performance_report_form_period_unique'
            );
            $table->index(['portfolio_id', 'status'], 'me_performance_report_portfolio_status_idx');
            $table->index(['project_component_id', 'reporting_year'], 'me_performance_report_component_year_idx');
        });

        Schema::create('me_performance_report_indicator_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('me_performance_reports')->cascadeOnDelete();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->restrictOnDelete();
            $table->foreignUuid('indicator_result_id')
                ->nullable()
                ->constrained('me_indicator_results')
                ->nullOnDelete();
            $table->decimal('target_value', 20, 4)->nullable();
            $table->decimal('actual_value', 20, 4)->nullable();
            $table->decimal('progress_percent', 10, 2)->nullable();
            $table->string('reporting_frequency', 40);
            $table->timestamps();

            $table->unique(['report_id', 'indicator_id'], 'me_performance_report_indicator_unique');
            $table->index('indicator_result_id', 'me_performance_report_result_idx');
        });

        Schema::create('me_performance_report_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('me_performance_reports')->cascadeOnDelete();
            $table->string('document_name');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['report_id', 'created_at'], 'me_performance_report_document_idx');
        });

        $this->backfillFormComponentsAndIndicators();
    }

    public function down(): void
    {
        Schema::dropIfExists('me_performance_report_documents');
        Schema::dropIfExists('me_performance_report_indicator_results');
        Schema::dropIfExists('me_performance_reports');
        Schema::dropIfExists('me_data_entry_form_indicators');

        Schema::table('me_data_entry_forms', function (Blueprint $table): void {
            $table->dropIndex('me_forms_component_status_idx');
            $table->dropConstrainedForeignId('project_component_id');
        });
    }

    private function backfillFormComponentsAndIndicators(): void
    {
        $componentsByIndicator = DB::table('myb_indicators')
            ->whereNotNull('project_component_id')
            ->pluck('project_component_id', 'id');

        DB::table('me_data_entry_forms')
            ->whereNull('project_component_id')
            ->whereNotNull('indicator_id')
            ->get(['id', 'indicator_id'])
            ->each(function (object $form) use ($componentsByIndicator): void {
                $componentId = $componentsByIndicator->get($form->indicator_id);
                if (! $componentId) {
                    return;
                }

                DB::table('me_data_entry_forms')
                    ->where('id', $form->id)
                    ->update(['project_component_id' => $componentId]);
            });

        $links = collect();

        DB::table('me_data_entry_forms')
            ->whereNotNull('indicator_id')
            ->get(['id', 'indicator_id'])
            ->each(function (object $form) use ($links): void {
                $links->put($form->id.':'.$form->indicator_id, [
                    'id' => (string) Str::uuid(),
                    'form_id' => $form->id,
                    'indicator_id' => $form->indicator_id,
                    'is_primary' => true,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('me_data_entry_form_fields')
            ->whereNotNull('indicator_id')
            ->orderBy('form_id')
            ->orderBy('sort_order')
            ->get(['form_id', 'indicator_id', 'sort_order'])
            ->each(function (object $field) use ($links): void {
                $key = $field->form_id.':'.$field->indicator_id;
                if ($links->has($key)) {
                    return;
                }

                $links->put($key, [
                    'id' => (string) Str::uuid(),
                    'form_id' => $field->form_id,
                    'indicator_id' => $field->indicator_id,
                    'is_primary' => false,
                    'sort_order' => max(20, (int) $field->sort_order),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        $links->chunk(500)->each(
            fn ($chunk) => DB::table('me_data_entry_form_indicators')->insert($chunk->values()->all())
        );
    }
};

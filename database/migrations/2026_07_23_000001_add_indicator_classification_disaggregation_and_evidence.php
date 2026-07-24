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
        Schema::create('me_knowledge_evidence_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')
                ->constrained('myb_sectors')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type', 40)->default('means_of_verification');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('external_url')->nullable();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['portfolio_id', 'document_type'], 'me_evidence_portfolio_type_index');
        });

        Schema::table('myb_indicators', function (Blueprint $table) {
            $table->foreignUuid('project_component_id')
                ->nullable()
                ->after('indicatorable_id')
                ->constrained('myb_projects')
                ->nullOnDelete();
            $table->string('results_level', 32)
                ->nullable()
                ->after('indicator_level_id');
            $table->text('data_collection_method')
                ->nullable()
                ->after('methodology');
            $table->foreignUuid('means_of_verification_id')
                ->nullable()
                ->after('primary_source')
                ->constrained('me_knowledge_evidence_items')
                ->nullOnDelete();
        });

        Schema::create('me_indicator_disaggregations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')
                ->constrained('myb_indicators')
                ->cascadeOnDelete();
            $table->string('level', 16);
            $table->string('dimension', 120);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();

            $table->unique(['indicator_id', 'level'], 'me_indicator_disaggregation_level_unique');
            $table->index(['indicator_id', 'parent_id'], 'me_indicator_disaggregation_parent_index');
        });

        DB::table('myb_indicators')
            ->whereNull('data_collection_method')
            ->update([
                'data_collection_method' => DB::raw('COALESCE(methodology, primary_source)'),
            ]);

        $levelNames = DB::table('me_indicator_levels')->pluck('name', 'id');
        DB::table('myb_indicators')
            ->whereNull('results_level')
            ->orderBy('id')
            ->select(['id', 'indicator_level_id'])
            ->each(function (object $indicator) use ($levelNames): void {
                $legacyLevel = Str::lower((string) $levelNames->get($indicator->indicator_level_id, ''));
                $resultsLevel = Str::contains($legacyLevel, ['impact', 'outcome', 'pdo'])
                    ? 'pdo'
                    : 'intermediate_results';

                DB::table('myb_indicators')
                    ->where('id', $indicator->id)
                    ->update(['results_level' => $resultsLevel]);
            });

        $this->backfillProjectComponents();
        $this->ensureIndicatorReportingCadences();
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicator_disaggregations');

        Schema::table('myb_indicators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('means_of_verification_id');
            $table->dropConstrainedForeignId('project_component_id');
            $table->dropColumn([
                'results_level',
                'data_collection_method',
            ]);
        });

        Schema::dropIfExists('me_knowledge_evidence_items');
    }

    private function backfillProjectComponents(): void
    {
        DB::table('myb_indicators')
            ->whereNull('project_component_id')
            ->where('indicatorable_type', 'App\\Models\\Project')
            ->get(['id', 'indicatorable_id'])
            ->each(function (object $indicator): void {
                DB::table('myb_indicators')
                    ->where('id', $indicator->id)
                    ->update(['project_component_id' => $indicator->indicatorable_id]);
            });

        DB::table('myb_indicators as indicators')
            ->join('myb_activities as activities', 'activities.id', '=', 'indicators.indicatorable_id')
            ->whereNull('indicators.project_component_id')
            ->where('indicators.indicatorable_type', 'App\\Models\\Activity')
            ->get(['indicators.id', 'activities.project_id'])
            ->each(function (object $indicator): void {
                DB::table('myb_indicators')
                    ->where('id', $indicator->id)
                    ->update(['project_component_id' => $indicator->project_id]);
            });

        DB::table('myb_indicators as indicators')
            ->join('myb_sub_activities as sub_activities', 'sub_activities.id', '=', 'indicators.indicatorable_id')
            ->join('myb_activities as activities', 'activities.id', '=', 'sub_activities.activity_id')
            ->whereNull('indicators.project_component_id')
            ->where('indicators.indicatorable_type', 'App\\Models\\SubActivity')
            ->get(['indicators.id', 'activities.project_id'])
            ->each(function (object $indicator): void {
                DB::table('myb_indicators')
                    ->where('id', $indicator->id)
                    ->update(['project_component_id' => $indicator->project_id]);
            });
    }

    private function ensureIndicatorReportingCadences(): void
    {
        $cadences = [
            [
                'name' => 'Monthly',
                'code' => 'MONTHLY',
                'aliases' => ['month', 'monthly'],
                'interval_unit' => 'month',
                'interval_value' => 1,
                'frequency_in_days' => 30,
                'sort_order' => 10,
            ],
            [
                'name' => 'Quarterly',
                'code' => 'QUARTERLY',
                'aliases' => ['quarter', 'quarterly'],
                'interval_unit' => 'quarterly',
                'interval_value' => 1,
                'frequency_in_days' => 90,
                'sort_order' => 20,
            ],
            [
                'name' => 'Semi-Annual',
                'code' => 'SEMI_ANNUAL',
                'aliases' => ['semi annual', 'semi-annual', 'semi_annual', 'semiannual'],
                'interval_unit' => 'month',
                'interval_value' => 6,
                'frequency_in_days' => 182,
                'sort_order' => 30,
            ],
            [
                'name' => 'Annual',
                'code' => 'ANNUAL',
                'aliases' => ['annual', 'year', 'yearly'],
                'interval_unit' => 'annual',
                'interval_value' => 1,
                'frequency_in_days' => 365,
                'sort_order' => 40,
            ],
        ];

        DB::table('myb_sectors')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($portfolioId) use ($cadences): void {
                foreach ($cadences as $cadence) {
                    $existing = DB::table('me_reporting_frequencies')
                        ->where('portfolio_id', $portfolioId)
                        ->where(function ($query) use ($cadence) {
                            $query->whereIn(DB::raw('LOWER(name)'), $cadence['aliases'])
                                ->orWhereIn(DB::raw('LOWER(code)'), $cadence['aliases']);
                        })
                        ->first();

                    if ($existing) {
                        DB::table('me_reporting_frequencies')
                            ->where('id', $existing->id)
                            ->update([
                                'interval_unit' => $cadence['interval_unit'],
                                'interval_value' => $cadence['interval_value'],
                                'frequency_in_days' => $cadence['frequency_in_days'],
                                'is_active' => true,
                                'updated_at' => now(),
                            ]);

                        continue;
                    }

                    DB::table('me_reporting_frequencies')->insert([
                        'id' => (string) Str::uuid(),
                        'portfolio_id' => $portfolioId,
                        'name' => $cadence['name'],
                        'code' => $cadence['code'],
                        'interval_unit' => $cadence['interval_unit'],
                        'interval_value' => $cadence['interval_value'],
                        'frequency_in_days' => $cadence['frequency_in_days'],
                        'description' => $cadence['name'].' indicator reporting cadence.',
                        'sort_order' => $cadence['sort_order'],
                        'is_active' => true,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};

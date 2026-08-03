<?php

use App\Models\MeIndicatorAchievement;
use App\Models\MePerformanceReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_repository_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->constrained('myb_sectors')->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['portfolio_id', 'name'], 'me_repository_folder_portfolio_name_unique');
        });

        Schema::create('me_repository_folder_indicators', function (Blueprint $table): void {
            $table->foreignUuid('folder_id')->constrained('me_repository_folders')->cascadeOnDelete();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->foreignUuid('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['folder_id', 'indicator_id'], 'me_repository_folder_indicator_unique');
        });

        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->foreignUuid('folder_id')
                ->nullable()
                ->after('portfolio_id')
                ->constrained('me_repository_folders')
                ->nullOnDelete();
            $table->index(['folder_id', 'document_type'], 'me_repository_folder_document_type_idx');
        });
        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->foreignUuid('means_of_verification_folder_id')
                ->nullable()
                ->after('means_of_verification_id')
                ->constrained('me_repository_folders')
                ->nullOnDelete();
        });

        $this->backfillFolders();
        $this->alignDisaggregationTaxonomy();
    }

    public function down(): void
    {
        $periodDimensionId = DB::table('me_disaggregation_dimensions')
            ->where('code', 'reporting_period')
            ->value('id');
        if ($periodDimensionId) {
            DB::table('me_indicator_disaggregation_requirements')
                ->where('dimension_id', $periodDimensionId)
                ->delete();
            DB::table('me_disaggregation_options')->where('dimension_id', $periodDimensionId)->delete();
            DB::table('me_disaggregation_dimensions')->where('id', $periodDimensionId)->delete();
        }
        DB::table('me_disaggregation_options')
            ->whereIn('code', ['other_not_reported', 'not_reported'])
            ->update(['is_active' => true, 'updated_at' => now()]);

        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('means_of_verification_folder_id');
        });
        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->dropIndex('me_repository_folder_document_type_idx');
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::dropIfExists('me_repository_folder_indicators');
        Schema::dropIfExists('me_repository_folders');
    }

    private function backfillFolders(): void
    {
        $now = now();
        DB::table('me_knowledge_evidence_items')
            ->whereNull('folder_id')
            ->select('portfolio_id')
            ->distinct()
            ->pluck('portfolio_id')
            ->each(function (string $portfolioId) use ($now): void {
                $folderId = (string) Str::uuid();
                DB::table('me_repository_folders')->insert([
                    'id' => $folderId,
                    'portfolio_id' => $portfolioId,
                    'name' => 'Imported Repository Documents',
                    'description' => 'Existing knowledge and evidence retained during the folder-based repository upgrade.',
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('me_knowledge_evidence_items')
                    ->where('portfolio_id', $portfolioId)
                    ->whereNull('folder_id')
                    ->update(['folder_id' => $folderId]);
            });

        DB::table('myb_indicators')
            ->whereNotNull('means_of_verification_id')
            ->get(['id', 'means_of_verification_id', 'created_by'])
            ->each(function (object $indicator) use ($now): void {
                $folderId = DB::table('me_knowledge_evidence_items')
                    ->where('id', $indicator->means_of_verification_id)
                    ->value('folder_id');
                $this->linkFolderToIndicator($folderId, $indicator->id, $indicator->created_by, $now);
                DB::table('myb_indicators')->where('id', $indicator->id)->update([
                    'means_of_verification_folder_id' => $folderId,
                ]);
            });

        DB::table('me_repository_document_links')
            ->where('linkable_type', MeIndicatorAchievement::class)
            ->get(['repository_item_id', 'linkable_id', 'linked_by'])
            ->each(function (object $link) use ($now): void {
                $folderId = DB::table('me_knowledge_evidence_items')
                    ->where('id', $link->repository_item_id)
                    ->value('folder_id');
                $indicatorId = DB::table('me_indicator_achievements')
                    ->where('id', $link->linkable_id)
                    ->value('indicator_id');
                $this->linkFolderToIndicator($folderId, $indicatorId, $link->linked_by, $now);
            });

        DB::table('me_repository_document_links')
            ->where('linkable_type', MePerformanceReport::class)
            ->get(['repository_item_id', 'linkable_id', 'linked_by'])
            ->each(function (object $link) use ($now): void {
                $folderId = DB::table('me_knowledge_evidence_items')
                    ->where('id', $link->repository_item_id)
                    ->value('folder_id');
                DB::table('me_performance_report_indicator_results')
                    ->where('report_id', $link->linkable_id)
                    ->pluck('indicator_id')
                    ->each(fn ($indicatorId) => $this->linkFolderToIndicator(
                        $folderId,
                        $indicatorId,
                        $link->linked_by,
                        $now
                    ));
            });
    }

    private function alignDisaggregationTaxonomy(): void
    {
        $now = now();
        $dimensionId = DB::table('me_disaggregation_dimensions')
            ->where('code', 'reporting_period')
            ->value('id');
        if (! $dimensionId) {
            $dimensionId = (string) Str::uuid();
            DB::table('me_disaggregation_dimensions')->insert([
                'id' => $dimensionId,
                'code' => 'reporting_period',
                'name' => 'Reporting period',
                'dimension_group' => 'classification',
                'description' => 'Quarterly, semi-annual, or annual reporting cadence; captured automatically by each report.',
                'sort_order' => 55,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'quarterly' => 'Quarterly',
            'semi_annual' => 'Semi-Annual',
            'annual' => 'Annual',
        ] as $code => $name) {
            $option = DB::table('me_disaggregation_options')
                ->where('dimension_id', $dimensionId)
                ->where('code', $code);
            $attributes = [
                'id' => (string) Str::uuid(),
                'parent_id' => null,
                'name' => $name,
                'metadata' => null,
                'sort_order' => match ($code) {
                    'quarterly' => 10,
                    'semi_annual' => 20,
                    default => 30,
                },
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($option->exists()) {
                unset($attributes['id'], $attributes['created_at']);
                $option->update($attributes);
            } else {
                DB::table('me_disaggregation_options')->insert([
                    'dimension_id' => $dimensionId,
                    'code' => $code,
                    ...$attributes,
                ]);
            }
        }

        DB::table('me_disaggregation_options')
            ->whereIn('code', ['other_not_reported', 'not_reported'])
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    private function linkFolderToIndicator(?string $folderId, ?string $indicatorId, ?string $userId, mixed $now): void
    {
        if (! $folderId || ! $indicatorId) {
            return;
        }

        DB::table('me_repository_folder_indicators')->insertOrIgnore([
            'folder_id' => $folderId,
            'indicator_id' => $indicatorId,
            'linked_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};

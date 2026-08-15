<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_sections')) {
            return;
        }

        Schema::table('evaluation_sections', function (Blueprint $table): void {
            if (! Schema::hasColumn('evaluation_sections', 'parent_section_id')) {
                $table->foreignUuid('parent_section_id')
                    ->nullable()
                    ->after('evaluation_id')
                    ->constrained('evaluation_sections')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('evaluation_sections', 'show_subtotal')) {
                // Existing sections always exposed a section total in the builder,
                // so true is the backward-compatible default.
                $table->boolean('show_subtotal')->default(true)->after('description');
            }

            if (! Schema::hasColumn('evaluation_sections', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('show_subtotal');
            }
        });

        if (Schema::hasColumn('evaluation_sections', 'sort_order')) {
            $this->backfillRootSectionOrder();
        }

        if (
            Schema::hasColumn('evaluation_sections', 'parent_section_id')
            && Schema::hasColumn('evaluation_sections', 'sort_order')
        ) {
            Schema::table('evaluation_sections', function (Blueprint $table): void {
                $table->index(
                    ['evaluation_id', 'parent_section_id', 'sort_order'],
                    'evaluation_sections_hierarchy_order_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluation_sections')) {
            return;
        }

        if (
            Schema::hasColumn('evaluation_sections', 'parent_section_id')
            && Schema::hasColumn('evaluation_sections', 'sort_order')
        ) {
            Schema::table('evaluation_sections', function (Blueprint $table): void {
                $table->dropIndex('evaluation_sections_hierarchy_order_index');
            });
        }

        Schema::table('evaluation_sections', function (Blueprint $table): void {
            if (Schema::hasColumn('evaluation_sections', 'parent_section_id')) {
                $table->dropConstrainedForeignId('parent_section_id');
            }

            $columns = collect(['show_subtotal', 'sort_order'])
                ->filter(fn (string $column): bool => Schema::hasColumn('evaluation_sections', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function backfillRootSectionOrder(): void
    {
        $evaluationIds = DB::table('evaluation_sections')
            ->whereNull('parent_section_id')
            ->distinct()
            ->pluck('evaluation_id');

        foreach ($evaluationIds as $evaluationId) {
            DB::table('evaluation_sections')
                ->where('evaluation_id', $evaluationId)
                ->whereNull('parent_section_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id')
                ->each(function (string $sectionId, int $index): void {
                    DB::table('evaluation_sections')
                        ->where('id', $sectionId)
                        ->update(['sort_order' => $index + 1]);
                });
        }
    }
};

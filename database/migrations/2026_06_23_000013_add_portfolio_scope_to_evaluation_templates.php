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
        if (! Schema::hasTable('evaluations')) {
            return;
        }

        Schema::table('evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluations', 'portfolio_id')) {
                $table->uuid('portfolio_id')->nullable()->after('type');
                $table->index('portfolio_id', 'evaluations_portfolio_id_index');
            }
        });

        $portfolioIds = DB::table('myb_sectors')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($portfolioIds->isEmpty()) {
            return;
        }

        $templates = DB::table('evaluations')
            ->whereIn('type', ['services', 'goods'])
            ->whereIn('status', ['draft', 'active', 'close'])
            ->whereNull('portfolio_id')
            ->get();

        foreach ($templates as $template) {
            $primaryPortfolioId = $portfolioIds->first();

            DB::table('evaluations')
                ->where('id', $template->id)
                ->update([
                    'portfolio_id' => $primaryPortfolioId,
                    'updated_at' => now(),
                ]);

            $this->cloneTemplateForOtherPortfolios($template, $portfolioIds->slice(1)->values());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluations') || ! Schema::hasColumn('evaluations', 'portfolio_id')) {
            return;
        }

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_portfolio_id_index');
            $table->dropColumn('portfolio_id');
        });
    }

    private function cloneTemplateForOtherPortfolios(object $template, $portfolioIds): void
    {
        if ($portfolioIds->isEmpty()) {
            return;
        }

        $sections = DB::table('evaluation_sections')
            ->where('evaluation_id', $template->id)
            ->orderBy('created_at')
            ->get();

        $criteria = DB::table('evaluation_criteria')
            ->whereIn('evaluation_section_id', $sections->pluck('id')->all())
            ->orderBy('created_at')
            ->get()
            ->groupBy('evaluation_section_id');

        foreach ($portfolioIds as $portfolioId) {
            $newEvaluationId = (string) Str::uuid();
            $evaluationPayload = (array) $template;
            $evaluationPayload['id'] = $newEvaluationId;
            $evaluationPayload['portfolio_id'] = $portfolioId;
            $evaluationPayload['created_at'] = now();
            $evaluationPayload['updated_at'] = now();

            DB::table('evaluations')->insert($evaluationPayload);

            foreach ($sections as $section) {
                $newSectionId = (string) Str::uuid();
                DB::table('evaluation_sections')->insert([
                    'id' => $newSectionId,
                    'evaluation_id' => $newEvaluationId,
                    'name' => $section->name,
                    'description' => $section->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($criteria->get($section->id, collect()) as $criterion) {
                    DB::table('evaluation_criteria')->insert([
                        'id' => (string) Str::uuid(),
                        'evaluation_section_id' => $newSectionId,
                        'name' => $criterion->name,
                        'description' => $criterion->description,
                        'max_score' => $criterion->max_score,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};

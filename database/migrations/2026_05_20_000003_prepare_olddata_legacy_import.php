<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->prepareUsers();
        $this->prepareApplicants();
        $this->prepareEvaluations();
        $this->preparePrescreeningCriteria();
        $this->prepareSiteVisitEvaluations();
        $this->prepareThinkDatasets();
    }

    public function down(): void
    {
        $this->dropColumnsIfPresent('users', ['email_verified_at']);

        $this->dropColumnsIfPresent('evaluations', array_merge(
            ['applicant_id', 'evaluator_id'],
            $this->evaluationScoreColumns(),
            $this->evaluationTextColumns(),
            ['total_score', 'overall_comments', 'video_path']
        ));

        $this->dropColumnsIfPresent('prescreening_criteria', array_merge(
            ['applicant_id', 'evaluator_id', 'status', 'eligible'],
            $this->prescreeningStringColumns(),
            $this->prescreeningTextColumns(),
            $this->prescreeningScoreColumns()
        ));
    }

    private function prepareUsers(): void
    {
        $this->addTimestampColumns('users', ['email_verified_at']);
    }

    private function prepareApplicants(): void
    {
        $this->alterColumnsToText('applicants', [
            'sub_region',
            'focus_areas',
            'members_names',
            'consortium_name',
            'covered_countries',
        ]);
    }

    private function prepareEvaluations(): void
    {
        $this->addUuidColumns('evaluations', ['applicant_id', 'evaluator_id']);
        $this->addIntegerColumns('evaluations', $this->evaluationScoreColumns());
        $this->addTextColumns('evaluations', $this->evaluationTextColumns());
        $this->addDecimalColumns('evaluations', ['total_score']);
        $this->addTextColumns('evaluations', ['overall_comments']);
        $this->addStringColumns('evaluations', ['video_path']);
    }

    private function preparePrescreeningCriteria(): void
    {
        $this->addUuidColumns('prescreening_criteria', ['applicant_id', 'evaluator_id']);
        $this->addStringColumns('prescreening_criteria', array_merge(
            ['status', 'eligible'],
            $this->prescreeningStringColumns()
        ));
        $this->addTextColumns('prescreening_criteria', $this->prescreeningTextColumns());
        $this->addIntegerColumns('prescreening_criteria', $this->prescreeningScoreColumns());
    }

    private function prepareSiteVisitEvaluations(): void
    {
        $this->alterColumnsToText('site_visit_evaluations', [
            's1_1_weakness',
            's1_2_weakness',
            's1_3_weakness',
            's1_4_weakness',
            's2_1_weakness',
            's2_2_weakness',
            's2_3_weakness',
            's3_1_weakness',
            's3_2_weakness',
            's3_3_weakness',
            's4_1_weakness',
            's4_2_weakness',
            's4_3_weakness',
            's5_1_weakness',
            's5_2_weakness',
            's5_3_weakness',
            's6_1_weakness',
            's6_2_weakness',
            's6_3_weakness',
            'overall_weakness',
        ]);
    }

    private function prepareThinkDatasets(): void
    {
        $this->alterColumnsToText('think_datasets', [
            'ottd_id',
            'other_offices',
            'address',
            'Funding_sources',
            'Funding_Mechanism',
            'tt_affiliations',
            'topics',
            'geographies',
            'twitter_handle_link',
            'facebook_page',
            'youtube_page',
            'instagram_acc',
            'linkedIn_acc',
        ]);
    }

    private function evaluationScoreColumns(): array
    {
        return [
            'relevance_score',
            'cross_border_score',
            'quality_originality_score',
            'policy_impact_score',
            'capacity_building_sustainability_score',
            'female_representation_score',
            'consortia_quality_score',
            'coordinator_score',
            'key_personnel_score',
            'technical_capacity_score',
            'monitoring_evaluation_score',
            'financial_management_score',
            'procurement_knowledge_score',
            'budget_feasibility_score',
            'budget_limits_score',
            'safeguards_score',
        ];
    }

    private function evaluationTextColumns(): array
    {
        $columns = [];
        foreach ([
            'relevance',
            'cross_border',
            'quality_originality',
            'policy_impact',
            'capacity_building_sustainability',
            'female_representation',
            'consortia_quality',
            'coordinator',
            'key_personnel',
            'technical_capacity',
            'monitoring_evaluation',
            'financial_management',
            'procurement_knowledge',
            'budget_feasibility',
            'budget_limits',
            'safeguards',
        ] as $area) {
            $columns[] = 'strength_' . $area;
            $columns[] = 'gap_' . $area;
        }

        return $columns;
    }

    private function prescreeningStringColumns(): array
    {
        return [
            'eligibility_lead_thinktank',
            'eligibility_sub_regions',
            'eligibility_priority_themes',
            'selection_application_form',
            'selection_workplan_budget',
            'selection_coordinator_cv',
            'selection_deputy_cv',
            'selection_team_cvs',
            'selection_experience',
            'selection_commitment_letter',
            'selection_registration_copy',
            'selection_board_structure',
            'selection_audited_reports',
        ];
    }

    private function prescreeningTextColumns(): array
    {
        return [
            'gap_lead_thinktank',
            'gap_sub_regions',
            'gap_priority_themes',
            'gap_application_form',
            'gap_workplan_budget',
            'gap_coordinator_cv',
            'gap_deputy_cv',
            'gap_team_cvs',
            'gap_experience',
            'gap_commitment_letter',
            'gap_registration_copy',
            'gap_board_structure',
            'gap_audited_reports',
            'comment_quality_alignment',
            'comment_key_personnel',
            'comment_budget',
            'strength_financial_health',
            'gap_financial_health',
            'strength_accuracy',
            'gap_accuracy',
            'strength_revenue',
            'gap_revenue',
            'strength_fund_use',
            'gap_fund_use',
            'strength_liabilities',
            'gap_liabilities',
            'strength_compliance',
            'gap_compliance',
            'overall_assessment',
        ];
    }

    private function prescreeningScoreColumns(): array
    {
        return [
            'score_quality_alignment',
            'score_key_personnel',
            'score_budget',
        ];
    }

    private function addUuidColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->uuid($column)->nullable());
            }
        }
    }

    private function addStringColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string($column)->nullable());
            }
        }
    }

    private function addTextColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->text($column)->nullable());
            }
        }
    }

    private function addIntegerColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->integer($column)->nullable());
            }
        }
    }

    private function addDecimalColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->decimal($column, 15, 2)->nullable());
            }
        }
    }

    private function addTimestampColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->timestamp($column)->nullable());
            }
        }
    }

    private function alterColumnsToText(string $table, array $columns): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE TEXT USING %s::TEXT',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column)
            ));
        }
    }

    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));

        if ($existing) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($existing));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};

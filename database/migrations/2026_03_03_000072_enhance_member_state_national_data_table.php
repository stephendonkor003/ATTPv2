<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_member_state_national_data')) {
            return;
        }

        Schema::table('myb_member_state_national_data', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_member_state_national_data', 'reporting_period_type')) {
                $table->string('reporting_period_type', 20)->default('monthly')->after('recorded_on');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'reporting_year')) {
                $table->unsignedSmallInteger('reporting_year')->nullable()->after('reporting_period_type');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'reporting_month')) {
                $table->unsignedTinyInteger('reporting_month')->nullable()->after('reporting_year');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'reporting_day')) {
                $table->unsignedTinyInteger('reporting_day')->nullable()->after('reporting_month');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'progress_status')) {
                $table->string('progress_status', 30)->default('in_progress')->after('cooperation_score');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'people_reached')) {
                $table->unsignedInteger('people_reached')->nullable()->after('progress_status');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'households_impacted')) {
                $table->unsignedInteger('households_impacted')->nullable()->after('people_reached');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'budget_allocated_usd')) {
                $table->decimal('budget_allocated_usd', 20, 2)->nullable()->after('households_impacted');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'budget_executed_usd')) {
                $table->decimal('budget_executed_usd', 20, 2)->nullable()->after('budget_allocated_usd');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'agenda_relevance_summary')) {
                $table->text('agenda_relevance_summary')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'policy_actions')) {
                $table->longText('policy_actions')->nullable()->after('agenda_relevance_summary');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'institutional_steps')) {
                $table->longText('institutional_steps')->nullable()->after('policy_actions');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'livelihood_impact_summary')) {
                $table->longText('livelihood_impact_summary')->nullable()->after('institutional_steps');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'public_engagement_summary')) {
                $table->longText('public_engagement_summary')->nullable()->after('livelihood_impact_summary');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'awareness_outreach_channels')) {
                $table->longText('awareness_outreach_channels')->nullable()->after('public_engagement_summary');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'national_projects_programs')) {
                $table->longText('national_projects_programs')->nullable()->after('awareness_outreach_channels');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'youth_women_inclusion_actions')) {
                $table->longText('youth_women_inclusion_actions')->nullable()->after('national_projects_programs');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'partnerships_support')) {
                $table->longText('partnerships_support')->nullable()->after('youth_women_inclusion_actions');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'commodity_preservation_policies')) {
                $table->longText('commodity_preservation_policies')->nullable()->after('partnerships_support');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'commodity_value_addition')) {
                $table->longText('commodity_value_addition')->nullable()->after('commodity_preservation_policies');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'risk_challenges')) {
                $table->longText('risk_challenges')->nullable()->after('commodity_value_addition');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'next_steps_commitments')) {
                $table->longText('next_steps_commitments')->nullable()->after('risk_challenges');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'citizen_feedback_summary')) {
                $table->longText('citizen_feedback_summary')->nullable()->after('next_steps_commitments');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'evidence_links')) {
                $table->text('evidence_links')->nullable()->after('citizen_feedback_summary');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'flagship_projects_supported')) {
                $table->json('flagship_projects_supported')->nullable()->after('evidence_links');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'commodity_focus')) {
                $table->json('commodity_focus')->nullable()->after('flagship_projects_supported');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'agenda_awareness_score')) {
                $table->decimal('agenda_awareness_score', 5, 2)->nullable()->after('commodity_focus');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'flagship_awareness_score')) {
                $table->decimal('flagship_awareness_score', 5, 2)->nullable()->after('agenda_awareness_score');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'outreach_coverage_score')) {
                $table->decimal('outreach_coverage_score', 5, 2)->nullable()->after('flagship_awareness_score');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('myb_member_state_national_data')) {
            return;
        }

        $columns = [
            'reporting_period_type',
            'reporting_year',
            'reporting_month',
            'reporting_day',
            'progress_status',
            'people_reached',
            'households_impacted',
            'budget_allocated_usd',
            'budget_executed_usd',
            'agenda_relevance_summary',
            'policy_actions',
            'institutional_steps',
            'livelihood_impact_summary',
            'public_engagement_summary',
            'awareness_outreach_channels',
            'national_projects_programs',
            'youth_women_inclusion_actions',
            'partnerships_support',
            'commodity_preservation_policies',
            'commodity_value_addition',
            'risk_challenges',
            'next_steps_commitments',
            'citizen_feedback_summary',
            'evidence_links',
            'flagship_projects_supported',
            'commodity_focus',
            'agenda_awareness_score',
            'flagship_awareness_score',
            'outreach_coverage_score',
        ];

        $existingColumns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('myb_member_state_national_data', $column)));

        if (!empty($existingColumns)) {
            Schema::table('myb_member_state_national_data', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};

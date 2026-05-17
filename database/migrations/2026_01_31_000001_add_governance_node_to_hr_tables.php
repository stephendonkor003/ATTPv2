<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds governance_node_id to all HR tables for organizational scoping.
     */
    public function up(): void
    {
        // Add governance_node_id to hr_positions
        if (!Schema::hasColumn('hr_positions', 'governance_node_id')) {
            Schema::table('hr_positions', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        // Add governance_node_id to hr_vacancies
        if (!Schema::hasColumn('hr_vacancies', 'governance_node_id')) {
            Schema::table('hr_vacancies', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        // Add governance_node_id to hr_applicants
        if (!Schema::hasColumn('hr_applicants', 'governance_node_id')) {
            Schema::table('hr_applicants', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        // Add governance_node_id to hr_employees
        if (!Schema::hasColumn('hr_employees', 'governance_node_id')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        // Sync existing data: Positions inherit governance from their Resource
        DB::statement("
            UPDATE hr_positions p
            SET governance_node_id = r.governance_node_id
            FROM myb_resources r
            WHERE p.resource_id = r.id
              AND p.governance_node_id IS NULL
              AND r.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Vacancies inherit governance from their Position
        DB::statement("
            UPDATE hr_vacancies v
            SET governance_node_id = p.governance_node_id
            FROM hr_positions p
            WHERE v.position_id = p.id
              AND v.governance_node_id IS NULL
              AND p.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Applicants inherit governance from their Vacancy
        DB::statement("
            UPDATE hr_applicants a
            SET governance_node_id = v.governance_node_id
            FROM hr_vacancies v
            WHERE a.vacancy_id = v.id
              AND a.governance_node_id IS NULL
              AND v.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Employees inherit governance from their Position
        DB::statement("
            UPDATE hr_employees e
            SET governance_node_id = p.governance_node_id
            FROM hr_positions p
            WHERE e.position_id = p.id
              AND e.governance_node_id IS NULL
              AND p.governance_node_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('hr_employees', 'governance_node_id')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }

        if (Schema::hasColumn('hr_applicants', 'governance_node_id')) {
            Schema::table('hr_applicants', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }

        if (Schema::hasColumn('hr_vacancies', 'governance_node_id')) {
            Schema::table('hr_vacancies', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }

        if (Schema::hasColumn('hr_positions', 'governance_node_id')) {
            Schema::table('hr_positions', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};

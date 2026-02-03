<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('hr_positions', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        // Add governance_node_id to hr_vacancies
        Schema::table('hr_vacancies', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        // Add governance_node_id to hr_applicants
        Schema::table('hr_applicants', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        // Add governance_node_id to hr_employees
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        // Sync existing data: Positions inherit governance from their Resource
        DB::statement("
            UPDATE hr_positions p
            INNER JOIN myb_resources r ON p.resource_id = r.id
            SET p.governance_node_id = r.governance_node_id
            WHERE p.governance_node_id IS NULL AND r.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Vacancies inherit governance from their Position
        DB::statement("
            UPDATE hr_vacancies v
            INNER JOIN hr_positions p ON v.position_id = p.id
            SET v.governance_node_id = p.governance_node_id
            WHERE v.governance_node_id IS NULL AND p.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Applicants inherit governance from their Vacancy
        DB::statement("
            UPDATE hr_applicants a
            INNER JOIN hr_vacancies v ON a.vacancy_id = v.id
            SET a.governance_node_id = v.governance_node_id
            WHERE a.governance_node_id IS NULL AND v.governance_node_id IS NOT NULL
        ");

        // Sync existing data: Employees inherit governance from their Position
        DB::statement("
            UPDATE hr_employees e
            INNER JOIN hr_positions p ON e.position_id = p.id
            SET e.governance_node_id = p.governance_node_id
            WHERE e.governance_node_id IS NULL AND p.governance_node_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });

        Schema::table('hr_applicants', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });

        Schema::table('hr_vacancies', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });

        Schema::table('hr_positions', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};

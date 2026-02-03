<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Program Funding - Member States pivot table
        Schema::create('myb_program_funding_member_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')
                ->constrained('myb_program_fundings')
                ->onDelete('cascade');
            $table->foreignUuid('member_state_id')
                ->constrained('myb_au_member_states')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['program_funding_id', 'member_state_id'], 'pf_member_state_unique');
        });

        // Program Funding - Regional Blocks pivot table
        Schema::create('myb_program_funding_regional_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')
                ->constrained('myb_program_fundings')
                ->onDelete('cascade');
            $table->foreignUuid('regional_block_id')
                ->constrained('myb_au_regional_blocks')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['program_funding_id', 'regional_block_id'], 'pf_regional_block_unique');
        });

        // Program Funding - Aspirations pivot table
        Schema::create('myb_program_funding_aspirations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')
                ->constrained('myb_program_fundings')
                ->onDelete('cascade');
            $table->foreignUuid('aspiration_id')
                ->constrained('myb_au_aspirations')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['program_funding_id', 'aspiration_id'], 'pf_aspiration_unique');
        });

        // Program Funding - Goals pivot table
        Schema::create('myb_program_funding_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')
                ->constrained('myb_program_fundings')
                ->onDelete('cascade');
            $table->foreignUuid('goal_id')
                ->constrained('myb_au_goals')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['program_funding_id', 'goal_id'], 'pf_goal_unique');
        });

        // Program Funding - Flagship Projects pivot table
        Schema::create('myb_program_funding_flagship_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('program_funding_id');
            $table->uuid('flagship_project_id');
            $table->timestamps();

            $table->foreign('program_funding_id', 'pf_fp_funding_fk')
                ->references('id')
                ->on('myb_program_fundings')
                ->onDelete('cascade');

            $table->foreign('flagship_project_id', 'pf_fp_flagship_fk')
                ->references('id')
                ->on('myb_au_flagship_projects')
                ->onDelete('cascade');

            $table->unique(['program_funding_id', 'flagship_project_id'], 'pf_flagship_project_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myb_program_funding_flagship_projects');
        Schema::dropIfExists('myb_program_funding_goals');
        Schema::dropIfExists('myb_program_funding_aspirations');
        Schema::dropIfExists('myb_program_funding_regional_blocks');
        Schema::dropIfExists('myb_program_funding_member_states');
    }
};

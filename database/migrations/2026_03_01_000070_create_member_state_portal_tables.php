<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_member_state_national_data')) {
            Schema::create('myb_member_state_national_data', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('member_state_id')
                    ->constrained('myb_au_member_states')
                    ->cascadeOnDelete();
                $table->date('recorded_on');
                $table->foreignUuid('aspiration_id')
                    ->nullable()
                    ->constrained('myb_au_aspirations')
                    ->nullOnDelete();
                $table->foreignUuid('goal_id')
                    ->nullable()
                    ->constrained('myb_au_goals')
                    ->nullOnDelete();
                $table->string('indicator_name');
                $table->decimal('indicator_value', 20, 4);
                $table->string('unit')->nullable();
                $table->decimal('cooperation_score', 5, 2)->default(0);
                $table->string('data_source')->nullable();
                $table->text('notes')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['member_state_id', 'recorded_on', 'indicator_name'],
                    'ms_national_data_unique_entry'
                );
                $table->index(['member_state_id', 'recorded_on'], 'ms_national_data_state_date_idx');
                $table->index(['aspiration_id', 'goal_id'], 'ms_national_data_agenda_idx');
            });
        }

        if (!Schema::hasTable('myb_member_state_communications')) {
            Schema::create('myb_member_state_communications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('member_state_id')
                    ->constrained('myb_au_member_states')
                    ->cascadeOnDelete();
                $table->date('communication_date');
                $table->string('subject');
                $table->longText('message');
                $table->string('channel')->default('official_note');
                $table->string('status')->default('pending_response');
                $table->longText('response_text')->nullable();
                $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['member_state_id', 'communication_date'], 'ms_comm_state_date_idx');
                $table->index(['member_state_id', 'status'], 'ms_comm_state_status_idx');
            });
        }

        if (!Schema::hasTable('myb_member_state_questions')) {
            Schema::create('myb_member_state_questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('member_state_id')
                    ->constrained('myb_au_member_states')
                    ->cascadeOnDelete();
                $table->date('asked_on');
                $table->string('subject');
                $table->longText('question_text');
                $table->string('priority')->default('normal');
                $table->string('status')->default('open');
                $table->longText('answer_text')->nullable();
                $table->foreignUuid('answered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['member_state_id', 'asked_on'], 'ms_questions_state_date_idx');
                $table->index(['member_state_id', 'status'], 'ms_questions_state_status_idx');
            });
        }

        if (!Schema::hasTable('myb_commodities')) {
            Schema::create('myb_commodities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('category')->nullable();
                $table->string('unit_of_measure')->nullable();
                $table->text('description')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('myb_member_state_commodity_trends')) {
            Schema::create('myb_member_state_commodity_trends', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('member_state_id')
                    ->constrained('myb_au_member_states')
                    ->cascadeOnDelete();
                $table->foreignUuid('commodity_id')
                    ->constrained('myb_commodities')
                    ->cascadeOnDelete();
                $table->date('recorded_on');
                $table->decimal('production_volume', 20, 3)->nullable();
                $table->decimal('export_volume', 20, 3)->nullable();
                $table->decimal('export_value_usd', 20, 2)->nullable();
                $table->decimal('growth_rate_pct', 8, 3)->nullable();
                $table->text('trend_summary')->nullable();
                $table->text('impact_notes')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['member_state_id', 'commodity_id', 'recorded_on'],
                    'ms_commodity_trend_unique_day'
                );
                $table->index(['member_state_id', 'recorded_on'], 'ms_commodity_state_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_member_state_commodity_trends');
        Schema::dropIfExists('myb_commodities');
        Schema::dropIfExists('myb_member_state_questions');
        Schema::dropIfExists('myb_member_state_communications');
        Schema::dropIfExists('myb_member_state_national_data');
    }
};

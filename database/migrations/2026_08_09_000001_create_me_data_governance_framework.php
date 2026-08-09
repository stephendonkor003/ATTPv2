<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_data_governance_controls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('control_code', 80);
            $table->string('title', 240);
            $table->string('governance_domain', 60);
            $table->string('instrument_type', 40)->default('control');
            $table->string('version', 30)->default('1.0');
            $table->string('scope_type', 30)->default('enterprise');
            $table->foreignUuid('portfolio_id')->nullable()->constrained('myb_sectors')->nullOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('steward_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('data_classification', 30)->default('internal');
            $table->string('risk_rating', 30)->default('moderate');
            $table->string('status', 30)->default('draft');
            $table->string('implementation_status', 30)->default('not_started');
            $table->string('review_frequency', 30)->default('annual');
            $table->date('effective_date')->nullable();
            $table->date('next_review_date')->nullable();
            $table->text('description')->nullable();
            $table->longText('requirements')->nullable();
            $table->text('evidence_notes')->nullable();
            $table->foreignUuid('evidence_repository_item_id')->nullable()->constrained('me_knowledge_evidence_items')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['control_code', 'version'], 'me_dg_control_code_version_unique');
            $table->index(['status', 'implementation_status'], 'me_dg_control_lifecycle_idx');
            $table->index(['governance_domain', 'risk_rating'], 'me_dg_control_domain_risk_idx');
            $table->index(['next_review_date', 'status'], 'me_dg_control_review_idx');
            $table->index(['portfolio_id', 'think_tank_member_id'], 'me_dg_control_scope_idx');
        });

        Schema::create('me_data_governance_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('control_id')->constrained('me_data_governance_controls')->cascadeOnDelete();
            $table->string('action_type', 30)->default('remediation');
            $table->string('title', 240);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'due_date'], 'me_dg_action_queue_idx');
            $table->index(['control_id', 'status'], 'me_dg_action_control_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_data_governance_actions');
        Schema::dropIfExists('me_data_governance_controls');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_treaties')) {
            Schema::create('myb_treaties', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('short_title')->nullable();
                $table->string('reference_code')->nullable();
                $table->text('description')->nullable();
                $table->date('adoption_date')->nullable();
                $table->date('entry_into_force_date')->nullable();
                $table->string('status')->default('active');
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('adoption_date');
                $table->unique('reference_code');
            });
        }

        if (!Schema::hasTable('myb_treaty_member_state_statuses')) {
            Schema::create('myb_treaty_member_state_statuses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('treaty_id')
                    ->constrained('myb_treaties')
                    ->cascadeOnDelete();
                $table->foreignUuid('member_state_id')
                    ->constrained('myb_au_member_states')
                    ->cascadeOnDelete();
                $table->boolean('is_signed')->default(false);
                $table->timestamp('signed_at')->nullable();
                $table->foreignUuid('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('signed_document_path')->nullable();
                $table->string('signed_document_name')->nullable();
                $table->text('signed_notes')->nullable();
                $table->boolean('is_ratified')->default(false);
                $table->timestamp('ratified_at')->nullable();
                $table->foreignUuid('ratified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ratified_document_path')->nullable();
                $table->string('ratified_document_name')->nullable();
                $table->text('ratified_notes')->nullable();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['treaty_id', 'member_state_id'], 'treaty_member_state_unique');
                $table->index(['treaty_id', 'is_signed'], 'treaty_signed_idx');
                $table->index(['treaty_id', 'is_ratified'], 'treaty_ratified_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_treaty_member_state_statuses');
        Schema::dropIfExists('myb_treaties');
    }
};

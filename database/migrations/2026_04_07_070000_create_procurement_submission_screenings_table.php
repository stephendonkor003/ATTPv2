<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_submission_screenings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->unique()->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignUuid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->default('3pap');
            $table->string('checked_via')->nullable();
            $table->string('request_status')->default('success');
            $table->string('entity_name')->nullable();
            $table->string('entity_country')->nullable();
            $table->string('risk_level')->nullable();
            $table->unsignedInteger('total_matches')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['request_status', 'risk_level'], 'pss_status_risk_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_submission_screenings');
    }
};

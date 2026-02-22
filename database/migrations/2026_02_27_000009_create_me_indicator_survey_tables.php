<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicator_survey_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->foreignUuid('methodology_id')->nullable()->constrained('indicator_methodologies')->nullOnDelete();
            $table->string('public_token', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique('indicator_id');
            $table->index(['methodology_id', 'is_active']);
        });

        Schema::create('me_indicator_survey_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->foreignUuid('methodology_id')->nullable()->constrained('indicator_methodologies')->nullOnDelete();
            $table->foreignUuid('survey_link_id')->nullable()->constrained('me_indicator_survey_links')->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_email')->nullable();
            $table->string('respondent_phone', 60)->nullable();
            $table->string('respondent_organization')->nullable();
            $table->json('answers');
            $table->json('responsible_user_ids')->nullable();
            $table->json('responsible_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['indicator_id', 'submitted_at']);
            $table->index(['methodology_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicator_survey_responses');
        Schema::dropIfExists('me_indicator_survey_links');
    }
};


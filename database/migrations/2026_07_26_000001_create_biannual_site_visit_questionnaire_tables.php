<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biannual_site_visit_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->json('visibility')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version'], 'bsv_templates_code_version_uq');
            $table->index(['status', 'is_default'], 'bsv_templates_status_default_idx');
        });

        Schema::create('biannual_site_visit_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')
                ->constrained('biannual_site_visit_templates')
                ->cascadeOnDelete();
            $table->string('section_key', 120);
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('guidance')->nullable();
            $table->json('settings')->nullable();
            $table->json('visibility')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'section_key'], 'bsv_sections_template_key_uq');
            $table->index(['template_id', 'sort_order'], 'bsv_sections_template_order_idx');
        });

        Schema::create('biannual_site_visit_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('section_id')
                ->constrained('biannual_site_visit_sections')
                ->cascadeOnDelete();
            $table->string('topic_key', 120);
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('guidance')->nullable();
            $table->json('settings')->nullable();
            $table->json('visibility')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'topic_key'], 'bsv_topics_section_key_uq');
            $table->index(['section_id', 'sort_order'], 'bsv_topics_section_order_idx');
        });

        Schema::create('biannual_site_visit_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')
                ->constrained('biannual_site_visit_templates')
                ->cascadeOnDelete();
            $table->foreignUuid('topic_id')
                ->constrained('biannual_site_visit_topics')
                ->cascadeOnDelete();
            $table->string('question_key', 160);
            $table->string('question_type', 40)->default('textarea');
            $table->longText('prompt');
            $table->longText('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->json('visibility')->nullable();
            $table->json('settings')->nullable();
            $table->json('rating_labels')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_scored')->default(false);
            $table->boolean('allows_na')->default(false);
            $table->decimal('maximum_score', 12, 4)->nullable();
            $table->decimal('score_weight', 10, 4)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'question_key'], 'bsv_questions_template_key_uq');
            $table->index(['topic_id', 'sort_order'], 'bsv_questions_topic_order_idx');
            $table->index(['template_id', 'is_scored'], 'bsv_questions_template_scored_idx');
            $table->index('question_type', 'bsv_questions_type_idx');
        });

        Schema::create('biannual_site_visit_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')
                ->constrained('site_visits')
                ->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')
                ->constrained('attp_consortium_think_tanks')
                ->restrictOnDelete();
            $table->foreignUuid('template_id')
                ->constrained('biannual_site_visit_templates')
                ->restrictOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('title');
            $table->unsignedInteger('template_version');
            $table->unsignedSmallInteger('cycle_year');
            $table->unsignedTinyInteger('cycle_half');
            $table->string('location')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->longText('objectives')->nullable();
            $table->json('questionnaire_snapshot');
            $table->json('settings')->nullable();
            $table->json('visibility_snapshot')->nullable();
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->longText('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('site_visit_id', 'bsv_profiles_site_visit_uq');
            $table->unique(
                ['think_tank_member_id', 'cycle_year', 'cycle_half'],
                'bsv_profiles_tank_cycle_uq'
            );
            $table->index(['template_id', 'template_version'], 'bsv_profiles_template_version_idx');
            $table->index(['cycle_year', 'cycle_half', 'starts_on'], 'bsv_profiles_cycle_start_idx');
        });

        Schema::create('biannual_site_visit_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->constrained('biannual_site_visit_profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('question_id')
                ->nullable()
                ->constrained('biannual_site_visit_questions')
                ->nullOnDelete();
            $table->string('question_key', 160);
            $table->json('value')->nullable();
            $table->decimal('score', 12, 4)->nullable();
            $table->decimal('maximum_score', 12, 4)->nullable();
            $table->decimal('score_weight', 10, 4)->default(1);
            $table->string('rating_label')->nullable();
            $table->longText('strength')->nullable();
            $table->longText('weakness')->nullable();
            $table->longText('evidence_notes')->nullable();
            $table->boolean('is_not_applicable')->default(false);
            $table->text('na_reason')->nullable();
            $table->json('question_snapshot');
            $table->json('metadata')->nullable();
            $table->foreignUuid('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'question_key'], 'bsv_answers_profile_key_uq');
            $table->index(['profile_id', 'question_id'], 'bsv_answers_profile_question_idx');
            $table->index(['profile_id', 'is_not_applicable'], 'bsv_answers_profile_na_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biannual_site_visit_answers');
        Schema::dropIfExists('biannual_site_visit_profiles');
        Schema::dropIfExists('biannual_site_visit_questions');
        Schema::dropIfExists('biannual_site_visit_topics');
        Schema::dropIfExists('biannual_site_visit_sections');
        Schema::dropIfExists('biannual_site_visit_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approved_work_plan_item_reviews', function (Blueprint $table) {
            $table->string('tor_path')->nullable()->after('document_uploaded_at');
            $table->string('tor_name')->nullable()->after('tor_path');
            $table->timestamp('tor_uploaded_at')->nullable()->after('tor_name');
            $table->string('concept_note_path')->nullable()->after('tor_uploaded_at');
            $table->string('concept_note_name')->nullable()->after('concept_note_path');
            $table->timestamp('concept_note_uploaded_at')->nullable()->after('concept_note_name');
        });
    }

    public function down(): void
    {
        Schema::table('approved_work_plan_item_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'tor_path',
                'tor_name',
                'tor_uploaded_at',
                'concept_note_path',
                'concept_note_name',
                'concept_note_uploaded_at',
            ]);
        });
    }
};

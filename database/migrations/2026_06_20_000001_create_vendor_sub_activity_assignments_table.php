<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_sub_activity_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('program_id')->nullable()->constrained('myb_programs')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('myb_projects')->nullOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained('myb_activities')->nullOnDelete();
            $table->foreignUuid('sub_activity_id')->constrained('myb_sub_activities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'sub_activity_id'], 'vendor_sub_activity_unique');
            $table->index(['vendor_id', 'program_id']);
            $table->index(['vendor_id', 'project_id']);
            $table->index(['vendor_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_sub_activity_assignments');
    }
};

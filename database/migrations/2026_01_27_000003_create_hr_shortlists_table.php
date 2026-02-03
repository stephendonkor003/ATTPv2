<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_shortlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('applicant_id')->nullable();
            $table->string('stage')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUuid('shortlisted_by')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shortlists');
    }
};

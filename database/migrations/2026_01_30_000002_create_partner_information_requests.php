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
        Schema::create('myb_partner_information_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('funder_id')
                ->constrained('myb_funders')
                ->cascadeOnDelete()
                ->comment('The funding partner making the request');

            $table->foreignUuid('program_funding_id')->nullable()
                ->constrained('myb_program_fundings')
                ->nullOnDelete()
                ->comment('Specific program funding this request relates to (optional)');

            $table->foreignUuid('requested_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Partner user who submitted the request');

            $table->string('request_type')
                ->comment('Type: financial_report, progress_update, documentation, other');

            $table->string('subject')
                ->comment('Subject/title of the request');

            $table->text('message')
                ->comment('Detailed request message');

            $table->string('status')->default('pending')
                ->comment('Status: pending, in_progress, completed, rejected');

            $table->text('response')->nullable()
                ->comment('Admin response to the request');

            $table->foreignUuid('responded_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Admin user who responded');

            $table->timestamp('responded_at')->nullable()
                ->comment('When the response was provided');

            $table->string('priority')->default('normal')
                ->comment('Priority: low, normal, high, urgent');

            $table->timestamps();

            // Indexes for query performance
            $table->index(['funder_id', 'status']);
            $table->index('program_funding_id');
            $table->index('requested_by');
            $table->index('status');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myb_partner_information_requests');
    }
};

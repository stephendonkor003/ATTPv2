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
        Schema::create('myb_partner_activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('funder_id')
                ->constrained('myb_funders')
                ->cascadeOnDelete()
                ->comment('The funding partner');

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The partner user performing the action');

            $table->string('action')
                ->comment('Action type: login, view_dashboard, view_program, request_info, download_document');

            $table->string('ip_address', 45)->nullable()
                ->comment('IP address of the user');

            $table->text('user_agent')->nullable()
                ->comment('Browser user agent string');

            $table->json('metadata')->nullable()
                ->comment('Additional contextual data about the action');

            $table->timestamp('created_at')
                ->comment('When the action occurred');

            // Indexes for audit queries
            $table->index(['funder_id', 'created_at']);
            $table->index(['user_id', 'action']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myb_partner_activity_log');
    }
};

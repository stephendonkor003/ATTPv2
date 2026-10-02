<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attp_think_tank_procurement_status_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')
                ->constrained('attp_think_tank_procurement_events')
                ->cascadeOnDelete();
            $table->foreignUuid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_scope', 30);
            $table->json('audiences');
            $table->string('heading');
            $table->text('message');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 160)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'recipient_email'], 'attp_tt_proc_status_notice_event_email_uq');
            $table->index(['event_id', 'status'], 'attp_tt_proc_status_notice_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attp_think_tank_procurement_status_notifications');
    }
};

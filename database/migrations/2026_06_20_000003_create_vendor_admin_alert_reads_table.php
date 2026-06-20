<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_admin_alert_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('alert_type', 60);
            $table->uuid('source_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['admin_id', 'alert_type', 'source_id'], 'vendor_admin_alert_reads_unique');
            $table->index(['admin_id', 'alert_type', 'read_at'], 'vendor_admin_alert_reads_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_admin_alert_reads');
    }
};

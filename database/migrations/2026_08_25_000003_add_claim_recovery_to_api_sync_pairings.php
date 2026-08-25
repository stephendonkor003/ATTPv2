<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->char('claim_recovery_hash', 64)->nullable()->unique();
            $table->timestamp('abandoned_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->dropUnique(['claim_recovery_hash']);
            $table->dropIndex(['abandoned_at']);
            $table->dropColumn(['claim_recovery_hash', 'abandoned_at']);
        });
    }
};

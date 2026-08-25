<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_sync_invitation_events') || Schema::hasColumn('api_sync_invitation_events', 'lifecycle_key')) {
            return;
        }

        // Existing history remains untouched because this table is already
        // append-only. New lifecycle facts receive a deterministic hash, so
        // PostgreSQL rejects a duplicate without rewriting older evidence.
        Schema::table('api_sync_invitation_events', function (Blueprint $table): void {
            $table->char('lifecycle_key', 64)->nullable()->after('event_type');
            $table->unique('lifecycle_key', 'api_sync_invitation_lifecycle_once_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('api_sync_invitation_events') && Schema::hasColumn('api_sync_invitation_events', 'lifecycle_key')) {
            Schema::table('api_sync_invitation_events', function (Blueprint $table): void {
                $table->dropUnique('api_sync_invitation_lifecycle_once_idx');
                $table->dropColumn('lifecycle_key');
            });
        }
    }
};

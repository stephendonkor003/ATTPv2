<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sync_invitations', function (Blueprint $table): void {
            $table->uuid('authorization_id')->nullable()->unique()->after('approved_at');
            $table->text('authorization_receipt')->nullable()->after('authorization_id');
            $table->timestamp('authorization_verified_at')->nullable()->after('authorization_receipt');
            $table->string('terminal_error_code', 100)->nullable()->after('receipt_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_sync_invitations', function (Blueprint $table): void {
            $table->dropUnique(['authorization_id']);
            $table->dropColumn([
                'authorization_id',
                'authorization_receipt',
                'authorization_verified_at',
                'terminal_error_code',
            ]);
        });
    }
};

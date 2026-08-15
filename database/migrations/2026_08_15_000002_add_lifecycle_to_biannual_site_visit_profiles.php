<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biannual_site_visit_profiles', function (Blueprint $table): void {
            $table->boolean('is_active')
                ->default(true)
                ->after('notes')
                ->index('bsv_profiles_active_idx');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignUuid('deactivated_by')
                ->nullable()
                ->after('deactivated_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('deactivation_reason')->nullable()->after('deactivated_by');
            $table->timestamp('reactivated_at')->nullable()->after('deactivation_reason');
            $table->foreignUuid('reactivated_by')
                ->nullable()
                ->after('reactivated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('biannual_site_visit_profiles', function (Blueprint $table): void {
            $table->dropForeign(['deactivated_by']);
            $table->dropForeign(['reactivated_by']);
            $table->dropIndex('bsv_profiles_active_idx');
            $table->dropColumn([
                'is_active',
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'reactivated_at',
                'reactivated_by',
            ]);
        });
    }
};

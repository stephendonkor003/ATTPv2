<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vendor_category')) {
                $table->string('vendor_category')->nullable()->after('user_type');
            }
            if (!Schema::hasColumn('users', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('vendor_category');
            }
            if (!Schema::hasColumn('users', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable()->after('is_disabled');
            }
            if (!Schema::hasColumn('users', 'disabled_reason')) {
                $table->string('disabled_reason')->nullable()->after('disabled_at');
            }
            if (!Schema::hasColumn('users', 'is_blacklisted')) {
                $table->boolean('is_blacklisted')->default(false)->after('disabled_reason');
            }
            if (!Schema::hasColumn('users', 'blacklisted_at')) {
                $table->timestamp('blacklisted_at')->nullable()->after('is_blacklisted');
            }
            if (!Schema::hasColumn('users', 'blacklisted_reason')) {
                $table->string('blacklisted_reason')->nullable()->after('blacklisted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'vendor_category',
                'is_disabled',
                'disabled_at',
                'disabled_reason',
                'is_blacklisted',
                'blacklisted_at',
                'blacklisted_reason',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

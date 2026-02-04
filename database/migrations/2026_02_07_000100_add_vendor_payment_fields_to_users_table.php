<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'payment_method_preference')) {
                $table->string('payment_method_preference')->nullable()->after('vendor_category');
            }
            if (!Schema::hasColumn('users', 'payment_bank_name')) {
                $table->string('payment_bank_name')->nullable()->after('payment_method_preference');
            }
            if (!Schema::hasColumn('users', 'payment_account_name')) {
                $table->string('payment_account_name')->nullable()->after('payment_bank_name');
            }
            if (!Schema::hasColumn('users', 'payment_account_number')) {
                $table->string('payment_account_number')->nullable()->after('payment_account_name');
            }
            if (!Schema::hasColumn('users', 'payment_swift_code')) {
                $table->string('payment_swift_code')->nullable()->after('payment_account_number');
            }
            if (!Schema::hasColumn('users', 'payment_iban')) {
                $table->string('payment_iban')->nullable()->after('payment_swift_code');
            }
            if (!Schema::hasColumn('users', 'payment_mobile_provider')) {
                $table->string('payment_mobile_provider')->nullable()->after('payment_iban');
            }
            if (!Schema::hasColumn('users', 'payment_mobile_number')) {
                $table->string('payment_mobile_number')->nullable()->after('payment_mobile_provider');
            }
            if (!Schema::hasColumn('users', 'payment_tax_id')) {
                $table->string('payment_tax_id')->nullable()->after('payment_mobile_number');
            }
            if (!Schema::hasColumn('users', 'payment_address')) {
                $table->string('payment_address')->nullable()->after('payment_tax_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'payment_method_preference',
                'payment_bank_name',
                'payment_account_name',
                'payment_account_number',
                'payment_swift_code',
                'payment_iban',
                'payment_mobile_provider',
                'payment_mobile_number',
                'payment_tax_id',
                'payment_address',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

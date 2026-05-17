<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table) {
            if (! Schema::hasColumn('attp_consortium_think_tanks', 'vendor_user_id')) {
                $table->foreignUuid('vendor_user_id')
                    ->nullable()
                    ->after('portal_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_orders', 'po_type')) {
                $table->string('po_type', 40)->default('procurement')->after('reference_no')->index();
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'consortium_id')) {
                $table->foreignUuid('consortium_id')
                    ->nullable()
                    ->after('governance_node_id')
                    ->constrained('attp_consortia')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'think_tank_member_id')) {
                $table->foreignUuid('think_tank_member_id')
                    ->nullable()
                    ->after('consortium_id')
                    ->constrained('attp_consortium_think_tanks')
                    ->nullOnDelete();
            }
        });

        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'consortium_id')) {
                $table->foreignUuid('consortium_id')
                    ->nullable()
                    ->after('governance_node_id')
                    ->constrained('attp_consortia')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_disbursements', 'think_tank_member_id')) {
                $table->foreignUuid('think_tank_member_id')
                    ->nullable()
                    ->after('consortium_id')
                    ->constrained('attp_consortium_think_tanks')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_disbursements', 'think_tank_member_id')) {
                $table->dropConstrainedForeignId('think_tank_member_id');
            }

            if (Schema::hasColumn('procurement_disbursements', 'consortium_id')) {
                $table->dropConstrainedForeignId('consortium_id');
            }
        });

        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_purchase_orders', 'think_tank_member_id')) {
                $table->dropConstrainedForeignId('think_tank_member_id');
            }

            if (Schema::hasColumn('procurement_purchase_orders', 'consortium_id')) {
                $table->dropConstrainedForeignId('consortium_id');
            }

            if (Schema::hasColumn('procurement_purchase_orders', 'po_type')) {
                $table->dropColumn('po_type');
            }
        });

        Schema::table('attp_consortium_think_tanks', function (Blueprint $table) {
            if (Schema::hasColumn('attp_consortium_think_tanks', 'vendor_user_id')) {
                $table->dropConstrainedForeignId('vendor_user_id');
            }
        });
    }
};

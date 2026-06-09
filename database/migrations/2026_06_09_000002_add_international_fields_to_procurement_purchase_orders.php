<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_orders', 'purchase_request_id')) {
                $table->foreignUuid('purchase_request_id')
                    ->nullable()
                    ->after('budget_commitment_id')
                    ->constrained('myb_purchase_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'po_title')) {
                $table->string('po_title')->nullable()->after('reference_no');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'supplier_reference')) {
                $table->string('supplier_reference')->nullable()->after('po_title');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'contract_reference')) {
                $table->string('contract_reference')->nullable()->after('supplier_reference');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'buyer_contact_name')) {
                $table->string('buyer_contact_name')->nullable()->after('contract_reference');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'buyer_contact_email')) {
                $table->string('buyer_contact_email')->nullable()->after('buyer_contact_name');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'buyer_contact_phone')) {
                $table->string('buyer_contact_phone')->nullable()->after('buyer_contact_email');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'vendor_contact_name')) {
                $table->string('vendor_contact_name')->nullable()->after('buyer_contact_phone');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'vendor_contact_email')) {
                $table->string('vendor_contact_email')->nullable()->after('vendor_contact_name');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'vendor_contact_phone')) {
                $table->string('vendor_contact_phone')->nullable()->after('vendor_contact_email');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('vendor_contact_phone');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('billing_address');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'delivery_location')) {
                $table->text('delivery_location')->nullable()->after('shipping_address');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'incoterm')) {
                $table->string('incoterm', 30)->nullable()->after('delivery_location');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'delivery_terms')) {
                $table->string('delivery_terms')->nullable()->after('incoterm');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->after('delivery_terms');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'warranty_terms')) {
                $table->text('warranty_terms')->nullable()->after('payment_terms');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'inspection_requirements')) {
                $table->text('inspection_requirements')->nullable()->after('warranty_terms');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('inspection_requirements');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'terms_conditions')) {
                $table->text('terms_conditions')->nullable()->after('special_instructions');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable()->after('issued_at');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('expected_delivery_date');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurement_purchase_orders', 'purchase_request_id')) {
            Schema::table('procurement_purchase_orders', function (Blueprint $table) {
                $table->dropForeign(['purchase_request_id']);
                $table->dropColumn('purchase_request_id');
            });
        }

        $columns = collect([
            'po_title',
            'supplier_reference',
            'contract_reference',
            'buyer_contact_name',
            'buyer_contact_email',
            'buyer_contact_phone',
            'vendor_contact_name',
            'vendor_contact_email',
            'vendor_contact_phone',
            'billing_address',
            'shipping_address',
            'delivery_location',
            'incoterm',
            'delivery_terms',
            'payment_terms',
            'warranty_terms',
            'inspection_requirements',
            'special_instructions',
            'terms_conditions',
            'expected_delivery_date',
            'valid_until',
        ])->filter(fn ($column) => Schema::hasColumn('procurement_purchase_orders', $column))->values()->all();

        if ($columns === []) {
            return;
        }

        Schema::table('procurement_purchase_orders', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};

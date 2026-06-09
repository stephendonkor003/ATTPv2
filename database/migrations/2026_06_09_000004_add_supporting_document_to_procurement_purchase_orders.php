<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_orders', 'supporting_document_path')) {
                $table->string('supporting_document_path')->nullable()->after('terms_conditions');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'supporting_document_name')) {
                $table->string('supporting_document_name')->nullable()->after('supporting_document_path');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'supporting_document_mime_type')) {
                $table->string('supporting_document_mime_type')->nullable()->after('supporting_document_name');
            }

            if (! Schema::hasColumn('procurement_purchase_orders', 'supporting_document_size')) {
                $table->unsignedBigInteger('supporting_document_size')->nullable()->after('supporting_document_mime_type');
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'supporting_document_size',
            'supporting_document_mime_type',
            'supporting_document_name',
            'supporting_document_path',
        ])->filter(fn ($column) => Schema::hasColumn('procurement_purchase_orders', $column))->values()->all();

        if ($columns === []) {
            return;
        }

        Schema::table('procurement_purchase_orders', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};

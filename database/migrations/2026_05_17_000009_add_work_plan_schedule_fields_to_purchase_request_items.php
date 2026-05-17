<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_months')) {
                $table->json('work_plan_months')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_audience')) {
                $table->string('work_plan_audience')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_units')) {
                $table->string('work_plan_units')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_payment_basis')) {
                $table->string('work_plan_payment_basis', 40)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_unit_rate')) {
                $table->decimal('work_plan_unit_rate', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_person_months')) {
                $table->unsignedInteger('work_plan_person_months')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_monthly_amount')) {
                $table->decimal('work_plan_monthly_amount', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'work_plan_months',
                'work_plan_audience',
                'work_plan_units',
                'work_plan_payment_basis',
                'work_plan_unit_rate',
                'work_plan_person_months',
                'work_plan_monthly_amount',
            ]);
        });
    }
};

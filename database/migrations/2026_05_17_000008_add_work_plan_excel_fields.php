<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_requests', 'work_plan_source')) {
                $table->string('work_plan_source', 120)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'work_plan_component')) {
                $table->text('work_plan_component')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'work_plan_sub_component')) {
                $table->text('work_plan_sub_component')->nullable();
            }
        });

        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_source')) {
                $table->string('work_plan_source', 120)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_sort_order')) {
                $table->unsignedInteger('work_plan_sort_order')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'work_plan_serial')) {
                $table->string('work_plan_serial', 40)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'implemented_by')) {
                $table->string('implemented_by')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'budget_code')) {
                $table->string('budget_code')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'object_type')) {
                $table->string('object_type')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'estimated_amount')) {
                $table->decimal('estimated_amount', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'intermediate_indicator')) {
                $table->longText('intermediate_indicator')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'result_indicator')) {
                $table->longText('result_indicator')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'observations')) {
                $table->longText('observations')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'world_bank_comments')) {
                $table->longText('world_bank_comments')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'attp_secretariat_comments')) {
                $table->longText('attp_secretariat_comments')->nullable();
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'world_bank_amount')) {
                $table->decimal('world_bank_amount', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'work_plan_source',
                'work_plan_sort_order',
                'work_plan_serial',
                'implemented_by',
                'budget_code',
                'object_type',
                'estimated_amount',
                'intermediate_indicator',
                'result_indicator',
                'observations',
                'world_bank_comments',
                'attp_secretariat_comments',
                'world_bank_amount',
            ]);
        });

        Schema::table('myb_purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'work_plan_source',
                'work_plan_component',
                'work_plan_sub_component',
            ]);
        });
    }
};

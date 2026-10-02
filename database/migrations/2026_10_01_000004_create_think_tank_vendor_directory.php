<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attp_think_tank_vendor_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('think_tank_member_id')
                ->constrained('attp_consortium_think_tanks')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['think_tank_member_id', 'normalized_name'],
                'attp_tt_vendor_categories_tenant_name_uq'
            );
            $table->unique(
                ['id', 'think_tank_member_id'],
                'attp_tt_vendor_categories_id_tenant_uq'
            );
            $table->index(
                ['think_tank_member_id', 'is_active'],
                'attp_tt_vendor_categories_tenant_active_idx'
            );
        });

        Schema::create('attp_think_tank_vendor_user', function (Blueprint $table): void {
            $table->foreignUuid('think_tank_member_id')
                ->constrained('attp_consortium_think_tanks')
                ->cascadeOnDelete();
            $table->foreignUuid('vendor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->boolean('can_manage_setup')->default(false);
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->primary(
                ['think_tank_member_id', 'vendor_user_id'],
                'attp_tt_vendor_user_pk'
            );
            $table->index('vendor_user_id', 'attp_tt_vendor_user_vendor_idx');
            $table->index(
                ['think_tank_member_id', 'status'],
                'attp_tt_vendor_user_tenant_status_idx'
            );
        });

        Schema::create('attp_think_tank_vendor_category_user', function (Blueprint $table): void {
            $table->foreignUuid('think_tank_vendor_category_id')
                ->constrained('attp_think_tank_vendor_categories')
                ->cascadeOnDelete();
            $table->foreignUuid('vendor_user_id')->constrained('users')->cascadeOnDelete();
            // This redundant tenant key makes every directory query explicitly
            // tenant-scoped and prevents an unscoped pivot read from crossing tenants.
            $table->foreignUuid('think_tank_member_id')
                ->constrained('attp_consortium_think_tanks')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(
                ['think_tank_vendor_category_id', 'vendor_user_id'],
                'attp_tt_vendor_category_user_pk'
            );
            $table->index(
                ['think_tank_member_id', 'vendor_user_id'],
                'attp_tt_vendor_category_user_tenant_vendor_idx'
            );
            $table->foreign(
                ['think_tank_vendor_category_id', 'think_tank_member_id'],
                'attp_tt_vendor_category_user_category_tenant_fk'
            )->references(['id', 'think_tank_member_id'])
                ->on('attp_think_tank_vendor_categories')
                ->cascadeOnDelete();
            $table->foreign(
                ['think_tank_member_id', 'vendor_user_id'],
                'attp_tt_vendor_category_user_membership_fk'
            )->references(['think_tank_member_id', 'vendor_user_id'])
                ->on('attp_think_tank_vendor_user')
                ->cascadeOnDelete();
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->json('tenant_vendor_category_ids')->nullable()->after('vendor_categories');
            $table->json('tenant_vendor_ids')->nullable()->after('tenant_vendor_category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn(['tenant_vendor_category_ids', 'tenant_vendor_ids']);
        });

        Schema::dropIfExists('attp_think_tank_vendor_category_user');
        Schema::dropIfExists('attp_think_tank_vendor_user');
        Schema::dropIfExists('attp_think_tank_vendor_categories');
    }
};

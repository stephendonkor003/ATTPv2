<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (!Schema::hasColumn('procurements', 'visibility_type')) {
                $table->string('visibility_type')->default('public')->after('status');
            }
            if (!Schema::hasColumn('procurements', 'vendor_categories')) {
                $table->json('vendor_categories')->nullable()->after('visibility_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('procurements', 'visibility_type')) {
                $columns[] = 'visibility_type';
            }
            if (Schema::hasColumn('procurements', 'vendor_categories')) {
                $columns[] = 'vendor_categories';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

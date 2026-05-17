<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table) {
            if (! Schema::hasColumn('attp_consortium_think_tanks', 'au_sap_vendor_number')) {
                $table->string('au_sap_vendor_number')->nullable()->after('vendor_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table) {
            if (Schema::hasColumn('attp_consortium_think_tanks', 'au_sap_vendor_number')) {
                $table->dropColumn('au_sap_vendor_number');
            }
        });
    }
};
